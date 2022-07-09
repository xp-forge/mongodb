<?php namespace com\mongodb\result;

class Run extends Result {
  private $proto, $session;
  private $cursor= null;

  /**
   * Creates a new run result
   *
   * @see    com.mongodb.Collection::run()
   * @param  com.mongodb.io.Protocol $proto
   * @param  ?com.mongodb.Session $session
   * @param  [:var] $result
   */
  public function __construct($proto, $session, $result) {
    $this->proto= $proto;
    $this->session= $session;
    parent::__construct($result);
  }

  /** @return [:var] */
  public function value() { return $this->result['body']; }

  /** @return bool */
  public function isCursor() { return isset($this->result['body']['cursor']); }

  /**
   * Returns this result as a cursor. Returns NULL if this result
   * is not a cursor.
   * 
   * @return ?com.mongodb.result.Cursor
   */
  public function cursor() {
    return $this->cursor ?? (isset($this->result['body']['cursor'])
      ? $this->cursor= new Cursor($this->proto, $this->session, $this->result['body']['cursor'])
      : null
    );
  }

  /** @return void */
  public function __destruct() {
    if (
      null === $this->cursor &&
      isset($this->result['body']['cursor']) &&
      $this->result['body']['cursor']['id']->number() > 0
    ) {

      // If cursor was previously fetched using cursor(), any remaining elements will
      // be discarded by that Cursor instance. Otherwise, do this ourselves.
      sscanf($this->result['body']['cursor']['ns'], "%[^.].%[^\r]", $database, $collection);
      $this->proto->read($this->session, [
        'killCursors' => $collection,
        'cursors'     => [$this->result['body']['cursor']['id']],
        '$db'         => $database,
      ]);
    }
  }
}
