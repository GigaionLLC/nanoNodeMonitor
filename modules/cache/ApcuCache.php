<?php

class ApcuCache extends Cache {
  private $ttl;

  // how long to wait for another request's rebuild before rebuilding anyway
  const LOCK_WAIT_SECONDS = 10;
  // safety expiry of the rebuild lock should its holder die unexpectedly
  const LOCK_TTL = 30;

  public function __construct(array $options = array()) {
    $this->ttl = array_key_exists('ttl', $options) ? $options['ttl'] : 30;
  }

  public function read($key) {
    $data = apcu_fetch($key, $success);
    if ($success) return $data;
    return NULL;
  }

  public function write($key, $data) {
    return apcu_store($key, $data, $this->ttl);
  }

  /**
   * Single-flight fetch: apcu_add() on "<key>.lock" is an atomic lock, so
   * only one request rebuilds an expired entry while the others wait
   * (bounded) for its result. The lock is also released if the callback
   * exits (e.g. node down -> HTTP 503). If the wait times out, this falls
   * back to the plain read-callback-write behaviour.
   */
  public function fetch($key, $callback) {
    $data = $this->read($key);
    if (!is_null($data)) return $data;

    $lockKey = $key . '.lock';
    $token   = uniqid('', true);
    $locked  = apcu_add($lockKey, $token, self::LOCK_TTL);

    if (!$locked) {
      $deadline = microtime(true) + self::LOCK_WAIT_SECONDS;
      while (!$locked && microtime(true) < $deadline) {
        usleep(50000);
        $data = $this->read($key);
        if (!is_null($data)) return $data;
        $locked = apcu_add($lockKey, $token, self::LOCK_TTL);
      }
    }

    if (!$locked) {
      $data = $callback();
      $this->write($key, $data);
      return $data;
    }

    $released = false;
    $release  = function () use ($lockKey, $token, &$released) {
      if (!$released && apcu_fetch($lockKey) === $token) {
        apcu_delete($lockKey);
      }
      $released = true;
    };
    register_shutdown_function($release);

    try {
      // another request may have rebuilt the entry just before we locked
      $data = $this->read($key);
      if (is_null($data)) {
        $data = $callback();
        $this->write($key, $data);
      }
    } finally {
      $release();
    }
    return $data;
  }
}
