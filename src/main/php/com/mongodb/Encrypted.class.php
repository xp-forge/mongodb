<?php namespace com\mongodb;

class Encrypted {
  private $ciphertext;

  public function __construct($ciphertext) {
    $this->ciphertext= (string)$ciphertext;
  }

  /** @return int */
  public function length() { return strlen($this->ciphertext); }

  /** @return string */
  public function ciphertext() { return $this->ciphertext; }

}