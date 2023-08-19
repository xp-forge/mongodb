<?php namespace com\mongodb;

/** @test com.mongodb.unittest.OptionsTest */
class Options {
  protected $pairs;

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
   * Sets read concern (the read isolation level)
   *
   * @see    https://www.mongodb.com/docs/manual/reference/read-concern/
   * @param  string $level
   * @return self
   */
  public function readConcern($level) {
    $this->pairs['readConcern']= ['level' => $level];
    return $this;
  }

  /**
   * Sets write concern (the write acknowledgment)
   *
   * @see    https://www.mongodb.com/docs/manual/reference/write-concern/
   * @param  string|int $w
   * @param  ?int $wtimeout
   * @param  ?bool $journal
   * @return self
   */
  public function writeConcern($w, $wtimeout= null, $journal= null) {
    $this->pairs['writeConcern']= ['w' => $w];
    null === $wtimeout || $this->pairs['writeConcern']['wtimeout']= (int)$wtimeout;
    null === $journal || $this->pairs['writeConcern']['j']= (bool)$journal;
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