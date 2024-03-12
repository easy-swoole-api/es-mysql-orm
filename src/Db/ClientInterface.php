<?php


namespace EasySwoole\ORM\Db;


use EasySwoole\Mysqli\QueryBuilder;

interface ClientInterface
{
    public function query(QueryBuilder $builder, float $timeout = null): Result;

    public function connectionName(?string $name = null): ?string;
}
