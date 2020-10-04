<?php namespace com\mongodb\unittest\result;

use com\mongodb\ObjectId;
use com\mongodb\result\Insert;
use lang\IllegalStateException;
use unittest\{Assert, Expect, Test};

class InsertTest {
  const ID= '5f1dda9973edf2501751884b';

  #[Test]
  public function can_create() {
    new Insert(['n' => 1, 'ok' => 1], [new ObjectId(self::ID)]);
  }

  #[Test]
  public function inserted() {
    Assert::equals(
      1,
      (new Insert(['n' => 1, 'ok' => 1], [new ObjectId(self::ID)]))->inserted()
    );
  }

  #[Test]
  public function ids() {
    Assert::equals(
      [new ObjectId(self::ID)],
      (new Insert(['n' => 1, 'ok' => 1], [new ObjectId(self::ID)]))->ids()
    );
  }

  #[Test]
  public function id_for_single_result() {
    Assert::equals(
      new ObjectId(self::ID),
      (new Insert(['n' => 1, 'ok' => 1], [new ObjectId(self::ID)]))->id()
    );
  }

  #[Test, Expect(IllegalStateException::class)]
  public function id_throws_if_invoked_on_result_with_multiple_inserts() {
    (new Insert(['n' => 2, 'ok' => 1], [new ObjectId(self::ID), new ObjectId('4f1dda9973edf2501751884b')]))->id();
  }
}