<?php namespace com\mongodb\unittest;

use com\mongodb\{Protocol, ObjectId};
use lang\IllegalArgumentException;
use peer\Socket;
use unittest\Assert;
use util\Bytes;

class ProtocolTest {

  #[@test]
  public function can_create_with_connection_string() {
    new Protocol('mongodb://localhost');
  }

  #[@test]
  public function can_create_with_socket() {
    new Protocol(new Socket('localhost', 27017));
  }

  #[@test, @values([
  #  ['mongodb://localhost', ['params' => []]],
  #  ['mongodb://localhost?tls=true', ['params' => ['tls' => 'true']]],
  #  ['mongodb://u:p@localhost', ['user' => 'u', 'pass' => 'p', 'params' => []]],
  #  ['mongodb://u:p@localhost/admin', ['path' => '/admin', 'user' => 'u', 'pass' => 'p', 'params' => []]],
  #])]
  public function options_via_connection_string($uri, $expected) {
    Assert::equals(
      ['scheme' => 'mongodb', 'host' => 'localhost'] + $expected,
      (new Protocol($uri))->options()
    );
  }

  #[@test]
  public function options_merged_with_connection_string() {
    $fixture= new Protocol('mongodb://localhost?authSource=test', [
      'user'   => 'test',
      'params' => ['tls' => 'true']
    ]);
    
    Assert::equals(
      ['scheme' => 'mongodb', 'host' => 'localhost', 'user' => 'test', 'params' => [
        'authSource' => 'test',
        'tls'        => 'true'
      ]],
      $fixture->options()
    );
  }

  #[@test, @expect([
  #  'class'       => IllegalArgumentException::class,
  #  'withMessage' => 'Unknown authentication mechanism UNKNOWN'
  #])]
  public function unknown_auth_mechanism() {
    new Protocol('mongodb://localhost?authMechanism=UNKNOWN');
  }
}