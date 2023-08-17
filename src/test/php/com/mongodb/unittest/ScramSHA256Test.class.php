<?php namespace com\mongodb\unittest;

use com\mongodb\auth\ScramSHA256;
use lang\IllegalStateException;
use test\{Assert, Expect, Test};
use util\Bytes;

/** @see https://github.com/mongodb/specifications/blob/master/source/auth/auth.rst#scram-sha-256 */
class ScramSHA256Test {
  const CLIENT_NONCE = 'rOprNGfwEbeRWgbNEkqO';
  const SERVER_NONCE = '%hvYDpWUa2RaTCAfuxFIlj)hNlF$k0';
  const SERVER_SALT  = 'W22ZaJ0SNY7soEsUEjb6gQ==';
  const DATABASE     = 'test';

  /** @return com.mongodb.auth.ScramSHA256 */
  private function newFixture() {
    return (new ScramSHA256())
      ->nonce(function() { return self::CLIENT_NONCE; })
      ->conversation('user', 'pencil', self::DATABASE)
    ;
  }

  #[Test]
  public function first_message() {
    $auth= $this->newFixture();

    Assert::equals(
      [
        'saslStart' => 1,
        'mechanism' => 'SCRAM-SHA-256',
        'payload'   => new Bytes('n,,n=user,r='.self::CLIENT_NONCE),
        '$db'       => self::DATABASE
      ],
      $auth->current()
    );
  }

  #[Test]
  public function next_message() {
    $auth= $this->newFixture();
    $auth->send([
      'conversationId' => 1,
      'payload'        => new Bytes('r='.self::CLIENT_NONCE.self::SERVER_NONCE.',s='.self::SERVER_SALT.',i=4096'),
    ]);

    Assert::equals(
      [
        'saslContinue'   => 1,
        'payload'        => new Bytes('c=biws,r='.self::CLIENT_NONCE.self::SERVER_NONCE.',p=dHzbZapWIk4jUhN+Ute9ytag9zjfMHgsqmmiz7AndVQ='),
        'conversationId' => 1,
        '$db'            => self::DATABASE
      ],
      $auth->current()
    );
  }

  #[Test]
  public function final_message() {
    $auth= $this->newFixture();
    $auth->send([
      'conversationId' => 1,
      'payload'        => new Bytes('r='.self::CLIENT_NONCE.self::SERVER_NONCE.',s='.self::SERVER_SALT.',i=4096'),
    ]);
    $auth->send([
      'conversationId' => 1,
      'payload'        => new Bytes('v=6rriTRBi23WpRR/wtup+mMhUZUn/dB5nLTJRsjl95G4='),
    ]);

    Assert::equals(
      [
        'saslContinue'   => 1,
        'payload'        => '',
        'conversationId' => 1,
        '$db'            => self::DATABASE
      ],
      $auth->current()
    );
  }
}