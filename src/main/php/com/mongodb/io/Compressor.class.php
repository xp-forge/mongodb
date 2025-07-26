<?php namespace com\mongodb\io;

abstract class Compressor {
  public $id;

  public abstract function compress($data);

  public abstract function decompress($compressed);

}