<?php namespace com\mongodb;

use lang\IllegalArgumentException;

class Modifications implements \IteratorAggregate {
  private $updates, $filters, $multiple;

  public function __construct($updates, $filters, $multiple) {
    $this->updates= $updates;
    $this->filters= (object)$filters;
    $this->multiple= $multiple;
  }

  /** @return iterable */
  public function getIterator() {
    yield 'q'     => $this->filters;
    yield 'u'     => $this->updates;
    yield 'multi' => $this->multiple;
  }
}