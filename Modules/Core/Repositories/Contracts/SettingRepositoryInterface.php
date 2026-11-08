<?php

namespace Modules\Core\Repositories\Contracts;

use Illuminate\Support\Collection;
use Modules\Core\Entities\AppVersion;
use Modules\Core\Entities\Settings;

interface SettingRepositoryInterface extends RepositoryInterface
{
    /**
     * The row of the settings table that holds the site logo.
     */
    public function logoHolder(): Settings;

    /**
     * @return Collection<int, AppVersion>
     */
    public function appVersions(): Collection;

    /**
     * @param  array<string, mixed>  $version
     */
    public function saveAppVersion(array $version): void;

    /**
     * @param  list<string>  $platforms
     */
    public function deleteAppVersionsExcept(array $platforms): void;
}
