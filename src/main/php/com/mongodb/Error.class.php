<?php namespace com\mongodb;

use peer\ProtocolException;

class Error extends ProtocolException {
  private $kind;
  
  public function __construct($code, $kind, $message) {
    parent::__construct($message);
    $this->code= $code;
    $this->kind= $kind;
  }

  public static function newInstance($document) {
    return new self($document['code'], $document['codeName'], $document['errmsg']);
  }

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