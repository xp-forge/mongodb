<?php namespace com\mongodb;

class NoSuitableCandidates extends Error {
  
  public function __construct($intent, $candidates, $cause= null) {
    parent::__construct(
      6,
      'HostUnreachable',
      'No suitable candidates eligible for '.$intent.', tried '.implode(', ', $candidates),
      $cause
    );
  }
}