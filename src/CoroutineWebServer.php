<?php
/**
 * @author workbunny/Chaz6chez
 * @email chaz6chez1993@outlook.com
 */
declare(strict_types=1);

namespace Workbunny\WebmanCoroutine;

use Webman\App;
use Webman\Http\Request;
use Workbunny\WebmanCoroutine\Exceptions\WorkerException;
use Workbunny\WebmanCoroutine\Handlers\HandlerInterface;
use Workbunny\WebmanCoroutine\Utils\Coroutine\Coroutine;
use Workbunny\WebmanCoroutine\Utils\WaitGroup\WaitGroup;
use Workerman\Connection\ConnectionInterface;
use Workerman\Worker;

/**
 *  协程化web服务进程
 */
class CoroutineWebServer extends App
{
    /**
     * 每个连接的协程计数
     *
     * @var int[]
     */
    protected static array $_connectionCoroutineCount = [];

    /**
     * 获取连接的协程计数
     *
     * @return int[]|int
     */
    public static function getConnectionCoroutineCount(?string $connectionId = null): array|int
    {
        return $connectionId === null
            ? static::$_connectionCoroutineCount
            : (static::$_connectionCoroutineCount[$connectionId] ?? 0);
    }

    /**
     * 回收连接的协程计数
     *
     * @param string $connectionId
     * @param bool $force
     * @return void
     */
    public static function unsetConnectionCoroutineCount(string $connectionId, bool $force = false): void
    {
        if (!$force and self::getConnectionCoroutineCount($connectionId) > 0) {
            return;
        }
        unset(static::$_connectionCoroutineCount[$connectionId]);
    }

    /** @inheritdoc  */
    public function onWorkerStart($worker)
    {
        if (!config('plugin.workbunny.webman-coroutine.app.enable', false)) {
            return;
        }
        parent::onWorkerStart($worker);
        /** @var HandlerInterface $handler */
        $handler = Factory::getCurrentHandler();
        if (!$handler) {
            $className = $worker::class;
            throw new WorkerException("Please run Factory::init or set $className::\$EventLoopClass = event_loop(). ");
        }
        $handler::initEnv();
    }

    /**
     * 停止服务
     *
     *  - 不用返回值和参数标定是为了兼容
     *
     * @param Worker|mixed $worker
     * @return void
     */
    public function onWorkerStop($worker, ...$params)
    {
        if (method_exists(parent::class, 'onWorkerStop')) {
            parent::onWorkerStop($worker, ...$params);
        }
    }

    /**
     * 连接建立
     *
     *  - 不用返回值和参数标定是为了兼容
     *
     * @param ConnectionInterface $connection
     * @param mixed ...$params
     * @return void
     */
    public function onConnect($connection, ...$params): void
    {
        if (!is_object($connection)) {
            return;
        }
        if (method_exists(parent::class, 'onConnect')) {
            // 协程化创建连接
            new Coroutine(function () use ($connection, $params) {
                parent::onConnect($connection, ...$params);
            });
        }
        self::$_connectionCoroutineCount[spl_object_hash($connection)] = 0;
    }

    /**
     * 连接关闭
     *
     *  - 不用返回值和参数标定是为了兼容
     *
     * @param ConnectionInterface|mixed $connection
     * @param ...$params
     * @return void
     */
    public function onClose($connection, ...$params)
    {
        if (!is_object($connection)) {
            return;
        }
        if (method_exists(parent::class, 'onClose')) {
            // 协程化关闭连接
            new Coroutine(function () use ($connection, $params) {
                parent::onClose($connection, ...$params);
            });
        }
        self::unsetConnectionCoroutineCount(spl_object_hash($connection), true);
    }

    /**
     * @link parent::onMessage()
     * @param ConnectionInterface|mixed $connection
     * @param Request|mixed $request
     * @param ...$params
     * @return null
     * @link parent::onMessage()
     */
    public function onMessage($connection, $request, ...$params)
    {
        if (!is_object($connection)) {
            return null;
        }
        $connectionId = spl_object_hash($connection);
        $params = func_get_args();
        $res = null;
        // 检测协程数
        if (($consumerCount = config('plugin.workbunny.webman-coroutine.app.consumer_count', 0)) > 0) {
            // 等待协程回收
            wait_for(function () use ($connectionId, $consumerCount) {
                return self::getConnectionCoroutineCount($connectionId) <= $consumerCount;
            });
        }

        $waitGroup = new WaitGroup();
        $waitGroup->add();
        // 计数 ++
        self::$_connectionCoroutineCount[$connectionId] =
            isset(self::$_connectionCoroutineCount[$connectionId])
                ? (self::$_connectionCoroutineCount[$connectionId] + 1)
                : 1;
        // 请求消费协程
        try {
            new Coroutine(function () use (&$res, $waitGroup, $params, $connectionId) {
                try {
                    $res = parent::onMessage(...$params);
                } finally {
                    if (isset(self::$_connectionCoroutineCount[$connectionId])) {
                        // 计数 --
                        self::$_connectionCoroutineCount[$connectionId]--;
                        // 尝试回收
                        self::unsetConnectionCoroutineCount($connectionId);
                    }
                    // wg完成
                    $waitGroup->done();
                }
            });
        } finally {
            $waitGroup->done();
        }
        // 等待
        $waitGroup->wait();

        return $res;
    }
}
