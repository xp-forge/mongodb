<?php namespace com\mongodb\unittest;

use com\mongodb\Error;
use unittest\{Assert, Test};

class ErrorTest {
  const ARGS= [6100, 'MorePower', 'Too much power'];

  #[Test]
  public function can_create() {
    new Error(...self::ARGS);
  }

  #[Test]
  public function code() {
    Assert::equals(6100, (new Error(...self::ARGS))->getCode());
  }

  #[Test]
  public function message() {
    Assert::equals('Too much power', (new Error(...self::ARGS))->getMessage());
  }

  #[Test]
  public function compoundMessage() {
    Assert::equals(
      'Exception com.mongodb.Error (#6100:MorePower "Too much power")',
      (new Error(...self::ARGS))->compoundMessage()
    );
  }

  #[Test]
  public function newInstance() {
    $document= [
      'code'     => 6100,
      'codeName' => 'MorePower',
      'errmsg'   => 'Too much power'
    ];

    Assert::equals((new Error(...self::ARGS))->compoundMessage(), Error::newInstance($document)->compoundMessage());
  }
}