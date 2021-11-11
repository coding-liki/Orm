<?php

namespace CodingLiki\Orm\EntityManager\Events;

use CodingLiki\Orm\EntityManager\EntityManager;
use CodingLiki\Orm\EntityManager\EntityState;

class BeforeInvalidateStateEvent extends EntityManagerEvent
{
    public function __construct(EntityManager $entityManager, public EntityState $entityState)
    {
        parent::__construct($entityManager, $this->entityState->getEntity());
    }
}