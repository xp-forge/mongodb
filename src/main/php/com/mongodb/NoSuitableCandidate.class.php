<?php namespace com\mongodb;

use lang\Throwable;

/** @test com.mongodb.unittest.NoSuitableCandidateTest */
class NoSuitableCandidate extends Error {
  private $candidates;

  public function __construct(string $intent, array $candidates, Throwable $cause= null) {
    parent::__construct(
      6,
      'HostUnreachable',
      'No suitable candidate eligible for '.$intent.', tried '.implode(', ', $candidates),
      $cause
    );
    $this->candidates= $candidates;
  }

  /** @return string[] */
  public function candidates() { return $this->candidates; }
}