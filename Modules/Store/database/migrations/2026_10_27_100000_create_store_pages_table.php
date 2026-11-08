<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The store's own pages (policies, FAQ, the owner's pages). The system pages start with the
 * starter texts in resources/page-templates; {store}, {email}, {phone} and {whatsapp} are
 * filled when a page is shown.
 */
return new class extends Migration
{
    /** key => [slug, sort, ar title, en title] */
    private const SYSTEM = [
        'shipping' => ['shipping-policy', 10, 'سياسة الشحن والتوصيل', 'Shipping & delivery'],
        'returns' => ['return-policy', 20, 'سياسة الاستبدال والاسترجاع', 'Returns & refunds'],
        'privacy' => ['privacy-policy', 30, 'سياسة الخصوصية', 'Privacy policy'],
        'terms' => ['terms', 40, 'الشروط والأحكام', 'Terms & conditions'],
        'faq' => ['faq', 50, 'الأسئلة الشائعة', 'FAQ'],
    ];

    public function up(): void
    {
        Schema::create('store_pages', function (Blueprint $table) {
            $table->id();
            $table->string('key')->nullable()->unique(); // set on system pages only
            $table->string('slug')->unique();
            $table->boolean('is_published')->default(true);
            $table->boolean('show_in_footer')->default(true);
            $table->unsignedInteger('sort_order')->default(100);
            $table->timestamps();
        });

        Schema::create('store_page_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_page_id')->constrained('store_pages')->cascadeOnDelete();
            $table->string('locale', 5)->index();
            $table->string('title');
            $table->longText('body')->nullable();
            $table->string('meta_description', 300)->nullable();
            $table->unique(['store_page_id', 'locale']);
        });

        $now = now();
        foreach (self::SYSTEM as $key => [$slug, $sort, $ar, $en]) {
            $id = DB::table('store_pages')->insertGetId([
                'key' => $key, 'slug' => $slug, 'is_published' => true, 'show_in_footer' => true,
                'sort_order' => $sort, 'created_at' => $now, 'updated_at' => $now,
            ]);

            foreach (['ar' => $ar, 'en' => $en] as $locale => $title) {
                $template = module_path('Store', "resources/page-templates/{$key}.{$locale}.html");
                DB::table('store_page_translations')->insert([
                    'store_page_id' => $id,
                    'locale' => $locale,
                    'title' => $title,
                    'body' => is_file($template) ? trim((string) file_get_contents($template)) : null,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('store_page_translations');
        Schema::dropIfExists('store_pages');
    }
};
