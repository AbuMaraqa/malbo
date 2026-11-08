<?php

namespace Modules\Core\Repositories;

use Illuminate\Support\Collection;
use Modules\Core\Entities\AppVersion;
use Modules\Core\Entities\Settings;
use Modules\Core\Repositories\Contracts\SettingRepositoryInterface;

class SettingRepository extends BaseEloquentRepository implements SettingRepositoryInterface
{
    public function __construct(Settings $model)
    {
        parent::__construct($model);
    }

    public function logoHolder(): Settings
    {
        /** @var Settings */
        return $this->query()->with('media')->orderBy('id')->firstOrFail();
    }

    public function appVersions(): Collection
    {
        return AppVersion::query()->orderBy('id')->get()->toBase();
    }

    public function saveAppVersion(array $version): void
    {
        AppVersion::query()->updateOrCreate(
            ['platform' => $version['platform']],
            [
                'min_version' => $version['min_version'],
                'latest_version' => $version['latest_version'],
                'store_url' => $version['store_url'],
                'maintenance_mode' => (bool) ($version['maintenance_mode'] ?? false),
                'message' => $version['message'] ?? null,
            ],
        );
    }

    public function deleteAppVersionsExcept(array $platforms): void
    {
        AppVersion::query()->whereNotIn('platform', $platforms)->delete();
    }
}
