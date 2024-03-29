<?php

namespace CodingLiki\Orm\EntityManager;

use CodingLiki\DbModule\DbContainer;
use CodingLiki\Orm\EntityManager\Exceptions\NotManagedNamespaceException;

class EntityManagerContainer
{
    /**
     * @var array<string,EntityManager>
     */
    private static array $entityManagers = [];

    public static function add(
        string $modelsNamespace,
        string $dbName = DbContainer::DEFAULT_DB_NAME,
        string $tablePrefix = '',
        ?EntityManager $existedManager = null
    ): EntityManager
    {
        self::$entityManagers[$modelsNamespace] = $existedManager ?? new EntityManager(DbContainer::get($dbName), $tablePrefix);

        return  self::$entityManagers[$modelsNamespace];
    }

    public static function get(string $modelClass): ?EntityManager
    {
        return self::$entityManagers[self::fetchNamespace($modelClass)] ?? throw new NotManagedNamespaceException($modelClass);

    }

    private static function fetchNamespace(string $modelClass): string
    {
        $class = new \ReflectionClass($modelClass);

        return $class->getNamespaceName();
    }

}