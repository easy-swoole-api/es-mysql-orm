<?php
/**
 * Created by PhpStorm.
 * User: Tioncico
 * Date: 2019/10/22 0022
 * Time: 10:00
 */

namespace EasySwoole\ORM\Utility;

use EasySwoole\DDL\Enum\DataType;
use EasySwoole\ORM\Db\ClientInterface;
use EasySwoole\ORM\Db\MysqliClient;
use EasySwoole\ORM\DbManager;
use EasySwoole\ORM\Exception\Exception;
use EasySwoole\ORM\Utility\Schema\Column;
use EasySwoole\Mysqli\QueryBuilder;
use EasySwoole\ORM\Db\ConnectionInterface;
use EasySwoole\ORM\Utility\Schema\Table;

/**
 * 根据数据表信息生成table对象
 * Class getTableObject
 * @package EasySwoole\ORM\Utility
 */
class TableObjectGeneration
{
    protected $tableName;
    protected $connection;
    protected $tableColumns;
    protected $client;

    public function __construct(ConnectionInterface $connection, $tableName, ?ClientInterface $client = null)
    {
        $this->tableName = $tableName;
        $this->connection = $connection;
        $this->client = $client;

    }

    public function getTableColumnsInfo(string $connectionName = 'default')
    {
        $query = new QueryBuilder();
        $query->raw("show full columns from {$this->tableName}");

        if ($this->client instanceof ClientInterface) {
            $data = $this->client->query($query);
        } else {
            $data = DbManager::getInstance()->query($query, true, $connectionName);
        }

        if ($this->connection->getConfig()->isFetchMode()) {
            $data->getResult()->setReturnAsArray(true);
            $this->tableColumns = [];
            while ($tem = $data->getResult()->fetch()) {
                $this->tableColumns[] = $tem;
            }
        } else {
            $this->tableColumns = $data->getResult();
        }

        if (!is_array($this->tableColumns)) {
            throw new Exception("generationTable Error : " . $data->getLastError());
        }
        return $data->getResult();
    }

    public function generationTable(string $connectionName = 'default')
    {
        $this->getTableColumnsInfo($connectionName);
        $columns = $this->tableColumns;
        $table = new Table($this->tableName);
        foreach ($columns as $column) {
            //新增字段对象
            $columnObj = $this->getTableColumn($column);
            $table->addColumn($columnObj);
        }
        return $table;
    }

    protected function getTableColumn($column): Column
    {
        $columnTypeArr = explode(' ', $column['Type']);
        $tmpIndex = strpos($columnTypeArr[0], '(');
        if ($tmpIndex !== false) {
            $type = substr($columnTypeArr[0], 0, $tmpIndex);
            $limit = substr($columnTypeArr[0], $tmpIndex + 1, strpos($columnTypeArr[0], ')') - $tmpIndex - 1);
        } else {
            $type = $columnTypeArr[0];
            $limit = null;
        }

        $dataTypeEnum = DataType::tryFrom($type);

        if (!$dataTypeEnum) {
            throw new Exception("Data type '{$type}' is not supported yet");
        }

        $columnObj = new Column($column['Field'], $dataTypeEnum);

        //是否无符号
        if (in_array('unsigned', $columnTypeArr)) {
            $columnObj->setIsUnsigned();
        }

        //长度限制
        if ($limit !== null) {
            $limitArr = explode(',', $limit);
            if (isset($limitArr[1])) {
                $columnObj->setColumnLimit($limitArr);
            } else {
                $columnObj->setColumnLimit($limitArr[0]);
            }
        }

        //是否为主键
        if ($column['Key'] == 'PRI') {
            $columnObj->setIsPrimaryKey();
        }

        //默认值
        if ($column['Default'] !== null) {
            $columnObj->setDefaultValue($column['Default']);
        } else {
            $columnObj->setDefaultValue(null);
        }

        if ($column['Extra'] == 'auto_increment') {
            $columnObj->setIsAutoIncrement();
        }

        if (!empty($column['Comment'])) {
            $columnObj->setColumnComment($column['Comment']);
        }

        if (isset($column['Null']) && $column['Null'] == 'YES') {
            $columnObj->setIsNotNull(false);
        }

        return $columnObj;
    }

}
