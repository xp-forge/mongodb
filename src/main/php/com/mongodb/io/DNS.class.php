<?php namespace com\mongodb\io;

use io\IOException;

/** 
 * DNS-constructed seed list
 * 
 * ```
 * Record                            TTL   Class    Priority Weight Port  Target
 * _mongodb._tcp.server.example.com. 86400 IN SRV   0        5      27317 mongodb1.example.com.
 * _mongodb._tcp.server.example.com. 86400 IN SRV   0        5      27017 mongodb2.example.com.
 * ```
 *
 * @codeCoverageIgnore This can only be integration-tested!
 * @see   https://docs.mongodb.com/manual/reference/connection-string/#dns-seed-list-connection-format
 */
class DNS {

  /** @return iterable */
  public function members(string $srv) {
    if (false === ($lookup= dns_get_record('_mongodb._tcp.'.$srv, DNS_SRV))) {
      $e= new IOException('Cannot lookup SRV record for '.$srv);
      \xp::gc(__FILE__);
      throw $e;
    }

    foreach ($lookup as $record) {
      yield $record['target'] => $record['port'];
    }
  }

  /** @return iterable */
  public function params(string $srv) {
    if (false === ($lookup= dns_get_record($srv, DNS_TXT))) {
      $e= new IOException('Cannot lookup TXT record for '.$srv);
      \xp::gc(__FILE__);
      throw $e;
    }

    foreach ($lookup as $record) {
      yield $record['txt'];
    }
  }
}