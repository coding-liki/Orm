<?php

namespace CodingLiki\Orm\Attributes\Relation;

use Attribute;
use CodingLiki\Orm\BaseModel;
use CodingLiki\Orm\EntityManager\Events\EntityManagerEvent;
use CodingLiki\Orm\EntityManager\Query\BaseQuery;

abstract class AbstractRelation
{

    public string $fromModelClass;
    public string $toModelClass;
    public string $fieldName;
    public bool $notNull = false;
    public ?AbstractRelation $oppositeRelation = null;
    public bool $relationProcessed = false;

    /**
     * @return BaseModel[]
     */
    abstract public function getModels(BaseModel $fromModel): array;

    abstract public function getQuery(BaseModel $fromModel): BaseQuery;

    abstract public function initEventListeners(): void;

    abstract public function initDataByReflectionProperty(\ReflectionProperty $property): void;

    public function canProcessEvent(EntityManagerEvent $event, bool $checkProcessed = false): bool
    {
        return $event->entity::class === $this->fromModelClass && (!$checkProcessed || !$this->isRelationProcessed());
    }

    public function setUnprocessed(): self
    {
        $this->relationProcessed = false;
        return $this;
    }

    public function isRelationProcessed(): bool
    {
        if (!$this->relationProcessed) {
            $this->relationProcessed = true;

            return false;
        }

        return true;

    }
}