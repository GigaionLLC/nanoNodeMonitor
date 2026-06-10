<?php

require_once __DIR__.'/cache/ApcuCache.php';
require_once __DIR__.'/cache/FileCache.php';
require_once __DIR__.'/cache/RedisCache.php';
require_once __DIR__.'/cache/NullCache.php';

abstract class Cache {
  public static function factory() {
    global $cache;
    switch ($cache["engine"]) {
      // the APC extension is gone since PHP 7; 'apc' maps to its successor APCu
      case 'apc':
      case 'apcu': return new ApcuCache($cache['options']);
      case 'files': return new FileCache($cache['options']);
      case 'redis': return new RedisCache($cache['options']);
      default: return new NullCache();
    }
  }

  abstract public function read($key);
  abstract public function write($key, $data);

  public function fetch($key, $callback) {
    $data = $this->read($key);
    if (is_null($data)) {
      $data = $callback();
      $this->write($key, $data);
    }

    return $data;
  }
}
