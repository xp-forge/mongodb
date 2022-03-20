<?php namespace com\mongodb\unittest;

use com\mongodb\io\Connection;
use com\mongodb\{Timestamp, Int64};
use peer\ProtocolException;
use util\Bytes;

class TestingConnection extends Connection {
  private $replies;
  private $sent= [];

  /**
   * Creates a new testing connection with specified replies.
   *
   * @param  string $address
   * @param  ?[:var][] $replies
   */
  public function __construct($address, $replies= []) {
    parent::__construct(new TestingSocket(null === $replies ? null : [], $address));
    $this->replies= $replies ?? [];
  }

  /**
   * Sends a command to the server and returns its result
   *
   * @param  int $operation One of the OP_* constants
   * @param  string $header
   * @param  [:var] $sections
   * @return var
   * @throws peer.ProtocolException
   */
  public function send($operation, $header, $sections) {
    $this->sent[]= $sections;

    $reply= $this->replies ? array_shift($this->replies) : null;
    if (null === $reply) {
      throw new ProtocolException('Received EOF while reading');
    }

    $this->lastUsed= time();
    return $reply + [
      '$clusterTime'  => [
        'clusterTime' => new Timestamp(1647687293, 41),
        'signature'   => [
          'hash'  => new Bytes("-<s_\343\224\305\001\327\3339\213\265d\200P\343^U\342"),
          'keyId' => new Int64(7014587823877521409)
        ]
      ],
      'operationTime' => new Timestamp(1647687293, 41),
    ];
  }

  /**
   * Access the sent command history.
   *
   * @param  int $offset
   * @return var[]
   */
  public function command($offset) {
    return $this->sent[$offset < 0 ? sizeof($this->sent) + $offset : $offset] ?? null;
  }
}