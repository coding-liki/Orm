<?php

namespace CodingLiki\Orm\Attributes\Relation;

use Attribute;
use CodingLiki\Orm\Attributes\Relation\Exceptions\FieldHasBadTypeException;
use CodingLiki\Orm\Attributes\Relation\Exceptions\NotModelClassTypeException;
use CodingLiki\Orm\BaseModel;
use CodingLiki\Orm\EntityManager\EntityState;
use CodingLiki\Orm\EntityManager\Events\AfterPersistEvent;
use CodingLiki\Orm\EntityManager\Events\BeforeInsertEvent;
use CodingLiki\Orm\EntityManager\Events\BeforeInvalidateStateEvent;
use CodingLiki\Orm\EntityManager\Query\BaseQuery;
use CodingLiki\Orm\EventSubSystem\EventSubSystem;
use CodingLiki\Orm\Helper\ModelHelper;

#[Attribute]
class OneToMany extends AbstractRelation
{
    public function __construct(public string|array $fkFields, string $relationClass, public ?string $reverseField = NULL)
    {
        $this->toModelClass = $relationClass;
    }

    /**
     * @inheritDoc
     */
    public function getModels(BaseModel $fromModel): array
    {
        return $this->getQuery($fromModel)->all();
    }

    public function getQuery(BaseModel $fromModel): BaseQuery
    {
        /** @var BaseModel $model */
        $model = $this->toModelClass;
        $query = $model::find();

        foreach ((array)$this->fkFields as $fkFromField => $fkToField) {
            $fieldValue = ModelHelper::getEntityFieldValue($fromModel, $fkFromField);

            $fullFieldName = $this->toModelClass . '.' . $fkToField;
            $query->where(
                $query->equal($fullFieldName, $query->addParam($fkToField, $fieldValue))
            );
        }

        return $query;
    }

    public function initEventListeners(): void
    {
        EventSubSystem::subscribe(AfterPersistEvent::class, [$this, 'afterPersistListener']);
    }


    public function afterPersistListener(AfterPersistEvent $event)
    {
        if (!$this->canProcessEvent($event)) {
            return;
        }

        $entityState = $event->entityManager->getEntityState($event->entity);
        if (!in_array($entityState->getState(), [EntityState::STATE_NEW, EntityState::STATE_NEED_DELETE])) {
            $relationEntities = $this->getModels($event->entity);
            ModelHelper::setEntityFieldValue($event->entity, $this->fieldName, $relationEntities);
        }

        if ($this->reverseField !== null) {
            try {
                $relationEntities = ModelHelper::getEntityFieldValue($event->entity, $this->fieldName);
                foreach ($relationEntities as $entity) {
                    $event->entityManager->getEntityState($entity);
                    ModelHelper::setEntityFieldValue($entity, $this->reverseField, $event->entity);
                }
            } catch (\Throwable $t) {

            }
        }

    }

    public function initDataByReflectionProperty(\ReflectionProperty $property): void
    {
        $property->getType()->getName() === 'array' ?: throw new FieldHasBadTypeException($property->getName(), $property->class, 'array');

        $this->fieldName = $property->getName();
        $this->notNull = !$property->getType()->allowsNull();
        $this->oppositeRelation = ModelHelper::getModelRelation($this->toModelClass, $this->reverseField);
    }
}