<?php namespace com\mongodb\unittest;

use com\mongodb\ObjectId;
use lang\IllegalArgumentException;
use test\{Assert, Expect, Test, Values};

class ObjectIdTest {
  const ID= '5f1dda9973edf2501751884b';

  #[Test]
  public function can_create() {
    new ObjectId(self::ID);
  }

  #[Test]
  public function string() {
    Assert::equals(self::ID, (new ObjectId(self::ID))->string());
  }

  #[Test]
  public function string_cast() {
    Assert::equals(self::ID, (string)(new ObjectId(self::ID)));
  }

  #[Test]
  public function less_than() {
    Assert::equals(-1, (new ObjectId(self::ID))->compareTo(new ObjectId('5f1dda9973edf2501751884c')));
  }

  #[Test]
  public function equal_to() {
    Assert::equals(0, (new ObjectId(self::ID))->compareTo(new ObjectId(self::ID)));
  }

  #[Test]
  public function larger_than() {
    Assert::equals(1, (new ObjectId(self::ID))->compareTo(new ObjectId('4f1dda9973edf2501751884b')));
  }

  #[Test]
  public function equals() {
    Assert::true((new ObjectId(self::ID))->equals(new ObjectId(self::ID)));
    Assert::false((new ObjectId(self::ID))->equals(new ObjectId('4f1dda9973edf2501751884b')));
  }

  #[Test]
  public function create() {
    Assert::matches('/^[0-9a-f]{24}$/', ObjectId::create());
  }

  #[Test]
  public function create_with_same_timestamp_generates_unique_ids() {
    $ts= time();
    Assert::notEquals(ObjectId::create($ts), ObjectId::create($ts));
  }

  #[Test]
  public function create_from_timestamp_is_monotonic() {
    $t= time();
    $oid= ObjectId::create($t);
    Assert::equals(dechex($t), substr($oid, 0, 8));
  }

  #[Test, Expect(IllegalArgumentException::class), Values(['', 'ABC', '5f1dda9973e****01751884b',])]
  public function cannot_create_from($string) {
    new ObjectId($string);
  }
}