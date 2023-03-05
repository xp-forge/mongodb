<?php namespace com\mongodb\unittest;

use com\mongodb\Int64;
use test\{Assert, Test, Values};

class Int64Test {

  /** @return iterable */
  private function numbers() {
    yield [0];
    yield [1];
    yield [-1];
    yield [PHP_INT_MAX];
    yield [PHP_INT_MIN];
  }

  #[Test]
  public function can_create() {
    new Int64(0);
  }

  #[Test, Values(from: 'numbers')]
  public function number($n) {
    Assert::equals($n, (new Int64($n))->number());
  }

  #[Test, Values(from: 'numbers')]
  public function string_cast($n) {
    Assert::equals((string)$n, (string)new Int64($n));
  }

  #[Test, Values(from: 'numbers')]
  public function string_representation($n) {
    Assert::equals("com.mongodb.Int64({$n})", (new Int64($n))->toString());
  }

  #[Test, Values(from: 'numbers')]
  public function hash_of($n) {
    Assert::equals($n.'L', (new Int64($n))->hashCode());
  }

  #[Test, Values(from: 'numbers')]
  public function comparison($n) {
    $fixture= new Int64($n);

    Assert::equals(new Int64($n), $fixture);
    Assert::notEquals(new Int64(6100), $fixture);
  }
}