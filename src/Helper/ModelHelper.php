<?php

namespace CodingLiki\Orm\Helper;

use CodingLiki\Orm\Attributes\ModelField;
use CodingLiki\Orm\BaseModel;
use CodingLiki\Orm\Normalizer\SnakeCaseToCamelCaseNormalizer;

class ModelHelper
{
    /**
     * @var array<string,<string, ModelField>>
     */
    private static array $modelFields = [];
    private static array $modelFieldsByDbNames = [];
    private static array $modelGetters = [];
    private static array $modelSetters = [];

    /**
     * @param string $modelClass
     * @return array<string, ModelField>
     */
    public static function getModelFields(string $modelClass): array
    {

        isset(self::$modelFields[$modelClass]) ?: self::extractModelFields($modelClass);

        return self::$modelFields[$modelClass];
    }

    /**
     * @param string $modelClass
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


    public static function getModelFieldSetters(string $modelClass): array
    {
        isset(self::$modelSetters[$modelClass]) ?: self::extractFieldSetters($modelClass);

        return self::$modelSetters[$modelClass];
    }

    public static function entitiesAreEqual(BaseModel $a, BaseModel $b): bool
    {
        if ($a::class !== $b::class) {
            return false;
        }

        foreach (self::getModelFieldGetters($a::class) as $getter) {
            if ($a->$getter() !== $b->$getter()) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param string $modelClass
     * @throws \ReflectionException
     */
    private static function extractModelFields(string $modelClass): void
    {
        $reflectionClass = new \ReflectionClass($modelClass);

        $privateFields = $reflectionClass->getProperties(\ReflectionProperty::IS_PRIVATE);


        $onlyOrmFields = array_filter($privateFields, function (\ReflectionProperty $property) {
            $fieldAttributes = $property->getAttributes(ModelField::class);
            return count($fieldAttributes) === 1;
        });

        $modelFields = [];
        $modelFieldsByDbname = [];

        foreach ($onlyOrmFields as $field) {
            $attribute = $field->getAttributes(ModelField::class)[0];

            /** @var ModelField $modelField */
            $modelField = $attribute->newInstance();

            $modelField->fieldName !== null ?: $modelField->fieldName = $field->getName();
            $modelField->modelFieldName = $field->getName();

            $modelFields[$field->getName()] = $modelField;
            $modelFieldsByDbname[$modelField->fieldName] = $modelField;

        }

        self::$modelFields[$modelClass] = $modelFields;
        self::$modelFieldsByDbNames[$modelClass] = $modelFieldsByDbname;

    }

    private static function extractFieldGetters(string $modelClass): void
    {
        $fieldNames = self::getModelFields($modelClass);

        $fieldGetters = [];

        $normalizer = new SnakeCaseToCamelCaseNormalizer();
        foreach ($fieldNames as $fieldName => $modelField) {
            $fieldGetters[$fieldName] = 'get' . $normalizer->normalize($fieldName);
        }

        self::$modelGetters[$modelClass] = $fieldGetters;
    }

    private static function extractFieldSetters(string $modelClass): void
    {
        $fieldNames = self::getModelFields($modelClass);

        $fieldSetters = [];

        $normalizer = new SnakeCaseToCamelCaseNormalizer();
        foreach ($fieldNames as $fieldName => $modelField) {
            $fieldSetters[$fieldName] = 'set' . $normalizer->normalize($fieldName);
        }

        self::$modelSetters[$modelClass] = $fieldSetters;
    }
}