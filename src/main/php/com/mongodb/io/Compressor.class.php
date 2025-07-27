<?php namespace com\mongodb\io;

use lang\Value;

abstract class Compressor implements Value {
  public $id;

  public abstract function compress($data);

  public abstract function decompress($compressed);

  public function toString() { return nameof($this).'(id: '.$this->id.')'; }

  public function hashCode() { return 'C'.$this->id; }

  public function compareTo($value) { return $value instanceof self ? $this->id <=> $value->id : 1; }

}