<?php namespace com\mongodb\unittest;

use peer\Socket;

class TestingSocket extends Socket {
  public $requests, $replies;
  public $connected= null;

  public function __construct($replies= []) {
    $this->requests= [];
    $this->replies= $replies;
    $this->host= 'localhost';
    $this->port= 27017;
  }

  public function connect($timeout= 2.0) {
    $this->connected= true;
  }

  public function write($bytes) {
    $this->requests[]= $bytes;
  }

  public function readBinary($length) {
    return array_shift($this->replies);
  }

  public function close() {
    $this->connected= false;
  }
}