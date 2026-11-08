<?php

namespace Modules\Core\Repositories\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * What every entity repository offers. Meaningful reads are named methods on the entity's own
 * interface; writes with business rules belong to the entity's Service, which uses these.
 */
interface RepositoryInterface
{
    /**
     * A fresh builder, for Filament tables and Spatie QueryBuilder. Read-only use.
     */
    public function query(): Builder;

    public function find(int|string $id): ?Model;

    public function findOrFail(int|string $id): Model;

    public function paginate(int $perPage = 15): LengthAwarePaginator;

    /**
     * Persists attributes only; translations are passed explicitly by the Service.
     */
    public function create(array $attributes): Model;

    public function update(Model $model, array $attributes): Model;

    public function delete(Model $model): bool;
}
