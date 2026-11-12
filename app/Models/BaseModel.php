<?php

namespace App\Models;

use App\Support\SpaQuery;
use App\Support\SpaValidation;
use Illuminate\Pagination\LengthAwarePaginator;

abstract class BaseModel
{
    protected $primaryKey = 'id';
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $deletedField = 'deleted_at';
    protected $useSoftDeletes = false;
    protected $validationRules = [];
    protected $beforeInsert = [];
    protected $beforeUpdate = [];

    public LengthAwarePaginator $pager;
    private ?SpaQuery $query = null;
    private array $validationErrors = [];
    private bool $skipValidation = false;

    public function builder(): SpaQuery
    {
        $query = SpaQuery::forTable($this->table);
        if ($this->useSoftDeletes) {
            $query->where($this->table.'.'.$this->deletedField, null);
        }
        return $query;
    }

    private function takeQuery(): SpaQuery
    {
        $query = $this->query ?? $this->builder();
        $this->query = null;
        return $query;
    }

    public function __call(string $method, array $arguments): mixed
    {
        $query = $this->query ??= $this->builder();
        $result = $query->$method(...$arguments);
        if ($result === $query) {
            return $this;
        }
        $this->query = null;
        return $result;
    }

    public function find(mixed $id): ?array
    {
        return $this->takeQuery()->where($this->table.'.'.$this->primaryKey, $id)->firstArray();
    }

    public function first(): ?array
    {
        return $this->takeQuery()->firstArray();
    }

    public function findAll(): array
    {
        return $this->takeQuery()->getResultArray();
    }

    public function countAllResults(): int
    {
        return $this->takeQuery()->countAllResults();
    }

    public function paginate(int $perPage, ?int $page = null): array
    {
        $page ??= (int) request()->query('page', 1);
        $perPage = max(1, min(200, $perPage));
        $this->pager = $this->takeQuery()->paginate($perPage, ['*'], 'page', max(1, $page));
        return array_map(fn (object $row) => (array) $row, $this->pager->items());
    }

    public function errors(): array
    {
        return $this->validationErrors;
    }

    public function skipValidation(bool $skip): self
    {
        $this->skipValidation = $skip;
        return $this;
    }

    private function prepareWrite(array $data, mixed $id = null): ?array
    {
        $data = array_intersect_key($data, array_flip($this->allowedFields));
        $rules = $id === null ? $this->validationRules : array_intersect_key($this->validationRules, $data);
        $this->validationErrors = [];
        if (!$this->skipValidation && $rules) {
            $validator = SpaValidation::make($data, $rules, [], $id);
            if ($validator->fails()) {
                $this->validationErrors = $validator->errors()->toArray();
                return null;
            }
        }

        foreach ($id === null ? $this->beforeInsert : $this->beforeUpdate as $callback) {
            $data = $this->$callback(['data' => $data])['data'];
        }

        if ($this->useTimestamps) {
            $now = date('Y-m-d H:i:s');
            if ($id === null && $this->createdField !== '') {
                $data[$this->createdField] ??= $now;
            }
            if ($this->updatedField !== '') {
                $data[$this->updatedField] ??= $now;
            }
        }

        return $data;
    }

    public function insert(array $data): int|false
    {
        $data = $this->prepareWrite($data);
        return $data === null ? false : $this->builder()->insertGetId($data, $this->primaryKey);
    }

    public function update(mixed $id, array $data): bool
    {
        $query = $this->takeQuery();
        $data = $this->prepareWrite($data, $id);
        if ($data === null) {
            return false;
        }
        if ($data !== []) {
            $query->where($this->primaryKey, $id)->update($data);
        }
        return true;
    }

    public function delete(mixed $id): bool
    {
        $query = $this->takeQuery()->where($this->primaryKey, $id);
        if ($this->useSoftDeletes) {
            $query->update([$this->deletedField => date('Y-m-d H:i:s')]);
        } else {
            $query->delete();
        }
        return true;
    }
}
