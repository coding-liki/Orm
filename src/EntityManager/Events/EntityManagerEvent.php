<?php

namespace CodingLiki\Orm\EntityManager\Events;

use CodingLiki\Orm\BaseModel;
use CodingLiki\Orm\EntityManager\EntityManager;

class EntityManagerEvent
{
    public function __construct(public EntityManager $entityManager, public BaseModel $entity )
    {
    }
}