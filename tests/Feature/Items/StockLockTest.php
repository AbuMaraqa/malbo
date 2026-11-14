<?php

namespace Tests\Feature\Items;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Items\Services\InventoryService;
use PDO;
use Tests\DatabaseTestCase;

/**
 * Inserting an order line takes a FOR KEY SHARE lock on its product (the foreign key check). A
 * stock change must not conflict with it, or two orders for the same product deadlock and one
 * buyer gets a server error with plenty of stock left.
 */
class StockLockTest extends DatabaseTestCase
{
    private const SIDE = 'pgsql_side';

    private ?int $productId = null;

    private string $table = '';

    /** @var array<string, mixed> */
    private array $connection = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = config('database.connections.pgsql');
        config(['database.connections.'.self::SIDE => $this->connection]);
        $this->table = config('items.table_prefix').'products';
    }

    public function test_a_sale_is_not_blocked_by_an_order_line_referencing_the_product(): void
    {
        $side = DB::connection(self::SIDE);
        $table = $this->table;

        // Committed, so both connections see it.
        $this->productId = $side->table($table)->insertGetId([
            'slug' => 'lock-'.Str::lower(Str::random(8)),
            'sku' => 'LOCK-'.Str::upper(Str::random(8)),
            'base_price' => 10,
            'manage_stock' => true,
            'stock_quantity' => 5,
            'low_stock_threshold' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $side->beginTransaction();

        try {
            // What PostgreSQL's foreign key check takes when another order inserts a line for it.
            $side->select("select id from {$table} where id = ? for key share", [$this->productId]);

            DB::statement("set local lock_timeout = '2s'");
            app(InventoryService::class)->sell([$this->productId => 1]);

            $this->assertSame(4, (int) DB::table($table)->where('id', $this->productId)->value('stock_quantity'));
        } finally {
            $side->rollBack();
            DB::purge(self::SIDE);
        }
    }

    protected function tearDown(): void
    {
        parent::tearDown(); // rolls the test transaction back and releases its locks

        if ($this->productId !== null) {
            $c = $this->connection;
            $pdo = new PDO("pgsql:host={$c['host']};port={$c['port']};dbname={$c['database']}", $c['username'], $c['password']);
            $pdo->prepare('delete from '.$this->table.' where id = ?')->execute([$this->productId]);
        }
    }
}
