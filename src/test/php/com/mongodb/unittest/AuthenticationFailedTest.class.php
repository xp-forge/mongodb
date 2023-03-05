<?php namespace com\mongodb\unittest;

use com\mongodb\AuthenticationFailed;
use test\{Assert, Before, Test};
use util\Secret;

class AuthenticationFailedTest {
  private $args;

  #[Before]
  public function args() {
    $this->args= ['Password incorrect', 'admin', new Secret('incorrect')];
  }

  #[Test]
  public function can_create() {
    new AuthenticationFailed(...$this->args);
  }

  #[Test]
  public function user() {
    Assert::equals('admin', (new AuthenticationFailed(...$this->args))->user());
  }

  #[Test]
  public function secret() {
    Assert::equals('incorrect', (new AuthenticationFailed(...$this->args))->secret()->reveal());
  }

  #[Test]
  public function compoundMessage() {
    Assert::equals(
      'Exception com.mongodb.AuthenticationFailed (#18:AuthenticationFailed "Password incorrect")',
      (new AuthenticationFailed(...$this->args))->compoundMessage()
    );
  }
}