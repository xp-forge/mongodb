<?php namespace com\mongodb;

use lang\Value;

class Int64 implements Value {
  private $number;

  /** @param int */
  public function __construct($number) {
    $this->number= $number;
  }

  /** @return int */
  public function number() { return $this->number; }

  /** @return string */
  public function __toString() { return $this->number; }

  /** @return string */
  public function hashCode() { return $this->string.'L'; }

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