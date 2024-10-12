<?php namespace com\mongodb;

use lang\XPException;

/**
 * MongoDB error
 *
 * @test  com.mongodb.unittest.ErrorTest
 * @see   https://raw.githubusercontent.com/mongodb/mongo/master/src/mongo/base/error_codes.yml
 */
class Error extends XPException {
  const NOT_PRIMARY= [10107 => 1, 11602 => 1, 13435 => 1, 13436 => 1];

  private $kind;
  
  /**
   * Creates a new error
   *
   * @param  int $code
   * @param  string $kind
   * @param  string $message
   * @param  ?lang.Throwable $cause
   */
  public function __construct($code, $kind, $message, $cause= null) {
    parent::__construct($message, $cause);
    $this->code= $code;
    $this->kind= $kind;
  }

  /**
   * Creates an error from a given error document
   *
   * @param  [:var] $document
   * @param  int|bool $retried
   * @return self
   */
  public static function newInstance($document, $retried= 0) {
    return new self(
      $document['code'],
      $document['codeName'],
      $document['errmsg'].($retried ? " - retried {$retried} time(s)" : '')
    );
  }

  /** @return string */
  public function compoundMessage() {
    return sprintf(
      'Exception %s (#%d:%s "%s")',
      nameof($this),
      $this->code,
      $this->kind,
      $this->message
    );
  }
}