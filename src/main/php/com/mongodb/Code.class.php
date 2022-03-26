<?php namespace com\mongodb;

use lang\Value;

/** @test com.mongodb.unittest.CodeTest */
class Code implements Value {
  private $source;

  /** @param string */
  public function __construct($source) {
    $this->source= $source;
  }

  /** @return string */
  public function source() { return $this->source; }

  /** @return int */
  public function length() { return strlen($this->source); }

  /** @return string */
  public function __toString() { return (string)$this->source; }

  /** @return string */
  public function hashCode() { return 'JS'.crc32($this->source); }

  /** @return string */
  public function toString() { return nameof($this).'(`'.$this->source.'`)'; }

  /**
   * Compare
   *
   * @param  var $value
   * @return int
   */
  public function compareTo($value) {
    return $value instanceof self ? $this->source <=> $value->source : 1;
  }
}