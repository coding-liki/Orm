<?php

namespace CodingLiki\Orm\EntityManager\Relations;

use CodingLiki\Orm\BaseModel;
use CodingLiki\Orm\EntityManager\Relations\Query\ModelQueryInterface;

class OneToOne extends BaseRelation
{

    /**
     * @param array<string,string>  $fieldPairs
     */
    public function __construct(private array $fieldPairs, string $fromModelClass, string $toModelClass, )
    {
        parent::__construct($fromModelClass, $toModelClass);
    }

    /**
     * @inheritDoc
     */
    public function getFromModels(BaseModel $toModel): array
    {
        return [$this->getFromQuery($toModel)->one()];
    }

    /**
     * @inheritDoc
     */
    public function getToModels(BaseModel $fromModel): array
    {
        return [$this->getFromQuery($fromModel)->one()];
    }

    public function getFromQuery(BaseModel $toModel): ModelQueryInterface
    {
        $query = $toModel::getQuery();

        $where = [];
        foreach ($this->fieldPairs as $from => $to){
            $where[$from] = $toModel->$to;
        }
        return $query->find($this->getFromModelClass())->where($where);
    }

    public function getToQuery(BaseModel $fromModel): ModelQueryInterface
    {
        $query = $fromModel::getQuery();

        $where = [];
        foreach ($this->fieldPairs as $from => $to){
            $where[$to] = $fromModel->$from;
        }
        return $query->find($this->getToModelClass())->where($where);
    }
}