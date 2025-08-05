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

  public function compress($data) {
    return $this->algorithm->compress($data, $this->options);
  }

  public function decompress($compressed) {
    return $this->algorithm->decompress($compressed);
  }

  public function toString() { return nameof($this).'(id: '.$this->id.', options: '.$this->options.')'; }

  public function hashCode() { return 'C'.$this->id; }

  public function compareTo($value) { return $value instanceof self ? $this->id <=> $value->id : 1; }

}