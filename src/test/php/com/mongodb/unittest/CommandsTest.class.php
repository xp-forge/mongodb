<?php namespace com\mongodb\unittest;

use com\mongodb\Collection;
use com\mongodb\io\Commands;
use test\{Assert, Before, Test};

class CommandsTest {
  use WireTesting;

  private $cluster;

  #[Before]
  public function cluster() {
    $this->cluster= [
      self::$PRIMARY    => [$this->hello(self::$PRIMARY)],
      self::$SECONDARY1 => [$this->hello(self::$SECONDARY1)],
      self::$SECONDARY2 => [$this->hello(self::$SECONDARY2)],
    ];
  }

  #[Test]
  public function writing() {
    $protocol= $this->protocol($this->cluster)->connect();
    Assert::equals($protocol->connections()[self::$PRIMARY], Commands::writing($protocol)->connection());
  }

  #[Test]
  public function reading_from_primary() {
    $protocol= $this->protocol($this->cluster, 'primary')->connect();
    Assert::equals($protocol->connections()[self::$PRIMARY], Commands::reading($protocol)->connection());
  }

  #[Test]
  public function reading_from_one_of_the_secondaries() {
    $protocol= $this->protocol($this->cluster, 'secondary')->connect();
    Assert::true(in_array(
      Commands::reading($protocol)->connection(),
      [$protocol->connections()[self::$SECONDARY1], $protocol->connections()[self::$SECONDARY2]]
    ));
  }

  #[Test]
  public function send_write() {
    $responses= [
      $this->hello(self::$PRIMARY),
      $this->ok(['n' => 1, 'nModified' => 2]),
    ];
    $protocol= $this->protocol([self::$PRIMARY => $responses] + $this->cluster)->connect();
    $coll= new Collection($protocol, 'testing', 'tests');

    Assert::equals(2, $coll->update([], ['$inc' => ['qty' => 1]])->modified());
  }

  #[Test]
  public function not_writable_primary_retried() {
    $responses= [
      $this->hello(self::$PRIMARY),
      $this->error(10107, 'NotWritablePrimary'),
      $this->hello(self::$PRIMARY),
      $this->ok(['n' => 1, 'nModified' => 2]),
    ];
    $protocol= $this->protocol([self::$PRIMARY => $responses] + $this->cluster)->connect();
    $coll= new Collection($protocol, 'testing', 'tests');

    Assert::equals(2, $coll->update([], ['$inc' => ['qty' => 1]])->modified());
  }
}