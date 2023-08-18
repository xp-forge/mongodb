<?php namespace com\mongodb;

use com\mongodb\auth\{Mechanism, ScramSHA1, ScramSHA256};
use lang\IllegalArgumentException;

/** @test com.mongodb.unittest.AuthenticationTest */
abstract class Authentication {
  const MECHANISMS= ['SCRAM-SHA-1', 'SCRAM-SHA-256'];

  /**
   * Returns an authentication by a given mechanism name
   *
   * @throws lang.IllegalArgumentException if the mechanism is unknown
   */
  public static function mechanism(string $name): Mechanism {
    switch ($name) {
      case 'SCRAM-SHA-1': return new ScramSHA1();
      case 'SCRAM-SHA-256': return new ScramSHA256();
      default: throw new IllegalArgumentException('Unknown authentication mechanism '.$name);
    }
  }

  /**
   * Negotiates one of the supported authentication mechansim from a list
   * of given mechanisms.
   *
   * @throws lang.IllegalArgumentException if none are supported
   */
  public static function negotiate(array $mechanisms): Mechanism {
    if ($supported= array_intersect($mechanisms, self::MECHANISMS)) {
      return self::mechanism(current($supported));
    }

    throw new IllegalArgumentException(sprintf(
      'None of the authentication mechanisms %s is supported',
      implode(', ', $mechanisms)
    ));
  }
}