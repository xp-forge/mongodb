<?php namespace com\mongodb;

/**
 * Indicates connection failed
 *
 * @see  com.mongodb.MongoConnection::connect()
 * @see  com.mongodb.NoSuitableCandidate
 * @see  https://github.com/xp-forge/mongodb/pull/32
 */
class CannotConnect extends Error {

}