<?php

namespace App\Infrastructures\Repository;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

abstract class Repository
{
    protected Model $model;

    public function __construct()
    {
        $this->model = $this->setModel();
    }

    /**
     * @return Model
     */
    abstract protected function setModel(): Model;

    /**
     * @return Model
     */
    public function getModel(): Model
    {
        return $this->model;
    }

    /**
     * @return Builder
     */
    public function getQuery(): Builder
    {
        return $this->model::query();
    }

    /**
     * @param int $id
     * @return Model
     */
    public function getById(int $id): Model
    {
        return $this->getQuery()->findOrFail($id);
    }

    /**
     * @param array $ids
     * @return Collection
     */
    public function getByIds(array $ids): Collection
    {
        return $this->getQuery()->whereIn('id', $ids)->get();
    }

    /**
     * @return Collection
     */
    public function getAll(): Collection
    {
        return $this->getQuery()->get();
    }

    /**
     * @param array $fields
     *
     * @return \Illuminate\Database\Eloquent\Collection|array
     */
    public function getAllWithSelectedFields(array $fields): Collection|array
    {
        return $this->getQuery()->select($fields)->get();
    }

    /**
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function updateById(int $id, array $data): bool
    {
        $model = $this->getById($id);
        return $model->update($data);
    }

    /**
     * @param Model $model
     * @param array $data
     * @return bool
     */
    public function update(Model $model, array $data): bool
    {
        return $model->update($data);
    }

    /**
     * @param array $data
     * @return Model
     */
    public function create(array $data): Model
    {
        return $this->getQuery()->create($data);
    }

    /**
     * @param int $id
     * @return bool
     */
    public function deleteById(int $id): bool
    {
        try {
            return $this->getById($id)->delete();
        } catch (\Exception $e) {
            Log::error($e->getMessage());
        }

        return false;
    }

    /**
     * @param array $data
     * @return bool
     */
    public function insert(array $data): bool
    {
        return $this->getQuery()->insert($data);
    }

    /**
     * @param Model $model
     * @return bool
     */
    public function delete(Model $model): bool
    {
        try {
            return $model->delete();
        } catch (\Exception $e) {
            Log::error($e->getMessage());
        }

        return false;
    }

    /**
     * @param Model $model
     * @return bool
     */
    public function forceDelete(Model $model): bool
    {
        try {
            return $model->forceDelete();
        } catch (\Exception $e) {
            Log::error($e->getMessage());
        }

        return false;
    }

    /**
     * @param array $data
     * @param string|null $orderBy
     * @return Model|null
     */
    public function getOneByFields(array $data, ?string $orderBy = null): ?Model
    {
        $query = $this->getQuery();
        foreach ($data as $key => $item) {
            $query->where($key, $item);
        }
        if ($orderBy !== null) {
            $query->orderBy($orderBy);
        }
        return $query->first();
    }

    /**
     * @param array $data
     * @param string|null $orderBy
     * @return Collection|null
     */
    public function getByFields(array $data, ?string $orderBy = null): ?Collection
    {
        $query = $this->getQuery();
        foreach ($data as $key => $item) {
            $query->where($key, $item);
        }
        if ($orderBy !== null) {
            $query->orderBy($orderBy);
        }
        return $query->get();
    }

    /**
     * @param array $attributes
     * @param array $values
     * @return Model|Builder
     */
    public function updateOrCreate(array $attributes, array $values = []): Model|Builder
    {
        return $this->getQuery()->updateOrCreate($attributes, $values);
    }

    /**
     * @param array $attributes
     * @param array $values
     * @return Model|Builder
     */
    public function firstOrNew(array $attributes, array $values = []): Model|Builder
    {
        return $this->getQuery()->firstOrNew($attributes, $values);
    }

    /**
     * @param array $attributes
     * @param array $values
     * @return Model|Builder
     */
    public function firstOrCreate(array $attributes, array $values = []): Model|Builder
    {
        return $this->getQuery()->firstOrCreate($attributes, $values);
    }
}
