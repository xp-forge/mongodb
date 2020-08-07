<?php namespace com\mongodb\unittest;

use com\mongodb\{Operations, Modifications, ObjectId};
use lang\IllegalArgumentException;
use unittest\Assert;

class OperationsTest {
  const ID = '5f1dda9973edf2501751884b';
  private static $expressions= ['$set' => ['item' => 'Test']];

  #[@test]
  public function can_create() {
    new Operations(self::$expressions);
  }

  #[@test, @values([
  #  [self::ID],
  #  [new ObjectId(self::ID)],
  #  [['_id' => new ObjectId(self::ID)]],
  #])]
  public function select($id) {
    Assert::equals(
      new Modifications(self::$expressions, ['_id' => new ObjectId(self::ID)], false),
      (new Operations(self::$expressions))->select($id)
    );
  }

  #[@test, @values([
  #  [[]],
  #  [['name' => 'Test']],
  #  [['qty' => ['$lt' => 5]]],
  #])]
  public function where($filter) {
    Assert::equals(
      new Modifications(self::$expressions, $filter, true),
      (new Operations(self::$expressions))->where($filter)
    );
  }

  #[@test, @expect(IllegalArgumentException::class)]
  public function filter_passed_to_select_cannot_be_empty() {
    (new Operations(self::$expressions))->select([]);
  }

  #[@test, @expect(IllegalArgumentException::class)]
  public function malformed_object_id_passed_to_select() {
    (new Operations(self::$expressions))->select('not.an.object.id');
  }
}