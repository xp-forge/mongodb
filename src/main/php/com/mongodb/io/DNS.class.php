<?php namespace com\mongodb\io;

/** 
 * DNS-constructed seed list
 * 
 * ```
 * Record                            TTL   Class    Priority Weight Port  Target
 * _mongodb._tcp.server.example.com. 86400 IN SRV   0        5      27317 mongodb1.example.com.
 * _mongodb._tcp.server.example.com. 86400 IN SRV   0        5      27017 mongodb2.example.com.
 * ```
 *
 * @see   https://docs.mongodb.com/manual/reference/connection-string/#dns-seed-list-connection-format
 */
class DNS {

  /** @return iterable */
  public function members(string $srv) {
    foreach (dns_get_record('_mongodb._tcp.'.$srv, DNS_SRV) as $record) {
      yield $record['target'] => $record['port'];
    }
  }

  /** @return iterable */
  public function params(string $srv) {
    foreach (dns_get_record($srv, DNS_TXT) as $record) {
      yield $record['txt'];
    }
  }
}