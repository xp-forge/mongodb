<?php namespace com\mongodb\auth;

/**
 * The MongoDB SCRAM-SHA-256 mechanism works similarly to the SCRAM-SHA-1 mechanism.
 *
 * @test  com.mongodb.unittest.ScramSHA256Test
 * @see   https://github.com/mongodb/specifications/blob/master/source/auth/auth.rst#scram-sha-256
 */
class ScramSHA256 extends Scram {
  const MECHANISM= 'SCRAM-SHA-256';
  const MIN_ITERATIONS= 4096;
  const HASH_ALGORITHM= 'sha256';

}