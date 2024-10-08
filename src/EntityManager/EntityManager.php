<?php

namespace CodingLiki\Orm\EntityManager;

use CodingLiki\DbModule\DbInterface;
use CodingLiki\DbModule\QueryResultInterface;
use CodingLiki\Orm\BaseModel;
use CodingLiki\Orm\EntityManager\Events\AfterInsertEvent;
use CodingLiki\Orm\EntityManager\Events\AfterPersistEvent;
use CodingLiki\Orm\EntityManager\Events\AfterUpdateEvent;
use CodingLiki\Orm\EntityManager\Events\BeforeInsertEvent;
use CodingLiki\Orm\EntityManager\Events\BeforePersistEvent;
use CodingLiki\Orm\EntityManager\Events\BeforeUpdateEvent;
use CodingLiki\Orm\EntityManager\Traits\QueryParamsTrait;
use CodingLiki\Orm\EventSubSystem\EventSubSystem;
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

    public function persist(BaseModel $entity, ?int $state = NULL): static
    {
        $class = $entity::class;

        ModelHelper::extractAllForClass($entity::class);
        $state ??= $this->guessEntityState($entity);

        EventSubSystem::dispatch(new BeforePersistEvent($this, $entity, $state));

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

        EventSubSystem::dispatch(new AfterPersistEvent($this, $entity));

        return $this;
    }

    public function flush(?BaseModel $entity = null): static
    {
        foreach ($this->identifiedEntityStates as $entityStates) {
            $this->flushNotFlashedStates($entityStates, $entity);
        }

        foreach ($this->newEntityStates as $class => $entityStates) {
            $this->insertNewStates($entityStates, $entity);
        }

        return $this;
    }

    public function getEntityState(BaseModel $entity): EntityState
    {
        if (isset($this->newEntityStates[$entity::class][spl_object_hash($entity)])) {
            return $this->newEntityStates[$entity::class][spl_object_hash($entity)];
        }

        try {
            $pk_hash = $this->buildPkHash($entity);
            return $this->identifiedEntityStates[$entity::class][$pk_hash];
        } catch (\Throwable $t) {
            $this->persist($entity);
            return $this->getEntityState($entity);
        }
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

        return $this->getEntityState($entity)->getEntity();
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
        $query = $this->prepareQuery($usedModelClasses, $query);

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
    private function flushNotFlashedStates(array $statesToUpdate, ?BaseModel $entity = null)
    {
        foreach ($statesToUpdate as $state) {
            if ($entity === null || $state->getEntity() === $entity) {
                $state->invalidateState();
                if ($state->getState() === EntityState::STATE_NEED_UPDATE) {
                    EventSubSystem::dispatch(new BeforeUpdateEvent($this, $state->getEntity()));
                    $this->updateStateInDb($state);
                    EventSubSystem::dispatch(new AfterUpdateEvent($this, $state->getEntity()));

                    $state->flushState();
                } else if ($state->getState() === EntityState::STATE_NEED_DELETE) {
                    $this->deleteStateInDb($state);
                    $state->flushState();
                }
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
            $where[] = RuleExpressionBuilder::equal($entityClass . '.' . $pkField, $this->addParam($pkField, $whereValue));
        }

        $query = $queryBuilder->update($entityClass, $setValues)->where($where)->getRaw();

        $result = $this->executeQuery($query, [$entityClass], $this->usedParams);
        $this->resetParams();

        $this->refreshStateByPk($state->getEntity()->getPrimaryKeyValues(), $state);
    }

    /**
     * @param EntityState[] $entityStates
     */
    private function insertNewStates(array $entityStates, ?BaseModel $entity = null)
    {
        $pkFields = NULL;
        $postInsertIdGeneration = false;

        foreach ($entityStates as $state) {
            $state->invalidateState();
            if ($entity !== null && $state->getEntity() !== $entity || $state->getState() !== EntityState::STATE_NEW) {
                continue;
            }
            EventSubSystem::dispatch(new BeforeInsertEvent($this, $state->getEntity()));

            if ($pkFields === NULL) {
                $pkFields = $state->getEntity()->getPrimaryKey();
                is_array($pkFields) ?: $pkFields = [$pkFields];
                $postInsertIdGeneration = count($pkFields) === 1;
            }

            $this->insertEntityToDb($postInsertIdGeneration, $pkFields, $state);

            EventSubSystem::dispatch(new AfterInsertEvent($this, $state->getEntity()));
        }
    }

    private function guessEntityState(BaseModel $entity): int
    {

        $identifiedEntityStates = $this->identifiedEntityStates[$entity::class] ?? [];
//        print_r($identifiedEntityStates);

//        return EntityState::STATE_FLUSHED;
        foreach ($identifiedEntityStates as $state) {
//            print_r([
//                'from state' => spl_object_hash($state->getEntity()),
//                'to check' => spl_object_hash($entity)
//            ]);
            if ($state->getEntity() === $entity) {
                return EntityState::STATE_FLUSHED;
            }
        }

        return EntityState::STATE_NEW;
    }

    /**
     * @param string[] $pkFields
     */
    private function insertEntityToDb(bool $postInsertIdGeneration, array $pkFields, EntityState $state): void
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

        $this->refreshStateByPk($pkValues, $state);
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

    private function refreshStateByPk(array $pkValues, EntityState $state): void
    {
        $modelClass = $state->getEntity()::class;

        $where = [];

        foreach ($pkValues as $fieldName => $value) {
            $where[] = RuleExpressionBuilder::equal($modelClass . '.' . $fieldName, $this->addParam($fieldName, $value));
        }

        $selectQuery = (new QueryBuilder())->select($modelClass . '.*')->from($modelClass)->where($where)->getRaw();
        $newModelData = $this->executeQuery($selectQuery, [$modelClass], $this->usedParams)->getNextRow();
        $this->resetParams();

        $this->flushEntityByNewData($newModelData, $state);
    }

    /**
     * @param array $usedModelClasses
     * @param mixed $query
     *
     * @return array|mixed|string|string[]
     */
    private function prepareQuery(array $usedModelClasses, string $query): string
    {
        foreach ($usedModelClasses as $modelClass) {
            $tableName = $this->getTableNameByModelClass($modelClass);
            $modelFields = ModelHelper::getModelFields($modelClass);
            foreach ($modelFields as $fieldName => $modelField) {
                $oldFieldRef = $modelClass . '.' . $fieldName;
                $newFieldRef = $tableName . '.' . $modelField->fieldName;
                $query = str_replace($oldFieldRef, $newFieldRef, $query);
            }
            $query = str_replace($modelClass, $tableName, $query);
        }

        return $query;
    }

}