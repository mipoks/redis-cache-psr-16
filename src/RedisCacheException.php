<?php


namespace mipoks\RedisCache;


use Psr\SimpleCache\CacheException;
use RedisException;

class RedisCacheException extends \RuntimeException implements CacheException
{
    public const CONNECT_ERROR = -1;
    public const NOT_ANSWERED = 500;


    /**
     * RedisCacheException constructor.
     * @param int $error
     * @param RedisException $previous
     */
    public function __construct(int $error, RedisException $previous)
    {
        $message = match ($error) {
            self::CONNECT_ERROR => 'The server did not respond',
            self::NOT_ANSWERED => 'The server did not answer',
            default => $previous->getMessage(),
        };
        parent::__construct($message, $error, $previous);
    }
}