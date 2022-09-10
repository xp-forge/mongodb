<?php namespace com\mongodb\result;

/** @test com.mongodb.unittest.result.UpdateTest */
class Update extends Result {

  /** Returns number of matched documents */
  public function matched(): int { return $this->result['n']; }

  /** Returns number of modified documents */
  public function modified(): int { return $this->result['nModified']; }

  /**
   * Returns IDs from an upsert, or an empty array if no upsers were performed.
   *
   * @return (string|com.mongodb.ObjectId)[]
   */
  public function upserted(): array {
    $r= [];
    foreach ($this->result['upserted'] ?? [] as $upsert) {
      $r[]= $upsert['_id'];
    }
    return $r;
  }
}
