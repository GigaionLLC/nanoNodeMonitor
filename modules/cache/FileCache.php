<?php
/**
 * FileCache
 *
 * http://github.com/inouet/file-cache/
 *
 * A simple PHP class for caching data in the filesystem.
 *
 * License
 *   This software is released under the MIT License, see LICENSE.txt.
 *
 * @package FileCache
 * @author  Taiji Inoue <inudog@gmail.com>
 */

class FileCache extends Cache
{

    /**
     * The root cache directory.
     * Defaults to a dedicated directory inside the system temp dir.
     * @var string
     */
    private $cache_dir;

    /**
     * The cache time in seconds.
     */
    private $ttl = 30;

    /**
     * Result of the base directory safety check (null = not checked yet).
     * @var bool|null
     */
    private $cache_dir_safe = null;

    /**
     * Maximum time in seconds to wait for another request's rebuild.
     */
    const LOCK_WAIT_SECONDS = 10;

    /**
     * Creates a FileCache object
     *
     * @param array $options
     */
    public function __construct(array $options = array())
    {
        $this->cache_dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'nanoNodeMonitor-cache';

        $available_options = array('cache_dir', 'ttl');
        foreach ($available_options as $name) {
            if (isset($options[$name])) {
                $this->$name = $options[$name];
            }
        }
    }

    /**
     * Fetches an entry from the cache.
     *
     * Entries are stored as JSON. Never unserialize() cache files: the cache
     * directory may be guessable on shared hosts, and unserialize() of
     * attacker-supplied data allows PHP object injection.
     *
     * @param string $id
     */
    public function read($id)
    {
        if (!$this->isCacheDirectorySafe()) {
            return NULL;
        }
        $file_name = $this->getFileName($id);

        if (is_link($file_name) || !is_file($file_name) || !is_readable($file_name)) {
            return NULL;
        }

        $lines    = file($file_name);
        $lifetime = array_shift($lines);
        $lifetime = (int) trim($lifetime);

        if ($lifetime !== 0 && $lifetime < time()) {
            @unlink($file_name);
            return NULL;
        }
        $data = json_decode(join('', $lines));
        return $data;
    }

    /**
     * Deletes a cache entry.
     *
     * @param string $id
     *
     * @return bool
     */
    public function delete($id)
    {
        $file_name = $this->getFileName($id);
        return unlink($file_name);
    }

    /**
     * Puts data into the cache.
     *
     * @param string $id
     * @param mixed  $data
     *
     * @return bool
     */
    public function write($id, $data)
    {
        if (!$this->isCacheDirectorySafe() || !$this->ensureDirectory($id)) {
            return false;
        }
        $file_name = $this->getFileName($id);
        if (is_link($file_name)) {
            return false;
        }
        $lifetime  = time() + $this->ttl;
        $encoded   = json_encode($data);
        $result    = file_put_contents($file_name, $lifetime . PHP_EOL . $encoded, LOCK_EX);
        if ($result === false) {
            return false;
        }
        return true;
    }

    /**
     * Fetches an entry, rebuilding it with $callback when missing/expired.
     *
     * Single-flight: only one request rebuilds an expired entry. The rebuild
     * runs under an exclusive flock() on "<cachefile>.lock"; concurrent
     * requests wait (bounded) for it and then read the fresh entry instead
     * of all hitting the node at once. The lock is released automatically
     * if the callback exits (e.g. node down -> HTTP 503). If locking is not
     * possible, or the wait times out, this falls back to the plain
     * read-callback-write behaviour.
     *
     * @param string   $id
     * @param callable $callback
     */
    public function fetch($id, $callback)
    {
        $data = $this->read($id);
        if (!is_null($data)) {
            return $data;
        }

        $lock = $this->acquireLock($id);
        if ($lock === null) {
            $data = $callback();
            $this->write($id, $data);
            return $data;
        }

        try {
            // another request may have rebuilt the entry while we waited
            $data = $this->read($id);
            if (is_null($data)) {
                $data = $callback();
                $this->write($id, $data);
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
        return $data;
    }

    //------------------------------------------------
    // PRIVATE METHODS
    //------------------------------------------------

    /**
     * Takes the rebuild lock for an entry.
     *
     * @param string $id
     *
     * @return resource|null  locked handle, or null when locking is not possible
     */
    protected function acquireLock($id)
    {
        if (!$this->isCacheDirectorySafe() || !$this->ensureDirectory($id)) {
            return null;
        }
        $lock_name = $this->getFileName($id) . '.lock';
        if (is_link($lock_name)) {
            return null;
        }
        $handle = @fopen($lock_name, 'c');
        if ($handle === false) {
            return null;
        }
        $deadline = microtime(true) + self::LOCK_WAIT_SECONDS;
        do {
            if (flock($handle, LOCK_EX | LOCK_NB)) {
                return $handle;
            }
            usleep(50000);
        } while (microtime(true) < $deadline);

        fclose($handle);
        return null;
    }

    /**
     * Creates the entry's sub directory inside the (already checked) base dir.
     *
     * @param string $id
     *
     * @return bool
     */
    protected function ensureDirectory($id)
    {
        $dir = $this->getDirectory($id);
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0700, true) && !is_dir($dir)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Checks that the base cache directory can be trusted.
     *
     * The default directory lives in the shared system temp dir, where another
     * local user could pre-create it (world-writable, or as a symlink) and
     * plant or redirect cache files. Only use it if it is a real directory
     * owned by the effective user of this process and not world-writable.
     * Otherwise the cache is disabled (NullCache behaviour), never fatal.
     * POSIX mode/owner checks are skipped on Windows, where they do not apply.
     *
     * @return bool
     */
    protected function isCacheDirectorySafe()
    {
        if ($this->cache_dir_safe !== null) {
            return $this->cache_dir_safe;
        }
        $base = $this->getCacheDirectory();
        if (!is_link($base) && !is_dir($base)) {
            @mkdir($base, 0700, true);
        }
        clearstatcache(true, $base);

        $safe = !is_link($base) && is_dir($base);
        if ($safe && PHP_OS_FAMILY !== 'Windows') {
            $perms = @fileperms($base);
            $safe  = $perms !== false && ($perms & 0002) === 0;
            if ($safe && function_exists('posix_geteuid')) {
                $safe = @fileowner($base) === posix_geteuid();
            }
        }
        if (!$safe) {
            error_log("nanoNodeMonitor: cache directory $base is not safe to use "
                . "(symlink, world-writable or owned by another user) - file cache disabled");
        }
        return $this->cache_dir_safe = $safe;
    }

    /**
     * Fetches a directory to store the cache data
     *
     * @param string $id
     *
     * @return string
     */
    protected function getDirectory($id)
    {
        $hash = sha1($id, false);
        $dirs = array(
            $this->getCacheDirectory(),
            substr($hash, 0, 2),
            substr($hash, 2, 2)
        );
        return join(DIRECTORY_SEPARATOR, $dirs);
    }

    /**
     * Fetches a base directory to store the cache data
     *
     * @return string
     */
    protected function getCacheDirectory()
    {
        return $this->cache_dir;
    }

    /**
     * Fetches a file path of the cache data
     *
     * @param string $id
     *
     * @return string
     */
    protected function getFileName($id)
    {
        $directory = $this->getDirectory($id);
        $hash      = sha1($id, false);
        $file      = $directory . DIRECTORY_SEPARATOR . $hash . '.cache';
        return $file;
    }
}
