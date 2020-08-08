<?php namespace com\mongodb\result;

class Delete extends Result {

  /** Returns number of matched documents */
  public function deleted(): int { return $this->result['n']; }

}
