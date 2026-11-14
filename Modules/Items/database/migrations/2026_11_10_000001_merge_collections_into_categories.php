<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;

/**
 * Collections and categories were the same idea in this store: every collection becomes a category.
 *
 * A collection whose slug a live category already has joins that category (its products are added
 * to it). Any other collection becomes a new root category with its names, descriptions, order,
 * image and activity log. The old /collections links redirect to the categories (Store routes).
 */
return new class extends Migration
{
    private const COLLECTION_MODEL = 'Modules\\Items\\Entities\\Collection';

    private const CATEGORY_MODEL = 'Modules\\Items\\Entities\\Category';

    public function up(): void
    {
        $prefix = config('items.table_prefix');

        if (! Schema::hasTable($prefix.'collections')) {
            return;
        }

        DB::transaction(function () use ($prefix): void {
            foreach (DB::table($prefix.'collections')->orderBy('id')->get() as $collection) {
                $categoryId = $this->categoryFor($prefix, $collection);

                $this->moveProducts($prefix, $collection->id, $categoryId);
                $this->moveImage($collection->id, $categoryId);

                DB::table(config('activitylog.table_name', 'activity_log'))
                    ->where('subject_type', self::COLLECTION_MODEL)
                    ->where('subject_id', $collection->id)
                    ->update(['subject_type' => self::CATEGORY_MODEL, 'subject_id' => $categoryId]);
            }

            DB::table('permissions')->where('name', 'like', 'Collection %')->delete();
        });

        Schema::dropIfExists($prefix.'collection_product');
        Schema::dropIfExists($prefix.'collection_translations');
        Schema::dropIfExists($prefix.'collections');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * The empty tables come back; the data stays in the categories.
     */
    public function down(): void
    {
        $prefix = config('items.table_prefix');

        Schema::create($prefix.'collections', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create($prefix.'collection_translations', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->foreignId('collection_id')->index()->constrained($prefix.'collections')->cascadeOnDelete();
            $table->string('locale', 5)->index();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unique(['collection_id', 'locale']);
        });

        Schema::create($prefix.'collection_product', function (Blueprint $table) use ($prefix) {
            $table->foreignId('collection_id')->constrained($prefix.'collections')->cascadeOnDelete();
            $table->foreignId('product_id')->index()->constrained($prefix.'products')->cascadeOnDelete();
            $table->integer('sort_order')->default(0);
            $table->primary(['collection_id', 'product_id']);
        });
    }

    /** The live category with the collection's slug, or a new root category made from it. */
    private function categoryFor(string $prefix, object $collection): int
    {
        $existing = DB::table($prefix.'categories')->where('slug', $collection->slug)->first();

        if ($existing !== null && $existing->deleted_at === null) {
            return (int) $existing->id;
        }

        // A deleted category keeps its slug: the new one takes a free one.
        $slug = $collection->slug;
        for ($n = 2; DB::table($prefix.'categories')->where('slug', $slug)->exists(); $n++) {
            $slug = "{$collection->slug}-{$n}";
        }

        $now = now();
        $id = DB::table($prefix.'categories')->insertGetId([
            'slug' => $slug,
            'status' => $collection->is_active ? 1 : 0,
            'sort_order' => $collection->sort_order,
            'parent_id' => null,
            'created_by' => $collection->created_by,
            'created_at' => $collection->created_at ?? $now,
            'updated_at' => $now,
        ]);

        foreach (DB::table($prefix.'collection_translations')->where('collection_id', $collection->id)->get() as $translation) {
            DB::table($prefix.'category_translations')->insert([
                'category_id' => $id,
                'locale' => $translation->locale,
                'name' => $translation->name,
                'description' => $translation->description,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return (int) $id;
    }

    private function moveProducts(string $prefix, int $collectionId, int $categoryId): void
    {
        $productIds = DB::table($prefix.'collection_product')->where('collection_id', $collectionId)->orderBy('sort_order')->pluck('product_id');
        $already = DB::table($prefix.'products_categories')->where('category_id', $categoryId)->pluck('product_id')->all();
        $now = now();

        $rows = $productIds->reject(fn ($id) => in_array($id, $already, false))
            ->map(fn ($id) => ['product_id' => $id, 'category_id' => $categoryId, 'created_at' => $now, 'updated_at' => $now])
            ->values()
            ->all();

        if ($rows !== []) {
            DB::table($prefix.'products_categories')->insert($rows);
        }
    }

    /** The image moves over, unless the category has its own; the file stays where it is. */
    private function moveImage(int $collectionId, int $categoryId): void
    {
        $image = DB::table('media')->where('model_type', self::COLLECTION_MODEL)->where('model_id', $collectionId)->first();

        if ($image === null) {
            return;
        }

        $hasOwn = DB::table('media')->where('model_type', self::CATEGORY_MODEL)->where('model_id', $categoryId)->where('collection_name', 'category')->exists();

        if ($hasOwn) {
            // The default path generator keeps a media item's files in a folder named by its id.
            Storage::disk($image->disk)->deleteDirectory((string) $image->id);
            DB::table('media')->where('id', $image->id)->delete();

            return;
        }

        DB::table('media')->where('id', $image->id)->update([
            'model_type' => self::CATEGORY_MODEL,
            'model_id' => $categoryId,
            'collection_name' => 'category',
        ]);
    }
};
