<?php namespace com\mongodb\unittest;

use com\mongodb\Int64;
use com\mongodb\io\{BSON, Connection, Compression};
use peer\ConnectException;
use test\verify\Runtime;
use test\{Assert, Before, Expect, Test, Values};
use util\Date;

class ConnectionTest {
  private $bson;

  /** Creates an OP_REPLY message */
  private function reply(array $sections): array {
    $payload= $this->bson->sections($sections);
    return [
      pack('VVVV', strlen($payload) + 36, 0, 0, Connection::OP_REPLY),
      pack('VPVV', 0, 0, 0, 1).$payload
    ];
  }

  /** Creates an OP_MSG message */
  private function msg(array $document): array {
    $payload= $this->bson->sections($document);
    return [
      pack('VVVV', strlen($payload) + 21, 0, 0, Connection::OP_MSG),
      pack('VC', 0, 0).$payload
    ];
  }

  /** Creates an OP_COMPRESSED message with an embedded OP_MSG opcode */
  private function compressed(array $document): array {
    $payload= pack('VC', 0, 0).$this->bson->sections($document);
    $compressed= gzcompress($payload);
    return [
      pack('VVVV', strlen($compressed) + 25, 0, 0, Connection::OP_COMPRESSED),
      pack('VVC', Connection::OP_MSG, strlen($payload), 2).$compressed
    ];
  }

  #[Before]
  public function bson() {
    $this->bson= new BSON();
  }

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
    $server= [
      'ismaster'                     => true,
      'maxBsonObjectSize'            => 16777216,
      'maxMessageSizeBytes'          => 48000000,
      'maxWriteBatchSize'            => 100000,
      'localTime'                    => new Date('2020-08-04 13:18:57+0000'),
      'logicalSessionTimeoutMinutes' => 30,
      'minWireVersion'               => 0,
      'maxWireVersion'               => 6,
      'readOnly'                     => false,
      'ok'                           => 1.0,
    ];
    $c= new Connection(new TestingSocket($this->reply($server)));
    $c->establish();

    Assert::equals(['$kind' => 'Standalone'] + $server, $c->server);
  }

  #[Test, Values([[[]], [['compression' => []]], [['compression' => ['unsupported']]]])]
  public function no_compression_negotiated($preference) {
    $c= new Connection(new TestingSocket($this->reply($preference + [
      'ok'             => 1.0,
      'minWireVersion' => 0,
      'maxWireVersion' => 6,
    ])));
    $c->establish();

    Assert::null($c->compression);
  }

  #[Test, Runtime(extensions: ['zlib']), Values([[['zlib']], [['unsupported', 'zlib']]])]
  public function zlib_compression_negotiated($compression) {
    $c= new Connection(new TestingSocket($this->reply([
      'ok'             => 1.0,
      'minWireVersion' => 0,
      'maxWireVersion' => 6,
      'compression'    => $compression,
    ])));
    $c->establish();

    Assert::instance(Compression::class, $c->compression);
  }

  #[Test]
  public function send_and_receive() {
    $documents= [['_id' => 'one']];
    $c= new Connection(new TestingSocket([
      ...$this->reply(['ok' => 1.0]),
      ...$this->msg([
        'cursor' => ['firstBatch' => $documents, 'id' => new Int64(0), 'ns' => 'test.entries'],
        'ok'     => 1,
      ]),
    ]));
    $c->establish();
    $reply= $c->send(Connection::OP_MSG, "\x00\x00\x00\x00\x00", ['find' => 'entries', '$db' => 'test']);

    Assert::equals($documents, $reply['body']['cursor']['firstBatch']);
  }

  #[Test, Runtime(extensions: ['zlib'])]
  public function send_and_receive_compressed() {
    $documents= [['_id' => 'one']];
    $c= new Connection(new TestingSocket([
      ...$this->reply(['ok' => 1.0, 'compression' => ['zlib']]),
      ...$this->compressed([
        'cursor' => ['firstBatch' => $documents, 'id' => new Int64(0), 'ns' => 'test.entries'],
        'ok'     => 1,
      ]),
    ]));
    $c->establish();
    $reply= $c->send(Connection::OP_MSG, "\x00\x00\x00\x00\x00", ['find' => 'entries', '$db' => 'test']);

    Assert::equals($documents, $reply['body']['cursor']['firstBatch']);
  }
}