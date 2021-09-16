<?php namespace com\mongodb;

use lang\{FormatException, Throwable};
use peer\{Socket, ConnectException};

/**
 * MongoDB Wire Protocol 
 *
 * @see https://docs.mongodb.com/manual/reference/mongodb-wire-protocol/
 */
class Protocol {
  const OP_REPLY        = 1;
  const OP_UPDATE       = 2001;
  const OP_INSERT       = 2002;
  const OP_QUERY        = 2004;
  const OP_GET_MORE     = 2005;
  const OP_DELETE       = 2006;
  const OP_KILL_CURSORS = 2007;
  const OP_MSG          = 2013;

  private $options, $conn, $auth;
  private $id= 1;
  public $bson;

  /**
   * Creates a new protocol instance
   *
   * @see    https://docs.mongodb.com/manual/reference/connection-string/
   * @see    https://www.mongodb.com/developer/article/srv-connection-strings/
   * @param  string|peer.Socket $arg Either a connection string or a socket
   * @param  [:string] $options
   */
  public function __construct($arg, $options= []) {
    if ($arg instanceof Socket) {
      $this->options= ['scheme' => 'mongodb', 'targets' => [[$arg->host, $arg->port]]] + $options;
      $this->conn= $arg;
    } else {
      preg_match('/([^:]+):\/\/(([^:]+):([^@]+)@)?([^\/?]+)(\/[^?]+)?(\?(.+))?/', $arg, $m);
      $this->options= ['scheme' => $m[1], 'targets' => []] + $options + ['params' => []];
      '' === $m[3] || $this->options['user']= $m[3];
      '' === $m[4] || $this->options['pass']= $m[4];
      '' === ($m[6] ?? '') || $this->options['path']= $m[6];

      // Handle MongoDB Seed Lists
      $p= $m[8] ?? '';
      if ('mongodb+srv' === $m[1]) {
        foreach (dns_get_record('_mongodb._tcp.'.$m[5], DNS_SRV) as $record) {
          $this->options['targets'][]= [$record['target'], $record['port']];
        }
        foreach (dns_get_record($m[5], DNS_TXT) as $record) {
          $p.= '&'.$record['txt'];
        }

        // As per spec: Use of the +srv connection string modifier automatically sets the tls
        // (or the equivalent ssl) option to true for the connection
        if (null === ($this->options['params']['ssl'] ?? $this->options['params']['tls'] ?? null)) {
          $this->options['params']['ssl']= 'true';
        }
      } else {
        foreach (explode(',', $m[5]) as $authority) {
          if ('[' === $authority[0]) {
            sscanf($authority, '[%[0-9a-fA-F:]]:%d', $host, $port);
          } else {
            sscanf($authority, '%[^:]:%d', $host, $port);
          }
          $this->options['targets'][]= [$host, $port ?? 27017];
        }
      }

      if ('' !== $p) {
        parse_str($p, $params);
        $this->options['params']+= $params;
      }

      $this->conn= new Socket(...$this->options['targets'][0]);
    }

    $this->auth= Authentication::mechanism($this->options['params']['authMechanism'] ?? 'SCRAM-SHA-1');
    $this->bson= new BSON();
  }

  /** @return [:var] */
  public function options() { return $this->options; }

  /** Returns connection string */
  public function connection(bool $password= false): string { 
    $uri= $this->options['scheme'].'://';
    if (isset($this->options['user'])) {
      $secret= ($password ? $this->options['pass'] : str_repeat('*', strlen($this->options['pass'])));
      $uri.= $this->options['user'].':'.$secret.'@';
    }

    $servers= '';
    foreach ($this->options['targets'] as $target) {
      $servers.= ','.$target[0].':'.$target[1];
    }
    $uri.= substr($servers, 1);

    $query= isset($this->options['path']) ? '&authSource='.ltrim($this->options['path'], '/') : '';
    foreach ($this->options['params'] as $key => $value) {
      $query.= '&'.$key.'='.$value;
    }
    $query && $uri.= '?'.substr($query, 1);

    return $uri;
  }

  /**
   * Connect (and authenticate, if credentials are present)
   *
   * @throws com.mongodb.AuthenticationFailed
   * @throws com.mongodb.Error
   */
  public function connect() {
    if ($this->conn->isConnected()) return;

    $this->conn->connect(($this->options['params']['connectTimeoutMS'] ?? 40000) / 1000);
    if ('true' === ($this->options['params']['ssl'] ?? $this->options['params']['tls'] ?? null)) {
      if (!stream_socket_enable_crypto($this->conn->getHandle(), true, STREAM_CRYPTO_METHOD_ANY_CLIENT)) {
        $e= new ConnectException('SSL handshake failed');
        \xp::gc(__FILE__);
        throw $e;
      }
    }

    $reply= $this->send(self::OP_QUERY, pack(
      'Va*xVVa*',
      0,   // flags
      'admin.$cmd',
      0,   // numberToSkip
      1,   // numberToReturn
      $this->bson->sections([
        'isMaster' => 1,
        'client'   => [
          'application' => ['name' => $this->options['params']['appName'] ?? $_SERVER['argv'][0]],
          'driver'      => ['name' => 'XP MongoDB Connectivity', 'version' => '1.0.0'],
          'os'          => ['name' => php_uname('s'), 'type' => PHP_OS, 'architecture' => php_uname('m'), 'version' => php_uname('r')]
        ]
      ])
    ));
    $this->options['server']= current($reply['documents']);

    if (!isset($this->options['user'])) return;

    // Authentication
    try {
      $conversation= $this->auth->conversation(
        urldecode($this->options['user']),
        urldecode($this->options['pass']),
        $this->options['params']['authSource'] ?? (isset($this->options['path']) ? ltrim($this->options['path'], '/') : 'admin')
      );

      do {
        $result= $this->msg(0, 0, $conversation->current());
        if (0 === (int)$result['body']['ok']) {
          throw Error::newInstance($result['body']);
        }

        $conversation->send($result['body']);
      } while ($conversation->valid());
    } catch (Throwable $t) {
      $e= new AuthenticationFailed($t->getMessage(), $this->optopns['user'], $this->optopns['pass']);
      $e->setCause($t);
      throw $t;
    }
  }

  private function hex($bytes) {
    $r= '';
    for ($i= 0; $i < strlen($bytes); $i++) {
      $c= $bytes[$i];
      if ($c < "\37" || $c > "\177") {
        $r.= sprintf("\\x%02x", ord($c));
      } else {
        $r.= $c;
      }
    }
    return $r;
  }

  /**
   * Sends an OP_MSG
   *
   * @param  int $flags
   * @param  int $kind
   * @param  var[] $sections
   * @return var
   * @throws com.mongodb.Error
   */
  public function msg($flags, $kind, $sections) {
    $result= $this->send(Protocol::OP_MSG, pack(
      'Vca*', 
      $flags,
      $kind,
      $this->bson->sections($sections)
    ));

    if (1 === (int)$result['body']['ok']) return $result;
    throw Error::newInstance($result['body']);
  }

  /**
   * Reads a given number of bytes.
   *
   * @param  int $bytes
   * @return string
   */
  private function read($bytes) {
    $b= '';
    do {
      $b.= $this->conn->readBinary($bytes);
    } while (strlen($b) < $bytes && !$this->conn->eof());
    return $b;
  }

  public function send($operation, $body) {
    $this->id > 2147483647 ? $this->id= 1 : $this->id++;

    $payload= pack('VVVV', strlen($body) + 16, $this->id, 0, $operation).$body;

    // \util\cmd\Console::writeLine('>>> ', strlen($payload), ': ', $this->hex($payload)); 
    $this->conn->write($payload);

    $header= unpack('VmessageLength/VrequestID/VresponseTo/VopCode', $this->read(16));
    // \util\cmd\Console::writeLine('<<< ', $header);

    $response= $this->read($header['messageLength'] - 16);
    // \util\cmd\Console::writeLine('<<< ', strlen($response), ': ', $this->hex($response));

    switch ($header['opCode']) {
      case self::OP_MSG:
        $flags= unpack('V', substr($response, 0, 4))[1];

        switch ($response[4]) {
          case "\x00":
            $offset= 5;
            return ['flags' => $flags, 'body' => $this->bson->document($response, $offset)];

          case "\x01":
            // return ['flags' => $flags, 'sequence'];

          default:
            throw new FormatException('Unknown sequence kind '.ord($response[4]));
        }

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
    $this->conn->isConnected() && $this->conn->close();
  }
}