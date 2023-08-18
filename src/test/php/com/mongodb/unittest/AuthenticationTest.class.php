<?php namespace com\mongodb\unittest;

use com\mongodb\Authentication;
use com\mongodb\auth\Mechanism;
use lang\IllegalArgumentException;
use test\{Assert, Expect, Test, Values};

class AuthenticationTest {

  #[Test]
  public function scram_sha_1() {
    Assert::instance(Mechanism::class, Authentication::mechanism('SCRAM-SHA-1'));
  }

  #[Test]
  public function scram_sha_256() {
    Assert::instance(Mechanism::class, Authentication::mechanism('SCRAM-SHA-256'));
  }

  #[Test, Expect(IllegalArgumentException::class)]
  public function unknown() {
    Authentication::mechanism('unknown');
  }

  #[Test, Values([[['SCRAM-SHA-1']], [['SCRAM-SHA-256']]])]
  public function negotiate_only_option($supplied) {
    Assert::equals($supplied[0], Authentication::negotiate($supplied)->name());
  }

  #[Test, Values([[['SCRAM-SHA-256', 'SCRAM-SHA-1']], [['SCRAM-SHA-1', 'SCRAM-SHA-256']]])]
  public function negotiate_sha256_is_default_if_contained($supplied) {
    Assert::equals('SCRAM-SHA-256', Authentication::negotiate($supplied)->name());
  }

  #[Test]
  public function negotiate_with_unsupported_plain() {
    Assert::equals('SCRAM-SHA-1', Authentication::negotiate(['PLAIN', 'SCRAM-SHA-1'])->name());
  }

  #[Test]
  public function negotiate_returns_sha1_if_empty() {
    Assert::equals('SCRAM-SHA-1', Authentication::negotiate([])->name());
  }

  #[Test, Expect(IllegalArgumentException::class)]
  public function negotiate_none_supported() {
    Authentication::negotiate(['PLAIN', 'AWS'])->name();
  }
}