<?php

namespace EasySwoole\ORM\Db;

use Co\MySQL\Statement;
use EasySwoole\Mysqli\Client;
use EasySwoole\Mysqli\QueryBuilder;
use EasySwoole\ORM\Exception\Exception;
use EasySwoole\Pool\ObjectInterface;
use mysqli_result;

class MysqliClient extends Client implements ClientInterface, ObjectInterface
{
    private $name;

    public function connectionName(?string $name = null): ?string
    {
        if ($name !== null) {
            $this->name = $name;
        }

        return $this->name;
    }


    public function query(QueryBuilder $builder, float $timeout = null): Result
    {
        $result = new Result();
        $ret    = null;
        $errno  = 0;
        $error  = '';
        $stmt   = null;

        try {
            if ($this->config->isUseMysqli()) {
                // with php mysqli client
                $stmt = $this->mysqlClient()->prepare($builder->getLastPrepareQuery());

                if (!$stmt) {
                    throw new Exception("prepare {$builder->getLastPrepareQuery()} fail");
                }

                $paramStr = '';
                foreach ($builder->getLastBindParams() as $item) {
                    $paramStr .= $this->determineType($item);
                }

                if (!empty($paramStr)) {
                    $paramStr = [$paramStr];
                    foreach ($builder->getLastBindParams() as $param) {
                        $paramStr[] = $param;
                    }
                    $stmt->bind_param(...$paramStr);
                }

                $stmt->execute();
                $ret = $stmt->get_result();
                $mysqliResult = false;

                if ($ret instanceof mysqli_result) {
                    $mysqliResult = $ret->fetch_all(MYSQLI_ASSOC);
                }

                $errno = $stmt->errno;
                $error = $stmt->error;

                if ($mysqliResult === false) {
                    if ($errno) {
                        throw new Exception($error);
                    }

                    if ($stmt->sqlstate === '00000') {
                        $mysqliResult = true;
                    }
                }

                // 结果设置
                $result->setResult($mysqliResult);

                $result->setLastError($error);
                $result->setLastErrorNo($errno);
                $result->setLastInsertId($stmt->insert_id);
                $result->setAffectedRows($stmt->affected_rows);

                /*
                 * 重置mysqli客户端成员属性，避免下次使用
                 */
                $this->lastInsertId   = 0;
                $this->lastAffectRows = 0;

                $stmt->close();
            } else {
                // with swoole mysql client
                $timeout = $this->config->getTimeout();
                /** @var Statement $stmt */
                $stmt    = $this->mysqlClient()->prepare($builder->getLastPrepareQuery(), $timeout);

                if ($stmt) {
                    $ret = $stmt->execute($builder->getLastBindParams(), $timeout);
                } else {
                    $ret = false;
                }

                $errno         = $this->mysqlClient()->errno;
                $error         = $this->mysqlClient()->error;
                $insert_id     = $this->mysqlClient()->insert_id;
                $affected_rows = $this->mysqlClient()->affected_rows;

                /*
                 * 重置mysqli客户端成员属性，避免下次使用
                 */
                $this->mysqlClient()->errno         = 0;
                $this->mysqlClient()->error         = '';
                $this->mysqlClient()->insert_id     = 0;
                $this->mysqlClient()->affected_rows = 0;

                // 结果设置
                if ($ret && $this->config->isFetchMode()) {
                    $result->setResult(new Cursor($stmt));
                } else {
                    $result->setResult($ret);
                }

                $result->setLastError($error);
                $result->setLastErrorNo($errno);
                $result->setLastInsertId($insert_id);
                $result->setAffectedRows($affected_rows);
            }
        } catch (\Throwable $throwable) {
            throw $throwable;
        } finally {
            if ($errno) {

                /**
                 * 断线收回链接
                 */
                if (in_array($errno, [2006, 2013])) {
                    $this->close();
                }

                $exception = new Exception($error . " [{$builder->getLastQuery()}]");
                $exception->setLastQueryResult($result);
                throw $exception;
            }
        }

        return $result;
    }

    function gc()
    {
//        if (isset($this->__inTransaction)) {
//            $isInTransaction = (bool)$this->__inTransaction;
//            if ($isInTransaction) {
//                try {
//                    $this->mysqlClient()->rollback();
//                } catch (\Throwable $throwable) {
//                    trigger_error($throwable->getMessage());
//                }
//            }
//        }

        $this->close();
    }

    function objectRestore()
    {
//        if (isset($this->__inTransaction)) {
//            $isInTransaction = (bool)$this->__inTransaction;
//            if ($isInTransaction) {
//                try {
//                    $this->mysqlClient()->rollback();
//                } catch (\Throwable $throwable) {
//                    trigger_error($throwable->getMessage());
//                }
//            }
//        }
    }

    function beforeUse(): ?bool
    {
        return $this->connect();
    }
}
