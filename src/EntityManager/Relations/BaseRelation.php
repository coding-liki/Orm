<?php

namespace CodingLiki\Orm\EntityManager\Relations;

abstract class BaseRelation implements RelationInterface
{
    public function __construct(private string $fromModelClass, private string $toModelClass)
    {
    }

    public function getFromModelClass(): string
    {
        return $this->fromModelClass;
    }

    public function getToModelClass(): string
    {
        return $this->toModelClass;
    }

}

