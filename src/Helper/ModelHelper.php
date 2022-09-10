<?php

namespace CodingLiki\Orm\Helper;

use CodingLiki\Orm\Attributes\ModelField;
use CodingLiki\Orm\Attributes\Relation\AbstractRelation;
use CodingLiki\Orm\BaseModel;
use CodingLiki\Orm\Exceptions\NotModelFieldException;
use CodingLiki\Orm\Normalizer\SnakeCaseToCamelCaseNormalizer;

class ModelHelper
{
    /**
     * @var array<string,array<string, ModelField>>
     */
    private static array $modelFields          = [];
    private static array $modelFieldsByDbNames = [];
    private static array $modelGetters         = [];
    private static array $modelSetters         = [];
    private static array $modelRelations       = [];

    /**
     * @param string $modelClass
     *
     * @return array<string, ModelField>
     */
    public static function getModelFields(string $modelClass): array
    {

        isset(self::$modelFields[$modelClass]) ?: self::extractModelFields($modelClass);

        return self::$modelFields[$modelClass];
    }

    /**
     * @param string $modelClass
     *
     * @return array<string, ModelField>
     */
    public static function getModelFieldsByDbNames(string $modelClass): array
    {
        isset(self::$modelFieldsByDbNames[$modelClass]) ?: self::extractModelFields($modelClass);

        return self::$modelFieldsByDbNames[$modelClass];
    }

    public static function getModelFieldGetters(string $modelClass): array
    {
        isset(self::$modelGetters[$modelClass]) ?: self::extractFieldGetters($modelClass);

        return self::$modelGetters[$modelClass];
    }

    /**
     * @throws NotModelFieldException
     */
    public static function getModelFieldGetter(string $modelClass, string $fieldName): string
    {
        return self::getModelFieldGetters($modelClass)[$fieldName] ?? throw new NotModelFieldException($modelClass, $fieldName);
    }

    public static function getModelFieldSetters(string $modelClass): array
    {
        isset(self::$modelSetters[$modelClass]) ?: self::extractFieldSetters($modelClass);

        return self::$modelSetters[$modelClass];
    }

    /**
     * @throws NotModelFieldException
     */
    public static function getModelFieldSetter(string $modelClass, string $fieldName): string
    {
        return self::getModelFieldSetters($modelClass)[$fieldName] ?? throw new NotModelFieldException($modelClass, $fieldName);
    }

    /**
     * @return array<string, AbstractRelation>
     */
    public static function getModelRelations(string $modelClass): array
    {
        isset(self::$modelRelations[$modelClass]) ?: self::extractModelRelations($modelClass);

        return self::$modelRelations[$modelClass] ?? [];
    }

    public static function getModelRelation(string $modelClass, ?string $fieldName): ?AbstractRelation
    {
        return self::getModelRelations($modelClass)[$fieldName] ?? NULL;
    }

    /**
     * @throws NotModelFieldException
     */
    public static function getEntityFieldValue(BaseModel $entity, string $fieldName)
    {
        $getter = self::getModelFieldGetter($entity::class, $fieldName);

        return $entity->$getter();
    }

    /**
     * @throws NotModelFieldException
     */
    public static function setEntityFieldValue(BaseModel $entity, string $fieldName, $value)
    {
        $setter = self::getModelFieldSetter($entity::class, $fieldName);

        return $entity->$setter($value);
    }

    public static function entitiesAreEqual(BaseModel $a, BaseModel $b): bool
    {
        if ($a::class !== $b::class) {
            return false;
        }

        foreach (self::getModelFields($a::class) as $fieldName => $modelField) {
            if (ModelHelper::getEntityFieldValue($a, $fieldName) !== ModelHelper::getEntityFieldValue($b, $fieldName)) {
                return false;
            }
        }

        return true;
    }

    public static function extractAllForClass(string $modelClass)
    {
        self::extractFieldGetters($modelClass);
        self::extractFieldSetters($modelClass);
    }

    /**
     * @param string $modelClass
     *
     * @throws \ReflectionException
     */
    private static function extractModelFields(string $modelClass): void
    {
        if (isset(self::$modelFields[$modelClass])) {
            return;
        }

        $reflectionClass = new \ReflectionClass($modelClass);

        $privateFields = $reflectionClass->getProperties(\ReflectionProperty::IS_PRIVATE);

        $onlyOrmFields = array_filter($privateFields, function (\ReflectionProperty $property) {
            $fieldAttributes = $property->getAttributes(ModelField::class);

            return count($fieldAttributes) === 1;
        });

        $modelFields         = [];
        $modelFieldsByDbname = [];

        foreach ($onlyOrmFields as $field) {
            $attribute = $field->getAttributes(ModelField::class)[0];

            /** @var ModelField $modelField */
            $modelField = $attribute->newInstance();

            $modelField->fieldName !== NULL ?: $modelField->fieldName = $field->getName();
            $modelField->modelFieldName = $field->getName();

            $modelFields[$field->getName()]              = $modelField;
            $modelFieldsByDbname[$modelField->fieldName] = $modelField;
        }

        self::$modelFields[$modelClass]          = $modelFields;
        self::$modelFieldsByDbNames[$modelClass] = $modelFieldsByDbname;

        self::extractModelRelations($modelClass);
    }

    private static function extractFieldGetters(string $modelClass): void
    {
        if (isset(self::$modelGetters[$modelClass])) {
            return;
        }

        $fieldNames     = self::getModelFields($modelClass);
        $fieldRelations = self::getModelRelations($modelClass);

        $fieldGetters = [];

        $normalizer = new SnakeCaseToCamelCaseNormalizer();
        foreach ($fieldNames as $fieldName => $modelField) {
            $fieldGetters[$fieldName] = 'get' . $normalizer->normalize($fieldName);
        }
        foreach ($fieldRelations as $fieldName => $relation) {
            $fieldGetters[$fieldName] = 'get' . $normalizer->normalize($fieldName);
        }

        self::$modelGetters[$modelClass] = $fieldGetters;
    }

    private static function extractFieldSetters(string $modelClass): void
    {
        if (isset(self::$modelSetters[$modelClass])) {
            return;
        }
        $fieldNames     = self::getModelFields($modelClass);
        $fieldRelations = self::getModelRelations($modelClass);

        $fieldSetters = [];

        $normalizer = new SnakeCaseToCamelCaseNormalizer();
        foreach ($fieldNames as $fieldName => $modelField) {
            $fieldSetters[$fieldName] = 'set' . $normalizer->normalize($fieldName);
        }
        foreach ($fieldRelations as $fieldName => $relation) {
            $fieldSetters[$fieldName] = 'set' . $normalizer->normalize($fieldName);
        }
        self::$modelSetters[$modelClass] = $fieldSetters;
    }

    private static function extractModelRelations(string $modelClass): void
    {
        if (isset(self::$modelRelations[$modelClass])) {
            return;
        }
        $reflectionClass = new \ReflectionClass($modelClass);

        $privateFields = $reflectionClass->getProperties(\ReflectionProperty::IS_PRIVATE);

        $onlyOrmFields                     = array_filter($privateFields, function (\ReflectionProperty $property) {
            $fieldAttributes = $property->getAttributes(AbstractRelation::class, \ReflectionAttribute::IS_INSTANCEOF);

            return count($fieldAttributes) === 1;
        });
        self::$modelRelations[$modelClass] = [];

        foreach ($onlyOrmFields as $field) {
            /** @var AbstractRelation $relation */
            $relation = $field->getAttributes(AbstractRelation::class, \ReflectionAttribute::IS_INSTANCEOF)[0]->newInstance();

            $relation->fromModelClass = $modelClass;

            self::$modelRelations[$modelClass][$field->getName()] = $relation;

            $relation->initDataByReflectionProperty($field);
            $relation->initEventListeners();
        }
    }
}