<?php

namespace Modules\Store\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Repositories\BaseEloquentRepository;
use Modules\Store\Entities\ContactMessage;
use Modules\Store\Repositories\Contracts\ContactMessageRepositoryInterface;

class ContactMessageRepository extends BaseEloquentRepository implements ContactMessageRepositoryInterface
{
    public function __construct(ContactMessage $model)
    {
        parent::__construct($model);
    }

    public function inboxQuery(): Builder
    {
        return $this->query()->latest('id');
    }

    public function unreadCount(): int
    {
        return $this->query()->whereNull('read_at')->count();
    }

    public function setRead(array $ids, bool $read): int
    {
        return $this->query()
            ->whereKey($ids)
            ->when($read, fn (Builder $query) => $query->whereNull('read_at'))
            ->update(['read_at' => $read ? now() : null]);
    }
}
