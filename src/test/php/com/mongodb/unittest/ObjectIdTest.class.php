<?php namespace com\mongodb\unittest;

use com\mongodb\ObjectId;
use lang\IllegalArgumentException;
use unittest\Assert;

class ObjectIdTest {
  const ID = '5f1dda9973edf2501751884b';

  #[@test]
  public function can_create() {
    new ObjectId(self::ID);
  }

  #[@test]
  public function string() {
    Assert::equals(self::ID, (new ObjectId(self::ID))->string());
  }

  #[@test]
  public function string_cast() {
    Assert::equals(self::ID, (string)(new ObjectId(self::ID)));
  }

  #[@test]
  public function less_than() {
    Assert::equals(-1, (new ObjectId(self::ID))->compareTo(new ObjectId('5f1dda9973edf2501751884c')));
  }

  #[@test]
  public function equal_to() {
    Assert::equals(0, (new ObjectId(self::ID))->compareTo(new ObjectId(self::ID)));
  }

  #[@test]
  public function larger_than() {
    Assert::equals(1, (new ObjectId(self::ID))->compareTo(new ObjectId('4f1dda9973edf2501751884b')));
  }

  #[@test]
  public function create() {
    Assert::equals(1, preg_match('/^[0-9a-f]{24}$/', ObjectId::create()));
  }

  #[@test]
  public function create_generates_unique_ids() {
    Assert::notEquals(ObjectId::create(), ObjectId::create());
  }

  #[@test]
  public function create_from_timestamp() {
    $t= time();
    $oid= ObjectId::create($t);
    Assert::equals(dechex($t), substr($oid, 0, 8));
  }

  #[@test, @expect(IllegalArgumentException::class), @values([
  #  '',
  #  'ABC',
  #  '5f1dda9973e****01751884b',
  #])]
  public function cannot_create_from($string) {
    new ObjectId($string);
  }
}