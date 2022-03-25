<?php namespace com\mongodb;

use lang\Value;
use math\BigInt;

/**
 * Decimal 128
 *
 * @ext   bcmath
 * @test  com.mongodb.unittest.Decimal128Test
 * @see   https://github.com/estolfo/bson-ruby/blob/RUBY-1098-decimal128/lib/bson/decimal128.rb
 */
class Decimal128 implements Value {
  const NAN = 0x7c00000000000000;
  const INF = 0x7800000000000000;
  const THB = 0x6000000000000000;
  const SBM = '9223372036854775808';
  const U64 = '18446744073709551616';

  const EXPONENT_OFFSET = 6176;

  private $lo, $hi;
  private $string= null;

  /** @param ?string|int $in */
  public function __construct($in= null) {
    if (null === $in) return;

    $decimal= explode('.', ltrim($in, '+-')) + ['', ''];
    if ($decimal[0] >= 0) {
      $digits= $decimal[0].$decimal[1];
    } else {
      $digits= ltrim($decimal[1], '0');
    }

    // Round exact, then clamp
    $exponent= -strlen($decimal[1]);
    while ($exponent < -6176 && '0' === $digits[strlen($digits) - 1]) {
      $exponent++;
      $digits= substr($digits, 0, -1);
    }
    while ($exponent > 6111 && strlen($digits) < 34) {
      $exponent--;
      $digits.= '0';
    }
    $exponent+= self::EXPONENT_OFFSET;

    // Hi = Digits >> 64, Lo = (Hi << 64) ^ Digits
    $d= new BigInt($digits);
    $this->hi= $d->shiftRight(64);
    $this->lo= $this->hi->shiftLeft(64)->bitwiseXor($d);

    if ($this->hi->shiftRight(49)->equals(1)) {
      $this->hi= $this->hi
        ->bitwiseAnd(0x7fffffffffff)
        ->bitwiseOr(self::THB)
        ->bitwiseOr(($exponent & 0x3fff) << 47)
      ;
    } else {
      $this->hi= $this->hi->bitwiseOr($exponent << 49);
    }

    // Handle sign
    if (is_int($in) ? $in < 0 : '-' === $in[0]) {
      $this->hi= $this->hi->bitwiseOr(self::SBM);
    }
  }

  /**
   * Creates a new instance with LO and HI values
   *
   * @param  int $lo
   * @param  int $hi
   * @return self
   */
  public static function create($lo, $hi) {
    $self= new self();
    $self->lo= $lo < 0 ? (new BigInt($lo))->add0(self::U64) : new BigInt($lo);
    $self->hi= $hi < 0 ? (new BigInt($hi))->add0(self::U64) : new BigInt($hi);
    return $self;
  }

  /** @return int */
  public function lo() {
    return ($this->lo->compare('9223372036854775807') > 0 ? $this->lo->subtract0(self::U64) : $this->lo)->intValue();
  }

  /** @return int */
  public function hi() {
    return ($this->hi->compare('9223372036854775807') > 0 ? $this->hi->subtract0(self::U64) : $this->hi)->intValue();
  }

  /** @return string */
  public function __toString() {
    if (null !== $this->string) return $this->string;

    $sign= $this->hi->bitwiseAnd(self::SBM)->equals(self::SBM) ? '-' : '';

    // Special values
    if ($this->hi->bitwiseAnd(self::NAN)->equals(self::NAN)) return $this->string= 'NaN';
    if ($this->hi->bitwiseAnd(self::INF)->equals(self::INF)) return $this->string= $sign.'Infinity';

    // The two highest bits of the 64 high order bits are set
    if (self::THB === $this->hi->bitwiseAnd(self::THB)->intValue()) {
      $significand= '0';
      $exponent= $this->hi->bitwiseAnd(0x1fffe00000000000)->shiftRight(47)->subtract(self::EXPONENT_OFFSET)->intValue();
    } else {
      $significand= (string)$this->hi->bitwiseAnd(0x1ffffffffffff)->shiftLeft(64)->bitwiseOr($this->lo);
      $exponent= $this->hi->bitwiseAnd(0x7fff800000000000)->shiftRight(49)->subtract(self::EXPONENT_OFFSET)->intValue();
    }

    // Handle exponent
    if ($exponent >= 0) return $this->string= $sign.$significand;
    $l= strlen($significand);
    $a= abs($exponent);
    if ($l > $a) {
      $dec= $l - $a;
      return $this->string= $sign.substr($significand, 0, $dec).'.'.substr($significand, $dec);
    } else {
      $pad= abs($exponent + $l);
      return $this->string= $sign.'0.'.str_repeat('0', $pad).$significand;
    }
  }

  /** @return string */
  public function hashCode() { return $this->lo.','.$this->hi.'D'; }

  /** @return string */
  public function toString() { return nameof($this).'('.$this->__toString().')'; }

  /**
   * Compare
   *
   * @param  var $value
   * @return int
   */
  public function compareTo($value) {
    return $value instanceof self ? $this->__toString() <=> $value->__toString() : 1;
  }
}