<?php

namespace CodingLiki\Orm\EntityManager\Events;

use CodingLiki\Orm\BaseModel;
use CodingLiki\Orm\EntityManager\EntityManager;

class BeforePersistEvent extends EntityManagerEvent
{
    public function __construct(EntityManager $entityManager, BaseModel $entity, public int $state)
    {
        parent::__construct($entityManager, $entity);
    }
}