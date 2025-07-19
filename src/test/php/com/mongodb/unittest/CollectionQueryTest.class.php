<?php namespace com\mongodb\unittest;

use com\mongodb\{Collection, Document};
use test\{Assert, Test};

class CollectionQueryTest {
  use WireTesting;

  const FIRST= ['_id' => 'one', 'name' => 'A'];
  const SECOND= ['_id' => 'one', 'name' => 'A'];
  const DOCUMENTS= [self::FIRST, self::SECOND];
  const QUERY= ['$db' => 'testing', '$readPreference' => ['mode' => 'primary']];

  /**
   * Returns a new fixture
   *
   * @param  var... $messages
   * @return com.mongodb.io.Protocol
   */
  private function newFixture(... $messages) {
    return $this->protocol([self::$PRIMARY => [$this->hello(self::$PRIMARY), ...$messages]], 'primary')->connect();
  }

  #[Test]
  public function query() {
    $protocol= $this->newFixture($this->cursor(self::DOCUMENTS));
    Assert::equals(
      [new Document(self::FIRST), new Document(self::SECOND)],
      (new Collection($protocol, 'testing', 'tests'))->query()->all()
    );
    Assert::equals(
      ['find' => 'tests', 'filter' => (object)[]] + self::QUERY,
      current($protocol->connections())->command(1)
    );
  }

  #[Test]
  public function first() {
    $protocol= $this->newFixture($this->cursor(self::DOCUMENTS));
    Assert::equals(
      new Document(self::FIRST),
      (new Collection($protocol, 'testing', 'tests'))->first()
    );
    Assert::equals(
      ['find' => 'tests', 'filter' => (object)[]] + self::QUERY,
      current($protocol->connections())->command(1)
    );
  }

  #[Test]
  public function first_with_id() {
    $protocol= $this->newFixture($this->cursor(self::DOCUMENTS));
    Assert::equals(
      new Document(self::FIRST),
      (new Collection($protocol, 'testing', 'tests'))->first('one')
    );
    Assert::equals(
      ['find' => 'tests', 'filter' => ['_id' => 'one']] + self::QUERY,
      current($protocol->connections())->command(1)
    );
  }

  #[Test]
  public function first_with_query() {
    $protocol= $this->newFixture($this->cursor(self::DOCUMENTS));
    Assert::equals(
      new Document(self::FIRST),
      (new Collection($protocol, 'testing', 'tests'))->first(['_id' => 'one'])
    );
    Assert::equals(
      ['find' => 'tests', 'filter' => ['_id' => 'one']] + self::QUERY,
      current($protocol->connections())->command(1)
    );
  }

  #[Test]
  public function first_with_pipeline() {
    $pipeline= [['$match' => ['_id' => 'one']]];
    $protocol= $this->newFixture($this->cursor(self::DOCUMENTS));
    Assert::equals(
      new Document(self::FIRST),
      (new Collection($protocol, 'testing', 'tests'))->first($pipeline)
    );
    Assert::equals(
      ['aggregate' => 'tests', 'pipeline' => $pipeline, 'cursor' => (object)[]] + self::QUERY,
      current($protocol->connections())->command(1)
    );
  }

  #[Test]
  public function first_without_result() {
    $protocol= $this->newFixture($this->cursor([]));
    Assert::null((new Collection($protocol, 'testing', 'tests'))->first());
  }
}