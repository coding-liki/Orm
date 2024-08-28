<?php

namespace CodingLiki\Orm\EntityManager;

use CodingLiki\Orm\BaseModel;
use CodingLiki\RequestResponseCollection\Helper\ClassHelper;

class ModelRegistrar
{
    public static function registerModel(string|BaseModel $className)
    {
        $class = new \ReflectionClass($className);

        EntityManagerContainer::add($class->getNamespaceName(), $className::DATABASE_NAME);
    }

    public static function registerRecursiveDirectory(string $directory)
    {
        $dirtyClasses = ClassHelper::getClassesFromDirectoryRecursive($directory);

        foreach ($dirtyClasses as $class) {
            if (is_a($class, BaseModel::class, true)) {
                self::registerModel($class);
            }
        }
    }
}