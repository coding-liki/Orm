<?php

namespace CodingLiki\Orm\EntityManager;

use CodingLiki\Orm\BaseModel;
use CodingLiki\Orm\EntityManager\Events\AfterInvalidateStateEvent;
use CodingLiki\Orm\EntityManager\Events\BeforeInvalidateStateEvent;
use CodingLiki\Orm\EventSubSystem\EventSubSystem;
use CodingLiki\Orm\Helper\ModelHelper;

class EntityState
{
    public const STATE_NEW         = 0;
    public const STATE_FLUSHED     = 1;
    public const STATE_NEED_UPDATE = 2;
    public const STATE_NEED_DELETE = 3;

    private int        $state      = self::STATE_NEW;
    private ?BaseModel $initEntity = NULL;

    public function __construct(private BaseModel $entity)
    {
    }

    public function getState(): int
    {
        return $this->state;
    }

    public function setState(int $state): EntityState
    {
        $this->state = $state;

        return $this;
    }

    /**
     * @return BaseModel
     */
    public function getEntity(): BaseModel
    {
        return $this->entity;
    }

    public function invalidateState()
    {
        EventSubSystem::dispatch(new BeforeInvalidateStateEvent(EntityManagerContainer::get($this->entity::class), $this));

        if ($this->initEntity === NULL) {
            $this->initEntity = clone $this->entity;
        }

        if (!in_array($this->state, [self::STATE_NEW, self::STATE_NEED_DELETE]) && !ModelHelper::entitiesAreEqual($this->initEntity, $this->entity)) {
            $this->state = self::STATE_NEED_UPDATE;
        }

        EventSubSystem::dispatch(new AfterInvalidateStateEvent(EntityManagerContainer::get($this->entity::class), $this));
    }

    public function getDiff(): array
    {
        $modelFields = ModelHelper::getModelFields($this->entity::class);

        $diff = [];

        foreach ($modelFields as $fieldName => $modelField) {
            $getter = ModelHelper::getModelFieldGetter($this->entity::class, $fieldName);
            if ($this->entity->$getter() !== $this->initEntity->$getter()) {
                $diff[$fieldName] = $this->entity->$getter();
            }
        }

        return $diff;
    }

    public function flushState(): self
    {
        $this->initEntity = clone $this->entity;
        $this->state      = self::STATE_FLUSHED;

        return $this;
    }
}