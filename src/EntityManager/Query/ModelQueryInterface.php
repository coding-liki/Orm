<?php
namespace CodingLiki\Orm\EntityManager\Query;

use CodingLiki\Orm\BaseModel;

interface ModelQueryInterface
{
    public function find(string $modelClass): static;

    public function byPk(int|string|array $identifier): static;

    public function where(array $where): static;

    public function orWhere(array $where): static;

    public function limit(int $limit, ?int $offset = null): static;

    public function offset(int $offset): static;

    public function orderBy(array $orderBy): static;

    /**
     * @return BaseModel[]
     */
    public function all(): array;

    public function one(): BaseModel;

    public function scalar();

    public function string(): string;

    public function int(): int;

    public function bool(): bool;

    public function float(): float;

    public function addParam(string $name, $value): string;
}

