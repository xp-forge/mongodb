<?php namespace com\mongodb;

use peer\ProtocolException;

/**
 * MongoDB error
 *
 * @see   https://raw.githubusercontent.com/mongodb/mongo/master/src/mongo/base/error_codes.yml
 */
class Error extends ProtocolException {
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
   * @return self
   */
  public static function newInstance($document) {
    return new self($document['code'], $document['codeName'], $document['errmsg']);
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