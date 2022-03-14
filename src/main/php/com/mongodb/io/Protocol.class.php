<?php namespace com\mongodb\io;

use com\mongodb\Authentication;
use lang\{IllegalStateException, Throwable};
use peer\{Socket, ConnectException, ProtocolException};

/**
 * MongoDB Wire Protocol 
 *
 * @see https://docs.mongodb.com/manual/reference/mongodb-wire-protocol/
 */
class Protocol {
  private $options, $conn, $auth;
  public $nodes= null;
  public $readPreference;

  /**
   * Creates a new protocol instance
   *
   * @see    https://docs.mongodb.com/manual/reference/connection-string/
   * @see    https://www.mongodb.com/developer/article/srv-connection-strings/
   * @param  string|peer.Socket $arg Either a connection string or a socket
   * @param  [:string] $options
   * @param  com.mongodb.io.BSON $bson
   * @param  com.mongodb.io.DNS $dns
   */
  public function __construct($arg, $options= [], $bson= null, $dns= null) {
    $bson ?? $bson= new BSON();

    if ($arg instanceof Socket) {
      $conn= new Connection($arg, null, $bson);
      $this->options= ['scheme' => 'mongodb', 'nodes' => $conn->address()] + $options;
      $this->conn= [$conn->address() => $conn];
    } else {
      preg_match('/([^:]+):\/\/(([^:]+):([^@]+)@)?([^\/?]+)(\/[^?]*)?(\?(.+))?/', $arg, $m);
      $this->options= ['scheme' => $m[1], 'nodes' => $m[5]] + $options + ['params' => []];
      '' === $m[3] || $this->options['user']= $m[3];
      '' === $m[4] || $this->options['pass']= $m[4];
      '' === ($m[6] ?? '') || $this->options['path']= $m[6];

      // Handle MongoDB Seed Lists
      $p= $m[8] ?? '';
      if ('mongodb+srv' === $m[1]) {
        $dns ?? $dns= new DNS();

        foreach ($dns->members($m[5]) as $host => $port) {
          $conn= new Connection($host, $port, $bson);
          $this->conn[$conn->address()]= $conn;
        }
        foreach ($dns->params($m[5]) as $param) {
          $p.= '&'.$param;
        }

        // As per spec: Use of the +srv connection string modifier automatically sets the tls
        // (or the equivalent ssl) option to true for the connection
        if (null === ($this->options['params']['ssl'] ?? $this->options['params']['tls'] ?? null)) {
          $this->options['params']['ssl']= 'true';
        }
      } else {
        foreach (explode(',', $m[5]) as $authority) {
          $conn= new Connection($authority, null, $bson);
          $this->conn[$conn->address()]= $conn;
        }
      }

      if ('' !== $p) {
        parse_str($p, $params);
        $this->options['params']+= $params;
      }
    }

    $this->readPreference= ['mode' => $this->options['params']['readPreference'] ?? 'primary'];
    $this->auth= isset($this->options['user'])
      ? Authentication::mechanism($this->options['params']['authMechanism'] ?? 'SCRAM-SHA-1')
      : null
    ;
  }

  /** @return [:var] */
  public function options() { return $this->options; }

  /** @return [:com.mongodb.io.Connection] */
  public function connections() { return $this->conn; }

  /** Returns connection string */
  public function connection(bool $password= false): string { 
    $uri= $this->options['scheme'].'://';
    if (isset($this->options['user'])) {
      $secret= ($password ? $this->options['pass'] : str_repeat('*', strlen($this->options['pass'])));
      $uri.= $this->options['user'].':'.$secret.'@';
    }
    $uri.= $this->options['nodes'];

    $p= ltrim($this->options['path'] ?? '', '/');
    $query= $p ? '&authSource='.$p : '';

    foreach ($this->options['params'] as $key => $value) {
      $query.= '&'.$key.'='.$value;
    }
    $query && $uri.= '?'.substr($query, 1);

    return $uri;
  }

  /**
   * Connect (and authenticate, if credentials are present)
   *
   * @throws peer.ConnectException
   * @throws com.mongodb.AuthenticationFailed
   */
  public function connect() {
    if ($this->nodes) return;

    try {
      $this->select(array_keys($this->conn), 'initial connect');
    } catch (IllegalStateException $e) {
      throw new ConnectException('Cannot connect to '.$this->options['scheme'].'://'.$this->options['nodes'], $e);
    }
  }

  /**
   * Select a connection
   *
   * @see    https://github.com/mongodb/specifications/blob/master/source/server-selection/server-selection.rst#checking-an-idle-socket-after-socketcheckintervalms
   * @param  string[] $candidates
   * @param  string $intent used within potential error messages
   * @return com.mongodb.io.Connection
   * @throws lang.IllegalStateException
   */
  private function select($candidates, $intent) {
    $cause= null;
    foreach ($candidates as $candidate) {
      try {
        $conn= $this->conn[$candidate];
        // \util\cmd\Console::writeLine('[SELECT] For ', $intent, ': ', $candidate);

        // Refresh view into cluster every time we succesfully connect to a node
        if (null === $conn->server) {
          $conn->establish($this->options, $this->auth);
          $this->nodes= ['primary' => $conn->server['primary'] ?? $candidate, 'secondary' => []];
          foreach ($conn->server['hosts'] ?? [] as $host) {
            if ($conn->server['primary'] !== $host) $this->nodes['secondary'][]= $host;
          }
        }

        return $conn;
      } catch (ConnectException $t) {
        $conn->close();
        $cause ? $cause->setCause($t) : $cause= $t;
      }
    }

    throw new IllegalStateException('No suitable candidates eligible for '.$intent.', tried '.implode(', ', $candidates), $cause);
  }

  /**
   * Perform a read operation
   *
   * @see    https://github.com/mongodb/specifications/blob/master/source/server-selection/server-selection.rst#read-preference
   * @see    https://docs.mongodb.com/manual/core/read-preference-mechanics/
   * @param  [:var] $sections
   * @return var
   * @throws com.mongodb.Error
   */
  public function read($sections) {
    $rp= $this->readPreference['mode'];

    if ('primary' === $rp) {
      $selected= $this->select([$this->nodes['primary']], 'reading with '.$rp);
    } else if ('secondary' === $rp) {
      $selected= $this->select($this->nodes['secondary'], 'reading with '.$rp);
    } else if ('primaryPreferred' === $rp) {
      $selected= $this->select(array_merge([$this->nodes['primary']], $this->nodes['secondary']), 'reading with '.$rp);
    } else if ('secondaryPreferred' === $rp) {
      $selected= $this->select(array_merge($this->nodes['secondary'], [$this->nodes['primary']]), 'reading with '.$rp);
    } else if ('nearest' === $rp) {  // Prefer to stay on already open connections
      $connected= null;
      foreach ($this->conn as $id => $conn) {
        if (null === $conn->server) continue;
        $connected= $id;
        break;
      }
      $selected= $this->select(
        array_unique(array_merge([$connected, $this->nodes['primary']], $this->nodes['secondary'])),
        'reading with '.$rp
      );
    }

    return $selected->message($sections, $this->readPreference);
  }

  /**
   * Perform a write operation
   *
   * @param  [:var] $sections
   * @return var
   * @throws com.mongodb.Error
   */
  public function write($sections) {
    return $this->select([$this->nodes['primary']], 'writing')->message($sections, $this->readPreference);
  }

  /** @return void */
  public function close() {
    foreach ($this->conn as $conn) { 
      $conn->close();
    }
    $this->nodes= null;
  }
}