<?php

namespace Tests\Feature\Store;

use Modules\Store\Repositories\Contracts\ContactMessageRepositoryInterface;
use Modules\Store\Repositories\ContactMessageRepository;
use Modules\Store\Services\ContactMessageService;
use Tests\DatabaseTestCase;

class ContactMessageServiceTest extends DatabaseTestCase
{
    public function test_layer_resolves(): void
    {
        $this->assertInstanceOf(ContactMessageRepository::class, app(ContactMessageRepositoryInterface::class));
        $this->assertInstanceOf(ContactMessageService::class, app(ContactMessageService::class));
    }
}
