<?php namespace com\mongodb\unittest;

use peer\{Socket, ConnectException};

class TestingSocket extends Socket {
  public $requests, $replies;
  private $connected= false;

  public function __construct($replies= [], $address= 'localhost:27017') {
    $this->requests= [];
    $this->replies= $replies;
    sscanf($address, '%[^:]:%d', $this->host, $this->port);
  }

  public function connect($timeout= 2.0) {
    if (null === $this->replies) {
      throw new ConnectException('Cannot connect to '.$this->host.':'.$this->port.' within '.$timeout.' seconds');
    }
    $this->connected= true;
  }

  public function isConnected() {
    return $this->connected;
  }

  public function write($bytes) {
    $this->requests[]= $bytes;
  }

  public function readBinary($maxLen= 4096) {
    return array_shift($this->replies);
  }

  public function close() {
    $this->connected= false;
  }
}