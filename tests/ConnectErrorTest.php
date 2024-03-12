<?php
/**
 * 连接错误
 * User: Administrator
 * Date: 2019/11/16 0016
 * Time: 10:05
 */

namespace EasySwoole\ORM\Tests;

use EasySwoole\ORM\Db\Config;
use EasySwoole\ORM\Db\Connection;
use EasySwoole\ORM\Db\MysqlPool;
use EasySwoole\ORM\DbManager;
use EasySwoole\Pool\Exception\Exception;
use PHPUnit\Framework\TestCase;


class ConnectErrorTest extends TestCase
{
    public function testConnectError1()
    {
        $config = new Config([
            'host'      => '127.0.0.1',
            'port'      => 3306,
            'user'      => 'error',
            'password'  => 'error',
            'database'  => 'demo',
            'timeout'   => 5,
            'charset'   => 'utf8mb4',
            'useMysqli' => false,
        ]);
        $con    = new Connection($config);
        DbManager::getInstance()->addConnection($con, 'error1');

        $conf = DbManager::getInstance()->getConnection('error1')->getConfig();
        $pool = new MysqlPool($conf);

        try {
            $obj = $pool->defer(1);
        } catch (\Exception $e) {
            $this->assertInstanceOf(Exception::class, $e);
        }
    }

    public function testConnectError2()
    {
        $config = new Config([
            'host'      => '127.0.0.1',
            'port'      => 3306,
            'user'      => 'error',
            'password'  => 'error',
            'database'  => 'demo',
            'timeout'   => 5,
            'charset'   => 'utf8mb4',
            'useMysqli' => true,
        ]);
        $con    = new Connection($config);
        DbManager::getInstance()->addConnection($con, 'error2');

        $conf = DbManager::getInstance()->getConnection('error2')->getConfig();
        $pool = new MysqlPool($conf);

        try {
            $obj = $pool->defer(1);
        } catch (\Exception $e) {
            $this->assertInstanceOf(Exception::class, $e);
        }
    }
}
