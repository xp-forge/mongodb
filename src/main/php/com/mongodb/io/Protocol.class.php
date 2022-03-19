<?php namespace com\mongodb\io;

use com\mongodb\{Authentication, NoSuitableCandidates};
use lang\{IllegalStateException, Throwable};
use peer\{ConnectException, Socket, SocketException};

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
   * @param  string|com.mongodb.io.Connection[] $arg Either a connection string or a socket
   * @param  [:string] $options
   * @param  com.mongodb.io.BSON $bson
   * @param  com.mongodb.io.DNS $dns
   */
  public function __construct($arg, $options= [], $bson= null, $dns= null) {
    $bson ?? $bson= new BSON();

    if (is_array($arg)) {
      $nodes= '';
      $this->conn= [];
      foreach ($arg as $conn) {
        $nodes.= ','.$conn->address();
        $this->conn[$conn->address()]= $conn;
      }
      $this->options= ['scheme' => 'mongodb', 'nodes' => substr($nodes, 1)] + $options;
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
   * @return void
   * @throws com.mongodb.Error
   * @throws com.mongodb.AuthenticationFailed
   */
  public function connect() {
    $this->nodes || $this->send(array_keys($this->conn), null, 'initial connect');
  }

  /**
   * Select a connection, then send message
   *
   * @see    https://github.com/mongodb/specifications/blob/master/source/server-selection/server-selection.rst#checking-an-idle-socket-after-socketcheckintervalms
   * @param  string[] $candidates
   * @param  [:var] $sections
   * @param  string $intent used within potential error messages
   * @return var
   * @throws com.mongodb.Error
   */
  private function send($candidates, $sections, $intent) {
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

        return null === $sections ? null : $conn->message($sections, $this->readPreference);
      } catch (SocketException $e) {
        $conn->close();
        $cause ? $cause->setCause($e) : $cause= $e;
      }
    }

    throw new NoSuitableCandidates($intent, $candidates, $cause);
  }

  /**
   * Perform a read operation
   *
   * @see    https://github.com/mongodb/specifications/blob/master/source/server-selection/server-selection.rst#read-preference
   * @see    https://docs.mongodb.com/manual/core/read-preference-mechanics/
   * @param  ?com.mongodb.Session $session
   * @param  [:var] $sections
   * @return var
   * @throws com.mongodb.Error
   */
  public function read($session, $sections) {
    $session && $sections+= $session->send($this);
    $rp= $this->readPreference['mode'];

    if ('primary' === $rp) {
      $candidates= [$this->nodes['primary']];
    } else if ('secondary' === $rp) {
      $candidates= $this->nodes['secondary'];
    } else if ('primaryPreferred' === $rp) {
      $candidates= array_merge([$this->nodes['primary']], $this->nodes['secondary']);
    } else if ('secondaryPreferred' === $rp) {
      $candidates= array_merge($this->nodes['secondary'], [$this->nodes['primary']]);
    } else if ('nearest' === $rp) {  // Prefer to stay on already open connections
      $connected= null;
      foreach ($this->conn as $id => $conn) {
        if (null === $conn->server) continue;
        $connected= $id;
        break;
      }
      $candidates= array_unique(array_merge([$connected, $this->nodes['primary']], $this->nodes['secondary']));
    }

    return $this->send($candidates, $sections, 'reading with '.$rp);
  }

  /**
   * Perform a write operation
   *
   * @param  ?com.mongodb.Session $session
   * @param  [:var] $sections
   * @return var
   * @throws com.mongodb.Error
   */
  public function write($session, $sections) {
    $session && $sections+= $session->send($this);

    return $this->send([$this->nodes['primary']], $sections, 'writing');
  }

  /** @return void */
  public function close() {
    foreach ($this->conn as $conn) { 
      $conn->close();
    }
    $this->nodes= null;
  }
}