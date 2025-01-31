<?php namespace com\mongodb\auth;

use lang\IllegalStateException;
use util\Bytes;

/**
 * Salted Challenge Response Authentication Mechanism (SCRAM) is the default
 * authentication mechanism for MongoDB.
 *
 * @see   https://docs.mongodb.com/manual/core/security-scram/
 */
abstract class Scram implements Mechanism {
  protected $nonce= null;

  /** Creates a new instance, initializing nonce */
  public function __construct() {
    $this->nonce= fn() => base64_encode(random_bytes(24));
  }

  /** @return string */
  public function name() { return static::MECHANISM; }

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

  /**
   * Parses `k=...,r=...` into a map
   *
   * @param  string $bytes
   * @return [:string]
   */
  protected function pairs($bytes) {
    $pairs= [];
    foreach (explode(',', $bytes) as $pair) {
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
      'mechanism' => static::MECHANISM,
      'payload'   => new Bytes($gs2.$c1b),
      '$db'       => $authsource,
    ];

    $pairs= $this->pairs($first['payload']);
    if (0 !== substr_compare($pairs['r'], $nonce, 0, strlen($nonce))) {
      throw new IllegalStateException('Server did not extend client nonce '.$nonce.' ('.$pairs['r'].')');
    }

    if ($pairs['i'] < static::MIN_ITERATIONS) {
      throw new IllegalStateException('Server requested less than '.static::MIN_ITERATIONS.' iterations ('.$pairs['i'].')');
    }

    // Step 2: User server-supplied nonce and hash password
    $c2wop= 'c='.base64_encode($gs2).',r='.$pairs['r'];
    $message= $c1b.','.$first['payload'].','.$c2wop;
    $salted= hash_pbkdf2(static::HASH_ALGORITHM, $password, base64_decode($pairs['s']), (int)$pairs['i'], 0, true);
    $client= hash_hmac(static::HASH_ALGORITHM, 'Client Key', $salted, true);
    $server= hash_hmac(static::HASH_ALGORITHM, 'Server Key', $salted, true);
    $signature= hash_hmac(static::HASH_ALGORITHM, $message, hash(static::HASH_ALGORITHM, $client, true), true);

    $next= yield [
      'saslContinue'   => 1,
      'payload'        => new Bytes($c2wop.',p='.base64_encode($client ^ $signature)),
      'conversationId' => $first['conversationId'],
      '$db'            => $authsource,
    ];

    $pairs= $this->pairs($next['payload']);
    $signature= hash_hmac(static::HASH_ALGORITHM, $message, $server, true);
    if (base64_decode($pairs['v']) !== $signature) {
      throw new IllegalStateException('Server validation failed '.base64_encode($signature).' ('.$pairs['v'].')');
    }

    // Step 3: After having verified server signature, finalize
    yield [
      'saslContinue'   => 1,
      'payload'        => new Bytes([]),
      'conversationId' => $next['conversationId'],
      '$db'            => $authsource,
    ];
  }
}