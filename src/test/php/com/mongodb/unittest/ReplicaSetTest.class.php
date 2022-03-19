<?php namespace com\mongodb\unittest;

use com\mongodb\io\Protocol;
use com\mongodb\{ObjectId, Int64, Timestamp, NoSuitableCandidate};
use peer\ConnectException;
use unittest\{Assert, Values, Test};
use util\Date;

class ReplicaSetTest {
  const PRIMARY    = 'shard2.test:27017';
  const SECONDARY1 = 'shard1.test:27017';
  const SECONDARY2 = 'shard0.test:27017';

  /**
   * Connect to a given replica set definition
   *
   * @param  [:var[]] $definition
   * @param  string $readPreference
   * @return com.mongodb.io.Protocol
   */
  private function connect($definition, $readPreference= 'primary') {
    $conn= [];
    foreach ($definition as $node => $replies) {
      $conn[]= new TestingConnection($node, $replies);
    }

    return (new Protocol($conn, ['params' => ['readPreference' => $readPreference]]))->connect();
  }

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

  /**
   * Creates a hello reply
   *
   * @param  string $node
   * @return [:var]
   */
  private function hello($node) {
    return [
      'responseFlags'   => 8,
      'cursorID'        => 0,
      'startingFrom'    => 0,
      'numberReturned'  => 1,
      'documents'       => [[
        'topologyVersion'              => ['processId' => new ObjectId('6235b5ddda38998abb76bed3'), new Int64(6)],
        'hosts'                        => [self::PRIMARY, self::SECONDARY1, self::SECONDARY2],
        'setName'                      => 'atlas-test-shard-0',
        'setVersion'                   => 8,
        'isWritablePrimary'            => self::PRIMARY === $node,
        'secondary'                    => self::PRIMARY !== $node,
        'primary'                      => self::PRIMARY,
        'tags'                         => [
          'provider'     => 'AWS',
          'region'       => 'EU_CENTRAL_1',
          'nodeType'     => 'ELECTABLE',
          'workloadType' => 'OPERATIONAL'
        ],
        'me'                           => $node,
        'electionId'                   => new ObjectId('7fffffff0000000000000136'),
        'lastWrite'                    => [
          'opTime'            => ['ts' => new Timestamp(1647687293, 41), 't' => new Int64(310)],
          'lastWriteDate'     => new Date('2022-03-19 10:54:53+0000'),
          'majorityOpTime'    => ['ts' => new Timestamp(1647687293, 41), 't' => new Int64(310)],
          'majorityWriteDate' => new Date('2022-03-19 10:54:53+0000'),
        ],
        'maxBsonObjectSize'            => 16777216,
        'maxMessageSizeBytes'          => 48000000,
        'maxWriteBatchSize'            => 100000,
        'localTime'                    => new Date('2022-03-19 10:54:53+0000'),
        'logicalSessionTimeoutMinutes' => 30,
        'connectionId'                 => 873,
        'minWireVersion'               => 0,
        'maxWireVersion'               => 13,
        'readOnly'                     => self::PRIMARY !== $node,
        'ok'                           => 1,
      ]]
    ];
  }

  /**
   * Creates a count reply
   *
   * @param  int $n
   * @return [:var]
   */
  private function count($n) {
    return [
      'flags' => 0,
      'body'  => [
        'cursor' => ['firstBatch' => [['n' => $n]], 'id' => new Int64(0), 'ns' => 'test.entries'],
        'ok'     => 1,
      ],
    ];
  }

  #[Test]
  public function connections_when_first_is_primary() {
    $fixture= $this->connect([
      self::PRIMARY    => [$this->hello(self::PRIMARY)],
      self::SECONDARY1 => [$this->hello(self::SECONDARY1)],
      self::SECONDARY2 => [$this->hello(self::SECONDARY2)],
    ]);

    Assert::equals(
      [self::PRIMARY => TestingConnection::RSPrimary, self::SECONDARY1 => null, self::SECONDARY2 => null],
      $this->connected($fixture)
    );
  }

  #[Test]
  public function connections_when_first_is_secondary() {
    $fixture= $this->connect([
      self::SECONDARY1 => [$this->hello(self::SECONDARY1)],
      self::SECONDARY2 => [$this->hello(self::SECONDARY2)],
      self::PRIMARY    => [$this->hello(self::PRIMARY)],
    ]);

    Assert::equals(
      [self::SECONDARY1 => TestingConnection::RSSecondary, self::SECONDARY2 => null, self::PRIMARY => null],
      $this->connected($fixture)
    );
  }

  #[Test]
  public function connects_to_primary_when_no_secondary_available() {
    $replicaSet= [
      self::PRIMARY    => [$this->hello(self::PRIMARY), $this->count(0)],
      self::SECONDARY1 => [],
      self::SECONDARY2 => [],
    ];
    $fixture= $this->connect($replicaSet, 'secondaryPreferred');
    $fixture->read(null, [/* anything */]);

    Assert::equals(
      [self::PRIMARY => TestingConnection::RSPrimary, self::SECONDARY1 => null, self::SECONDARY2 => null],
      $this->connected($fixture)
    );
  }

  #[Test, Expect(class: NoSuitableCandidate::class, withMessage: '/No suitable candidate eligible for initial connect/')]
  public function throws_exception_if_none_of_the_nodes_are_reachable() {
    $this->connect([self::PRIMARY => null, self::SECONDARY1 => null, self::SECONDARY2 => null]);
  }

  #[Test, Expect(class: NoSuitableCandidate::class, withMessage: '/No suitable candidate eligible for initial connect/')]
  public function throws_exception_if_none_of_the_nodes_respond() {
    $this->connect([self::PRIMARY => [], self::SECONDARY1 => [], self::SECONDARY2 => []]);
  }

  #[Test, Values(['secondary', 'secondaryPreferred'])]
  public function reads_from_first_secondary_with($readPreference) {
    $replicaSet= [
      self::PRIMARY    => [$this->hello(self::PRIMARY)],
      self::SECONDARY1 => [$this->hello(self::SECONDARY1), $this->count(0)],
      self::SECONDARY2 => [$this->hello(self::SECONDARY2)],
    ];
    $fixture= $this->connect($replicaSet, $readPreference);
    $fixture->read(null, [/* anything */]);

    Assert::equals(
      [self::PRIMARY => TestingConnection::RSPrimary, self::SECONDARY1 => TestingConnection::RSSecondary, self::SECONDARY2 => null],
      $this->connected($fixture)
    );
  }

  #[Test, Values(['secondary', 'secondaryPreferred'])]
  public function reads_from_first_available_secondary_with($readPreference) {
    $replicaSet= [
      self::PRIMARY    => [$this->hello(self::PRIMARY)],
      self::SECONDARY1 => [],
      self::SECONDARY2 => [$this->hello(self::SECONDARY2), $this->count(0)],
    ];
    $fixture= $this->connect($replicaSet, $readPreference);
    $fixture->read(null, [/* anything */]);

    Assert::equals(
      [self::PRIMARY => TestingConnection::RSPrimary, self::SECONDARY1 => null, self::SECONDARY2 => TestingConnection::RSSecondary],
      $this->connected($fixture)
    );
  }

  #[Test, Values(['secondary', 'secondaryPreferred'])]
  public function reads_from_another_secondary($readPreference) {
    $replicaSet= [
      self::PRIMARY    => [$this->hello(self::PRIMARY)],
      self::SECONDARY1 => [$this->hello(self::SECONDARY1)],
      self::SECONDARY2 => [$this->hello(self::SECONDARY2), $this->count(0)],
    ];
    $fixture= $this->connect($replicaSet, $readPreference);
    $fixture->read(null, [/* anything */]);

    Assert::equals(
      [self::PRIMARY => TestingConnection::RSPrimary, self::SECONDARY1 => null, self::SECONDARY2 => TestingConnection::RSSecondary],
      $this->connected($fixture)
    );
  }

  #[Test, Values(map: ['primary' => 45, 'primaryPreferred' => 45, 'secondary' => 44, 'secondaryPreferred' => 44, 'nearest' => 45])]
  public function reading_with($readPreference, $result) {
    $replicaSet= [
      self::PRIMARY     => [$this->hello(self::PRIMARY), $this->count(45)],
      self::SECONDARY1  => [$this->hello(self::SECONDARY1), $this->count(44)],
      self::SECONDARY2  => [$this->hello(self::SECONDARY1), $this->count(44)],
    ];
    $response= $this->connect($replicaSet, $readPreference)->read(null, [
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
      self::PRIMARY     => [$this->hello(self::PRIMARY), [
        'flags' => 0,
        'body'  => [
          'n'          => 1,
          'electionId' => new ObjectId('7fffffff0000000000000136'),
          'opTime'     => ['ts' => new Timestamp(1647691293, 15), 't' => new Int64(310)],
          'ok'         => 1
        ]
      ]],
      self::SECONDARY1  => [$this->hello(self::SECONDARY1), [
        'flags' => 0,
        'body'  => [
          'topologyVersion' => ['processId' => new ObjectId('6235b49ff525c94b4ecdd835'), 'counter' => new Int64(4)],
          'ok'              => 0,
          'errmsg'          => 'not master',
          'code'            => 10107,
          'codeName'        => 'NotWritablePrimary',
        ]
      ]],
      self::SECONDARY2  => [],
    ];
    $response= $this->connect($replicaSet)->write(null, [
      'delete'    => 'test',
      'deletes'   => [['q' => ['_id' => new ObjectId('622b53218e7205b37f8f8774')], 'limit' => 1]],
      'ordered'   => true,
      '$db'       => 'test',
    ]);

    Assert::equals(1, $response['body']['n']);
  }

  #[Test, Expect(class: NoSuitableCandidate::class, withMessage: '/No suitable candidate eligible for reading with secondary/')]
  public function read_throws_if_no_secondaries_are_available() {
    $replicaSet= [
      self::PRIMARY    => [$this->hello(self::PRIMARY)],
      self::SECONDARY1 => [$this->hello(self::SECONDARY1)],
      self::SECONDARY2 => [$this->hello(self::SECONDARY2)],
    ];
    $fixture= $this->connect($replicaSet, 'secondary');
    $fixture->read(null, [/* anything */]);
  }

  #[Test, Expect(class: NoSuitableCandidate::class, withMessage: '/No suitable candidate eligible for writing/')]
  public function write_throws_if_no_primary_is_available() {
    $replicaSet= [
      self::PRIMARY    => [$this->hello(self::PRIMARY)],
      self::SECONDARY1 => [$this->hello(self::SECONDARY1)],
      self::SECONDARY2 => [$this->hello(self::SECONDARY2)],
    ];
    $fixture= $this->connect($replicaSet, 'primary');
    $fixture->write(null, [/* anything */]);
  }

  #[Test]
  public function reconnects_when_checking_for_socket_with_ping_fails() {
    $replicaSet= [
      self::PRIMARY    => [$this->hello(self::PRIMARY)],
      self::SECONDARY1 => [$this->hello(self::SECONDARY1), $this->count(44), null, $this->hello(self::SECONDARY1), $this->count(45)],
      self::SECONDARY2 => [],
    ];
    $fixture= $this->connect($replicaSet, 'secondary');
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
}