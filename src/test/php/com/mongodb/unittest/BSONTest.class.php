<?php namespace com\mongodb\unittest;

use com\mongodb\io\BSON;
use com\mongodb\{Decimal128, Document, Int64, ObjectId, Regex, Timestamp};
use lang\{FormatException, IllegalArgumentException};
use unittest\{Assert, Expect, Test, Values};
use util\{Bytes, Date, UUID};

class BSONTest {

  /** @return iterable */
  private function values() {
    static $uuid= '5f375bfe-af78-4af8-bb03-5d441a66a5fb';

    // Constants
    yield [false, "\x08test\x00\x00"];
    yield [true, "\x08test\x00\x01"];
    yield [null, "\x0atest\x00"];

    // Strings
    yield ['', "\x02test\x00\x01\x00\x00\x00\x00"];
    yield ['Test', "\x02test\x00\x05\x00\x00\x00Test\x00"];

    // Floating point
    yield [0.0, "\x01test\x00\x00\x00\x00\x00\x00\x00\x00\x00"];
    yield [6.1, "\x01test\x00\x66\x66\x66\x66\x66\x66\x18\x40"];
    yield [-6.1, "\x01test\x00\x66\x66\x66\x66\x66\x66\x18\xc0"];

    // Integers
    yield [0, "\x10test\x00\x00\x00\x00\x00"];
    yield [6100, "\x10test\x00\xd4\x17\x00\x00"];
    yield [-6100, "\x10test\x00\x2c\xe8\xff\xff"];

    // Long integers
    yield [new Int64(0), "\x12test\x00\x00\x00\x00\x00\x00\x00\x00\x00"];
    yield [new Int64(9223372036854775807), "\x12test\x00\xff\xff\xff\xff\xff\xff\xff\x7f"];

    // 128 bit decimal numbers
    yield [new Decimal128('0'), "\x13test\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x40\x30"];
    yield [new Decimal128('0.99'), "\x13test\x00\x63\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x3c\x30"];
    yield [new Decimal128('-6.10'), "\x13test\x00\x62\x02\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x3c\xb0"];
    yield [new Decimal128('1234567.89'), "\x13test\x00\x15\xcd\x5b\x07\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x3c\x30"];
    yield [new Decimal128('12699025049277956096.22'), "\x13test\x00\x16\x00\x00\x00\x00\x00\x70\xd7\x44\x00\x00\x00\x00\x00\x3c\x30"];

    // Arrays
    yield [[], "\x04test\x00\x05\x00\x00\x00\x00"];
    yield [[1, 2], "\x04test\x00\x13\x00\x00\x00\x10\x30\x00\x01\x00\x00\x00\x10\x31\x00\x02\x00\x00\x00\x00"];

    // Objects
    yield [(object)[], "\x03test\x00\x05\x00\x00\x00\x00"];
    yield [['one' => 1], "\x03test\x00\x0e\x00\x00\x00\x10one\x00\x01\x00\x00\x00\x00"];

    // Special types
    yield [new Bytes('abc'), "\x05test\x00\x03\x00\x00\x00\x00abc"];
    yield [new Date('2020-08-03 17:41 +0200'), "\x09test\x00\xe0\xae\xfb\xb4\x73\x01\x00\x00"];
    yield [new Date('1969-08-03 17:41 +0200'), "\x09test\x00\xe0\x3e\xbd\xf9\xfc\xff\xff\xff"];
    yield [new Timestamp(1596543032, 1), "\x11test\x00\x01\x00\x00\x00\x38\x50\x29\x5f"];
    yield [new UUID($uuid), "\x05test\x00\x10\x00\x00\x00\x04\x5f\x37\x5b\xfe\xaf\x78\x4a\xf8\xbb\x03\x5d\x44\x1a\x66\xa5\xfb"];
    yield [new ObjectId('5f1dda9973edf2501751884b'), "\x07test\x00\x5f\x1d\xda\x99\x73\xed\xf2\x50\x17\x51\x88\x4b"];
  }

  #[Test, Values('values')]
  public function encode($value, $bytes) {
    Assert::equals(new Bytes($bytes), new Bytes((new BSON())->bytes('test', $value)));
  }

  #[Test, Values('values')]
  public function decode($value, $bytes) {
    $offset= 0;
    Assert::equals($value, (new BSON())->value('test', $bytes, $offset));
  }

  #[Test]
  public function encode_document() {
    Assert::equals(
      new Bytes("\x03test\x00\x0e\x00\x00\x00\x10one\x00\x01\x00\x00\x00\x00"),
      new Bytes((new BSON())->bytes('test', new Document(['one' => 1])))
    );
  }

  #[Test]
  public function encode_traversable() {
    $f= function() {
      yield 'q'     => (object)[];
      yield 'u'     => ['$set' => ['active' => true]];
      yield 'multi' => false;
    };
    Assert::equals(
      new Bytes(
        "\x03test\x00\x31\x00\x00\x00".
        "\x03q\x00\x05\x00\x00\x00\x00".
        "\x03u\x00\x19\x00\x00\x00\x03\$set\x00\x0e\x00\x00\x00\x08active\x00\x01\x00\x00".
        "\x08multi\x00\x00".
        "\x00"
      ),
      new Bytes((new BSON())->bytes('test', $f()))
    );
  }

  #[Test]
  public function encode_regex() {
    Assert::equals(
      new Bytes("\x0btest\x0099[a-z]+\x00i\x00"),
      new Bytes((new BSON())->bytes('test', new Regex('99[a-z]+', 'i')))
    );
  }

  #[Test, Expect(['class'       => IllegalArgumentException::class, 'withMessage' => '/Cannot encode value test of type .+BSONTest/',])]
  public function encode_unknown() {
    (new BSON())->bytes('test', $this);
  }

  #[Test, Expect(['class'       => FormatException::class, 'withMessage' => '/Unknown type 0x99: .+/',])]
  public function decode_unknown() {
    $offset= 0;
    (new BSON())->value('test', "\x99test\x00ABC", $offset);
  }
}