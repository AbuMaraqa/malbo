<?php

namespace Modules\Core\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Core\Services\NotificationService;

class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $notificationService) {}

    /**
     * JSON for the bell in the header, loaded when it is opened.
     */
    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->notificationService->latest($request->user())]);
    }

    public function readAll(Request $request): RedirectResponse
    {
        $this->notificationService->markAllAsRead($request->user());

        return back();
    }

    public function read(Request $request, string $notification): RedirectResponse
    {
        $this->notificationService->markAsRead($request->user(), $notification);

        return back();
    }
}
