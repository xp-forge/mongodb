<?php namespace com\mongodb;

use com\mongodb\auth\{Mechanism, ScramSHA1};
use lang\IllegalArgumentException;

abstract class Authentication {

  /**
   * Returns an authentication by a given mechanism name
   *
   * @throws lang.IllegalArgumentException if the mechanism is unknown
   */
  public static function mechanism(string $name): Mechanism {
    switch ($name) {
      case 'SCRAM-SHA-1': return new ScramSHA1();
      // case 'SCRAM-SHA-256': return new ScramSHA256();
      default: throw new IllegalArgumentException('Unknown authentication mechanism '.$name);
    }
  }
}