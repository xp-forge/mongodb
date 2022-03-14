<?php namespace com\mongodb;

use lang\Throwable;
use peer\ConnectException;
use util\Secret;

class AuthenticationFailed extends ConnectException {
  private $user, $secret;

  public function __construct(string $message, string $user, Secret $secret, Throwable $cause= null) {
    parent::__construct($message, $cause);
    $this->user= $user;
    $this->secret= $secret;
  }

  /** Returns user used for connecting */
  public function user(): string { return $this->user; }

  /** Returns user secret used for connecting */
  public function secret(): Secret { return $this->secret; }
}