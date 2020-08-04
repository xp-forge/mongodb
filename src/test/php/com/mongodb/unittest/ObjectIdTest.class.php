<?php namespace com\mongodb\unittest;

use com\mongodb\ObjectId;
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
}