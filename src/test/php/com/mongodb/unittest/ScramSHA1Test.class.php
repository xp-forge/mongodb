<?php namespace com\mongodb\unittest;

use com\mongodb\auth\ScramSHA1;
use lang\IllegalStateException;
use unittest\Assert;
use util\Bytes;

/** @see https://github.com/xdg-go/scram/tree/master/testdata */
class ScramSHA1Test {
  const CLIENT_NONCE = 'clientNONCE';
  const SERVER_NONCE = 'serverNONCE';
  const SERVER_SALT  = 'c2FsdFNBTFRzYWx0';
  const DATABASE     = 'test';

  /** @return com.mongodb.auth.ScramSHA1 */
  private function newFixture() {
    return (new ScramSHA1())
      ->nonce(function() { return self::CLIENT_NONCE; })
      ->conversation('user', 'pencil', self::DATABASE)
    ;
  }

  #[@test]
  public function first_message() {
    $auth= $this->newFixture();

    Assert::equals(
      [
        'saslStart' => 1,
        'mechanism' => 'SCRAM-SHA-1',
        'payload'   => new Bytes('n,,n=user,r='.self::CLIENT_NONCE),
        '$db'       => self::DATABASE
      ],
      $auth->current()
    );
  }

  #[@test]
  public function next_message() {
    $auth= $this->newFixture();
    $auth->send([
      'conversationId' => 1,
      'payload'        => new Bytes('r='.self::CLIENT_NONCE.self::SERVER_NONCE.',s='.self::SERVER_SALT.',i=4096'),
    ]);

    Assert::equals(
      [
        'saslContinue'   => 1,
        'payload'        => new Bytes('c=biws,r='.self::CLIENT_NONCE.self::SERVER_NONCE.',p=ip667BSjGUFu+Kr6RPDvrZM+cQE='),
        'conversationId' => 1,
        '$db'            => self::DATABASE
      ],
      $auth->current()
    );
  }

  #[@test]
  public function final_message() {
    $auth= $this->newFixture();
    $auth->send([
      'conversationId' => 1,
      'payload'        => new Bytes('r='.self::CLIENT_NONCE.self::SERVER_NONCE.',s='.self::SERVER_SALT.',i=4096'),
    ]);
    $auth->send([
      'conversationId' => 1,
      'payload'        => new Bytes('v=dRzECHF7m5mH86oElDPZ/kl7CEU='),
    ]);

    Assert::equals(
      [
        'saslContinue'   => 1,
        'payload'        => '',
        'done'           => true,
        'conversationId' => 1,
        '$db'            => self::DATABASE
      ],
      $auth->current()
    );
  }

  #[@test, @expect(['class' => IllegalStateException::class, 'withMessage' => '/Server requested less than 4096 iterations.+/'])]
  public function iterations_must_be_4096_min() {
    $auth= $this->newFixture();
    $auth->send([
      'conversationId' => 1,
      'payload'        => new Bytes('r='.self::CLIENT_NONCE.self::SERVER_NONCE.',s='.self::SERVER_SALT.',i=2048'),
    ]);
  }

  #[@test, @expect(['class' => IllegalStateException::class, 'withMessage' => '/Server did not extend client nonce.+/'])]
  public function fails_to_extend_client_nonce() {
    $auth= $this->newFixture();
    $auth->send([
      'conversationId' => 1,
      'payload'        => new Bytes('r=INCORRECT_NONCE'.self::SERVER_NONCE.',s='.self::SERVER_SALT.',i=4096'),
    ]);
  }

  #[@test, @expect(['class' => IllegalStateException::class, 'withMessage' => '/Server validation failed.+/'])]
  public function server_validation_fails() {
    $auth= $this->newFixture();
    $auth->send([
      'conversationId' => 1,
      'payload'        => new Bytes('r='.self::CLIENT_NONCE.self::SERVER_NONCE.',s='.self::SERVER_SALT.',i=4096'),
    ]);
    $auth->send([
      'conversationId' => 1,
      'payload'        => new Bytes('v=INCORRECT_SIGNATURE'),
    ]);
  }
}