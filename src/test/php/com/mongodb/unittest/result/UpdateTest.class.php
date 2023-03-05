<?php namespace com\mongodb\unittest\result;

use com\mongodb\ObjectId;
use com\mongodb\result\Update;
use test\{Assert, Test};

class UpdateTest {
  const RESULT= ['n' => 1, 'nModified' => 0, 'ok' => 1];

  #[Test]
  public function can_create() {
    new Update(self::RESULT);
  }

  #[Test]
  public function matched() {
    Assert::equals(1, (new Update(self::RESULT))->matched());
  }

  #[Test]
  public function modified() {
    Assert::equals(0, (new Update(self::RESULT))->modified());
  }

  #[Test]
  public function upserted() {
    $upsert= ['index' => 0, '_id' => new ObjectId('631c6206306c05628f1caff7')];
    Assert::equals([$upsert['_id']], (new Update(self::RESULT + ['upserted' => [$upsert]]))->upserted());
  }

  #[Test]
  public function no_upsert() {
    Assert::equals([], (new Update(self::RESULT))->upserted());
  }

  #[Test]
  public function string_representation() {
    Assert::equals(
      "com.mongodb.result.Update@[\n  n => 1\n  nModified => 0\n  ok => 1\n]",
      (new Update(self::RESULT))->toString()
    );
  }

  #[Test]
  public function comparison() {
    Assert::equals(new Update(self::RESULT), new Update(self::RESULT));
    Assert::notEquals(new Update(['n' => 2, 'nModified' => 0, 'ok' => 1]), new Update(self::RESULT));
  }

  #[Test]
  public function hash_of() {
    Assert::equals('U548269860', (new Update(self::RESULT))->hashCode());
  }
}