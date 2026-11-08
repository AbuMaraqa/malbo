<?php

namespace Tests\Unit\Architecture;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Modules\Core\Repositories\BaseEloquentRepository;
use Modules\Core\Repositories\Contracts\RepositoryInterface;
use Modules\Items\Entities\Category;
use Tests\DatabaseTestCase;

/**
 * The shared repository contract, exercised through a Category repository.
 */
class BaseEloquentRepositoryTest extends DatabaseTestCase
{
    private RepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new class(new Category) extends BaseEloquentRepository {};
    }

    public function test_create_find_update_delete_roundtrip(): void
    {
        $category = $this->repository->create(['name' => 'قسم', 'slug' => 'c-'.Str::random(6), 'status' => 1, 'sort_order' => 2]);

        $this->assertTrue($category->exists);
        $this->assertTrue($this->repository->find($category->id)->is($category));

        $updated = $this->repository->update($category, ['sort_order' => 5]);
        $this->assertSame(5, (int) $updated->fresh()->sort_order);

        $this->assertTrue($this->repository->delete($category));
        $this->assertNull($this->repository->find($category->id));
    }

    public function test_query_is_fresh_each_call(): void
    {
        $first = $this->repository->query();

        $this->assertInstanceOf(Builder::class, $first);
        $this->assertInstanceOf(Category::class, $first->getModel());
        $this->assertNotSame($first, $this->repository->query());
    }

    public function test_find_or_fail_throws(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->repository->findOrFail(999999999);
    }

    public function test_paginate_returns_paginator_of_models(): void
    {
        $this->repository->create(['name' => 'قسم', 'slug' => 'c-'.Str::random(6), 'status' => 1]);

        $page = $this->repository->paginate(1);

        $this->assertInstanceOf(LengthAwarePaginator::class, $page);
        $this->assertSame(1, $page->perPage());
        $this->assertInstanceOf(Category::class, $page->first());
    }
}
