<?php

namespace CodingLiki\Orm\EntityManager\Exceptions;

use Exception;

class NotFullPrimaryKey extends Exception
{
    public function __construct(string $modelClass)
    {
        parent::__construct('Not full primary key for using with byPk for class ' . $modelClass);
    }
}