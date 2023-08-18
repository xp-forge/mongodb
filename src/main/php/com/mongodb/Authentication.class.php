<?php namespace com\mongodb;

use com\mongodb\auth\{Mechanism, ScramSHA1, ScramSHA256};
use lang\IllegalArgumentException;

/** @test com.mongodb.unittest.AuthenticationTest */
abstract class Authentication {
  const MECHANISMS= ['SCRAM-SHA-256', 'SCRAM-SHA-1'];

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
   * If SCRAM-SHA-256 is present in the list of mechanism, then it MUST be used as
   * the default; otherwise, SCRAM-SHA-1 MUST be used as the default, regardless of
   * whether SCRAM-SHA-1 is in the list. If saslSupportedMechs is not present in the
   * handshake response for mechanism negotiation, then SCRAM-SHA-1 MUST be used
   *
   * @see    https://github.com/mongodb/specifications/blob/master/source/auth/auth.rst#defaults
   * @throws lang.IllegalArgumentException if none are supported
   */
  public static function negotiate(array $mechanisms): Mechanism {
    if (empty($mechanisms)) {
      return self::mechanism('SCRAM-SHA-1');
    } else if ($supported= array_intersect(self::MECHANISMS, $mechanisms)) {
      return self::mechanism(current($supported));
    }

    throw new IllegalArgumentException(sprintf(
      'None of the authentication mechanisms %s is supported',
      implode(', ', $mechanisms)
    ));
  }
}