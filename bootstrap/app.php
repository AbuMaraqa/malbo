<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withBroadcasting(__DIR__.'/../routes/channels.php')
    ->withMiddleware(function (Middleware $middleware): void {

        $middleware->trustProxies(at: '*');

        $middleware->web(append: [
            \Modules\Core\Http\Middleware\SetRouteLocale::class,
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Guests go to /login (staff and customers sign in there).
        $middleware->redirectGuestsTo(fn () => route('login'));

        $middleware->alias([
            'staff' => \App\Http\Middleware\EnsureStaff::class,
            'api.localize' => \Modules\Core\Http\Middleware\ApiLocalization::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
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
