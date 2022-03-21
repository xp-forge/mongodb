<?php namespace com\mongodb;

use lang\Value;

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

  const EXPONENT_OFFSET = 6176;

  private $lo, $hi;
  private $string= null;

  /** @param ?string|int $in */
  public function __construct($in= null) {
    static $I64= '9223372036854775807';
    static $S64= '18446744073709551616';

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
    $this->hi= bcdiv($digits, $S64);
    $h= bcmul($this->hi, $S64);
    $p= unpack('J2', pack('JJ',
      bcdiv($h, $I64), bcmod($h, $I64)) ^ pack('JJ', bcdiv($digits, $I64), bcmod($digits, $I64))
    );
    $n= bcadd(bcmul($p[1], $I64), $p[2]);
    $this->lo= bccomp($n, $I64) > 0 ? bcsub($n, $S64) : $n;

    if (1 === $this->hi >> 49) {
      $this->hi &= 0x7fffffffffff;
      $this->hi |= self::THB;
      $this->hi |= ($exponent & 0x3fff) << 47;
    } else {
      $this->hi |= $exponent << 49;
    }

    // Handle sign
    if (is_int($in) ? $in < 0 : '-' === $in[0]) {
      $this->hi |= (1 << 63);
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
    $self->lo= $lo;
    $self->hi= $hi;
    return $self;
  }

  /** @return int */
  public function lo() { return $this->lo; }

  /** @return int */
  public function hi() { return $this->hi; }

  /** @return string */
  public function __toString() {
    if (null !== $this->string) return $this->string;
    $sign= $this->hi < 0 ? '-' : '';

    // Special values
    if (self::NAN === $this->hi & self::NAN) return $this->string= 'NaN';
    if (self::INF === $this->hi & self::INF) return $this->string= $sign.'Infinity';

    // The two highest bits of the 64 high order bits are set
    if (self::THB === $this->hi & self::THB) {
      $significand= '0';
      $exponent= (($this->hi & 0x1fffe00000000000) >> 47) - self::EXPONENT_OFFSET;
    } else {
      $significand= ltrim(((($this->hi & 0x1ffffffffffff) << 64) | $this->lo), '-');
      $exponent= (($this->hi & 0x7fff800000000000) >> 49) - self::EXPONENT_OFFSET;
    }

    // Handle exponent
    if ($exponent >= 0) return $this->string= $sign.$significand;
    $l= strlen($significand);
    if ($l > abs($exponent)) {
      $dec= $l - abs($exponent);
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