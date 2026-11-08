<?php

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;

/**
 * Controllers of entities that moved to the repository layer must not talk to Eloquent themselves:
 * reads go through named repository methods or `query()`, writes through services. A guard, not a
 * guarantee: it reads the files line by line, so a write on a variable that holds a repository or
 * service under another name is reported, and code built dynamically is not seen.
 *
 * ENTITIES and FILES are generated: an entity counts as moved when it has a repository, and every
 * controller in `Modules/<M>/Http/Controllers` is checked.
 * What is known and owed is in KNOWN: a new line in any other file fails, and so does a file that
 * no longer needs its entry.
 */
class LayeringTest extends TestCase
{
    /** Repositories of something that is not one entity. */
    private const NOT_ENTITIES = ['BaseEloquent', 'Dashboard', 'Setting'];

    /**
     * Files whose violations are known. Each is owed a service, see the reason.
     *
     * @var array<string, string>
     */
    private const KNOWN = [
        // Placing an order and changing its status write here: to move to OrderService, which the
        // Items pages introduce, with the same server-side prices (tests/Feature/Api/OrderApiTest).
        'Modules/Items/Http/Controllers/Api/V1/OrderController.php' => 'Writes belong in OrderService',
        // syncOrders() reads Order::query(): to move to OrderRepositoryInterface.
        'Modules/Items/Http/Controllers/Api/V1/SyncController.php' => 'Read belongs in OrderRepository',
        // getAttributes() reads Attribute::query(). This controller belongs to the Items worker.
        'Modules/Items/Http/Controllers/Api/V1/ProductController.php' => 'Attribute lookup needs a repository method',
    ];

    public function test_controllers_do_not_query_eloquent_directly(): void
    {
        $violations = [];

        foreach (self::files() as $file) {
            foreach (self::scan((string) file_get_contents(self::root().'/'.$file)) as $hit) {
                $violations[$file][] = $hit;
            }
        }

        $unknown = array_diff_key($violations, self::KNOWN);
        $lines = [];
        foreach ($unknown as $file => $hits) {
            foreach ($hits as $hit) {
                $lines[] = "{$file}:{$hit}";
            }
        }

        $this->assertSame([], $lines, "Direct Eloquent use in controllers:\n".implode("\n", $lines));
    }

    public function test_known_violations_are_still_real(): void
    {
        foreach (array_keys(self::KNOWN) as $file) {
            $path = self::root().'/'.$file;

            $this->assertFileExists($path, "{$file} is listed in KNOWN but no longer exists.");
            $this->assertNotSame([], self::scan((string) file_get_contents($path)), "{$file} no longer needs its KNOWN entry: remove it.");
        }
    }

    public function test_the_lists_are_generated_from_the_tree(): void
    {
        $files = self::files();

        $this->assertContains('Modules/Core/Http/Controllers/Admin/CurrencyController.php', $files);
        $this->assertContains('Modules/Items/Http/Controllers/Api/V1/ProductController.php', $files);

        foreach (['Product', 'Category', 'Page', 'ContactMessage', 'Member', 'Currency', 'User', 'Branch', 'City'] as $entity) {
            $this->assertContains($entity, self::entities());
        }
        $this->assertNotContains('BaseEloquent', self::entities());
    }

    public function test_guard_catches_a_violation(): void
    {
        $code = <<<'PHP'
            <?php
            $products = Product::query()->get();
            $record->update(['status' => 1]);
            $this->service->update($record, []);
            $this->products->delete($record);
            ->unique(Product::class, 'slug')
            DB::table('items_products')->get();
            PHP;

        $this->assertSame([
            '2 Product::query(',
            '3 ->update(',
            '7 DB::table(',
        ], self::scan($code));
    }

    public function test_guard_catches_any_static_call_and_relation_writes(): void
    {
        $code = <<<'PHP'
            <?php
            $tag = Product::whereTranslation('name', $name)->first();
            $count = Category::count();
            $product->categories()->sync([1]);
            $product->tags()->attach(1);
            $product->variations()->create([]);
            $product->increment('views');
            PHP;

        $this->assertSame([
            '2 Product::whereTranslation(',
            '3 Category::count(',
            '4 ->sync(',
            '5 ->attach(',
            '6 ->create(',
            '7 ->increment(',
        ], self::scan($code));
    }

    public function test_guard_allows_services_repositories_and_storage(): void
    {
        $code = <<<'PHP'
            <?php
            $this->brandService->update($brand, []);
            $this->orders->delete($order);
            $this->productRepository->create([]);
            $userRepository->create([]);
            app(ProductService::class)->delete($product);
            Storage::disk('public')->delete($path);
            $request->user()?->currentAccessToken()?->delete();
            // $product->delete();
            $this->service
                ->update($record, []);
            ->unique(Product::class, 'slug')
            PHP;

        $this->assertSame([], self::scan($code));
    }

    private static function root(): string
    {
        return dirname(__DIR__, 3);
    }

    /**
     * @return list<string> every controller, relative to the project root
     */
    private static function files(): array
    {
        $root = self::root();
        $finder = Finder::create()->files()->name('*.php')->in([
            ...glob($root.'/Modules/*/Http/Controllers', GLOB_ONLYDIR),
        ]);

        $files = [];
        foreach ($finder as $file) {
            $files[] = str_replace('\\', '/', substr($file->getRealPath(), strlen(realpath($root)) + 1));
        }
        sort($files);

        return $files;
    }

    /**
     * @return list<string> class names of the entities with a repository
     */
    private static function entities(): array
    {
        $entities = array_map(
            fn (string $file) => basename($file, 'Repository.php'),
            glob(self::root().'/Modules/*/Repositories/*Repository.php'),
        );

        return array_values(array_diff($entities, self::NOT_ENTITIES));
    }

    /**
     * @return array<int, string> "line pattern" for each hit
     */
    private static function scan(string $code): array
    {
        $entities = implode('|', self::entities());
        // Any static call on a migrated entity except ::class.
        $static = "/\\b(?:{$entities})::(?!class\\b)\\w+\\s*\\(/";
        // Writes, also through relations, on anything but an injected service/repository or a disk.
        $write = '/->(?:save|saveQuietly|update|updateQuietly|delete|forceDelete|restore|create|createMany|forceCreate|sync|syncWithoutDetaching|attach|detach|increment|decrement|insert|upsert)\(/';
        $allowedReceiver = '/(?:\$(?:this->)?\w*(?:[Ss]ervice|[Rr]epository|products|categories|orders)|app\([\\\\\w]+(?:Service|RepositoryInterface)::class\)|Storage::disk\([^)]*\)|currentAccessToken\(\)\??)\s*$/';

        $hits = [];
        $previous = '';
        foreach (preg_split('/\R/', $code) as $index => $line) {
            if (str_starts_with(ltrim($line), '//')) {
                continue; // commented-out code does not run
            }

            if (preg_match_all($static, $line, $matches)) {
                foreach ($matches[0] as $match) {
                    $hits[] = ($index + 1).' '.$match;
                }
            }

            if (preg_match_all($write, $line, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as [$match, $offset]) {
                    $receiver = substr($line, 0, $offset);
                    // A chain split over lines: the receiver is the end of the previous line.
                    if (trim($receiver) === '') {
                        $receiver = rtrim($previous);
                    }
                    if (! preg_match($allowedReceiver, $receiver)) {
                        $hits[] = ($index + 1).' '.$match;
                    }
                }
            }

            if (preg_match_all('/\bDB::table\(/', $line, $matches)) {
                foreach ($matches[0] as $match) {
                    $hits[] = ($index + 1).' '.$match;
                }
            }

            if (trim($line) !== '') {
                $previous = $line;
            }
        }

        return $hits;
    }
}
