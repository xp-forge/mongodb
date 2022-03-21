<?php namespace com\mongodb\unittest;

use com\mongodb\Timestamp;
use unittest\{Assert, Test, Values};

class TimestampTest {
  const SECONDS= 1647897308;

  #[Test]
  public function can_create() {
    new Timestamp(self::SECONDS);
  }

  #[Test]
  public function seconds() {
    Assert::equals(self::SECONDS, (new Timestamp(self::SECONDS))->seconds());
  }

  #[Test]
  public function increment_defaults_to_one() {
    Assert::equals(1, (new Timestamp(self::SECONDS))->increment());
  }

  #[Test]
  public function increment() {
    Assert::equals(14, (new Timestamp(self::SECONDS, 14))->increment());
  }

  #[Test]
  public function string_cast() {
    Assert::equals(self::SECONDS.',14', (string)new Timestamp(self::SECONDS, 14));
  }

  #[Test]
  public function hash_of() {
    Assert::equals(self::SECONDS.',14', (new Timestamp(self::SECONDS, 14))->hashCode());
  }

  #[Test]
  public function string_representation() {
    Assert::equals('com.mongodb.Timestamp('.self::SECONDS.', 14)', (new Timestamp(self::SECONDS, 14))->toString());
  }

  #[Test]
  public function comparison() {
    $fixture= new Timestamp(self::SECONDS);

    Assert::equals(new Timestamp(self::SECONDS), $fixture);
    Assert::notEquals(new Timestamp(self::SECONDS, 14), $fixture);
    Assert::notEquals(new Timestamp(6100), $fixture);
  }
}