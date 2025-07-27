<?php namespace com\mongodb\unittest;

use com\mongodb\io\Protocol;
use com\mongodb\{Int64, ObjectId, Timestamp};
use util\{Date, Bytes};

trait WireTesting {
  private static $PRIMARY    = 'shard2.test:27017';
  private static $SECONDARY1 = 'shard1.test:27017';
  private static $SECONDARY2 = 'shard0.test:27017';

  /**
   * Return a protocol with a given replica set definition
   *
   * @param  var[]|[:var[]] $definition
   * @param  string $readPreference
   * @return com.mongodb.io.Protocol
   */
  private function protocol($definition, $readPreference= 'primary') {
    if (0 === key($definition)) {
      $conn= [new TestingConnection(self::$PRIMARY, $definition)];
    } else {
      $conn= [];
      foreach ($definition as $node => $replies) {
        $conn[]= new TestingConnection($node, $replies);
      }
    }
    return new Protocol($conn, ['params' => ['readPreference' => $readPreference]]);
  }

  /**
   * Creates a hello reply
   *
   * @param  string $node
   * @param  [:var] $fields
   * @return [:var]
   */
  private function hello($node, $fields= []) {
    return [
      'responseFlags'   => 8,
      'cursorID'        => 0,
      'startingFrom'    => 0,
      'numberReturned'  => 1,
      'documents'       => [$fields + [
        'topologyVersion'              => ['processId' => new ObjectId('6235b5ddda38998abb76bed3'), new Int64(6)],
        'hosts'                        => [self::$PRIMARY, self::$SECONDARY1, self::$SECONDARY2],
        'setName'                      => 'atlas-test-shard-0',
        'setVersion'                   => 8,
        'isWritablePrimary'            => self::$PRIMARY === $node,
        'secondary'                    => self::$PRIMARY !== $node,
        'primary'                      => self::$PRIMARY,
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
        'readOnly'                     => self::$PRIMARY !== $node,
        'ok'                           => 1,
      ]]
    ];
  }

  /**
   * Creates an OK reply
   *
   * @param  [:var] $body
   * @return [:var]
   */
  private function ok($body= []) {
    return ['flags' => 0, 'body' => ['ok' => 1] + $body];
  }

  /**
   * Creates an error reply
   *
   * @param  int $code
   * @param  string $name
   * @param  string $message
   * @return [:var]
   */
  private function error($code, $name, $message= 'Test') {
    return ['flags' => 0, 'body' => ['ok' => 0, 'code' => $code, 'codeName' => $name, 'errmsg' => $message]];
  }

  /**
   * Creates a cursor reply
   *
   * @param  [:var][] $documents
   * @return [:var]
   */
  private function cursor($documents) {
    return [
      'flags' => 0,
      'body'  => [
        'cursor' => ['firstBatch' => $documents, 'id' => new Int64(0), 'ns' => 'test.entries'],
        'ok'     => 1,
      ],
    ];
  }
}