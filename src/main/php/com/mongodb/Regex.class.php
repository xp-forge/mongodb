<?php namespace com\mongodb;

class Regex {
  private $pattern, $modifiers;

  public function __construct($pattern, $modifiers) {
    $this->pattern= $pattern;
    $this->modifiers= $modifiers;
  }

  public function pattern() { return $this->pattern; }

  public function modifiers() { return $this->modifiers; }
}