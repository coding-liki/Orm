<?php

namespace CodingLiki\Orm\EntityManager\Relations;

use CodingLiki\Orm\BaseModel;
use CodingLiki\Orm\EntityManager\Relations\Query\ModelQueryInterface;

interface RelationInterface
{
    public function getFromModelClass(): string;

    public function getToModelClass(): string;

    /**
     * @return BaseModel[]
     */
    public function getFromModels(BaseModel $toModel): array;

    /**
     * @return BaseModel[]
     */
    public function getToModels(BaseModel $fromModel): array;

    public function getFromQuery(BaseModel $toModel): ModelQueryInterface;

    public function getToQuery(BaseModel $fromModel): ModelQueryInterface;
}

