<?php namespace com\mongodb;

use lang\Throwable;

class NoSuitableCandidates extends Error {
  
  public function __construct(string $intent, array $candidates, Throwable $cause= null) {
    parent::__construct(
      6,
      'HostUnreachable',
      'No suitable candidates eligible for '.$intent.', tried '.implode(', ', $candidates),
      $cause
    );
  }
}