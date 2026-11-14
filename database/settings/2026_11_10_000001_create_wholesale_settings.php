<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * The wholesale minimum order, by value and by pieces. Null: no minimum. A merchant's own override
 * (wholesale_merchants) wins over these.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('wholesale.minimum_order_value', null);
        $this->migrator->add('wholesale.minimum_order_pieces', null);
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('wholesale.minimum_order_value');
        $this->migrator->deleteIfExists('wholesale.minimum_order_pieces');
    }
};
