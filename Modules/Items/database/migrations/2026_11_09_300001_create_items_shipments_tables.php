<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Shipments: an order on its way with a courier (its key in config/couriers.php), a tracking
 * number, a status and a cost; and every status the shipment reached. An order has at most one
 * shipment that is not cancelled: a partial unique index holds it (PostgreSQL), and
 * ShipmentService checks it on the locked order.
 */
return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('items.table_prefix');

        Schema::create($prefix.'shipments', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->foreignId('order_id')->index()->constrained($prefix.'orders')->cascadeOnDelete();
            $table->string('courier', 40)->index();
            $table->string('tracking_number', 100)->nullable();
            $table->string('status', 30)->index();
            $table->decimal('cost', 10, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->foreignId('created_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        DB::statement("CREATE UNIQUE INDEX {$prefix}shipments_one_active ON {$prefix}shipments (order_id) WHERE status <> 'cancelled'");

        Schema::create($prefix.'shipment_events', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->foreignId('shipment_id')->index()->constrained($prefix.'shipments')->cascadeOnDelete();
            $table->string('status', 30);
            $table->text('note')->nullable();
            $table->timestamp('at');
            $table->foreignId('created_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $prefix = config('items.table_prefix');

        Schema::dropIfExists($prefix.'shipment_events');
        Schema::dropIfExists($prefix.'shipments');
    }
};
