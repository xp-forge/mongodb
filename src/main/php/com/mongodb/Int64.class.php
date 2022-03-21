<?php namespace com\mongodb;

use lang\Value;

/** @test com.mongodb.unittest.Int64Test */
class Int64 implements Value {
  private $number;

  /** @param int */
  public function __construct($number) {
    $this->number= $number;
  }

  /** @return int */
  public function number() { return $this->number; }

  /** @return string */
  public function __toString() { return (string)$this->number; }

  /** @return string */
  public function hashCode() { return $this->number.'L'; }

  /** @return string */
  public function toString() { return nameof($this).'('.$this->number.')'; }

  /**
   * Compare
   *
   * @param  var $value
   * @return int
   */
  public function compareTo($value) {
    return $value instanceof self ? $this->number <=> $value->number : 1;
  }
}