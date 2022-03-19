<?php namespace com\mongodb;

use lang\Throwable;

class NoSuitableCandidate extends Error {
  private $candidates;

  public function __construct(string $intent, array $candidates, Throwable $cause= null) {
    parent::__construct(
      6,
      'HostUnreachable',
      'No suitable candidate eligible for '.$intent.', tried '.implode(', ', $candidates),
      $cause
    );
  }

  /** @return string[] */
  public function candidates() { return $this->candidates; }
}