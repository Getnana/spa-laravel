<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class SpaQuery
{
    private ?Builder $group = null;

    private function __construct(private Builder $query)
    {
    }

    public static function forTable(string $table): self
    {
        $table = preg_replace('/^(\w+) (\w+)$/D', '$1 as $2', $table);
        return new self(DB::table($table));
    }

    public function __clone()
    {
        $this->query = clone $this->query;
    }

    public function replaceSelect(string $columns): self
    {
        $this->query->select([]);
        return $this->select($columns);
    }

    public function select(string $columns): self
    {
        $this->query->selectRaw($columns);
        return $this;
    }

    public function selectAvg(string $column, ?string $alias = null): self
    {
        $grammar = $this->query->getGrammar();
        return $this->select('AVG('.$grammar->wrap($column).') AS '.$grammar->wrap($alias ?? $column));
    }

    public function join(string $table, string $condition, string $type): self
    {
        $table = preg_replace('/^(\w+) (\w+)$/D', '$1 as $2', $table);
        [$left, $right] = explode(' = ', $condition, 2);
        $this->query->join($table, $left, '=', $right, $type);
        return $this;
    }

    public function where(string $field, mixed $value = null): self
    {
        $query = $this->group ?? $this->query;
        if (preg_match('/^([\w.]+) IS NOT NULL$/iD', $field, $matches)) {
            $query->whereNotNull($matches[1]);
            return $this;
        }

        $operator = '=';
        if (preg_match('/^(.+) (>=|<=|!=|<>|>|<|=)$/D', $field, $matches)) {
            [, $field, $operator] = $matches;
        }

        if (preg_match('/^DATE\(([\w.]+)\)$/iD', $field, $matches)) {
            $query->whereDate($matches[1], $operator, $value);
        } else {
            $query->where($field, $operator, $value);
        }
        return $this;
    }

    public function whereIn(string $field, array $values): self
    {
        ($this->group ?? $this->query)->whereIn($field, $values);
        return $this;
    }

    public function whereNotIn(string $field, array $values): self
    {
        ($this->group ?? $this->query)->whereNotIn($field, $values);
        return $this;
    }

    public function groupStart(): self
    {
        $this->group = $this->query->forNestedWhere();
        return $this;
    }

    public function groupEnd(): self
    {
        $this->query->addNestedWhereQuery($this->group);
        $this->group = null;
        return $this;
    }

    public function like(string $field, string $term, string $side = 'both'): self
    {
        return $this->addLike($field, $term, $side, 'and');
    }

    public function orLike(string $field, string $term): self
    {
        return $this->addLike($field, $term, 'both', 'or');
    }

    private function addLike(string $field, string $term, string $side, string $boolean): self
    {
        $query = $this->group ?? $this->query;
        $column = $query->getGrammar()->wrap($field);
        $pattern = ($side === 'after' ? '' : '%').strtr($term, ['!' => '!!', '%' => '!%', '_' => '!_']).'%';
        $query->whereRaw("$column LIKE ? ESCAPE '!'", [$pattern], $boolean);
        return $this;
    }

    public function groupBy(string $columns): self
    {
        if (preg_match('/^DATE\([\w.]+\)$/iD', $columns)) {
            $this->query->groupByRaw($columns);
        } else {
            $this->query->groupBy(...preg_split('/,\s*/', $columns));
        }
        return $this;
    }

    public function limit(int $limit, int $offset = 0): self
    {
        $this->query->limit($limit)->offset($offset);
        return $this;
    }

    public function getRow(): ?object
    {
        return $this->query->first();
    }

    public function firstArray(): ?array
    {
        $row = $this->getRow();
        return $row === null ? null : (array) $row;
    }

    public function getResultArray(): array
    {
        return $this->query->get()->map(fn (object $row) => (array) $row)->all();
    }

    public function countAllResults(): int
    {
        return $this->query->count();
    }

    public function __call(string $method, array $arguments): mixed
    {
        $result = $this->query->$method(...$arguments);
        return $result === $this->query ? $this : $result;
    }
}
