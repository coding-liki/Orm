<?php

namespace CodingLiki\Orm\EntityManager\Exceptions;

use Exception;

class EmptyResultFromDb extends Exception
{
    public function __construct(string $rawQuery)
    {
        parent::__construct('Db returns empty result for query `' . $rawQuery . '`');
    }
}