<?php

namespace Modules\Core\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

/**
 * Turns permission names like `Product View List` into groups and labels for the roles page.
 */
final class PermissionLabels
{
    /** Longest first: `Category View List` must not end up as `Category View` + `List`. */
    private const ACTIONS = [
        'View Details', 'Details List', 'View List', 'Report List', 'Branch Pricings', 'CompanyApprove', 'UniversityApprove',
        'Create', 'Update', 'Delete', 'View', 'Info', 'Details', 'Submit', 'Sync', 'Print', 'Invoice', 'List',
    ];

    private const ACTION_WEIGHTS = [
        'View List' => 10, 'List' => 11, 'Create' => 20, 'View' => 30, 'Info' => 40, 'View Details' => 50, 'Details' => 51,
        'Details List' => 52, 'Update' => 60, 'Delete' => 70, 'Submit' => 80, 'Report List' => 90, 'Print' => 100, 'Invoice' => 110, 'Sync' => 120,
    ];

    private const AR_MODULES = [
        'Addon' => 'الإضافات',
        'Attribute' => 'الخصائص',
        'Branch' => 'الفروع',
        'Brand' => 'العلامات التجارية',
        'Category' => 'التصنيفات',
        'City' => 'المدن',
        'Country' => 'الدول',
        'Currency' => 'العملات',
        'Customer' => 'العملاء',
        'District' => 'المناطق',
        'General Settings' => 'الإعدادات العامة',
        'GeoLocation' => 'المواقع الجغرافية',
        'Governorate' => 'المحافظات',
        'Label' => 'الوسوم المرئية',
        'Offer' => 'العروض',
        'Order' => 'الطلبات',
        'Product' => 'المنتجات',
        'Roles And Permissions' => 'الأدوار والصلاحيات',
        'Setting' => 'الإعدادات',
        'Settings' => 'الإعدادات',
        'Sync System Data' => 'مزامنة بيانات النظام',
        'Tag' => 'الوسوم',
        'User' => 'المستخدمون',
    ];

    private const AR_ACTIONS = [
        'Branch Pricings' => 'تسعير الفروع',
        'CompanyApprove' => 'موافقة الشركة',
        'Create' => 'إضافة',
        'Delete' => 'حذف',
        'Details' => 'تفاصيل',
        'Details List' => 'قائمة التفاصيل',
        'Info' => 'معلومات',
        'Invoice' => 'فاتورة',
        'List' => 'قائمة',
        'Print' => 'طباعة',
        'Report List' => 'قائمة التقارير',
        'Submit' => 'تسليم',
        'Sync' => 'مزامنة',
        'UniversityApprove' => 'موافقة الجامعة',
        'Update' => 'تعديل',
        'View' => 'عرض',
        'View Details' => 'عرض التفاصيل',
        'View List' => 'عرض القائمة',
    ];

    /**
     * Every permission grouped by feature, for the role form.
     *
     * @param  Collection<int, Permission>  $permissions
     * @return list<array{key: string, label: string, permissions: list<array{id: int, label: string}>}>
     */
    public static function groups(Collection $permissions): array
    {
        return $permissions
            ->groupBy(fn (Permission $permission) => self::module($permission))
            ->map(fn (Collection $group, string $module) => [
                'key' => Str::slug($module, '_'),
                'label' => self::moduleLabel($module),
                'permissions' => self::sorted($group)
                    ->map(fn (Permission $permission) => ['id' => $permission->id, 'label' => self::label($permission->name)])
                    ->values()
                    ->all(),
            ])
            ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    /**
     * What a role can do, by feature.
     *
     * @param  Collection<int, Permission>  $permissions
     * @return array{total: int, group_count: int, groups: list<array{label: string, count: int, actions: list<string>}>}
     */
    public static function summary(Collection $permissions): array
    {
        $groups = $permissions
            ->groupBy(fn (Permission $permission) => self::module($permission))
            ->map(fn (Collection $group, string $module) => [
                'label' => self::moduleLabel($module),
                'count' => $group->count(),
                'actions' => self::sorted($group)
                    ->map(fn (Permission $permission) => self::actionLabel($permission->name))
                    ->values()
                    ->all(),
            ])
            ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        return [
            'total' => $permissions->count(),
            'group_count' => $groups->count(),
            'groups' => $groups->all(),
        ];
    }

    /**
     * @param  Collection<int, Permission>  $permissions
     * @return Collection<int, Permission>
     */
    private static function sorted(Collection $permissions): Collection
    {
        return $permissions->sortBy(fn (Permission $permission) => sprintf(
            '%03d-%s',
            self::ACTION_WEIGHTS[self::split($permission->name)[1] ?? ''] ?? 999,
            $permission->name,
        ));
    }

    private static function module(Permission $permission): string
    {
        return self::split($permission->name)[0] ?: ($permission->module_name ?: 'Other');
    }

    /**
     * @return array{0: string, 1: string|null} the feature and the action
     */
    private static function split(string $permission): array
    {
        foreach (self::ACTIONS as $action) {
            if (Str::endsWith($permission, ' '.$action)) {
                return [trim(Str::beforeLast($permission, ' '.$action)), $action];
            }
        }

        return [$permission, null];
    }

    public static function label(string $permission): string
    {
        if (! self::arabic()) {
            return Str::headline($permission);
        }

        [$module, $action] = self::split($permission);
        $moduleLabel = self::moduleLabel($module);

        return $action ? $moduleLabel.' - '.(self::AR_ACTIONS[$action] ?? Str::headline($action)) : $moduleLabel;
    }

    private static function actionLabel(string $permission): string
    {
        [, $action] = self::split($permission);

        if (! $action) {
            return self::label($permission);
        }

        return self::arabic() ? (self::AR_ACTIONS[$action] ?? Str::headline($action)) : Str::headline($action);
    }

    private static function moduleLabel(string $module): string
    {
        $module = (string) preg_replace('/\s+Management$/', '', trim($module));

        return self::arabic() ? (self::AR_MODULES[$module] ?? Str::headline($module)) : Str::headline($module);
    }

    private static function arabic(): bool
    {
        return str_starts_with(app()->getLocale(), 'ar');
    }
}
