<?php
/**
 * @author gaobinzhan <gaobinzhan@gmail.com>
 */


namespace EasySwoole\ORM\Tests;


use EasySwoole\Mysqli\QueryBuilder;
use EasySwoole\ORM\Db\Config;
use EasySwoole\ORM\Db\Connection;
use EasySwoole\ORM\DbManager;
use EasySwoole\ORM\Exception\Exception;
use EasySwoole\ORM\Tests\models\DuplicateModel;
use PHPUnit\Framework\TestCase;
use mysqli_sql_exception;

class DuplicateTest extends TestCase
{

    /**
     * @var $connection Connection
     */
    protected $connection;

    protected function setUp(): void
    {
        parent::setUp(); // TODO: Change the autogenerated stub
        $config = new Config(MYSQL_CONFIG);
        $config->setReturnCollection(true);
        $this->connection = new Connection($config);
        DbManager::getInstance()->addConnection($this->connection);
        $connection = DbManager::getInstance()->getConnection();
        $this->assertTrue($connection === $this->connection);
    }

    public function testAdd()
    {
        $builder = new QueryBuilder();
        $builder->raw('truncate ' . DuplicateModel::create()->tableName());
        DbManager::getInstance()->query($builder);

        DuplicateModel::create()->data(['id' => 1, 'id1' => 1, 'nickname' => '史迪仔', 'nickname1' => '史迪奇'])->save();
        try {
            DuplicateModel::create()->data(['id' => 1, 'id1' => 1, 'nickname' => '史迪仔', 'nickname1' => '史迪奇'])->save();
        } catch (\Throwable $throwable) {
            if ($this->connection->getConfig()->isUseMysqli()) {
                $this->assertInstanceOf(mysqli_sql_exception::class, $throwable);
                $this->assertSame("Duplicate entry '1-1' for key 'duplicate.PRIMARY'", $throwable->getMessage());
            } else {
                $this->assertEquals(1062, $throwable->lastQueryResult()->getLastErrorNo());
            }
        }

        DuplicateModel::create()->duplicate(['nickname' => '史迪奇'])->data(['id' => 1, 'id1' => 1, 'nickname' => '史迪仔', 'nickname1' => '史迪奇'])->save();
        $ret = DuplicateModel::create()->get(['id' => 1, 'id1' => 1])->toArray();
        $this->assertEquals('史迪奇', $ret['nickname']);
        $this->assertEquals('史迪奇', $ret['nickname1']);

        DuplicateModel::create()->duplicate(['nickname' => '史迪仔', 'nickname1' => '史迪仔'])->data(['id' => 1, 'id1' => 1, 'nickname' => '史迪仔', 'nickname1' => '史迪奇'])->save();
        $ret = DuplicateModel::create()->get(['id' => 1, 'id1' => 1])->toArray();
        $this->assertEquals('史迪仔', $ret['nickname']);
        $this->assertEquals('史迪仔', $ret['nickname1']);

        DuplicateModel::create()->duplicate(['nickname' => '史迪仔', 'nickname1' => '史迪仔'])->data(['id' => 1, 'id1' => 2, 'nickname' => '史迪仔', 'nickname1' => '史迪奇'])->save();
        $ret = DuplicateModel::create()->get(['id' => 1, 'id1' => 2])->toArray();
        $this->assertNotEquals('史迪奇', $ret['nickname']);
        $this->assertNotEquals('史迪仔', $ret['nickname1']);

        DuplicateModel::create()->destroy([], true);
    }
}
