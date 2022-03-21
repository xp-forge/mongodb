<?php namespace com\mongodb\result;

/** @test com.mongodb.unittest.result.ChangeStreamTest */
class ChangeStream extends Cursor {

  /** @return ?[:var] */
  public function resumeToken() {
    return $this->current['postBatchResumeToken'] ?? null;
  }
}