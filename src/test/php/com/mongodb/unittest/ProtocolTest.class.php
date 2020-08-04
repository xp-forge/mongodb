<?php namespace com\mongodb\unittest;

use com\mongodb\{Protocol, ObjectId};
use lang\IllegalArgumentException;
use unittest\Assert;
use util\{Bytes, Date};

class ProtocolTest {

  #[@test]
  public function can_create_with_connection_string() {
    new Protocol('mongodb://localhost');
  }

  #[@test]
  public function can_create_with_socket() {
    new Protocol(new TestingSocket());
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

  #[@test]
  public function connect_handshake_populates_server_options() {
    $s= new TestingSocket([
      "\xef\x00\x00\x00\x92\x09\x00\x00\x02\x00\x00\x00\x01\x00\x00\x00",
      "\x08\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00".
      "\x01\x00\x00\x00\xcb\x00\x00\x00\x08ismaster\x00\x01\x10maxBsonO".
      "bjectSize\x00\x00\x00\x00\x01\x10maxMessageSizeBytes\x00\x00l\xdc".
      "\x02\x10maxWriteBatchSize\x00\xa0\x86\x01\x00\x09localTime\x00\xef".
      "\x00\xa0\xb9s\x01\x00\x00\x10logicalSessionTimeoutMinutes\x00\x1e".
      "\x00\x00\x00\x10minWireVersion\x00\x00\x00\x00\x00\x10maxWireVers".
      "ion\x00\x06\x00\x00\x00\x08readOnly\x00\x00\x01ok\x00\x00\x00\x00".
      "\x00\x00\x00\xf0?\x00"
    ]);

    $p= new Protocol($s);
    $p->connect();

    Assert::equals(
      [
        'ismaster' => true,
        'maxBsonObjectSize' => 16777216,
        'maxMessageSizeBytes' => 48000000,
        'maxWriteBatchSize' => 100000,
        'localTime' => new Date('2020-08-04 13:18:57+0000'),
        'logicalSessionTimeoutMinutes' => 30,
        'minWireVersion' => 0,
        'maxWireVersion' => 6,
        'readOnly' => false,
        'ok' => 1.0,
      ],
      $p->options()['server'],
    );
  }
}