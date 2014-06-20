<?php

namespace wowotuan\Redis;

/**
 * Yii框架的组件封装
 * 'components'=> array(
 *      'redis'=> array(
 *      'class' => 'wowotuan.Yii.Redis.Components'
 *      'master' => array('host'=>'', 'port'=>6379),
 *      'slave' => array(
 *          array('host'=>'', 'port'=>6378),
 *      ),
 *      'options' => array(),
 *      )
 * )
 *
 * @author qixiaopeng <qixiaopeng@55tuan.com>
 */
class Components extends \CApplicationComponent
{

    /**
     *
     * @var array 主库的配置
     */
    public $master = array();

    /**
     *
     * @var array 从库的配置
     */
    public $slave = array();

    /**
     *
     * @var array 服务器配置项
     */
    public $options = array();

    /**
     *
     * @var Client Client得实例
     */
    protected $client = array();

    public function init()
    {
        parent::init();
        $this->client = new Client($this->master, $this->slave, $this->options);
    }

    public function getClient()
    {
        return $this->client;
    }

    /**
     * 从库连接获取的函数名称
     * @var array $slaveFunc
     */
    public $slaveFunc = array(
        'dump', 'exists', 'keys', 'pttl', 'randomkey', 'ttl', 'type', 'scan',
        'bitcount', 'bitop', 'get', 'getbit', 'getrange', 'mget', 'strlen',
        'hexists', 'hget', 'hgetall', 'hkeys', 'hlen', 'hmget', 'hvals', 'hscan',
        'lindex', 'llen', 'lrange',
        'scard', 'sdiff', 'sismember', 'smembers', 'srandmember', 'sunion', 'sscan',
        'zcard', 'zcount', 'zrange', 'zrangebyscore', 'zrank', 'zrevrange', 'zrevrangebyscore', 'zrevrank', 'zscore', 'zscan',
    );

    /**
     * @throws Exceptions\FunctionNotFoundException
     */
    public function __call($name, $args)
    {
        $clusterType = Client::USE_MASTER_CONNECTION;
        if (in_array(strtolower($name), $this->slaveFunc)) {
            $clusterType = Client::USE_SLAVE_CONNECTION;
        }
        $driver = $this->client->getConnection($clusterType);
        $ref = new \ReflectionClass($driver);
        $methods = array();
        foreach ($ref->getMethods() as $method) {
            if ($method->class == 'Redis') {
                $methods[] = strtolower($method->name);
            }
        }
        if (!in_array(strtolower($name), $methods)) {
            throw new Exceptions\FunctionNotFoundException("Redis function is not exist.");
        }

        return call_user_func_array(array($driver, $name), $args);
    }

}
