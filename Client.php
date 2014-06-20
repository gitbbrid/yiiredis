<?php

namespace gitbird\Redis;

/**
 * Redis客户端
 *
 * @author qixiaopeng <qixiaopeng@55tuan.com>
 */
class Client
{
    /**
     * 使用主库连接
     */
    const USE_MASTER_CONNECTION = 10000;

    /**
     * 使用从库连接
     */
    const USE_SLAVE_CONNECTION = 10001;

    /**
     *
     * @var array 服务器配置，一个二维数组，可以配置多个服务器，包括主从库
     */
    protected $servers;

    /**
     *
     * @var array Redis配置项
     */
    protected $options;

    /**
     *
     * @var boolean 是否自动建立连接
     */
    protected $autoConnect = false;

    /**
     *
     * @var int 超时时间
     */
    protected $timeout = 2;

    /**
     *
     * @var array redis连接得实例
     */
    protected $instances = array('master' => null, 'slave' => array());

    /**
     *
     * @var int 从库句柄编号
     */
    protected $slaveNo = 0;

    /**
     *
     * @var boolean 是否已经建立了连接
     */
    public $isConnected = false;

    /**
     * 实例化redis客户端
     * @param  array                           $master 主库配置，如果没有主从库，那么就只需要设置一个主库即可
     * @param  array                           $slave  从库配置，二维数组，如果没有，可以留空
     * @throws RedisExtensionNotFoundException
     */
    public function __construct(array $masterServer, array $slaveServer = array(), array $options = array())
    {
        if (!extension_loaded('Redis')) {
            throw new Exceptions\RedisExtensionException();
        }
        if (empty($masterServer) || !isset($masterServer['host']) || empty($masterServer['host'])) {
            throw new Exceptions\RedisServerConfigException('master server not found!');
        }
        $this->addMasterServer($masterServer);
        $this->addSlaveServer($slaveServer);
        $this->setOptions($options);
        if ($this->autoConnect == true) {
            $this->connect();
        }
    }

    /**
     * 添加主库
     * @param array $server 主库配置，hash数组
     */
    public function addMasterServer(array $server)
    {
        $this->servers['master'] = $server;
    }

    /**
     * 添加从库
     * @param  array   $servers
     * @return boolean
     */
    public function addSlaveServer(array $servers = array())
    {
        if (empty($servers)) {
            return false;
        }
        $this->servers['slave'] = $servers;
    }

    /**
     * 设置连接得配置项
     * @param array $options 配置项
     */
    public function setOptions(array $options = array())
    {
        if (isset($options['autoConnect']) && is_bool($options['autoConnect'])) {
            $this->autoConnect = $options['autoConnect'];
            unset($options['autoConnect']);
        }
        if (isset($options['timeout']) && is_int($options['timeout'])) {
            $this->timeout = $options['timeout'];
            unset($options['timeout']);
        }
        if (empty($options)) {
            return false;
        }
        $this->options = $options;
    }

    /**
     * 给Redis实例设置参数
     * @param \Redis $redisInstance
     * @param array  $options
     */
    public static function setRedisOptions(\Redis $redisInstance, array $options)
    {
        if (empty($options)) {
            return $redisInstance;
        }
        foreach ($options as $k => $v) {
            $redisInstance->setOption($k, $v);
        }

        return $redisInstance;
    }

    /**
     * 与redis服务建立连接
     */
    public function connect()
    {
        // 连接主库
        $this->connectMaster();
        $this->connectSlave();
        $this->isConnected = true;
    }

    /**
     * 连接主库
     * @return boolean
     * @throws Exceptions\RedisConnectException
     */
    public function connectMaster()
    {
        $host = $this->servers['master']['host'];
        $port = (isset($this->servers['master']['port']) && !empty($this->servers['master']['port']) && is_int($this->servers['master']['port'])) ? $this->servers['master']['port'] : 6379;
        $this->instances['master'] = self::createConnection($host, $port, $this->timeout, $this->options);

        return true;
    }

    /**
     * 连接所有得从库
     * @return boolean
     */
    public function connectSlave()
    {
        if (empty($this->servers['slave'])) {
            $this->instances['slave'][$this->slaveNo] = $this->instances['master'];
            $this->slaveNo ++;

            return true;
        }
        foreach ($this->servers['slave'] as $server) {
            $host = $server['host'];
            $port = (isset($server['port']) && !empty($server['port']) && is_int($server['port'])) ? $server['port'] : 6379;
            $this->instances['slave'][$this->slaveNo] = self::createConnection($host, $port, $this->timeout, $this->options);
            $this->slaveNo ++;
        }

        return true;
    }

    /**
     * 创建一个连接
     * @param string $host    服务器地址
     * @param int    $port    端口
     * @param int    $timeout 超时时间
     */
    public static function createConnection($host, $port, $timeout, $options)
    {
        $redis = new \Redis();
        if (!$redis->connect($host, intval($port), intval($timeout))) {
            throw new Exceptions\RedisConnectException($host . ':' . $port . ' connect error');
        }
        $redis = self::setRedisOptions($redis, $options);

        return $redis;
    }

    /**
     * 返回一个连接
     * @param  int    $connectionType 连接得类型，主库或者是从库
     * @return \Redis
     */
    public function getConnection($connectionType = self::USE_MASTER_CONNECTION)
    {
        if ($this->isConnected == false) {
            $this->connect();
        }
        if ($connectionType == self::USE_MASTER_CONNECTION) {
            return $this->instances['master'];
        } elseif ($connectionType == self::USE_SLAVE_CONNECTION) {
            return $this->getSlaveServer();
        }
    }

    /**
     * 随机返回一台从库
     */
    public function getSlaveServer()
    {
        if ($this->slaveNo == 1) {
            // 只有一个从库，直接返回
            return $this->instances['slave'][0];
        }
        $hashId = $this->hashId(mt_rand(), $this->slaveNo);

        return $this->instances['slave'][$hashId];
    }

    /**
     * 根据ID得到 hash 后 0～m-1 之间的值
     *
     * @param  string $id
     * @param  int    $m
     * @return int
     */
    private function hashId($id, $m = 10)
    {
        //把字符串K转换为 0～m-1 之间的一个值作为对应记录的散列地址
        $k = md5($id);
        $l = strlen($k);
        $b = bin2hex($k);
        $h = 0;
        for ($i = 0; $i < $l; $i++) {
            //相加模式HASH
            $h += substr($b, $i * 2, 2);
        }
        $hash = ($h * 1) % $m;

        return $hash;
    }
}
