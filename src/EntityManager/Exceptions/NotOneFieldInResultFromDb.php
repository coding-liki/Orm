<?php

namespace CodingLiki\Orm\EntityManager\Exceptions;

use Exception;

class NotOneFieldInResultFromDb extends Exception
{
    public function __construct(string $rawQuery)
    {
        parent::__construct('Db returns more than one field in result for query `' . $rawQuery . '`');
    }
}