<?php namespace com\mongodb\io;

use com\mongodb\{Authentication, NoSuitableCandidate, CannotConnect, Error};
use io\IOException;
use lang\{IllegalStateException, IllegalArgumentException, Throwable};
use peer\{ConnectException, Socket, SocketException};
use util\Objects;

/**
 * MongoDB Wire Protocol 
 *
 * @see   https://docs.mongodb.com/manual/reference/mongodb-wire-protocol/
 * @test  com.mongodb.unittest.ReplicaSetTest
 */
class Protocol {
  private $options;
  protected $auth= null;
  protected $conn= [];
  public $nodes= null;
  public $readPreference;
  public $socketCheckInterval= 5;

  /**
   * Creates a new protocol instance
   *
   * @see    https://docs.mongodb.com/manual/reference/connection-string/
   * @see    https://www.mongodb.com/developer/article/srv-connection-strings/
   * @param  string|com.mongodb.io.Connection[] $arg Either a connection string or connections
   * @param  [:string] $options
   * @param  com.mongodb.io.BSON $bson
   * @param  com.mongodb.io.DNS $dns
   * @throws lang.IllegalArgumentException
   */
  public function __construct($arg, $options= [], $bson= null, $dns= null) {
    $bson ?? $bson= new BSON();

    if (is_array($arg)) {
      $nodes= '';
      foreach ($arg as $conn) {
        $nodes.= ','.$conn->address();
        $this->conn[$conn->address()]= $conn;
      }
      $this->options= ['scheme' => 'mongodb', 'nodes' => substr($nodes, 1)] + $options;
    } else if (preg_match('/([^:]+):\/\/(([^:]+):([^@]+)@)?([^\/?]+)(\/[^?]*)?(\?(.+))?/', $arg, $m)) {
      $this->options= ['scheme' => $m[1], 'nodes' => $m[5]] + $options + ['params' => []];
      '' === $m[3] || $this->options['user']= $m[3];
      '' === $m[4] || $this->options['pass']= $m[4];
      '' === ($m[6] ?? '') || $this->options['path']= $m[6];

      // Handle MongoDB Seed Lists
      $p= $m[8] ?? '';
      if ('mongodb+srv' === $m[1]) {
        $dns ?? $dns= new DNS();

        try {
          foreach ($dns->members($m[5]) as $host => $port) {
            $conn= new Connection($host, $port, $bson);
            $this->conn[$conn->address()]= $conn;
          }
          foreach ($dns->params($m[5]) as $param) {
            $p.= '&'.$param;
          }
        } catch (IOException $e) {
          throw new CannotConnect(231, 'DNSProtocolError', 'DNS lookup failed for '.$m[5], $e);
        }

        if (empty($this->conn)) {
          throw new CannotConnect(230, 'DNSHostNotFound', 'DNS does not contain MongoDB SRV records for '.$m[5]);
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
    } else {
      throw new IllegalArgumentException(sprintf(
        'Expected a connection string or connections, have %s',
        Objects::stringOf($arg)
      ));
    }

    $this->readPreference= ['mode' => $this->options['params']['readPreference'] ?? 'primary'];

    // Check if an authentication mechanism was explicitely selected
    if ($mechanism= $this->options['params']['authMechanism'] ?? null) {
      $this->auth= Authentication::mechanism($mechanism);
    }
  }

  /** @return [:var] */
  public function options() { return $this->options; }

  /** @return [:com.mongodb.io.Connection] */
  public function connections() { return $this->conn; }

  /** Returns connection string */
  public function dsn(bool $password= false): string {
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
   * @return self
   * @throws com.mongodb.Error
   */
  public function connect() {
    $this->nodes || $this->establish(array_keys($this->conn), 'initial connect');
    return $this;
  }

  /**
   * Returns candidates for connecting to based on a given read preference.
   *
   * @see    https://github.com/mongodb/specifications/blob/master/source/server-selection/server-selection.rst#read-preference
   * @see    https://docs.mongodb.com/manual/core/read-preference-mechanics/
   * @param  [:var] $rp
   * @return string[]
   * @throws lang.IllegalArgumentException
   */
  public function candidates($rp) {
    if ('primary' === $rp['mode']) {
      return [$this->nodes['primary']];
    } else if ('secondary' === $rp['mode']) {
      return $this->nodes['secondary'];
    } else if ('primaryPreferred' === $rp['mode']) {
      return array_merge([$this->nodes['primary']], $this->nodes['secondary']);
    } else if ('secondaryPreferred' === $rp['mode']) {
      return array_merge($this->nodes['secondary'], [$this->nodes['primary']]);
    } else if ('nearest' === $rp['mode']) {  // Prefer to stay on already open connections
      $connected= null;
      foreach ($this->conn as $id => $conn) {
        if (null === $conn->server) continue;
        $connected= $id;
        break;
      }
      return array_unique(array_merge(
        (array)$connected,
        [$this->nodes['primary']],
        $this->nodes['secondary']
      ));
    }

    throw new IllegalArgumentException('Unknown read preference '.Objects::stringOf($rp));
  }

  /**
   * Refresh view of cluster with provided information by server
   *
   * @param  [:var] $server
   * @return void
   */
  private function useCluster($server) {
    $this->nodes= ['primary' => $server['primary'] ?? key($this->conn), 'secondary' => []];
    foreach ($server['hosts'] ?? [] as $host) {
      if ($server['primary'] !== $host) $this->nodes['secondary'][]= $host;
    }
    shuffle($this->nodes['secondary']);
  }

  /**
   * Establish a connection for a list of given candidates
   *
   * @param  string[] $candidates
   * @param  string $intent
   * @return com.mongodb.io.Connection
   * @throws com.mongodb.NoSuitableCandidate
   */
  public function establish($candidates, $intent) {
    $time= time();
    $cause= null;
    foreach ($candidates as $candidate) {
      try {
        $conn= $this->conn[$candidate];

        // Refresh view into cluster every time we succesfully connect to a node. For sockets that
        // have not been used for socketCheckInterval, issue the ping command to check liveness.
        if (null === $conn->server) {
          connect: $this->useCluster($conn->establish($this->options, $this->auth));
        } else if ($time - $conn->lastUsed >= $this->socketCheckInterval) {
          try {
            $conn->send(Connection::OP_MSG, "\x00\x00\x00\x00\x00", ['ping' => 1, '$db' => 'admin']);
          } catch (SocketException $e) {
            $conn->close();
            goto connect;
          }
        }

        return $conn;
      } catch (SocketException $e) {
        $conn->close();
        $cause ? $cause->setCause($e) : $cause= $e;
      }
    }

    throw new NoSuitableCandidate($intent, $candidates, $cause);
  }

  /**
   * Perform a read operation, which selecting a suitable node based on the
   * `readPreference` serting.
   *
   * @param  com.mongodb.Options[] $options
   * @param  [:var] $sections
   * @return var
   * @throws com.mongodb.Error
   */
  public function read(array $options, $sections) {
    foreach ($options as $option) {
      $sections+= $option->send($this);
    }

    $rp= $sections['$readPreference'] ?? $this->readPreference;
    return $this->establish($this->candidates($rp), 'reading with '.$rp['mode'])
      ->message($sections, $rp)
    ;
  }

  /**
   * Perform a write operation, which always uses the primary node.
   *
   * @param  com.mongodb.Options[] $options
   * @param  [:var] $sections
   * @return var
   * @throws com.mongodb.Error
   * @see    https://github.com/mongodb/mongo/blob/master/src/mongo/base/error_codes.yml
   */
  public function write(array $options, $sections) {
    static $NOT_PRIMARY= [10107 => 1, 11602 => 1, 13435 => 1, 13436 => 1];

    foreach ($options as $option) {
      $sections+= $option->send($this);
    }

    $rp= $sections['$readPreference'] ?? $this->readPreference;
    $retry= 1;

    // Use send() API to prevent using exceptions for flow control
    retry: $conn= $this->establish([$this->nodes['primary']], 'writing');
    $r= $conn->send(Connection::OP_MSG, "\x00\x00\x00\x00\x00", $sections, $rp);
    if (1 === (int)$r['body']['ok']) return $r;

    // Check for "NotWritablePrimary" error, which indicates our view of the cluster
    // may be outdated, see https://github.com/xp-forge/mongodb/issues/43. Refresh
    // view using the "hello" command, then retry the command once.
    if ($retry-- && isset($NOT_PRIMARY[$r['body']['code']])) {
      $this->useCluster($conn->hello());
      goto retry;
    }

    throw Error::newInstance($r['body']);
  }

  /** @return void */
  public function close() {
    foreach ($this->conn as $conn) { 
      $conn->close();
    }
    $this->nodes= null;
  }
}