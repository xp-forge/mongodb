<?php namespace com\mongodb\auth;

/**
 * SCRAM-SHA-1 is defined in RFC 5802.
 *
 * @test  com.mongodb.unittest.ScramSHA1Test
 * @see   https://github.com/mongodb/specifications/blob/master/source/auth/auth.rst#scram-sha-1
 */
class ScramSHA1 extends Scram {
  const MECHANISM= 'SCRAM-SHA-1';
  const MIN_ITERATIONS= 4096;
  const HASH_ALGORITHM= 'sha1';

  /**
   * The password variable MUST be the mongodb hashed variant. The mongo hashed variant
   * is computed as `hash = HEX(MD5(UTF8(username + ':mongo:' + plain_text_password)))`
   *
   * @return iterable
   */
  public function conversation(string $username, string $password, string $authsource) {
    return parent::conversation($username, md5($username.':mongo:'.$password), $authsource);
  }
}