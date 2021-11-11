<?php

namespace CodingLiki\Orm\Attributes\Relation;

use Attribute;
use CodingLiki\Orm\Attributes\Relation\Exceptions\NotModelClassTypeException;
use CodingLiki\Orm\BaseModel;
use CodingLiki\Orm\EntityManager\EntityManagerContainer;
use CodingLiki\Orm\EntityManager\EntityState;
use CodingLiki\Orm\EntityManager\Events\AfterPersistEvent;
use CodingLiki\Orm\EntityManager\Events\BeforeInsertEvent;
use CodingLiki\Orm\EntityManager\Events\BeforeInvalidateStateEvent;
use CodingLiki\Orm\EntityManager\Query\BaseQuery;
use CodingLiki\Orm\EventSubSystem\EventSubSystem;
use CodingLiki\Orm\Helper\ModelHelper;

#[Attribute]
class OneToOne extends AbstractRelation
{

    public function __construct(public string|array $fkFields, public string $reverseField)
    {
    }

    /**
     * @inheritDoc
     */
    public function getModels(BaseModel $fromModel): array
    {
        $result = $this->getQuery($fromModel)->one();

        return $result ? [$result] : [];
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
        EventSubSystem::subscribe(BeforeInvalidateStateEvent::class, [$this, 'beforeInvalidateStateListener']);
    }

    public function afterPersistListener(AfterPersistEvent $event)
    {
        if (!$this->canProcessEvent($event, true)) {
            return;
        }

        if ($this->notNull) {
            if ($this->oppositeRelation) {
                $this->oppositeRelation->relationProcessed = true;
            }
        }

        $entityState = $event->entityManager->getEntityState($event->entity);

        if (!in_array($entityState->getState(), [EntityState::STATE_NEW, EntityState::STATE_NEED_DELETE])) {
            $relationEntities = $this->getModels($event->entity);

            if (!empty($relationEntities)) {
                $relationEntity = $relationEntities[0];
                ModelHelper::setEntityFieldValue($event->entity, $this->fieldName, $relationEntity);
            }
        }

        try {
            $relationEntity = ModelHelper::getEntityFieldValue($event->entity, $this->fieldName);

            if ($relationEntity !== NULL) {
                $event->entityManager->getEntityState($relationEntity);
                ModelHelper::setEntityFieldValue($relationEntity, $this->reverseField, $event->entity);
            }
        } catch (\Throwable $t) {

        }
    }

    public function beforeInsertListener(BeforeInsertEvent $event)
    {

        if (!$this->canProcessEvent($event)) {
            return;
        }


        $this->updateForeignKeyValues($event->entity);
    }

    public function beforeInvalidateStateListener(BeforeInvalidateStateEvent $event)
    {
        if (!$this->canProcessEvent($event)) {
            return;
        }

        $this->updateForeignKeyValues($event->entity);
    }

    /**
     * @throws NotModelClassTypeException
     */
    public function initDataByReflectionProperty(\ReflectionProperty $property): void
    {
        !$property->getType()->isBuiltin() && is_subclass_of($property->getType()->getName(), BaseModel::class)
            ?: throw new NotModelClassTypeException($property->getName(), $property->class);
        $this->toModelClass = $property->getType()->getName();
        $this->fieldName = $property->getName();
        $this->notNull = !$property->getType()->allowsNull();
        $this->oppositeRelation = ModelHelper::getModelRelation($this->toModelClass, $this->reverseField);
    }

    /**
     * @param BeforeInvalidateStateEvent $event
     *
     * @throws \CodingLiki\Orm\Exceptions\NotModelFieldException
     */
    private function updateForeignKeyValues(BaseModel $entity): void
    {
        try {

            $relationEntity = ModelHelper::getEntityFieldValue($entity, $this->fieldName);
        } catch (\Throwable $t) {
            $relationEntity = null;
        }
        $em = EntityManagerContainer::get($entity::class);

        if ($relationEntity) {
            $state = $em->getEntityState($relationEntity);

            if ($state->getState() === EntityState::STATE_NEW) {
                $em->flush($relationEntity);
            }
            foreach ($this->fkFields as $fkFromField => $fkToField) {
                $relationFieldValue = ModelHelper::getEntityFieldValue($relationEntity, $fkToField);

                ModelHelper::setEntityFieldValue($entity, $fkFromField, $relationFieldValue);
            }
        }
    }
}