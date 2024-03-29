<?php
/**
 * @author gaobinzhan <gaobinzhan@gmail.com>
 */
namespace EasySwoole\ORM\Tests;

use EasySwoole\ORM\Db\Config;
use EasySwoole\ORM\Db\Connection;
use EasySwoole\ORM\DbManager;
use EasySwoole\ORM\Exception\Exception;
use EasySwoole\ORM\Tests\models\TestA;
use PHPUnit\Framework\TestCase;
use mysqli_sql_exception;

class LockTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $config = new Config(MYSQL_CONFIG);
        $config->setReturnCollection(true);
        $connection = new Connection($config);
        DbManager::getInstance()->addConnection($connection);

        $config = new Config(MYSQL_CONFIG);
        $config->setReturnCollection(true);
        $connection = new Connection($config);
        DbManager::getInstance()->addConnection($connection, 'a');

        $config = new Config(MYSQL_CONFIG);
        $config->setTimeout(2);
        $config->setReturnCollection(true);
        $connection = new Connection($config);
        DbManager::getInstance()->addConnection($connection, 'b');
    }

    private function add(): int
    {
        $model = new TestA();
        return $model->connection('a')->data(['a_name' => 1])->save();
    }

    public function testLockForUpdate()
    {
        $id = $this->add();

        $wg = new \Swoole\Coroutine\WaitGroup();
        $wg->add();
        go(function () use ($id, $wg) {
            // a连接处于事务 使用排他锁
            DbManager::getInstance()->startTransaction('a');
            TestA::create()->connection('a')->lockForUpdate()->get($id);
            \Co::sleep(3);
            DbManager::getInstance()->commit('a');
            $wg->done();
        });

        $wg->add();
        go(function () use ($id, &$result, $wg) {
            try {
                // 该数据加了锁 其它连接不可操作更新进入阻塞(任何锁不可加) b连接超时为2s 直接抛出异常
                TestA::create()->connection('b')->where(['id' => $id])->update(['a_name' => time()]);
            } catch (\Throwable $throwable) {
                $result = $throwable;
            } finally {
                $wg->done();
            }
        });
        $wg->wait(-1);

        if (!empty(MYSQL_CONFIG['useMysqli'])) {
            $this->assertInstanceOf(mysqli_sql_exception::class, $result);
        } else {
            $this->assertInstanceOf(Exception::class, $result);
        }

        $this->delete();
    }

    public function testSharedLock()
    {
        $id = $this->add();

        $wg = new \Swoole\Coroutine\WaitGroup();
        $wg->add();
        go(function () use ($id, $wg) {
            // a连接处于事务 使用共享锁
            DbManager::getInstance()->startTransaction('a');
            TestA::create()->connection('a')->sharedLock()->get($id);
            \Co::sleep(3);
            DbManager::getInstance()->commit('a');
            $wg->done();
        });

        $wg->add();
        go(function () use ($id, &$result, $wg, &$data) {
            try {
                $data = TestA::create()->connection('a')->sharedLock()->get($id);
                // 该数据加了锁 其它连接不可操作更新进入阻塞(可加共享锁) b连接超时为2s 直接抛出异常
                TestA::create()->connection('b')->where(['id' => $id])->update(['a_name' => time()]);
            } catch (\Throwable $throwable) {
                $result = $throwable;
            } finally {
                $wg->done();
            }
        });
        $wg->wait(-1);

        $this->assertEquals($id, $data['id']);

        if (!empty(MYSQL_CONFIG['useMysqli'])) {
            $this->assertInstanceOf(mysqli_sql_exception::class, $result);
        } else {
            $this->assertInstanceOf(Exception::class, $result);
        }

        $this->delete();
    }

    private function delete()
    {
        $model = new TestA();
        $model->connection('a')->destroy(null, true);
    }
}
