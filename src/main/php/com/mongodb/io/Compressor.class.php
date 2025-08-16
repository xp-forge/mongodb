<?php namespace com\mongodb\io;

use io\streams\compress\Algorithm;
use lang\Value;

class Compressor implements Value {
  public $id, $algorithm, $options;

  public function __construct(int $id, Algorithm $algorithm, $options= null) {
    $this->id= $id;
    $this->algorithm= $algorithm;
    $this->options= $options;
  }

  public function toString() { return nameof($this).'(id: '.$this->id.', options: '.$this->options.')'; }

  public function hashCode() { return 'C'.$this->id; }

  public function compareTo($value) { return $value instanceof self ? $this->id <=> $value->id : 1; }

}