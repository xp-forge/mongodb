<?php namespace com\mongodb\io;

class Zlib extends Compressor {
  public $id= 2;
  public $level;

  /** @param int $level */
  public function __construct($level= -1) {
    $this->level= $level;
  }

  public function compress($data) {
    return gzcompress($data, $this->level);
  }

  public function decompress($compressed) {
    return gzuncompress($compressed);
  }

  /** @return string */
  public function toString() {
    return nameof($this).'(id: '.$this->id.', level: '.$this->level.')';
  }
}