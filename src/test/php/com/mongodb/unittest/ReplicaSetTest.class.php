<?php namespace com\mongodb\unittest;

use com\mongodb\io\Protocol;
use com\mongodb\{Error, Int64, NoSuitableCandidate, ObjectId, Session, Timestamp};
use peer\ConnectException;
use test\{Assert, Expect, Test, Values};
use util\{Date, UUID};

class ReplicaSetTest {
  use WireTesting;

  /**
   * Returns map of addresses to server kinds
   * 
   * @param  com.mongodb.io.Protocol $proto
   * @return [:?string]
   */
  private function connected($proto) {
    return array_map(
      function($conn) { return $conn->connected() ? $conn->server['$kind'] : null; },
      $proto->connections()
    );
  }

  #[Test]
  public function connections_when_first_is_primary() {
    $fixture= $this->protocol([
      self::$PRIMARY    => [$this->hello(self::$PRIMARY)],
      self::$SECONDARY1 => [$this->hello(self::$SECONDARY1)],
      self::$SECONDARY2 => [$this->hello(self::$SECONDARY2)],
    ]);

    Assert::equals(
      [self::$PRIMARY => TestingConnection::RSPrimary, self::$SECONDARY1 => null, self::$SECONDARY2 => null],
      $this->connected($fixture->connect())
    );
  }

  #[Test]
  public function connections_when_first_is_secondary() {
    $fixture= $this->protocol([
      self::$SECONDARY1 => [$this->hello(self::$SECONDARY1)],
      self::$SECONDARY2 => [$this->hello(self::$SECONDARY2)],
      self::$PRIMARY    => [$this->hello(self::$PRIMARY)],
    ]);

    Assert::equals(
      [self::$SECONDARY1 => TestingConnection::RSSecondary, self::$SECONDARY2 => null, self::$PRIMARY => null],
      $this->connected($fixture->connect())
    );
  }

  #[Test]
  public function connects_to_primary_when_no_secondary_available() {
    $replicaSet= [
      self::$PRIMARY    => [$this->hello(self::$PRIMARY), $this->ok()],
      self::$SECONDARY1 => [],
      self::$SECONDARY2 => [],
    ];
    $fixture= $this->protocol($replicaSet, 'secondaryPreferred')->connect();
    $fixture->read(null, [/* anything */]);

    Assert::equals(
      [self::$PRIMARY => TestingConnection::RSPrimary, self::$SECONDARY1 => null, self::$SECONDARY2 => null],
      $this->connected($fixture)
    );
  }

  #[Test, Expect(class: NoSuitableCandidate::class, message: '/No suitable candidate eligible for initial connect/')]
  public function throws_exception_if_none_of_the_nodes_are_reachable() {
    $this->protocol([self::$PRIMARY => null, self::$SECONDARY1 => null, self::$SECONDARY2 => null])->connect();
  }

  #[Test, Expect(class: NoSuitableCandidate::class, message: '/No suitable candidate eligible for initial connect/')]
  public function throws_exception_if_none_of_the_nodes_respond() {
    $this->protocol([self::$PRIMARY => [], self::$SECONDARY1 => [], self::$SECONDARY2 => []])->connect();
  }

  #[Test, Values(['secondary', 'secondaryPreferred'])]
  public function reads_from_first_secondary_with($readPreference) {
    $replicaSet= [
      self::$PRIMARY    => [$this->hello(self::$PRIMARY)],
      self::$SECONDARY1 => [$this->hello(self::$SECONDARY1), $this->ok()],
      self::$SECONDARY2 => [],
    ];
    $fixture= $this->protocol($replicaSet, $readPreference)->connect();
    $fixture->read(null, [/* anything */]);

    Assert::equals(
      [self::$PRIMARY => TestingConnection::RSPrimary, self::$SECONDARY1 => TestingConnection::RSSecondary, self::$SECONDARY2 => null],
      $this->connected($fixture)
    );
  }

  #[Test, Values(['secondary', 'secondaryPreferred'])]
  public function reads_from_first_available_secondary_with($readPreference) {
    $replicaSet= [
      self::$PRIMARY    => [$this->hello(self::$PRIMARY)],
      self::$SECONDARY1 => [],
      self::$SECONDARY2 => [$this->hello(self::$SECONDARY2), $this->ok()],
    ];
    $fixture= $this->protocol($replicaSet, $readPreference)->connect();
    $fixture->read(null, [/* anything */]);

    Assert::equals(
      [self::$PRIMARY => TestingConnection::RSPrimary, self::$SECONDARY1 => null, self::$SECONDARY2 => TestingConnection::RSSecondary],
      $this->connected($fixture)
    );
  }

  #[Test, Values(['secondary', 'secondaryPreferred'])]
  public function reads_from_any_secondary($readPreference) {
    $replicaSet= [
      self::$PRIMARY    => [$this->hello(self::$PRIMARY)],
      self::$SECONDARY1 => [$this->hello(self::$SECONDARY1), $this->ok()],
      self::$SECONDARY2 => [$this->hello(self::$SECONDARY2), $this->ok()],
    ];
    $fixture= $this->protocol($replicaSet, $readPreference)->connect();
    $fixture->read(null, [/* anything */]);

    $connected= $this->connected($fixture);
    Assert::equals(TestingConnection::RSSecondary, $connected[self::$SECONDARY1] ?? $connected[self::$SECONDARY2]);
  }

  #[Test, Values(['secondary', 'secondaryPreferred'])]
  public function reads_from_another_secondary($readPreference) {
    $replicaSet= [
      self::$PRIMARY    => [$this->hello(self::$PRIMARY)],
      self::$SECONDARY1 => [],
      self::$SECONDARY2 => [$this->hello(self::$SECONDARY2), $this->ok()],
    ];
    $fixture= $this->protocol($replicaSet, $readPreference)->connect();
    $fixture->read(null, [/* anything */]);

    Assert::equals(
      [self::$PRIMARY => TestingConnection::RSPrimary, self::$SECONDARY1 => null, self::$SECONDARY2 => TestingConnection::RSSecondary],
      $this->connected($fixture)
    );
  }

  #[Test, Values([['primary', 45], ['primaryPreferred', 45], ['secondary', 44], ['secondaryPreferred', 44], ['nearest', 45]])]
  public function reading_with($readPreference, $result) {
    $replicaSet= [
      self::$PRIMARY     => [$this->hello(self::$PRIMARY), $this->cursor([['n' => 45]])],
      self::$SECONDARY1  => [$this->hello(self::$SECONDARY1), $this->cursor([['n' => 44]])],
      self::$SECONDARY2  => [$this->hello(self::$SECONDARY1), $this->cursor([['n' => 44]])],
    ];
    $response= $this->protocol($replicaSet, $readPreference)->connect()->read(null, [
      'aggregate' => 'test.entries',
      'pipeline'  => ['$count' => 'n'],
      'cursor'    => (object)[],
      '$db'       => 'test',
    ]);

    Assert::equals([['n' => $result]], $response['body']['cursor']['firstBatch']);
  }

  #[Test]
  public function writing() {
    $replicaSet= [
      self::$PRIMARY     => [$this->hello(self::$PRIMARY), [
        'flags' => 0,
        'body'  => [
          'n'          => 1,
          'electionId' => new ObjectId('7fffffff0000000000000136'),
          'opTime'     => ['ts' => new Timestamp(1647691293, 15), 't' => new Int64(310)],
          'ok'         => 1
        ]
      ]],
      self::$SECONDARY1  => [$this->hello(self::$SECONDARY1), [
        'flags' => 0,
        'body'  => [
          'topologyVersion' => ['processId' => new ObjectId('6235b49ff525c94b4ecdd835'), 'counter' => new Int64(4)],
          'ok'              => 0,
          'errmsg'          => 'not master',
          'code'            => 10107,
          'codeName'        => 'NotWritablePrimary',
        ]
      ]],
      self::$SECONDARY2  => [],
    ];
    $response= $this->protocol($replicaSet)->connect()->write(null, [
      'delete'    => 'test',
      'deletes'   => [['q' => ['_id' => new ObjectId('622b53218e7205b37f8f8774')], 'limit' => 1]],
      'ordered'   => true,
      '$db'       => 'test',
    ]);

    Assert::equals(1, $response['body']['n']);
  }

  #[Test, Expect(class: NoSuitableCandidate::class, message: '/No suitable candidate eligible for reading with secondary/')]
  public function read_throws_if_no_secondaries_are_available() {
    $replicaSet= [
      self::$PRIMARY    => [$this->hello(self::$PRIMARY)],
      self::$SECONDARY1 => [],
      self::$SECONDARY2 => [],
    ];
    $fixture= $this->protocol($replicaSet, 'secondary')->connect();
    $fixture->read(null, [/* anything */]);
  }

  #[Test, Expect(class: NoSuitableCandidate::class, message: '/No suitable candidate eligible for writing/')]
  public function write_throws_if_no_primary_is_available() {
    $replicaSet= [
      self::$PRIMARY    => [],
      self::$SECONDARY1 => [$this->hello(self::$SECONDARY1)],
      self::$SECONDARY2 => [$this->hello(self::$SECONDARY2)],
    ];
    $fixture= $this->protocol($replicaSet, 'primary')->connect();
    $fixture->write(null, [/* anything */]);
  }

  #[Test]
  public function reconnects_when_checking_for_socket_with_ping_fails() {
    $replicaSet= [
      self::$PRIMARY    => [$this->hello(self::$PRIMARY)],
      self::$SECONDARY1 => [$this->hello(self::$SECONDARY1), $this->cursor([['n' => 44]]), null, $this->hello(self::$SECONDARY1), $this->cursor([['n' => 45]])],
      self::$SECONDARY2 => [],
    ];
    $fixture= $this->protocol($replicaSet, 'secondary')->connect();
    $fixture->socketCheckInterval= 0;

    // This will connect and then read from secondary #1 successfully.
    $fixture->read(null, [
      'aggregate' => 'test.entries',
      'pipeline'  => ['$count' => 'n'],
      'cursor'    => (object)[],
      '$db'       => 'test',
    ]);

    // This will first run into an error while pinging secondary #1, closing the
    // connection, subsequently reconnecting and then fetching the new count.
    $response= $fixture->read(null, [
      'aggregate' => 'test.entries',
      'pipeline'  => ['$count' => 'n'],
      'cursor'    => (object)[],
      '$db'       => 'test',
    ]);

    Assert::equals([['n' => 45]], $response['body']['cursor']['firstBatch']);
  }

  #[Test]
  public function does_not_disconnect_for_operation_errors() {
    $replicaSet= [
      self::$PRIMARY    => [$this->hello(self::$PRIMARY), [
        'flags' => 0,
        'body'  => [
          'topologyVersion' => ['processId' => new ObjectId('6235b49ff525c94b4ecdd835'), 'counter' => new Int64(4)],
          'ok'              => 0,
          'errmsg'          => 'Unallowed argument in listDatabases command: filter',
          'code'            => 8000,
          'codeName'        => 'AtlasError',
        ]
      ]],
      self::$SECONDARY1 => [$this->hello(self::$SECONDARY1)],
      self::$SECONDARY2 => [$this->hello(self::$SECONDARY2)],
    ];
    $fixture= $this->protocol($replicaSet, 'primary')->connect();

    Assert::throws(Error::class, function() use($fixture) {
      $fixture->read(null, [/* anything */]);
    });
    Assert::equals(
      [self::$PRIMARY => TestingConnection::RSPrimary, self::$SECONDARY1 => null, self::$SECONDARY2 => null],
      $this->connected($fixture)
    );
  }

  #[Test]
  public function reconnect_using_nearest_when_disconnected() {
    $replicaSet= [
      self::$PRIMARY     => [$this->hello(self::$PRIMARY), $this->hello(self::$PRIMARY), $this->cursor([['n' => 45]])],
      self::$SECONDARY1  => [$this->hello(self::$SECONDARY1)],
      self::$SECONDARY2  => [$this->hello(self::$SECONDARY2)],
    ];
    $protocol= $this->protocol($replicaSet, 'nearest')->connect();

    // Simulate all connections are closed
    foreach ($protocol->connections() as $connection) {
      $connection->close();
    }

    // Calling read() will reconnect
    $response= $protocol->read(null, [
      'aggregate' => 'test.entries',
      'pipeline'  => ['$count' => 'n'],
      'cursor'    => (object)[],
      '$db'       => 'test',
    ]);
    Assert::equals([['n' => 45]], $response['body']['cursor']['firstBatch']);
  }
}