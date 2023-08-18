<?php namespace com\mongodb;

class Options {
  private $pairs;

  /** @param [:var] */
  public function __construct(array $pairs) {
    $this->pairs= $pairs;
  }

  /**
   * Returns fields to be sent along with the command. Used by Protocol class.
   *
   * @param  com.mongodb.io.Protocol
   * @return [:var]
   */
  public function send($proto) {
    return $this->pairs;
  }
}