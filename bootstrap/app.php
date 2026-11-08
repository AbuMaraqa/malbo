<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;
use Modules\Core\Traits\ApiResponse;
use Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedOnDomainException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Platform host only: once per central domain. URLs are generated for the first one.
            foreach (config('tenancy.central_domains') as $i => $domain) {
                Route::middleware('web')
                    ->domain($domain)
                    ->name($i === 0 ? 'central.' : "central.alias{$i}.")
                    ->group(base_path('routes/central.php'));
            }

            // Every host: `/`, `/{locale}` (see InitializeTenancyIfTenantHost).
            Route::middleware('web')->group(base_path('routes/universal.php'));

            // Store hosts only. Module routes are wrapped the same way by their RouteServiceProviders.
            Route::middleware(['web', 'tenant'])->group(base_path('routes/web.php'));
            Route::middleware(['api', 'tenant'])->prefix('api')->group(base_path('routes/api.php'));
        },
    )
    // /broadcasting/auth authorizes store users for store channels.
    ->withBroadcasting(__DIR__.'/../routes/channels.php', ['middleware' => ['web', 'tenant']])
    ->withMiddleware(function (Middleware $middleware): void {

        $middleware->trustProxies(at: '*');

        $middleware->web(append: [
            \Modules\Core\Http\Middleware\SetRouteLocale::class,
            \App\Http\Middleware\ScopeSessionToStore::class,
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Sessions are bound to their store after StartSession and before `auth` reads the login
        // (tenancy middleware already runs first, see TenancyServiceProvider).
        $middleware->appendToPriorityList(
            \Illuminate\Session\Middleware\StartSession::class,
            \App\Http\Middleware\ScopeSessionToStore::class,
        );

        // Guests on the platform host go to the platform sign-in,
        // on the same host; guests of a store go to its /login. `auth` runs before SetRouteLocale,
        // so the locale is read from the route here.
        $middleware->redirectGuestsTo(fn (\Illuminate\Http\Request $request) => tenancy()->initialized
            ? route('login')
            : \App\Http\Controllers\Platform\LoginController::loginUrl($request->route()?->parameter('locale')));

        // Store routes: identify the store by domain, refuse platform hosts, refuse inactive stores.
        $middleware->group('tenant', [
            \Stancl\Tenancy\Middleware\InitializeTenancyByDomain::class,
            \Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains::class,
            'store.active',
        ]);

        $middleware->alias([
            'tenancy.universal'       => \App\Http\Middleware\InitializeTenancyIfTenantHost::class,
            'store.active'            => \App\Http\Middleware\EnsureStoreIsActive::class,
            'staff'                   => \App\Http\Middleware\EnsureStaff::class,


            'api.localize'            =>\Modules\Core\Http\Middleware\ApiLocalization::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // A host that is neither central nor a store domain: plain 404 (JSON for api/* below).
        $exceptions->map(fn (TenantCouldNotBeIdentifiedOnDomainException $e) => new NotFoundHttpException($e->getMessage(), $e));

        // HTML requests: errors render inside the app, in the current language, with no stack
        // trace outside debug mode. 422 and redirects keep their normal handling.
        $exceptions->respond(function (\Symfony\Component\HttpFoundation\Response $response, \Throwable $e, \Illuminate\Http\Request $request) {
            $status = $response->getStatusCode();

            if ($request->expectsJson() || $request->is('api/*') || ! in_array($status, [403, 404, 419, 429, 500, 503], true)
                || (config('app.debug') && $status === 500)) {
                return $response;
            }

            return \Inertia\Inertia::render('Errors/Error', ['status' => $status, 'home' => url('/')])
                ->toResponse($request)
                ->setStatusCode($status);
        });

        $exceptions->render(function (\Throwable $e, \Illuminate\Http\Request $request) {

            if (($request->expectsJson() || $request->is('api/*'))) {

                $options = JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE;

                // 1. أخطاء المصادقة (Authentication - 401)
                if ($e instanceof \Illuminate\Auth\AuthenticationException) {
                    return response()->json([
                        'status'  => false,
                        'message' => 'Unauthenticated.', // أو 'انتهت الجلسة'
                        'data'    => null
                    ], 401, [], $options);
                }

                // 2. أخطاء الصلاحيات (Authorization/Permissions - 403)
                if ($e instanceof \Illuminate\Auth\Access\AuthorizationException ||
                    $e instanceof \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException) {
                    return response()->json([
                        'status'  => false,
                        'message' => 'This action is unauthorized.',
                        'data'    => null
                    ], 403, [], $options);
                }

                // 3. أخطاء التحقق من البيانات (Validation - 422)
                if ($e instanceof \Illuminate\Validation\ValidationException) {
                    return response()->json([
                        'status'  => false,
                        'message' => 'Validation Error',
                        'data'    => $e->errors() // إرجاع مصفوفة الأخطاء هنا
                    ], 422, [], $options);
                }

                // 4. أخطاء عدم وجود البيانات أو الروابط (Not Found - 404)
                if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException ||
                    $e instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {
                    return response()->json([
                        'status'  => false,
                        'message' => 'Resource not found.',
                        'data'    => null
                    ], 404, [], $options);
                }

                // 5. باقي أخطاء HTTP بكودها (429 كثرة الطلبات، 405، abort(…))
                if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
                    $code = $e->getStatusCode();

                    return response()->json([
                        'status'  => false,
                        'message' => $e->getMessage() ?: (\Symfony\Component\HttpFoundation\Response::$statusTexts[$code] ?? 'Error'),
                        'data'    => null
                    ], $code, $e->getHeaders(), $options);
                }

                // 6. أي أخطاء أخرى بالسيرفر (Server Error - 500)
                // في بيئة التطوير (local) نعرض تفاصيل الخطأ الحقيقي، وفي الإنتاج (production) نعرض رسالة عامة
                return response()->json([
                    'status'  => false,
                    'message' => config('app.debug') ? $e->getMessage() : 'Server Error.',
                    'data'    => config('app.debug') ? [
                        'file' => $e->getFile(),
                        'line' => $e->getLine()
                    ] : null
                ], 500, [], $options);
            }

        });
    })->create();
