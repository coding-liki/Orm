<?php

namespace CodingLiki\Orm;

use CodingLiki\Orm\EntityManager\EntityManagerContainer;
use CodingLiki\Orm\EntityManager\EntityState;
use CodingLiki\Orm\EntityManager\Query\ModelQueryInterface;
use CodingLiki\Orm\Helper\ModelHelper;
use CodingLiki\Orm\Normalizer\CamelCaseToSnakeCaseNormalizer;
use CodingLiki\Orm\Normalizer\StringNormalizerInterface;

class BaseModel
{
    protected static ?string $queryClass               = NULL;
    protected ?string        $tableName                = NULL;
    protected ?string        $tableNameNormalizerClass = CamelCaseToSnakeCaseNormalizer::class;

    /**
     * @var string[]|string
     */
    protected array|string $primaryKey = 'id';

    public function getTableName(): string
    {
        if ($this->tableName === NULL) {
            $classParts      = explode('\\', static::class);
            $this->tableName = $this->getTableNameNormalizer()->normalize(end($classParts));
        }

        return $this->tableName;
    }

    /**
     * @return array<string>|string
     */
    public function getPrimaryKey(): array|string
    {
        return $this->primaryKey;
    }

    public static function getQuery(): ModelQueryInterface
    {
        return new self::$queryClass();
    }

    public function getPrimaryKeyValues(): array
    {
        $primaryKeys = $this->getPrimaryKey();
        is_array($primaryKeys) ?: $primaryKeys = [$primaryKeys];
        $getters = ModelHelper::getModelFieldGetters(static::class);

        $values = [];
        foreach ($primaryKeys as $primaryKey)
        {
            $getter = $getters[$primaryKey];
            $values[$primaryKey] = $this->$getter();
        }

        return $values;
    }

    public function save() {
        $em = EntityManagerContainer::get(static::class);
        $em->persist($this)->flush();
    }

    public function delete() {
        $em = EntityManagerContainer::get(static::class);

        $em->persist($this, EntityState::STATE_NEED_DELETE);
    }
    private function getTableNameNormalizer(): StringNormalizerInterface
    {
        return new $this->tableNameNormalizerClass();
    }

}