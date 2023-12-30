<?php namespace com\mongodb\unittest;

use com\mongodb\Decimal128;
use test\{Assert, Test, Values};

class Decimal128Test {

  /** @return iterable */
  private function numbers() {
    yield ['0'];
    yield ['1'];
    yield ['1'];
    yield ['-1'];
    yield ['9223372036854775807'];
    yield ['-9223372036854775808'];
    yield ['0.5'];
    yield ['-0.5'];
    yield ['12345689012345789012345'];
    yield ['-12345689012345789012345'];
  }

  #[Test]
  public function can_create() {
    new Decimal128('0');
  }

  #[Test, Values(from: 'numbers')]
  public function string_cast($n) {
    Assert::equals((string)$n, (string)new Decimal128($n));
  }

  #[Test, Values([['00', '0'], ['01', '1'], ['0000128', '128']])]
  public function trims_leading_zeroes($input, $expected) {
    Assert::equals($expected, (string)new Decimal128($input));
  }

  #[Test, Values([['+0', '0'], ['+1', '1'], ['+128', '128']])]
  public function trims_leading_plus($input, $expected) {
    Assert::equals($expected, (string)new Decimal128($input));
  }

  #[Test, Values([['.5', '0.5'], ['+.5', '0.5'], ['-.5', '-0.5']])]
  public function leading_zero_for_fractions_without($input, $expected) {
    Assert::equals($expected, (string)new Decimal128($input));
  }

  #[Test, Values(from: 'numbers')]
  public function string_representation($n) {
    Assert::equals("com.mongodb.Decimal128({$n})", (new Decimal128($n))->toString());
  }

  #[Test, Values(from: 'numbers')]
  public function comparison($n) {
    $fixture= new Decimal128($n);

    Assert::equals(new Decimal128($n), $fixture);
    Assert::notEquals(new Decimal128(6100), $fixture);
  }

  #[Test, Values(from: 'numbers')]
  public function equals($n) {
    $fixture= new Decimal128($n);

    Assert::true((new Decimal128($n))->equals($fixture));
    Assert::false((new Decimal128(6100))->equals($fixture));
  }
}