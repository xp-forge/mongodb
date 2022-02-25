<?php namespace com\mongodb\auth;

use com\mongodb\auth\Mechanism;
use lang\IllegalStateException;
use util\Bytes;

/**
 * Salted Challenge Response Authentication Mechanism (SCRAM) is the default
 * authentication mechanism for MongoDB.
 *
 * @test  com.mongodb.unittest.ScramSHA1Test
 * @see   https://docs.mongodb.com/manual/core/security-scram/
 */
class ScramSHA1 implements Mechanism {
  const MIN_ITERATIONS = 4096;
  const HASH_ALGORITHM = 'sha1';

  private $nonce= null;

  public function __construct() {
    $this->nonce= function() { return base64_encode(random_bytes(24)); };
  }

  /**
   * Use the given function to generate nonce values
   *
   * @param  function(): string $callable
   * @return self
   */
  public function nonce($callable) {
    $this->nonce= $callable;
    return $this;
  }

  private function pairs($payload) {
    $pairs= [];
    foreach (explode(',', $payload) as $pair) {
      sscanf($pair, "%[^=]=%[^\r]", $key, $value);
      $pairs[$key]= $value;
    }
    return $pairs;
  }

  /**
   * Returns a conversation dialogue yielding and receiving client and server
   * payloads, respectively.
   *
   * @return iterable
   */
  public function conversation(string $username, string $password, string $authsource) {

    // Step 1: Initiate conversation with username and a random nonce
    $gs2= 'n,,';
    $nonce= ($this->nonce)();
    $c1b= 'n='.$username.',r='.$nonce;

    $first= yield [
      'saslStart' => 1,
      'mechanism' => 'SCRAM-SHA-1',
      'payload'   => new Bytes($gs2.$c1b),
      '$db'       => $authsource,
    ];

    $pairs= $this->pairs($first['payload']);
    if (0 !== substr_compare($pairs['r'], $nonce, 0, strlen($nonce))) {
      throw new IllegalStateException('Server did not extend client nonce '.$nonce.' ('.$pairs['r'].')');
    }

    if ($pairs['i'] < self::MIN_ITERATIONS) {
      throw new IllegalStateException('Server requested less than '.self::MIN_ITERATIONS.' iterations ('.$pairs['i'].')');
    }

    // Step 2: User server-supplied nonce and hash password
    $c2wop= 'c='.base64_encode($gs2).',r='.$pairs['r'];
    $message= $c1b.','.$first['payload'].','.$c2wop;
    $salted= hash_pbkdf2(self::HASH_ALGORITHM, md5($username.':mongo:'.$password), base64_decode($pairs['s']), (int)$pairs['i'], 0, true);
    $client= hash_hmac(self::HASH_ALGORITHM, 'Client Key', $salted, true);
    $server= hash_hmac(self::HASH_ALGORITHM, 'Server Key', $salted, true);
    $signature= hash_hmac(self::HASH_ALGORITHM, $message, sha1($client, true), true);

    $next= yield [
      'saslContinue'   => 1,
      'payload'        => new Bytes($c2wop.',p='.base64_encode($client ^ $signature)),
      'conversationId' => $first['conversationId'],
      '$db'            => $authsource,
    ];

    $pairs= $this->pairs($next['payload']);
    $signature= hash_hmac('sha1', $message, $server, true);
    if (base64_decode($pairs['v']) !== $signature) {
      throw new IllegalStateException('Server validation failed '.base64_encode($signature).' ('.$pairs['v'].')');
    }

    // Step 3: After having verified server signature, finalize
    yield [
      'saslContinue'   => 1,
      'payload'        => '',
      'conversationId' => $next['conversationId'],
      '$db'            => $authsource,
    ];
  }
}