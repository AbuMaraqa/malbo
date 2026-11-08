<?php

namespace App\Http\Middleware;

use App\Support\FrontendTranslations;
use App\Support\Locales;
use App\Support\Realtime;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Middleware;
use Modules\Core\Services\SidebarService;
use Modules\Core\Support\DashboardTheme;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    /**
     * The section and locale are part of the version: moving between admin and storefront, or
     * switching language, forces a full load so `once` props are never stale.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request).'-'.self::section($request).'-'.app()->getLocale();
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $section = self::section($request);

        Inertia::disableSsr(! in_array($section, ['storefront', 'platform'], true));

        return [
            ...parent::share($request),
            'locale' => fn () => app()->getLocale(),
            'dir' => fn () => Locales::isRtl() ? 'rtl' : 'ltr',
            'locales' => fn () => collect(config('app.locales'))
                ->map(fn (array $locale, string $code) => ['code' => $code, 'native' => $locale['native'], 'flag' => $locale['flag'], 'dir' => $locale['dir']])
                ->values(),
            'flash' => fn () => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
            'translations' => Inertia::once(fn () => FrontendTranslations::for($section)),
            'ziggy' => Inertia::once(fn () => (new Ziggy($section === 'admin' ? null : str_replace('platform-admin', 'platform', $section)))->toArray()),
            ...($section === 'admin' ? $this->adminProps($request) : []),
            ...(in_array($section, ['platform', 'platform-admin'], true) ? $this->platformProps($request) : []),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function adminProps(Request $request): array
    {
        return [
            'auth' => fn () => $request->user() ? [
                'user' => [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'avatar' => $request->user()->avatar_url,
                ],
                'permissions' => $request->user()->getAllPermissions()->pluck('name'),
            ] : ['user' => null, 'permissions' => []],
            'sidebar' => fn () => $request->user() ? app(SidebarService::class)->all() : [],
            'store' => fn () => tenancy()->initialized ? [
                'id' => tenant()->getTenantKey(),
                'name' => tenant()->name[app()->getLocale()] ?? tenant()->getTenantKey(),
            ] : null,
            'dashboardTheme' => fn () => tenancy()->initialized ? [
                'css' => app(DashboardTheme::class)->css(),
                'fontsUrl' => app(DashboardTheme::class)->fontsUrl(),
            ] : null,
            'unreadNotifications' => fn () => $request->user()?->unreadNotifications()->count() ?? 0,
            'realtime' => fn () => Realtime::client($request->user()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function platformProps(Request $request): array
    {
        return [
            'appName' => fn () => config('app.name'),
            // url(), not route(): stay on the central host the admin signed in on.
            'platformAdmin' => fn () => ($admin = $request->user('platform')) ? [
                'name' => $admin->name,
                'logoutUrl' => url('/'.app()->getLocale().'/platform/logout'),
            ] : null,
        ];
    }

    /**
     * Which part of the site this request belongs to. Decides SSR, routes and texts sent.
     */
    public static function section(Request $request): string
    {
        $name = $request->route()?->getName() ?? '';

        if (! tenancy()->initialized) {
            // central.platform.* on the first central domain, central.alias1.platform.* on the others.
            return preg_match('/^central\.(alias\d+\.)?platform\./', $name) === 1 ? 'platform-admin' : 'platform';
        }

        if ($name === 'home' || str_starts_with($name, 'store.')) {
            return 'storefront';
        }

        return 'admin';
    }
}
