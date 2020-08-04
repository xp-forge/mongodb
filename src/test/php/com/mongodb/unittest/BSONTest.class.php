<?php namespace com\mongodb\unittest;

use com\mongodb\{BSON, ObjectId, Int64, Timestamp, Document, Regex};
use lang\{IllegalArgumentException, FormatException};
use unittest\Assert;
use util\{Bytes, Date};

class BSONTest {

  /** @return iterable */
  private function values() {

    // Constants
    yield [false, "\x08test\x00\x00"];
    yield [true, "\x08test\x00\x01"];
    yield [null, "\x0atest"];

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

    // Arrays
    yield [[], "\x04test\x00\x05\x00\x00\x00\x00"];
    yield [[1, 2], "\x04test\x00\x13\x00\x00\x00\x10\x30\x00\x01\x00\x00\x00\x10\x31\x00\x02\x00\x00\x00\x00"];

    // Objects
    yield [['one' => 1], "\x03test\x00\x0e\x00\x00\x00\x10one\x00\x01\x00\x00\x00\x00"];
    yield [(object)[], "\x03test\x00\x05\x00\x00\x00\x00"];

    // Special types
    yield [new Bytes('abc'), "\x05test\x00\x03\x00\x00\x00\x00abc"];
    yield [new Date('2020-08-03 17:41 +0200'), "\x09test\x00\xe0\xae\xfb\xb4\x73\x01\x00\x00"];
    yield [new Date('1969-08-03 17:41 +0200'), "\x09test\x00\xe0\x3e\xbd\xf9\xfc\xff\xff\xff"];
    yield [new Timestamp(1596543032, 1), "\x11test\x00\x01\x00\x00\x00\x38\x50\x29\x5f"];
    yield [new ObjectId('5f1dda9973edf2501751884b'), "\x07test\x00\x5f\x1d\xda\x99\x73\xed\xf2\x50\x17\x51\x88\x4b"];
  }

  #[@test, @values('values')]
  public function encode($value, $bytes) {
    Assert::equals(new Bytes($bytes), new Bytes((new BSON())->bytes('test', $value)));
  }

  #[@test, @values('values')]
  public function decode($value, $bytes) {
    $offset= 0;
    Assert::equals($value, (new BSON())->value('test', $bytes, $offset));
  }

  #[@test]
  public function encode_document() {
    Assert::equals(
      new Bytes("\x03test\x00\x0e\x00\x00\x00\x10one\x00\x01\x00\x00\x00\x00"),
      new Bytes((new BSON())->bytes('test', new Document(['one' => 1])))
    );
  }

  #[@test]
  public function encode_regex() {
    Assert::equals(
      new Bytes("\x0btest\x0099[a-z]+\x00i\x00"),
      new Bytes((new BSON())->bytes('test', new Regex('99[a-z]+', 'i')))
    );
  }

  #[@test, @expect([
  #  'class'       => IllegalArgumentException::class,
  #  'withMessage' => '/Cannot encode value test of type .+BSONTest/',
  #])]
  public function encode_unknown() {
    (new BSON())->bytes('test', $this);
  }

  #[@test, @expect([
  #  'class'       => FormatException::class,
  #  'withMessage' => '/Unknown type 0x99: .+/',
  #])]
  public function decode_unknown() {
    $offset= 0;
    (new BSON())->value('test', "\x99test\x00ABC", $offset);
  }
}