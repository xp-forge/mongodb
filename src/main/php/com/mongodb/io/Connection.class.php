<?php namespace com\mongodb\io;

use com\mongodb\{Authentication, AuthenticationFailed, Error};
use lang\Throwable;
use peer\{Socket, ProtocolException, ConnectException};
use util\Secret;

/**
 * A single connection to MongoDB server, of which more than one may exist
 * in the Protocol class based on read preference.
 *
 * @see   https://docs.mongodb.com/manual/core/read-preference-mechanics/
 * @test  com.mongodb.unittest.ConnectionTest
 */
class Connection {
  const OP_REPLY        = 1;
  const OP_UPDATE       = 2001;
  const OP_INSERT       = 2002;
  const OP_QUERY        = 2004;
  const OP_GET_MORE     = 2005;
  const OP_DELETE       = 2006;
  const OP_KILL_CURSORS = 2007;
  const OP_MSG          = 2013;

  const RSGhost         = 'RSGhost';
  const RSPrimary       = 'RSPrimary';
  const RSMember        = 'RSMember';
  const RSSecondary     = 'RSSecondary';
  const RSArbiter       = 'RSArbiter';
  const Mongos          = 'Mongos';
  const Standalone      = 'Standalone';

  private $socket, $bson;
  private $packet= 1;
  public $server= null;
  public $lastUsed= null;

  /**
   * Creates a new connection
   *
   * ```php
   * new Connection(new Socket(...));
   * new Connection('localhost');
   * new Connection('localhost:27017');
   * new Connection('localhost', 27017);
   * ```
   *
   * @param  string|peer.Socket $arg
   * @param  ?int $port
   * @param  com.mongodb.io.BSON $bson optional reused BSON instance
   */
  public function __construct($arg, $port= null, $bson= null) {
    if (null !== $port) {
      $this->socket= new Socket($arg, $port);
    } else if ($arg instanceof Socket) {
      $this->socket= $arg;
    } else if ('[' === $arg[0]) {
      sscanf($arg, '[%[0-9a-fA-F:]]:%d', $host, $port);
      $this->socket= new Socket("[{$host}]", $port ?? 27017);
    } else {
      sscanf($arg, '%[^:]:%d', $host, $port);
      $this->socket= new Socket($host, $port ?? 27017);
    }
    $this->bson= $bson ?: new BSON();
  }

  /** @return string */
  public function address() {
    return $this->socket->host.':'.$this->socket->port;
  }

  /** @return bool */
  public function connected() { return $this->socket->isConnected(); }

  /**
   * Establishes this connection
   *
   * @param  [:var] $options
   * @param  ?com.mongodb.auth.Mechanism $auth
   * @return void
   * @throws com.mongodb.AuthenticationFailed
   * @throws peer.ConnectException
   */
  public function establish($options= [], $auth= null) {
    $this->socket->setTimeout(($options['params']['socketTimeoutMS'] ?? 60000) / 1000);
    $this->socket->connect(($options['params']['connectTimeoutMS'] ?? 40000) / 1000);
    if ('true' === ($options['params']['ssl'] ?? $options['params']['tls'] ?? null)) {
      if (!stream_socket_enable_crypto($this->socket->getHandle(), true, STREAM_CRYPTO_METHOD_ANY_CLIENT)) {
        $e= new ConnectException('SSL handshake failed');
        \xp::gc(__FILE__);
        throw $e;
      }
    }

    // Send hello package and determine connection kind
    // https://www.mongodb.com/docs/manual/reference/command/hello/
    $hello= [
      'hello'    => 1,
      'client'   => [
        'application' => ['name' => $options['params']['appName'] ?? $_SERVER['argv'][0] ?? 'php'],
        'driver'      => ['name' => 'XP MongoDB Connectivity', 'version' => '1.0.0'],
        'os'          => ['name' => php_uname('s'), 'type' => PHP_OS, 'architecture' => php_uname('m'), 'version' => php_uname('r')]
      ]
    ];

    // If the optional field saslSupportedMechs is specified, the command also returns
    // an array of SASL mechanisms used to create the specified user's credentials.
    if (isset($options['user'])) {
      $user= urldecode($options['user']);
      $pass= urldecode($options['pass']);
      $authSource= $options['params']['authSource'] ?? (isset($options['path']) ? ltrim($options['path'], '/') : 'admin');
      $hello['saslSupportedMechs']= "{$authSource}.{$user}";
    } else {
      $authSource= null;
    }

    try {
      $reply= $this->send(
        self::OP_QUERY,
        "\x00\x00\x00\x00admin.\$cmd\x00\x00\x00\x00\x00\x01\x00\x00\x00",
        $hello
      );
    } catch (ProtocolException $e) {
      throw new ConnectException('Server handshake failed @ '.$this->address(), $e);
    }

    // See https://github.com/mongodb/specifications/blob/master/source/server-discovery-and-monitoring/server-discovery-and-monitoring.rst#type
    $document= &$reply['documents'][0];
    $kind= self::Standalone;
    if (isset($document['isreplicaset'])) {
      $kind= self::RSGhost;
    } else if ('' !== ($document['setName'] ?? '')) {
      if ($document['isWritablePrimary'] ?? null) {
        $kind= self::RSPrimary;
      } else if ($document['hidden'] ?? null) {
        $kind= self::RSMember;
      } else if ($document['secondary'] ?? null) {
        $kind= self::RSSecondary;
      } else if ($document['arbiterOnly'] ?? null) {
        $kind= self::RSArbiter;
      } else {
        $kind= self::RSMember;
      }
    } else if ('isdbgrid' === ($document['msg'] ?? '')) {
      $kind= self::Mongos;
    }
    $this->server= ['$kind' => $kind] + $document;

    // Optionally, perform authentication
    if (null === $authSource) return;

    try {
      if ($auth) {
        // Use this explicitely specified mechanism
      } else if ($supported= $document['saslSupportedMechs'] ?? null) {
        $auth= Authentication::negotiate($supported);
      } else {
        $auth= Authentication::mechanism(Authentication::MECHANISMS[0]);
      }

      $conversation= $auth->conversation($user, $pass, $authSource);
      do {
        $result= $this->message($conversation->current(), null);
        if (0 === (int)$result['body']['ok']) {
          throw Error::newInstance($result['body']);
        }

        $conversation->send($result['body']);
      } while ($conversation->valid());
    } catch (Throwable $t) {
      throw new AuthenticationFailed($t->getMessage(), $options['user'], new Secret($options['pass']), $t);
    }
  }

  /**
   * Reads a given number of bytes. Throws an error if EOF is reached before
   * the buffer is completely populated.
   *
   * @param  int $n
   * @return string
   * @throws peer.ProtocolException
   */
  private function read0($n) {
    $b= '';
    do {
      if ('' === ($chunk= (string)$this->socket->readBinary($n))) {
        throw new ProtocolException('Received EOF while reading @ '.$this->address());
      }

      $b.= $chunk;
      $n-= strlen($chunk);
    } while ($n > 0);
    return $b;
  }

  /**
   * Sends a message
   * 
   * @param  [:var] $sections
   * @param  ?string $readPreference
   * @return var
   * @throws com.mongodb.Error
   */
  public function message($sections, $readPreference) {
    if (null !== $readPreference && self::Standalone !== $this->server['$kind']) {
      $sections+= ['$readPreference' => $readPreference];
    }

    // flags(V)= 0, kind(c)= 0
    $r= $this->send(self::OP_MSG, "\x00\x00\x00\x00\x00", $sections);
    if (1 === (int)$r['body']['ok']) return $r;

    throw Error::newInstance($r['body']);
  }

  /**
   * Sends a command to the server and returns its result
   *
   * @param  int $operation One of the OP_* constants
   * @param  string $header
   * @param  [:var] $sections
   * @return var
   * @throws peer.ProtocolException
   */
  public function send($operation, $header, $sections) {
    $this->packet > 2147483647 ? $this->packet= 1 : $this->packet++;
    $body= $header.$this->bson->sections($sections);
    $payload= pack('VVVV', strlen($body) + 16, $this->packet, 0, $operation).$body;

    $this->socket->write($payload);
    $meta= unpack('VmessageLength/VrequestID/VresponseTo/VopCode', $this->read0(16));
    $response= $this->read0($meta['messageLength'] - 16);
    $this->lastUsed= time();

    switch ($meta['opCode']) {
      case self::OP_MSG:
        $flags= unpack('V', substr($response, 0, 4))[1];
        if ("\x00" === $response[4]) {
          $offset= 5;
          return ['flags' => $flags, 'body' => $this->bson->document($response, $offset)];
        }

        throw new ProtocolException('Unknown sequence kind '.ord($response[4]));

      case self::OP_REPLY:
        $reply= unpack('VresponseFlags/PcursorID/VstartingFrom/VnumberReturned', substr($response, 0, 20));

        $offset= 20;
        $reply['documents']= [];
        for ($i= 0; $i < $reply['numberReturned']; $i++) {
          $reply['documents'][]= $this->bson->document($response, $offset);
        }

        return $reply;

      default:
        return ['opCode' => $meta['opCode']];
    }
  }

  /** @return void */
  public function close() {
    $this->server= null;
    $this->socket->isConnected() && $this->socket->close();
  }
}