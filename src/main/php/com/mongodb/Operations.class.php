<?php namespace com\mongodb;

use lang\IllegalArgumentException;

/**
 * Update operations
 *
 * @test  xp://com.mongodb.unittest.OperationsTest
 */
class Operations {
  private $expressions;

  /**
   * Creates an update document that contains update operator expressions
   * as defined in the MongoDB reference manual
   *
   * @see    https://docs.mongodb.com/manual/reference/operator/update/
   * @param  [:var] $expressions
   */
  public function __construct($expressions) {
    $this->expressions= $expressions;
  }

  /**
   * Selects a single document for updating either by a filter or its ID.
   *
   * @param  [:var]|com.mongodb.ObjectId|string $arg
   */
  public function select($arg) {
    if (is_array($arg)) {
      if (empty($arg)) throw new IllegalArgumentException('Filter passed to select() may not be empty');
      $filter= $arg;
    } else {
      $filter= ['_id' => $arg];
    }

    return new Modifications($this->expressions, $filter, false);
  }

  /**
   * Applies the update operator expressions to all documents matching
   * the given filter.
   *
   * @param  [:var] $filter
   */
  public function where($filter) {
    return new Modifications($this->expressions, $filter, true);
  }
}