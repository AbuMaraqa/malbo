<?php

namespace Modules\Core\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

/**
 * Generates the repository layer of a module entity: interface, Eloquent repository, service,
 * a test, and the binding in the module's RepositoryServiceProvider (created and registered
 * from the module's main provider when missing). Never overwrites a file.
 */
class MakeEntityCommand extends Command
{
    protected $signature = 'module:make-entity
        {module : The module name, e.g. Items}
        {entity : The model class name in Modules/<module>/Entities, e.g. Brand}';

    protected $description = 'Generate the repository interface, repository, service and binding of a module entity';

    public function handle(Filesystem $files): int
    {
        $module = Str::studly((string) $this->argument('module'));
        $entity = Str::studly((string) $this->argument('entity'));
        $root = base_path("Modules/{$module}");

        if (! $files->isDirectory($root)) {
            $this->components->error("Module [{$module}] does not exist.");

            return self::FAILURE;
        }
        if (! $files->exists("{$root}/Entities/{$entity}.php")) {
            $this->components->error("Model [Modules\\{$module}\\Entities\\{$entity}] does not exist.");

            return self::FAILURE;
        }

        $targets = [
            "{$root}/Repositories/Contracts/{$entity}RepositoryInterface.php" => 'repository-interface',
            "{$root}/Repositories/{$entity}Repository.php" => 'repository',
            "{$root}/Services/{$entity}Service.php" => 'service',
            base_path("tests/Feature/{$module}/{$entity}ServiceTest.php") => 'test',
        ];

        $existing = array_filter(array_keys($targets), fn (string $path) => $files->exists($path));
        if ($existing !== []) {
            foreach ($existing as $path) {
                $this->components->error('Already exists: '.str_replace(base_path().DIRECTORY_SEPARATOR, '', $path));
            }

            return self::FAILURE;
        }

        $replace = ['{{module}}' => $module, '{{entity}}' => $entity, '{{variable}}' => Str::camel(Str::plural($entity))];

        foreach ($targets as $path => $stub) {
            $files->ensureDirectoryExists(dirname($path));
            $files->put($path, $this->render($files, $stub, $replace));
            $this->components->info('Created '.str_replace(base_path().DIRECTORY_SEPARATOR, '', $path));
        }

        $this->bind($files, $root, $module, $entity, $replace);

        return self::SUCCESS;
    }

    private function bind(Filesystem $files, string $root, string $module, string $entity, array $replace): void
    {
        $provider = "{$root}/Providers/RepositoryServiceProvider.php";

        if (! $files->exists($provider)) {
            $files->put($provider, $this->render($files, 'provider', $replace));
        }

        $code = $files->get($provider);
        $interface = "Modules\\{$module}\\Repositories\\Contracts\\{$entity}RepositoryInterface";
        $repository = "Modules\\{$module}\\Repositories\\{$entity}Repository";

        if (! str_contains($code, "{$entity}RepositoryInterface::class =>")) {
            $code = preg_replace(
                '/(protected array \$repositories = \[\r?\n(?:.*\r?\n)*?)(    \];)/',
                "$1        {$entity}RepositoryInterface::class => {$entity}Repository::class,\n$2",
                $code,
                1,
            );
            foreach (["use {$interface};", "use {$repository};"] as $use) {
                if (! str_contains($code, $use)) {
                    $code = preg_replace('/^(use Illuminate\\\\Support\\\\ServiceProvider;)$/m', "$1\n{$use}", $code, 1);
                }
            }
            $files->put($provider, $code);
        }

        $main = "{$root}/Providers/{$module}ServiceProvider.php";
        if ($files->exists($main) && ! str_contains($files->get($main), 'RepositoryServiceProvider::class')) {
            $code = preg_replace(
                '/(public function register\(\): void\s*\{)/',
                "$1\n        \$this->app->register(RepositoryServiceProvider::class);",
                $files->get($main),
                1,
                $count,
            );
            if ($count === 1) {
                $files->put($main, $code);
            } else {
                $this->components->warn("Register Modules\\{$module}\\Providers\\RepositoryServiceProvider from {$module}ServiceProvider::register() yourself.");
            }
        }
    }

    private function render(Filesystem $files, string $stub, array $replace): string
    {
        return strtr($files->get(__DIR__."/stubs/{$stub}.stub"), $replace);
    }
}
