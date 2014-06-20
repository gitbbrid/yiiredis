<?php
namespace gitbird\Redis\Exceptions;

/**
 * redis扩展没有载入
 *
 * @author qixiaopeng <qixiaopeng@55tuan.com>
 */
class RedisExtensionException extends \Exception
{
    protected $message = 'Redis extension not loaded!';
}