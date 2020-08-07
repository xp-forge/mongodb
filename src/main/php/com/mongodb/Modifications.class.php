<?php namespace com\mongodb;

use lang\IllegalArgumentException;

class Modifications implements \IteratorAggregate {
  private $updates, $filters, $multiple;

  /**
   * Creates a new modifications document
   *
   * @param  [:var] $updates
   * @param  [:var] $filters
   * @param  bool $multiple
   */
  public function __construct($updates, $filters, $multiple) {
    $this->updates= $updates;
    $this->filters= empty($filters) ? (object)[] : $filters;
    $this->multiple= $multiple;
  }

  /** @return iterable */
  public function getIterator() {
    yield 'q'     => $this->filters;
    yield 'u'     => $this->updates;
    yield 'multi' => $this->multiple;
  }
}