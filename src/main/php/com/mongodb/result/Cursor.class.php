<?php namespace com\mongodb\result;

use IteratorAggregate, Traversable;
use com\mongodb\{Document, Int64};
use lang\{Value, IllegalStateException};
use util\Objects;

/** @test com.mongodb.unittest.result.CursorTest */
class Cursor implements Value, IteratorAggregate {
  protected $proto, $session, $current;

  /**
   * Creates a new cursor
   *
   * @param  com.mongodb.io.Protocol $proto
   * @param  ?com.mongodb.Session $session
   * @param  [:var] $current
   */
  public function __construct($proto, $session, $current) {
    $this->proto= $proto;
    $this->session= $session;
    $this->current= $current;
  }

  /** @return string */
  public function namespace() { return $this->current['ns']; }

  /** Iterates all documents, fetching batches as necessary */
  public function getIterator(): Traversable {
    foreach ($this->current['firstBatch'] ?? [] as $document) {
      yield new Document($document);
    }

    // Fetch subsequent batches
    sscanf($this->current['ns'], "%[^.].%[^\r]", $database, $collection);
    while ($this->current['id']->number() > 0) {
      $result= $this->proto->read($this->session, [
        'getMore'    => $this->current['id'],
        'collection' => $collection,
        '$db'        => $database,
      ]);

      $this->current= $result['body']['cursor'];
      foreach ($this->current['nextBatch'] as $document) {
        yield new Document($document);
      }
    }
  }

  /**
   * Returns whether any documents are present in this cursor.
   *
   * @return bool
   */
  public function present() {
    return !empty($this->current['firstBatch'] ?? $this->current['nextBatch'] ?? null);
  }

  /**
   * Returns the first document, if there is one; NULL otherwise
   *
   * @return ?com.mongodb.Document
   * @throws lang.IllegalStateException if the cursor has been forwarded
   */
  public function first() {
    if (isset($this->current['firstBatch'])) {
      return $this->current['firstBatch'] ? new Document($this->current['firstBatch'][0]) : null;
    }

    throw new IllegalStateException('Cursor has been forwarded - cannot fetch first document');
  }

  /**
   * Returns all documents in an array
   *
   * @return com.mongodb.Document[]
   * @throws lang.IllegalStateException if the cursor has been forwarded
   */
  public function all() {
    if (isset($this->current['firstBatch'])) {
      return iterator_to_array($this);
    }

    throw new IllegalStateException('Cursor has been forwarded - cannot fetch all documents');
  }

  /**
   * Closes this cursor, killing it if necessary
   *
   * @return void
   */
  public function close() {
    if (0 === $this->current['id']->number()) return;

    sscanf($this->current['ns'], "%[^.].%[^\r]", $database, $collection);
    $this->proto->read($this->session, [
      'killCursors' => $collection,
      'cursors'     => [$this->current['id']],
      '$db'         => $database,
    ]);

    // Short-circuit subsequent calls to close()
    $this->current['id']= new Int64(0);
  }

  /** @return string */
  public function toString() {
    return sprintf(
      '%s(id= %d, ns= %s, current= %s, size= %d)',
      nameof($this),
      $this->current['id']->number(),
      $this->current['ns'],
      isset($this->current['firstBatch']) ? 'firstBatch' : 'nextBatch',
      sizeof($this->current['firstBatch'] ?? $this->current['nextBatch'])
    );
  }

  /** @return string */
  public function hashCode() {
    return 'C'.spl_object_hash($this->proto).Objects::hashOf($this->current);
  }

  /**
   * Compare
   *
   * @param  var $value
   * @return int
   */
  public function compareTo($value) {
    return $value instanceof self && $this->proto === $value->proto
      ? Objects::compare($this->current, $value->current)
      : 1
    ;
  }

  /** @return void */
  public function __destruct() {
    $this->close();
  }
}