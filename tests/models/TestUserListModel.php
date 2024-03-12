<?php
/**
 * Created by PhpStorm.
 * User: Tioncico
 * Date: 2019/10/22 0022
 * Time: 15:08
 */

namespace EasySwoole\ORM\Tests\models;


use EasySwoole\DDL\Blueprint\Table;
use EasySwoole\Mysqli\QueryBuilder;
use EasySwoole\ORM\AbstractModel;
use EasySwoole\ORM\DbManager;
use EasySwoole\Utility\Str;

/**
 * Class TestUserModel
 * @package EasySwoole\ORM\Tests
 * @property $id
 * @property $name
 * @property $age
 * @property $addTime
 * @property $state
 */
class TestUserListModel extends AbstractModel
{
    protected $tableName='user_test_list';

    /**
     * 非模型属性字段 获取器，可用于append
     */
    public function getAppendOneAttr()
    {
        return "siam_append";
    }

    public function __construct(array $data = [])
    {
        $query = new QueryBuilder();
        $tableDDL = new Table($this->tableName);
        $tableDDL->setIfNotExists();
        $tableDDL->colInt('id', 11)->setIsPrimaryKey()->setIsAutoIncrement();
        $tableDDL->colVarChar('name', 255);
        $tableDDL->colTinyInt('age', 1);
        $tableDDL->colDateTime('addTime');
        $tableDDL->colTinyInt('state', 1);
        $tableDDL->setIfNotExists();

        $sql = $tableDDL->__createDDL();
        $query->raw($sql);
        DbManager::getInstance()->query($query);

        parent::__construct($data);
    }
}
