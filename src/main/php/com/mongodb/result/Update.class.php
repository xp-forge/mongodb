<?php namespace com\mongodb\result;

class Update extends Result {

  /** Returns number of matched documents */
  public function matched(): int { return $this->result['n']; }

  /** Returns number of modified documents */
  public function modified(): int { return $this->result['nModified']; }

}
