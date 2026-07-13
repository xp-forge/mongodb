<?php namespace com\mongodb\result;

use IteratorAggregate, Traversable;
use com\mongodb\{Document, Int64};
use lang\{Value, IllegalStateException};
use util\Objects;

/** @test com.mongodb.unittest.result.CursorTest */
class Cursor implements Value, IteratorAggregate {
  protected $commands, $options, $current;

  /**
   * Creates a new cursor
   *
   * @param  com.mongodb.io.Commands $commands
   * @param  com.mongodb.Options[] $options
   * @param  [:var] $current
   */
  public function __construct($commands, $options, $current) {
    $this->commands= $commands;
    $this->options= $options;
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
      $result= $this->commands->send($this->options, [
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
   * Keys the results with one of the following:
   *
   * - `'_id'`: Uses the documents' ID as keys, and the whole document as values.
   * - `['_id' => 'name']`: Uses the documents' ID as keys, and the 'name' field as values.
   * - `fn($d) => yield $d->id() => $d->get('owner.name')`: Most flexible approach.
   *
   * @param  string|[:string]|(function(com.mongodb.Document): iterable) $map
   * @return iterable
   * @throws lang.IllegalStateException if the cursor has been forwarded
   */
  public function keyBy($map) {
    if (isset($this->current['firstBatch'])) {
      if (is_string($map)) {
        foreach ($this as $document) {
          yield $document->get($map) => $document;
        }
      } else if (is_array($map)) {
        $k= key($map);
        $v= $map[$k];
        foreach ($this as $document) {
          yield $document->get($k) => $document->get($v);
        }
      } else {
        foreach ($this as $document) {
          yield from $map($document);
        }
      }
      return;
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
    $this->commands->send($this->options, [
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
    return 'C'.Objects::hashOf($this->commands).Objects::hashOf($this->current);
  }

  /**
   * Compare
   *
   * @param  var $value
   * @return int
   */
  public function compareTo($value) {
    return $value instanceof self && $this->commands === $value->commands
      ? Objects::compare($this->current, $value->current)
      : 1
    ;
  }

  /** @return void */
  public function __destruct() {
    $this->close();
  }
}