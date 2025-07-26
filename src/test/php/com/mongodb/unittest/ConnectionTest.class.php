<?php namespace com\mongodb\unittest;

use com\mongodb\io\{Connection, Compression};
use peer\ConnectException;
use test\verify\Runtime;
use test\{Assert, Expect, Test, Values};
use util\Date;

class ConnectionTest {
  use WireTesting;

  #[Test]
  public function can_create() {
    new Connection(new TestingSocket([]));
  }

  #[Test, Expect(class: ConnectException::class, message: 'Cannot connect to localhost:27017 within 40 seconds')]
  public function establish_throws_connect_exception_when_socket_connect_fails() {
    $c= new Connection(new TestingSocket(null));
    $c->establish();
  }

  #[Test, Expect(class: ConnectException::class, message: 'SSL handshake failed')]
  public function establish_throws_connect_exception_when_ssl_handshake_fails() {
    $c= new Connection(new TestingSocket([]));
    $c->establish(['params' => ['ssl' => 'true']]);
  }

  #[Test, Expect(class: ConnectException::class, message: 'Server handshake failed @ localhost:27017')]
  public function establish_throws_connect_exception_when_server_sends_empty_reply() {
    $c= new Connection(new TestingSocket([]));
    $c->establish();
  }

  #[Test]
  public function connect_handshake_populates_server_options() {
    $c= new Connection(new TestingSocket([
      "\xef\x00\x00\x00\x92\x09\x00\x00\x02\x00\x00\x00\x01\x00\x00\x00",
      "\x08\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00".
      "\x01\x00\x00\x00\xcb\x00\x00\x00\x08ismaster\x00\x01\x10maxBsonO".
      "bjectSize\x00\x00\x00\x00\x01\x10maxMessageSizeBytes\x00\x00l\xdc".
      "\x02\x10maxWriteBatchSize\x00\xa0\x86\x01\x00\x09localTime\x00\xef".
      "\x00\xa0\xb9s\x01\x00\x00\x10logicalSessionTimeoutMinutes\x00\x1e".
      "\x00\x00\x00\x10minWireVersion\x00\x00\x00\x00\x00\x10maxWireVers".
      "ion\x00\x06\x00\x00\x00\x08readOnly\x00\x00\x01ok\x00\x00\x00\x00".
      "\x00\x00\x00\xf0?\x00"
    ]));
    $c->establish();

    Assert::equals(
      [
        '$kind' => 'Standalone',
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
      $c->server
    );
  }

  #[Test, Values([[[], false], [['compression' => []], false], [['compression' => ['unsupported']], false]])]
  public function no_compression_negotiated($server, $expected) {
    $c= new TestingConnection(self::$PRIMARY, [$this->hello(self::$PRIMARY, $server)]);
    $c->establish();

    Assert::null($c->compression);
  }

  #[Test, Runtime(extensions: ['zlib'])]
  public function zlib_compression_negotiated() {
    $c= new TestingConnection(self::$PRIMARY, [$this->hello(self::$PRIMARY, ['compression' => ['zlib']])]);
    $c->establish();

    Assert::instance(Compression::class, $c->compression);
  }
}