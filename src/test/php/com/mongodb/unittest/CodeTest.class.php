<?php namespace com\mongodb\unittest;

use com\mongodb\Code;
use test\{Assert, Test};

class CodeTest {
  const SOURCE= 'console.log("Test")';

  #[Test]
  public function can_create() {
    new Code(self::SOURCE);
  }

  #[Test]
  public function source() {
    Assert::equals(self::SOURCE, (new Code(self::SOURCE))->source());
  }

  #[Test]
  public function length() {
    Assert::equals(strlen(self::SOURCE), (new Code(self::SOURCE))->length());
  }

  #[Test]
  public function hash_of() {
    Assert::equals('JS2892344631', (new Code(self::SOURCE))->hashCode());
  }

  #[Test]
  public function string_representation() {
    Assert::equals('com.mongodb.Code(`console.log("Test")`)', (new Code(self::SOURCE))->toString());
  }

  #[Test]
  public function string_cast() {
    Assert::equals(self::SOURCE, (string)new Code(self::SOURCE));
  }

  #[Test]
  public function compare() {
    Assert::equals(0, (new Code(self::SOURCE))->compareTo(new Code(self::SOURCE)));
    Assert::equals(1, (new Code(self::SOURCE))->compareTo(new Code('')));
  }

  #[Test]
  public function equals() {
    Assert::true((new Code(self::SOURCE))->equals(new Code(self::SOURCE)));
    Assert::false((new Code(self::SOURCE))->equals(new Code('')));
  }
}