<?php namespace com\mongodb\auth;

interface Mechanism {

  /** @return string */
  public function name();
}