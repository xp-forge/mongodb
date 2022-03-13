<?php namespace com\mongodb\unittest;

use com\mongodb\io\Connection;
use unittest\{Assert, Test};
use util\Date;

class ConnectionTest {

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
}