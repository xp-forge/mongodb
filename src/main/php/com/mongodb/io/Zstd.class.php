<?php namespace com\mongodb\io;

class Zstd extends Compressor {
  public $id= 3;
  public $level;

  /** @param int $level */
  public function __construct($level= -1) {
    $this->level= $level;
  }

  public function compress($data) {
    return zstd_compress($data, $this->level);
  }

  public function decompress($compressed) {
    return zstd_uncompress($compressed);
  }

  /** @return string */
  public function toString() {
    return nameof($this).'(id: '.$this->id.', level: '.$this->level.')';
  }
}