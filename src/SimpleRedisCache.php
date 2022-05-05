<?php


namespace mipoks\RedisCache;

use Psr\SimpleCache\CacheInterface;
use Redis;
use RedisException;
use Traversable;

class SimpleRedisCache implements CacheInterface
{
    private const DEFAULT_VALUE = false;
    private const DEFAULT_TTL = 60 * 60 * 24 * 7;

    private Redis $redis;

    /**
     * SimpleRedisCache constructor.
     * @param Redis $redis
     * @param int $ttl
     * @param bool $silentMode
     * @param mixed $default
     */
    public function __construct(Redis $redis, int $ttl = self::DEFAULT_TTL, private bool $silentMode = false)
    {
        $this->redis = $redis;
        try {
            $this->redis->ping();
            $this->redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_PHP);
        } catch (RedisException $exception) {
            $this->handleException(new RedisCacheException(RedisCacheException::CONNECT_ERROR, $exception));
        }
    }

    /**
     * @param $key
     * @param $default
     * @return mixed
     */
    public function get($key, $default = self::DEFAULT_VALUE): mixed
    {
        return $this->getMultiple([$key], $default)[$key];
    }

    /**
     * @param $key
     * @param $value
     * @param int $ttl
     * @return bool
     */
    public function set($key, $value, $ttl = self::DEFAULT_TTL): bool
    {
        return $this->setMultiple([$key => $value], $ttl);
    }

    /**
     * @param $key
     * @return bool
     */
    public function delete($key): bool
    {
        return $this->deleteMultiple([$key]);
    }

    /**
     * @return bool
     */
    public function clear(): bool
    {
        try {
            return $this->redis->flushDB();
        } catch (RedisException $exception) {
            return $this->handleException(new RedisCacheException($exception->getCode(), $exception));
        }
    }

    /**
     * @param $keys
     * @param $default
     * @return array
     */
    public function getMultiple($keys, $default = self::DEFAULT_VALUE): array
    {
        if (!is_array($keys) && !$keys instanceof Traversable) {
            throw new RedisInvalidArgumentException("Values should be an array or Traversable interface impl");
        }

        $keys = is_array($keys) ? array_values($keys) : iterator_to_array($keys);
        $keys = array_unique($keys);
        if ($keys === []) {
            return [];
        }
        try {
            $raw = $this->redis->mGet($keys);
        } catch (RedisException $exception) {
            $this->handleException(new RedisCacheException(RedisCacheException::NOT_ANSWERED, $exception));
        }

        $records = [];
        foreach ($raw as $key => $value) {
            if ($value === false) {
                $records[$key] = $default;
            } else {
                $records[$key] = $value;
            }
        }
        return array_combine($keys, $records);
    }

    /**
     * @param $values
     * @param int $ttl
     * @return bool
     */
    public function setMultiple($values, $ttl = self::DEFAULT_TTL): bool
    {
        if (!is_int($ttl)) {
            throw new RedisInvalidArgumentException("TTL should be an integer");
        }
        if (!is_array($values) && !$values instanceof Traversable) {
            throw new RedisInvalidArgumentException("Values should be an array or Traversable interface impl");
        }
        $values = is_array($values) ? $values : iterator_to_array($values);
        $cachedSuccessful = true;
        foreach ($values as $key => $value) {
            try {
                if (!$this->redis->setEx($key, $ttl, $value)) {
                    $cachedSuccessful = false;
                }
            } catch (RedisException $exception) {
                $this->handleException(new RedisCacheException(RedisCacheException::NOT_ANSWERED, $exception));
            }
        }
        return $cachedSuccessful;
    }

    /**
     * @param $keys
     * @return bool
     */
    public function deleteMultiple($keys): bool
    {
        if (!is_array($keys) && !$keys instanceof Traversable) {
            throw new RedisInvalidArgumentException("Keys should be an array or Traversable interface impl");
        }
        $keys = is_array($keys) ? array_values($keys) : iterator_to_array($keys);
        $keys = array_unique($keys);
        try {
            $result = $this->redis->del($keys);
        } catch (RedisException $exception) {
            return $this->handleException(new RedisCacheException(RedisCacheException::NOT_ANSWERED, $exception));
        }
        return $result === count($keys);
    }

    /**
     * @param $key
     * @return bool
     */
    public function has($key): bool
    {
        try {
            return $this->redis->exists($key) > 0;
        } catch (RedisException $exception) {
            return $this->handleException(new RedisCacheException(RedisCacheException::NOT_ANSWERED, $exception));
        }
    }


    /**
     * @param bool $mode
     */
    public function setMode(bool $mode): void
    {
        $this->silentMode = $mode;
    }

    /**
     * @return bool
     */
    public function getMode(): bool
    {
        return $this->silentMode;
    }

    /**
     * @param RedisCacheException $exception
     * @return false
     */
    private function handleException(RedisCacheException $exception): bool
    {
        return match ($this->silentMode) {
            true => throw $exception,
            false => $this->silentMode,
        };
    }
}