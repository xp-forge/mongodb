<?php namespace com\mongodb;

use lang\Throwable;
use util\Secret;

/** @test com.mongodb.unittest.AuthenticationFailedTest */
class AuthenticationFailed extends CannotConnect {
  private $user, $secret;

  public function __construct(string $message, string $user, Secret $secret, $cause= null) {
    parent::__construct(18, 'AuthenticationFailed', $message, $cause);
    $this->user= $user;
    $this->secret= $secret;
  }

  /** Returns user used for connecting */
  public function user(): string { return $this->user; }

  /** Returns user secret used for connecting */
  public function secret(): Secret { return $this->secret; }
}