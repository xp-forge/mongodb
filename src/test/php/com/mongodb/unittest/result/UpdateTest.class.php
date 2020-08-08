<?php namespace com\mongodb\unittest\result;

use com\mongodb\result\Update;
use unittest\Assert;

class UpdateTest {
  const ID= '5f1dda9973edf2501751884b';

  #[@test]
  public function can_create() {
    new Update(['n' => 1, 'nModified' => 1, 'ok' => 1]);
  }

  #[@test]
  public function matched() {
    Assert::equals(1, (new Update(['n' => 1, 'nModified' => 0, 'ok' => 1]))->matched());
  }

  #[@test]
  public function modified() {
    Assert::equals(0, (new Update(['n' => 1, 'nModified' => 0, 'ok' => 1]))->modified());
  }
}