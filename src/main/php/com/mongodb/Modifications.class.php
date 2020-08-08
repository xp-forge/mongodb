<?php namespace com\mongodb;

use lang\IllegalArgumentException;

class Modifications implements \IteratorAggregate {
  private $updates, $filter, $multiple;

  /**
   * Creates a new modifications document
   *
   * @param  [:var] $updates
   * @param  [:var] $filter
   * @param  bool $multiple
   */
  public function __construct($updates, $filter, $multiple) {
    $this->updates= $updates;
    $this->filter= $filter ?: (object)[];
    $this->multiple= $multiple;
  }

  /** @return iterable */
  public function getIterator() {
    yield 'q'     => $this->filter;
    yield 'u'     => $this->updates;
    yield 'multi' => $this->multiple;
  }
}