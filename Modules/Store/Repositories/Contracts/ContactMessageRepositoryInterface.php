<?php

namespace Modules\Store\Repositories\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Repositories\Contracts\RepositoryInterface;

interface ContactMessageRepositoryInterface extends RepositoryInterface
{
    /**
     * The admin inbox table's query: newest first (the table may filter it).
     */
    public function inboxQuery(): Builder;

    public function unreadCount(): int;

    /**
     * Mark the given messages read or unread in one statement.
     *
     * @param  list<int>  $ids
     */
    public function setRead(array $ids, bool $read): int;
}
