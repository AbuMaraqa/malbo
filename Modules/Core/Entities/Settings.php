<?php

namespace Modules\Core\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Http\UploadedFile;
use Modules\Core\Settings\GeneralSettings;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

// use Modules\Core\Database\Factories\SettingsFactory;

class Settings extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $table = 'settings';

    protected $fillable = ['id', 'name', 'group', 'payload'];

    public $timestamps = false;

    // تحميل صف الإعدادات مرة واحدة لكل طلب (الهيدر والسايدبار يستخدمانه معاً)
    public static function current(): ?self
    {
        return once(fn () => static::query()->with('media')->first());
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('logo')->useDisk('media')->singleFile();
    }

    public function handleLogoUpload($logo): void
    {
        if (is_array($logo) && !empty($logo)) {
            $logo = reset($logo);
        }

        if ($logo instanceof \Illuminate\Http\UploadedFile) {
            $this->addLogo($logo);
        }
    }

    public function addLogo($file)
    {
        if (
            !$file instanceof \Illuminate\Http\UploadedFile
        ) {
            return;
        }

        $this->clearMediaCollection('logo');

        $media = $this
            ->addMedia($file)
            ->usingFileName($file->getClientOriginalName())
            ->toMediaCollection('logo', 'media');

        // اختياري: احفظ رابط الشعار في GeneralSettings
        $settings = app(GeneralSettings::class);
        $settings->site_logo_url = $media->getUrl();
        $settings->save();
    }

    public const DEFAULT_LOGO = 'images/defaults/logo.png';

    public function getLogoUrl(): ?string
    {
        return $this->getFirstMediaUrl('logo') ?: asset(self::DEFAULT_LOGO);
    }

    // مسار الملف على القرص (لملفات PDF) مع الرجوع للشعار الافتراضي
    public function getLogoPath(): string
    {
        return $this->getFirstMediaPath('logo') ?: public_path(self::DEFAULT_LOGO);
    }
}
