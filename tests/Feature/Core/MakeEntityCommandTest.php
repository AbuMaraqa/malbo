<?php

namespace Tests\Feature\Core;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * module:make-entity generates the repository layer of an entity (spec §5.4), against a
 * throw-away module created and removed by the test.
 */
class MakeEntityCommandTest extends TestCase
{
    private string $module;

    protected function setUp(): void
    {
        parent::setUp();

        $this->module = base_path('Modules/Zzgen');
        File::deleteDirectory($this->module);
        File::ensureDirectoryExists($this->module.'/Entities');
        File::ensureDirectoryExists($this->module.'/Providers');
        File::put($this->module.'/Entities/Widget.php', <<<'PHP'
            <?php

            namespace Modules\Zzgen\Entities;

            use Illuminate\Database\Eloquent\Model;

            class Widget extends Model {}
            PHP);
        File::put($this->module.'/Providers/ZzgenServiceProvider.php', <<<'PHP'
            <?php

            namespace Modules\Zzgen\Providers;

            use Illuminate\Support\ServiceProvider;

            class ZzgenServiceProvider extends ServiceProvider
            {
                public function register(): void
                {
                }
            }
            PHP);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->module);
        File::deleteDirectory(base_path('tests/Feature/Zzgen'));

        parent::tearDown();
    }

    public function test_generates_files_and_binding(): void
    {
        $this->artisan('module:make-entity', ['module' => 'Zzgen', 'entity' => 'Widget'])->assertExitCode(0);

        foreach ([
            'Repositories/Contracts/WidgetRepositoryInterface.php',
            'Repositories/WidgetRepository.php',
            'Services/WidgetService.php',
            'Providers/RepositoryServiceProvider.php',
        ] as $file) {
            $this->assertFileExists($this->module.'/'.$file);
            $this->assertStringContainsString('namespace Modules\\Zzgen\\', File::get($this->module.'/'.$file), $file);
        }
        $this->assertFileExists(base_path('tests/Feature/Zzgen/WidgetServiceTest.php'));

        $provider = File::get($this->module.'/Providers/RepositoryServiceProvider.php');
        $this->assertStringContainsString('WidgetRepositoryInterface::class => WidgetRepository::class', $provider);
        $this->assertStringContainsString('$this->app->register(RepositoryServiceProvider::class);', File::get($this->module.'/Providers/ZzgenServiceProvider.php'));
    }

    public function test_refuses_to_overwrite_and_does_not_duplicate_binding(): void
    {
        $this->artisan('module:make-entity', ['module' => 'Zzgen', 'entity' => 'Widget'])->assertExitCode(0);
        File::put($this->module.'/Services/WidgetService.php', '<?php // edited by hand');

        $this->artisan('module:make-entity', ['module' => 'Zzgen', 'entity' => 'Widget'])->assertExitCode(1);

        $this->assertSame('<?php // edited by hand', File::get($this->module.'/Services/WidgetService.php'));
        $this->assertSame(1, substr_count(File::get($this->module.'/Providers/RepositoryServiceProvider.php'), 'WidgetRepositoryInterface::class =>'));
        $this->assertSame(1, substr_count(File::get($this->module.'/Providers/ZzgenServiceProvider.php'), 'RepositoryServiceProvider::class'));
    }

    public function test_refuses_a_missing_module_or_model(): void
    {
        $this->artisan('module:make-entity', ['module' => 'NoSuchModule', 'entity' => 'Widget'])->assertExitCode(1);
        $this->artisan('module:make-entity', ['module' => 'Zzgen', 'entity' => 'Gadget'])->assertExitCode(1);

        $this->assertDirectoryDoesNotExist($this->module.'/Repositories');
    }

    public function test_generated_code_resolves_from_the_container(): void
    {
        $this->artisan('module:make-entity', ['module' => 'Zzgen', 'entity' => 'Widget'])->assertExitCode(0);

        $this->app->register(\Modules\Zzgen\Providers\RepositoryServiceProvider::class);

        $repository = $this->app->make(\Modules\Zzgen\Repositories\Contracts\WidgetRepositoryInterface::class);
        $this->assertInstanceOf(\Modules\Zzgen\Repositories\WidgetRepository::class, $repository);
        $this->assertInstanceOf(\Modules\Core\Repositories\Contracts\RepositoryInterface::class, $repository);
        $this->assertInstanceOf(\Modules\Zzgen\Services\WidgetService::class, $this->app->make(\Modules\Zzgen\Services\WidgetService::class));
    }
}
