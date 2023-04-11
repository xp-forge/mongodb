<?php namespace com\mongodb;

/** @test com.mongodb.unittest.RegexTest */
class Regex {
  private $pattern, $modifiers;

  public function __construct($pattern, $modifiers= '') {
    $this->pattern= $pattern;
    $this->modifiers= $modifiers;
  }

  /** @return string */
  public function pattern() { return $this->pattern; }

  /** @return string */
  public function modifiers() { return $this->modifiers; }
}