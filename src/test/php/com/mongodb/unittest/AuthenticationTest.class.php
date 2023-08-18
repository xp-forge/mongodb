<?php namespace com\mongodb\unittest;

use com\mongodb\Authentication;
use com\mongodb\auth\Mechanism;
use lang\IllegalArgumentException;
use test\{Assert, Expect, Test};

class AuthenticationTest {

  #[Test]
  public function sha1_is_default_mechanism() {
    Assert::equals('SCRAM-SHA-1', Authentication::MECHANISMS[0]);
  }

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

  #[Test]
  public function negotiate_sha1() {
    Assert::equals('SCRAM-SHA-1', Authentication::negotiate(['SCRAM-SHA-1'])->name());
  }

  #[Test]
  public function negotiate_sha1_preferred() {
    Assert::equals('SCRAM-SHA-1', Authentication::negotiate(['SCRAM-SHA-1', 'SCRAM-SHA-256'])->name());
  }

  #[Test]
  public function negotiate_sha256_preferred() {
    Assert::equals('SCRAM-SHA-256', Authentication::negotiate(['SCRAM-SHA-256', 'SCRAM-SHA-1'])->name());
  }

  #[Test]
  public function negotiate_unsupported_plain_preferred() {
    Assert::equals('SCRAM-SHA-1', Authentication::negotiate(['PLAIN', 'SCRAM-SHA-1'])->name());
  }

  #[Test, Expect(IllegalArgumentException::class)]
  public function negotiate_none_supported() {
    Authentication::negotiate(['PLAIN', 'AWS'])->name();
  }

  #[Test, Expect(IllegalArgumentException::class)]
  public function negotiate_empty() {
    Authentication::negotiate([])->name();
  }
}