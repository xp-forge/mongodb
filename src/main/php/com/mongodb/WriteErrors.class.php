<?php namespace com\mongodb;

use util\Objects;

/** @see https://www.mongodb.com/docs/manual/reference/command/update/#mongodb-data-update.writeErrors */
class WriteErrors extends Error {
  public $result;

  /** Creates a new instance of this error */
  public function __construct($result) {
    parent::__construct(112, 'WriteConflict', 'Write error(s) occurred: '.Objects::stringOf($result['writeErrors']));
    $this->result= $result;
  }

  /** @return [:var][] */
  public function list() { return $this->result['writeErrors']; }
}