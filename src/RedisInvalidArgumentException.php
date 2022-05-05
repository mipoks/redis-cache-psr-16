<?php


namespace mipoks\RedisCache;

use InvalidArgumentException as DefaultInvalidArgumentException;
use Psr\SimpleCache\InvalidArgumentException;

class RedisInvalidArgumentException extends DefaultInvalidArgumentException implements InvalidArgumentException
{

}