<?php namespace com\mongodb\io;

use com\mongodb\{AuthenticationFailed, Error};
use lang\Throwable;
use peer\{Socket, ProtocolException, ConnectException};

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
    } else {
      if ('[' === $arg[0]) {
        sscanf($arg, '[%[0-9a-fA-F:]]:%d', $host, $port);
      } else {
        sscanf($arg, '%[^:]:%d', $host, $port);
      }
      $this->socket= new Socket($host, $port ?? 27017);
    }
    $this->bson= $bson ?: new BSON();
  }

  /** @return string */
  public function address() {
    return false === strpos($this->socket->host, ':')
      ? $this->socket->host.':'.$this->socket->port
      : '['.$this->socket->host.']:'.$this->socket->port
    ;
  }

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
    $this->socket->connect(($options['params']['connectTimeoutMS'] ?? 40000) / 1000);
    if ('true' === ($options['params']['ssl'] ?? $options['params']['tls'] ?? null)) {
      if (!stream_socket_enable_crypto($this->socket->getHandle(), true, STREAM_CRYPTO_METHOD_ANY_CLIENT)) {
        $e= new ConnectException('SSL handshake failed');
        \xp::gc(__FILE__);
        throw $e;
      }
    }

    // Send hello package and determine connection kind
    // https://docs.mongodb.com/v4.4/reference/command/hello/
    try {
      $reply= $this->send(
        self::OP_QUERY,
        "\x00\x00\x00\x00admin.\$cmd\x00\x00\x00\x00\x00\x01\x00\x00\x00",
        [
          'hello'    => 1,
          'client'   => [
            'application' => ['name' => $options['params']['appName'] ?? $_SERVER['argv'][0] ?? 'php'],
            'driver'      => ['name' => 'XP MongoDB Connectivity', 'version' => '1.0.0'],
            'os'          => ['name' => php_uname('s'), 'type' => PHP_OS, 'architecture' => php_uname('m'), 'version' => php_uname('r')]
          ]
        ]
      );
    } catch (ProtocolException $e) {
      throw new ConnectException('Server handshake failed @ '.$this->address(), $e);
    }

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
    if (null === $auth) return;

    try {
      $conversation= $auth->conversation(
        urldecode($options['user']),
        urldecode($options['pass']),
        $options['params']['authSource'] ?? (isset($options['path']) ? ltrim($options['path'], '/') : 'admin')
      );

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
   * Reads a given number of bytes.
   *
   * @param  int $bytes
   * @return string
   */
  private function read0($bytes) {
    $b= '';
    do {
      $b.= $this->socket->readBinary($bytes);
    } while (strlen($b) < $bytes && !$this->socket->eof());
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

    // \util\cmd\Console::writeLine('>>> ', strlen($payload), ': ', addcslashes($payload, "\0..\37!\177..\377")); 
    $this->socket->write($payload);

    $header= unpack('VmessageLength/VrequestID/VresponseTo/VopCode', $this->read0(16));
    // \util\cmd\Console::writeLine('<<< ', $header);
    if (false === $header) {
      $e= new ProtocolException(
        ($this->socket->eof() ? 'Received EOF while reading' : 'Reading header failed').
        ' @ '.
        $this->address()
      );
      \xp::gc(__FILE__);
      throw $e;
    }

    $response= $this->read0($header['messageLength'] - 16);
    // \util\cmd\Console::writeLine('<<< ', strlen($response), ': ', addcslashes($response, "\0..\37!\177..\377"));

    switch ($header['opCode']) {
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
        return ['opCode' => $header['opCode']];
    }
  }

  /** @return void */
  public function close() {
    $this->server= null;
    $this->socket->isConnected() && $this->socket->close();
  }
}