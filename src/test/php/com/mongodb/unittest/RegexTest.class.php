<?php namespace com\mongodb\unittest;

use com\mongodb\Regex;
use test\{Assert, Test, Values};

class RegexTest {
  const PATTERN= '<a href="([^"]+)">';

  #[Test]
  public function can_create() {
    new Regex(self::PATTERN);
  }

  #[Test]
  public function defaults_to_empty_modifiers() {
    Assert::equals('', (new Regex(self::PATTERN))->modifiers());
  }

  #[Test, Values(['', 'i', 'im'])]
  public function modifiers($arg) {
    Assert::equals($arg, (new Regex(self::PATTERN, $arg))->modifiers());
  }

  #[Test]
  public function pattern() {
    Assert::equals(self::PATTERN, (new Regex(self::PATTERN))->pattern());
  }
}