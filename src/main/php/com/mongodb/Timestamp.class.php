<?php namespace com\mongodb;

use lang\Value;

/**
 * BSON has a special timestamp type for internal MongoDB use and is not
 * associated with the regular Date type.
 *
 * @test  com.mongodb.unittest.TimestampTest
 * @see   https://docs.mongodb.com/manual/reference/bson-types/#timestamps
 */
class Timestamp implements Value {
  private $seconds, $increment;

  /**
   * Creates a new timestamp
   *
   * @param  int $seconds
   * @param  int $increment
   */
  public function __construct($seconds, $increment= 1) {
    $this->seconds= $seconds;
    $this->increment= $increment;
  }

  /** @return int */
  public function seconds() { return $this->seconds; }

  /** @return int */
  public function increment() { return $this->increment; }

  /** @return string */
  public function __toString() { return $this->seconds.','.$this->increment; }

  /** @return string */
  public function hashCode() { return $this->seconds.','.$this->increment; }

  /** @return string */
  public function toString() { return nameof($this).'('.$this->seconds.', '.$this->increment.')'; }

  /**
   * Compare
   *
   * @param  var $value
   * @return int
   */
  public function compareTo($value) {
    return $value instanceof self ? $this->seconds.','.$this->increment <=> $value->seconds.','.$value->increment : 1;
  }
}