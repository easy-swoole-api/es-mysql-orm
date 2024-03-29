<?php
namespace EasySwoole\ORM\Tests;

use EasySwoole\ORM\Db\Config;
use EasySwoole\ORM\Db\Connection;
use EasySwoole\ORM\DbManager;
use EasySwoole\ORM\Exception\Exception;
use EasySwoole\ORM\Tests\models\TestUserModel;
use PHPUnit\Framework\TestCase;
use mysqli_sql_exception;

class ReplaceIntoTest extends TestCase
{
    /**
     * @var $connection Connection
     */
    protected $connection;

    protected $ids = [];

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

    public function testException()
    {
        $model = TestUserModel::create();
        $addTime = date('Y-m-d');
        $data = [
            'name' => '史迪仔',
            'age' => 21,
            'addTime' => $addTime,
            'state' => 1
        ];
        $id = $model->data($data)->save();
        $this->assertIsInt($id);

        if (!empty(MYSQL_CONFIG['useMysqli'])) {
            $this->expectException(mysqli_sql_exception::class);
            $this->expectExceptionMessage("Duplicate entry '{$id}' for key 'test_user_model.PRIMARY'");
            $model->data($data)->save();
            $this->fail('replace test exception error');
        } else {
            $this->expectException(Exception::class);
            $this->expectExceptionMessage("SQLSTATE[23000] [1062] Duplicate entry '{$id}' for key 'test_user_model.PRIMARY' [INSERT  INTO `test_user_model` (`name`, `age`, `addTime`, `state`, `id`)  VALUES ('史迪仔', 21, '{$addTime}', 1, $id)]");
            $model->data($data)->save();
            $this->fail('replace test exception error');
        }
    }

    public function testReplaceInto()
    {
        TestUserModel::create()->destroy(null, true);
        $model = TestUserModel::create();
        $addTime = date('Y-m-d');
        $data = [
            'name' => '史迪仔',
            'age' => 21,
            'addTime' => $addTime,
            'state' => 1
        ];
        $id = $model->data($data)->save();
        $this->assertIsInt($id);

        $data['name'] = 'replace into';

        $model->data($data)->replace()->save();
        $sql = DbManager::getInstance()->getLastQuery()->getLastQuery();
        $this->assertEquals("REPLACE  INTO `test_user_model` (`name`, `age`, `addTime`, `state`, `id`)  VALUES ('replace into', 21, '{$addTime}', 1, {$id})", $sql);
        $ret = TestUserModel::create()->get($id);
        $this->assertEquals('replace into', $ret->name);
    }

    public function tearDown(): void
    {
        TestUserModel::create()->destroy(null, true);
    }
}
