<?php

namespace CodingLiki\Orm\EntityManager;

use CodingLiki\DbModule\DbInterface;
use CodingLiki\DbModule\QueryResultInterface;
use CodingLiki\Orm\Attributes\ModelField;
use CodingLiki\Orm\BaseModel;
use CodingLiki\Orm\EntityManager\Traits\QueryParamsTrait;
use CodingLiki\Orm\Helper\ModelHelper;
use CodingLiki\QueryBuilder\Expression\RuleExpressionBuilder;
use CodingLiki\QueryBuilder\QueryBuilder;

class EntityManager
{
    use QueryParamsTrait;

    /**
     * @var array<string,array<string,EntityState>>
     */
    private array $identifiedEntityStates = [];

    /**
     * @var array<string,array<string,EntityState>>
     */
    private array $newEntityStates = [];

    private bool $preventExecute = false;

    public function __construct(private DbInterface $dbConnection, private string $modelsPrefix)
    {
    }

    public function persist(BaseModel $entity, ?int $state = null): static
    {
        $class = $entity::class;

        $state ?: $state = $this->guessEntityState($entity);

        if ($state === EntityState::STATE_NEW) {
            isset($this->newEntityStates[$class][spl_object_hash($entity)])
                ?: $this->newEntityStates[$class][spl_object_hash($entity)] = new EntityState($entity);
            $entityState = $this->newEntityStates[$class][spl_object_hash($entity)];
        } else {
            $pk_hash = $this->buildPkHash($entity);
            isset($this->identifiedEntityStates[$class][$pk_hash])
                ?: $this->identifiedEntityStates[$class][$pk_hash] = new EntityState($entity);

            $entityState = $this->identifiedEntityStates[$class][$pk_hash];
        }

        $entityState->setState($state)->invalidateState();

        return $this;
    }

    public function flush(): static
    {
        foreach ($this->identifiedEntityStates as $entityStates) {
            $this->flushNotFlashedStates($entityStates);
        }

        foreach ($this->newEntityStates as $class => $entityStates) {
            $this->insertNewStates($entityStates);
            $this->newEntityStates[$class] = [];
        }

        return $this;
    }

    public function persistEntityFromArray(string $modelClass, array $modelData): BaseModel
    {
        $entity = new $modelClass();

        $fieldSetters = ModelHelper::getModelFieldSetters($modelClass);
        $modelFields = ModelHelper::getModelFields($modelClass);

        foreach ($modelFields as $fieldName => $modelField) {
            $setter = $fieldSetters[$fieldName];
            $entity->$setter($modelData[$modelField->fieldName]);
        }

        $this->persist($entity, EntityState::STATE_FLUSHED);

        return $entity;
    }

    /**
     * @param string $query
     * @param string[] $usedModelClasses
     * @param array $usedParams
     *
     * @return QueryResultInterface
     */
    public function executeQuery(string $query, array $usedModelClasses, array $usedParams = []): QueryResultInterface
    {
        foreach ($usedModelClasses as $modelClass) {
            $tableName = $this->getTableNameByModelClass($modelClass);

            $query = str_replace($modelClass, $tableName, $query);
        }

        return $this->dbConnection->query($query, $usedParams);
    }

    private function getTableNameByModelClass(mixed $modelClass): string
    {
        $modelEntityManager = EntityManagerContainer::get($modelClass);
        if ($this !== $modelEntityManager) {
            return $modelEntityManager->getTableNameByModelClass($modelClass);
        }

        /** @var BaseModel $model */
        $model = new $modelClass();

        return $this->modelsPrefix . $model->getTableName();
    }


    private function buildPkHash(BaseModel $entity): string
    {
        $getters = ModelHelper::getModelFieldGetters($entity::class);
        $pkArray = $entity->getPrimaryKey();
        is_array($pkArray) ?: $pkArray = [$pkArray];
        $fullPkString = '';

        foreach ($pkArray as $pkField) {
            $getter = $getters[$pkField];
            $pkValue = $entity->$getter();
            $fullPkString .= $pkValue;
        }

        return md5($fullPkString);
    }

    /**
     * @param EntityState[] $statesToUpdate
     */
    private function flushNotFlashedStates(array $statesToUpdate)
    {
        foreach ($statesToUpdate as $state) {
            if ($state->getState() === EntityState::STATE_NEED_UPDATE) {
                $this->updateStateInDb($state);
                $state->flushState();
            } else if ($state->getState() === EntityState::STATE_NEED_DELETE) {
                $this->deleteStateInDb($state);
                $state->flushState();
            }
        }
    }

    /**
     * @param EntityState $state
     */
    private function updateStateInDb(EntityState $state): void
    {
        $queryBuilder = new QueryBuilder();

        $entityClass = $state->getEntity()::class;
        $diffToUpdate = $state->getDiff();

        $setValues = [];
        $modelFields = ModelHelper::getModelFields($entityClass);
        $modelFieldGetters = ModelHelper::getModelFieldGetters($entityClass);

        foreach ($diffToUpdate as $fieldName => $newValue) {
            $dbFieldName = $modelFields[$fieldName]->fieldName;
            $setValues[$dbFieldName] = $this->addParam($fieldName, $newValue);
        }

        $pkArray = $state->getEntity()->getPrimaryKey();
        is_array($pkArray) ?: $pkArray = [$pkArray];
        $where = [];

        foreach ($pkArray as $pkField) {
            $getter = $modelFieldGetters[$pkField];
            $whereValue = $state->getEntity()->$getter();
            $dbFieldName = $modelFields[$pkField]->fieldName;
            $where[] = RuleExpressionBuilder::equal($dbFieldName, $this->addParam($pkField, $whereValue));
        }


        $query = $queryBuilder->update($entityClass, $setValues)->where($where)->getRaw();

        $result = $this->executeQuery($query, [$entityClass], $this->usedParams);
        print_r($result->getAllRows());
        $this->resetParams();
    }

    /**
     * @param EntityState[] $entityStates
     */
    private function insertNewStates(array $entityStates)
    {
        $pkFields = null;
        $postInsertIdGeneration = false;

        foreach ($entityStates as $state) {
            if ($pkFields === null) {
                $pkFields = $state->getEntity()->getPrimaryKey();
                is_array($pkFields) ?: $pkFields = [$pkFields];
                $postInsertIdGeneration = count($pkFields) === 1;
            }

            $newModelData = $this->insertEntityToDb($postInsertIdGeneration, $pkFields, $state);

            $this->flushEntityByNewData($newModelData, $state);
        }
    }

    private function guessEntityState(BaseModel $entity): int
    {

        $identifiedEntityStates = $this->identifiedEntityStates[$entity::class];
        foreach ($identifiedEntityStates as $state) {
            if ($state->getEntity() === $entity) {
                return EntityState::STATE_FLUSHED;
            }
        }

        return EntityState::STATE_NEW;
    }

    /**
     * @param string[] $pkFields
     */
    private function insertEntityToDb(bool $postInsertIdGeneration, array $pkFields, EntityState $state): array
    {
        $modelClass = $state->getEntity()::class;
        $modelFields = ModelHelper::getModelFields($modelClass);
        $modelFieldGetters = ModelHelper::getModelFieldGetters($modelClass);

        $insertFields = [];
        $insertValues = [];
        foreach ($modelFields as $fieldName => $modelField) {
            if ($postInsertIdGeneration && in_array($fieldName, $pkFields) || $modelField->ignoreOnInsert) {
                continue;
            }
            $getter = $modelFieldGetters[$fieldName];
            $insertFields[] = $modelField->fieldName;
            $insertValues[] = $this->addParam($fieldName, $state->getEntity()->$getter());
        }

        $insertQuery = (new QueryBuilder())->insert($modelClass, $insertFields, [$insertValues])->getRaw();
        $this->executeQuery($insertQuery, [$modelClass], $this->usedParams);
        $this->resetParams();

        $pkValues = $postInsertIdGeneration ? [$pkFields[0] => $this->dbConnection->getLastInsertId()] : $state->getEntity()->getPrimaryKeyValues();

        $where = [];

        foreach ($pkValues as $fieldName => $value) {
            $dbFieldName = $modelFields[$fieldName]->fieldName;
            $where[] = RuleExpressionBuilder::equal($modelClass . '.' . $dbFieldName, $this->addParam($fieldName, $value));
        }

        $selectQuery = (new QueryBuilder())->select($modelClass . '.*')->from($modelClass)->where($where)->getRaw();
        $newModelData = $this->executeQuery($selectQuery, [$modelClass], $this->usedParams)->getNextRow();
        $this->resetParams();

        return $newModelData;
    }

    /**
     * @param array<string,mixed> $newModelData
     */
    private function flushEntityByNewData(array $newModelData, EntityState $state): void
    {
        $modelClass = $state->getEntity()::class;
        $modelFieldsByDbNames = ModelHelper::getModelFieldsByDbNames($modelClass);
        $modelFieldSetters = ModelHelper::getModelFieldSetters($modelClass);

        foreach ($newModelData as $dbFieldName => $value) {
            if (!isset($modelFieldsByDbNames[$dbFieldName])) {
                continue;
            }

            $fieldName = $modelFieldsByDbNames[$dbFieldName]->modelFieldName;
            $setter = $modelFieldSetters[$fieldName];
            $state->getEntity()->$setter($value);
            $state->flushState();
            $this->identifiedEntityStates[$modelClass][$this->buildPkHash($state->getEntity())] = $state;
        }
    }

    private function deleteStateInDb(EntityState $state): void
    {
        if ($this->checkPreventExecute()) {
            return;
        }

        $modelClass = $state->getEntity()::class;
        $modelFields = ModelHelper::getModelFields($modelClass);

        $where = [];
        $pkValues = $state->getEntity()->getPrimaryKeyValues();

        foreach ($pkValues as $fieldName => $value) {
            $dbFieldName = $modelFields[$fieldName]->fieldName;
            $where[] = RuleExpressionBuilder::equal($modelClass . '.' . $dbFieldName, $this->addParam($fieldName, $value));
        }

        $deleteQuery = (new QueryBuilder())->delete()->from($modelClass)->where($where)->getRaw();
        $this->executeQuery($deleteQuery, [$modelClass], $this->usedParams);
        $this->resetParams();

    }

    private function checkPreventExecute(): bool
    {
        if ($this->preventExecute) {
            $this->preventExecute = false;
            return true;
        }

        return false;
    }

}