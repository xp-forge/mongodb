<?php namespace com\mongodb\result;

class Run extends Result {
  private $commands, $options;
  private $cursor= null;

  /**
   * Creates a new run result
   *
   * @see    com.mongodb.Collection::run()
   * @param  com.mongodb.io.Commands $commands
   * @param  com.mongodb.Options[] $options
   * @param  [:var] $result
   */
  public function __construct($commands, $options, $result) {
    $this->commands= $commands;
    $this->options= $options;
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
      ? $this->cursor= new Cursor($this->commands, $this->options, $this->result['body']['cursor'])
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
      $this->commands->send($this->options, [
        'killCursors' => $collection,
        'cursors'     => [$this->result['body']['cursor']['id']],
        '$db'         => $database,
      ]);
    }
  }
}
