<?php

namespace CodingLiki\Orm;

use CodingLiki\Orm\Normalizer\CamelCaseToSnakeCaseNormalizer;
use CodingLiki\Orm\Normalizer\StringNormalizerInterface;

class BaseModel
{
    protected ?string $tableName = null;
    protected ?string $tableNameNormalizerClass = CamelCaseToSnakeCaseNormalizer::class;

    public function getTableName(): string
    {
        if ($this->tableName === null) {
            $classParts = explode('\\', static::class);
            $this->tableName = $this->getTableNameNormalizer()->normalize(end($classParts));
        }
        return $this->tableName;
    }

    private function getTableNameNormalizer(): StringNormalizerInterface
    {
        return new $this->tableNameNormalizerClass();
    }

}