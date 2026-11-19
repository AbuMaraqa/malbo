<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * The store's name and description, still on the template's "Be Found Online": MALBO, and a line
 * from its brand guidelines' mission. A name or description the owner wrote stays.
 */
return new class extends SettingsMigration
{
    private const OLD_NAME = 'Be Found Online';

    private const NAME = 'MALBO';

    private const OLD_DESCRIPTIONS = ['Be Found Online', 'Be Found Online Description'];

    private const DESCRIPTION = 'ملابس وأحذية شبابية تجمع بين الجودة والراحة والتصاميم العصرية، بأسعار تناسبك.';

    public function up(): void
    {
        $this->migrator->update('general.site_name', fn (string $value) => $value === self::OLD_NAME ? self::NAME : $value);
        $this->migrator->update('general.site_description', fn (string $value) => in_array($value, self::OLD_DESCRIPTIONS, true) ? self::DESCRIPTION : $value);
    }

    public function down(): void
    {
        $this->migrator->update('general.site_name', fn (string $value) => $value === self::NAME ? self::OLD_NAME : $value);
        $this->migrator->update('general.site_description', fn (string $value) => $value === self::DESCRIPTION ? self::OLD_DESCRIPTIONS[0] : $value);
    }
};
