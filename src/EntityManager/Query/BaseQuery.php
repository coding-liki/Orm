<?php

namespace CodingLiki\Orm\EntityManager\Query;

use CodingLiki\Orm\BaseModel;
use CodingLiki\Orm\EntityManager\EntityManagerContainer;
use CodingLiki\Orm\EntityManager\Exceptions\EmptyResultFromDb;
use CodingLiki\Orm\EntityManager\Exceptions\NotFullPrimaryKey;
use CodingLiki\Orm\EntityManager\Exceptions\NotOneFieldInResultFromDb;
use CodingLiki\Orm\EntityManager\Exceptions\NotOneResultFromDb;
use CodingLiki\Orm\EntityManager\Traits\QueryParamsTrait;
use CodingLiki\Orm\Helper\ModelHelper;
use CodingLiki\QueryBuilder\Expression\Expression;
use CodingLiki\QueryBuilder\Expression\RuleExpressionBuilder;
use CodingLiki\QueryBuilder\QueryBuilder;

class BaseQuery implements ModelQueryInterface
{

    use QueryParamsTrait;

    private QueryBuilder $queryBuilder;
    private array $usedModelClasses = [];
    private string $baseModelClass;

    public function find(string $modelClass): static
    {
        $this->queryBuilder = new QueryBuilder();
        $this->usedModelClasses = [$modelClass];
        $this->baseModelClass = $modelClass;

        $this->queryBuilder->select($modelClass . '.*')->from($modelClass);

        return $this;
    }

    public function byPk(array|int|string $identifier): static
    {
        /** @var BaseModel $entity */
        $entity = new $this->baseModelClass();

        $pkArray = $entity->getPrimaryKey();;
        is_array($pkArray) ?: $pkArray = [$pkArray];

        is_array($identifier) ?: $identifier = [$identifier];

        if (count($identifier) !== count($pkArray)) {
            throw new NotFullPrimaryKey($this->baseModelClass);
        }

        $forWhere = [];
        foreach ($pkArray as $number => $pkField) {
            $value = $identifier[$number] ?? $identifier[$pkField];
            $fieldNames = ModelHelper::getModelFields($this->baseModelClass);

            $fullFieldName = $this->baseModelClass . '.' . $fieldNames[$pkField]->fieldName;
            $forWhere[] = RuleExpressionBuilder::equal($fullFieldName, $this->addParam($pkField, $value));
        }

        return $this->where($forWhere);
    }

    public function where(array|Expression $where): static
    {
        is_array($where) ?: $where = [$where];

        $this->queryBuilder->where($where);

        return $this;
    }

    public function orWhere(array|Expression $where): static
    {
        is_array($where) ?: $where = [$where];

        $this->queryBuilder->orWhere($where);

        return $this;
    }

    public function limit(int $limit, ?int $offset = NULL): static
    {
        $this->queryBuilder->limit($limit, $offset);

        return $this;
    }

    public function offset(int $offset): static
    {
        $this->queryBuilder->offset($offset);

        return $this;
    }

    public function orderBy(array $orderBy): static
    {
        $this->queryBuilder->orderBy($orderBy);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function all(): array
    {
        $query = $this->queryBuilder->getRaw();
        $entityManager = EntityManagerContainer::get($this->baseModelClass);

        $entitiesData = $entityManager->executeQuery($query, $this->usedModelClasses, $this->usedParams)->getAllRows();


        $result = [];
        foreach ($entitiesData as $data) {
            $result[] = $entityManager->persistEntityFromArray($this->baseModelClass, $data);
        }

        return $result;
    }

    public function one(): BaseModel
    {
        $entityData = $this->getOneResult();
        $entityManager = EntityManagerContainer::get($this->baseModelClass);

        return $entityManager->persistEntityFromArray($this->baseModelClass, $entityData);
    }

    private function getOneResult(): array
    {
        $query = $this->queryBuilder->getRaw();
        $entityManager = EntityManagerContainer::get($this->baseModelClass);

        $entitiesData = $entityManager->executeQuery($query, $this->usedModelClasses, $this->usedParams)->getAllRows();

        if (count($entitiesData) === 0) {
            throw new EmptyResultFromDb($query);
        }

        if (count($entitiesData) > 1) {
            throw new NotOneResultFromDb($query);
        }

        return $entitiesData[0];
    }

    public function scalar()
    {
        $data = $this->getOneResult();

        if (count($data) > 1) {
            throw new NotOneFieldInResultFromDb($this->queryBuilder->getRaw());
        }

        return end($data);
    }

    public function string(): string
    {
        return (string)$this->scalar();
    }

    public function int(): int
    {
        return (int)$this->scalar();
    }

    public function bool(): bool
    {
        return (bool)$this->scalar();
    }

    public function float(): float
    {
        return (float)$this->scalar();
    }

    public function equalRight(string|Expression $right): Expression
    {
        return RuleExpressionBuilder::equalRight($right);
    }

    public function rule(string|Expression $left, string|Expression $right, string $operation): Expression
    {
        $left = $this->appendBaseClassToFieldName($left);

        return RuleExpressionBuilder::rule($left, $right, $operation);
    }

    public function isNull(string|Expression $left): Expression
    {
        $left = $this->appendBaseClassToFieldName($left);

        return RuleExpressionBuilder::isNull($left);
    }

    public function isNotNull(string|Expression $left): Expression
    {
        $left = $this->appendBaseClassToFieldName($left);

        return RuleExpressionBuilder::isNotNull($left);
    }

    public function equal(string|Expression $left, string|Expression $right): Expression
    {

        return $this->rule($left, $right, '=');
    }

    public function notEqual(string|Expression $left, string|Expression $right): Expression
    {

        return $this->rule($left, $right, '<>');
    }

    public function less(string|Expression $left, string|Expression $right): Expression
    {

        return $this->rule($left, $right, '<');
    }

    public function lessOrEqual(string|Expression $left, string|Expression $right): Expression
    {

        return $this->rule($left, $right, '<=');
    }

    public function more(string|Expression $left, string|Expression $right): Expression
    {

        return $this->rule($left, $right, '>');
    }

    public function moreOrEqual(string|Expression $left, string|Expression $right): Expression
    {
        return $this->rule($left, $right, '>=');
    }

    public function in(string|Expression $left, string|Expression $in): Expression
    {
        $left = $this->appendBaseClassToFieldName($left);

        return RuleExpressionBuilder::in($left, $in);
    }

    public  function inRight(string|Expression $in): Expression
    {
        return RuleExpressionBuilder::inRight($in);
    }

    public  function like(string|Expression $left, string|Expression $like, bool $caseInsensitive = false): Expression
    {
        $left = $this->appendBaseClassToFieldName($left);

        return RuleExpressionBuilder::like($left, $like, $caseInsensitive);
    }

    public  function between(string|Expression $left, string|Expression $start, string|Expression $end): Expression
    {
        $left = $this->appendBaseClassToFieldName($left);

        return RuleExpressionBuilder::between($left, $start, $end);
    }

    public  function not(string|Expression $expression): Expression
    {
        return RuleExpressionBuilder::not($expression);
    }

    /**
     * @param string|Expression $left
     * @return Expression|string
     */
    private function appendBaseClassToFieldName(string|Expression $left): Expression|string
    {
        $modelFields = ModelHelper::getModelFields($this->baseModelClass);
        if (in_array($left, array_keys($modelFields))) {
            $left = $this->baseModelClass . '.' . $left;
        }

        return $left;
    }
}