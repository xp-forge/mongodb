<?php namespace com\mongodb\io;

class Zlib extends Compressor {
  public $id= 2;
  public $level;

  public function __construct($level) {
    $this->level= $level;
  }

  public function compress($data) {
    return gzcompress($data, $this->level);
  }

  public function decompress($compressed) {
    return gzuncompress($compressed);
  }
}