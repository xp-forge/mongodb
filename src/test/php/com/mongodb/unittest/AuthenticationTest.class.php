<?php namespace com\mongodb\unittest;

use com\mongodb\Authentication;
use com\mongodb\auth\Mechanism;
use lang\IllegalArgumentException;
use unittest\Assert;

class AuthenticationTest {

  #[@test]
  public function cram_sha_1() {
    Assert::instance(Mechanism::class, Authentication::mechanism('SCRAM-SHA-1'));
  }

  #[@test, @expect(IllegalArgumentException::class)]
  public function unknown() {
    Authentication::mechanism('unknown');
  }
}