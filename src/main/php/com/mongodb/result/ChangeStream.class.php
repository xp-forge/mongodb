<?php namespace com\mongodb\result;

class ChangeStream extends Cursor {

  /** @return ?[:var] */
  public function resumeToken() {
    return $this->current['postBatchResumeToken'] ?? null;
  }
}