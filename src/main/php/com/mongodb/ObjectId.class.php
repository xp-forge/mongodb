<?php namespace com\mongodb;

use lang\Value;

class ObjectId implements Value {
  private $string;

  /** @param string */
  public function __construct($string) {
    $this->string= $string;
  }

  /** @return string */
  public function string() { return $this->string; }

  /** @return string */
  public function __toString() { return $this->string; }

  /** @return string */
  public function hashCode() { return 'I'.$this->string; }

  /** @return string */
  public function toString() { return nameof($this).'('.$this->string.')'; }

  /**
   * Compare
   *
   * @param  var $value
   * @return int
   */
  public function compareTo($value) {
    return $value instanceof self ? $this->string <=> $value->string : 1;
  }
}