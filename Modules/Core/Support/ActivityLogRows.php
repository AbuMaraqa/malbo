<?php

namespace Modules\Core\Support;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

/**
 * Activity log entries as the admin's activity page shows them: the record's kind in words, the
 * event, who caused it and every changed attribute, old → new.
 *
 * - An entry is shown only to someone who may see its kind of record: each logged model maps to
 *   its module's View List permission. A kind not listed here is shown to nobody.
 * - A value whose name holds password, token, secret, recovery, api key, private key, credential
 *   or pin is masked at any depth (a JSON string is decoded first) before it leaves the server.
 */
final class ActivityLogRows
{
    public const MASK = '••••••';

    private const SECRET = '/password|token|secret|recovery|api_?key|private_?key|credential|pin\b/i';

    /**
     * The models that keep an activity log: class => [label text, the permission that shows it].
     * Class names as strings: Core does not load the other modules' classes for a label.
     */
    private const SUBJECTS = [
        'Modules\Items\Entities\Order' => ['Order', 'Order View List'],
        'Modules\Items\Entities\OrderItem' => ['Order item', 'Order View List'],
        'Modules\Items\Entities\Product' => ['Product', 'Product View List'],
        'Modules\Items\Entities\Category' => ['Category', 'Category View List'],
        'Modules\Items\Entities\Brand' => ['Brand', 'Brand View List'],
        'Modules\Items\Entities\Tag' => ['Tag', 'Tag View List'],
        'Modules\Items\Entities\Label' => ['Label', 'Label View List'],
        'Modules\Items\Entities\Attribute' => ['Attribute', 'Attribute View List'],
        'Modules\Items\Entities\AttributeValue' => ['Attribute value', 'Attribute View List'],
        'Modules\Items\Entities\Addon' => ['Addon', 'Addon View List'],
        'Modules\Items\Entities\AddonOption' => ['Addon option', 'Addon View List'],
        'Modules\Items\Entities\Offer' => ['Offer', 'Offer View List'],
        'Modules\Items\Entities\BranchPrice' => ['Branch price', 'Product Branch Pricings'],
        'Modules\Items\Entities\ReturnRequest' => ['Return request', 'Return Request View List'],
        'Modules\Items\Entities\Shipment' => ['Shipment', 'Shipment View List'],
        'Modules\Branch\Entities\Branch' => ['Branch', 'Branch View List'],
        'Modules\Branch\Entities\BranchWorkingHour' => ['Branch working hours', 'Branch View List'],
        'Modules\Core\Entities\Currency' => ['Currency', 'Currency View List'],
        'Modules\Core\Entities\User' => ['User', 'User View List'],
        'Modules\GeoLocation\Entities\Country' => ['Country', 'Country View List'],
        'Modules\GeoLocation\Entities\Governorate' => ['Governorate', 'Governorate View List'],
        'Modules\GeoLocation\Entities\City' => ['City', 'City View List'],
        'Modules\GeoLocation\Entities\District' => ['District', 'District View List'],
        'Modules\Store\Entities\ContactMessage' => ['Contact message', 'Contact Message View List'],
        'Modules\Store\Entities\Member' => ['Member', 'Member View List'],
        'Modules\Store\Entities\Page' => ['Page', 'Page View List'],
        'Modules\Store\Entities\ShippingZone' => ['Shipping zone', 'Shipping Zone View List'],
    ];

    private const EVENTS = ['created' => 'Created', 'updated' => 'Updated', 'deleted' => 'Deleted'];

    /**
     * The kinds of record whose entries $user may see.
     *
     * @return list<string> class names
     */
    public static function visibleTypes(Authorizable $user): array
    {
        return array_keys(array_filter(self::SUBJECTS, fn (array $subject) => $user->can($subject[1])));
    }

    /**
     * The record kinds $user may filter by.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function subjectOptions(Authorizable $user): array
    {
        return collect(self::visibleTypes($user))
            ->map(fn (string $class) => ['value' => $class, 'label' => __(self::SUBJECTS[$class][0])])
            ->sortBy('label')
            ->values()
            ->all();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function eventOptions(): array
    {
        return array_map(fn (string $event, string $label) => ['value' => $event, 'label' => __($label)], array_keys(self::EVENTS), self::EVENTS);
    }

    public static function subjectLabel(?string $type): string
    {
        if ($type === null || $type === '') {
            return '—';
        }

        return isset(self::SUBJECTS[$type]) ? __(self::SUBJECTS[$type][0]) : Str::headline(class_basename($type));
    }

    /**
     * @return array<string, mixed>
     */
    public static function row(Activity $activity): array
    {
        $event = (string) $activity->event;

        return [
            'id' => $activity->id,
            'event' => $event,
            'event_label' => isset(self::EVENTS[$event]) ? __(self::EVENTS[$event]) : Str::headline($event ?: (string) $activity->description),
            'subject' => self::subjectLabel($activity->subject_type),
            'subject_id' => $activity->subject_id,
            'causer' => $activity->causer?->getAttribute('name'),
            'created_at' => $activity->created_at?->format('Y-m-d H:i'),
            'changes' => self::changes($activity),
        ];
    }

    /**
     * Every attribute in the entry, its old and new value as text (null when absent).
     *
     * @return list<array{key: string, old: ?string, new: ?string}>
     */
    public static function changes(Activity $activity): array
    {
        $new = (array) ($activity->attribute_changes?->get('attributes') ?? []);
        $old = (array) ($activity->attribute_changes?->get('old') ?? []);
        $rows = [];

        foreach (array_unique([...array_keys($new), ...array_keys($old)]) as $key) {
            $secret = preg_match(self::SECRET, (string) $key) === 1;

            $rows[] = [
                'key' => (string) $key,
                'old' => array_key_exists($key, $old) ? ($secret ? self::MASK : self::text($old[$key])) : null,
                'new' => array_key_exists($key, $new) ? ($secret ? self::MASK : self::text($new[$key])) : null,
            ];
        }

        return $rows;
    }

    private static function text(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        // A JSON column logged as its text: read it, so a secret inside is masked too.
        $value = self::decoded($value);

        if (is_array($value) || is_object($value)) {
            return Str::limit((string) json_encode(self::mask((array) $value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 500);
        }

        return Str::limit((string) $value, 500);
    }

    /**
     * @param  array<mixed>  $values
     * @return array<mixed>
     */
    private static function mask(array $values): array
    {
        foreach ($values as $key => $value) {
            $value = self::decoded($value);

            if (is_string($key) && preg_match(self::SECRET, $key) === 1) {
                $values[$key] = self::MASK;
            } elseif (is_array($value) || is_object($value)) {
                $values[$key] = self::mask((array) $value);
            }
        }

        return $values;
    }

    /**
     * A string holding a JSON object or array, decoded; anything else as it is.
     */
    private static function decoded(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $trimmed = ltrim($value);

        if ($trimmed === '' || ! in_array($trimmed[0], ['{', '['], true)) {
            return $value;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : $value;
    }
}
