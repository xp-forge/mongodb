<?php namespace com\mongodb;

class Options {
  private $pairs;

  /** @param [:var] */
  public function __construct(array $pairs= []) {
    $this->pairs= $pairs;
  }

  /**
   * Sets read preference
   *
   * @param  string $mode
   * @return self
   */
  public function readPreference($mode) {
    $this->pairs['$readPreference']= ['mode' => $mode];
    return $this;
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