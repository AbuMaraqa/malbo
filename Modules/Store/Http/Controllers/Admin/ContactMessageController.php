<?php

namespace Modules\Store\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Core\Support\AdminFilter;
use Modules\Core\Support\AdminTable;
use Modules\Store\Entities\ContactMessage;
use Modules\Store\Repositories\Contracts\ContactMessageRepositoryInterface;
use Modules\Store\Services\ContactMessageService;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * The inbox of messages buyers sent from the storefront's contact page. Opening one marks it read.
 */
class ContactMessageController extends Controller
{
    public function __construct(
        private readonly ContactMessageRepositoryInterface $contactMessages,
        private readonly ContactMessageService $contactMessageService,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Store/ContactMessage/Index', [
            'table' => AdminTable::for($this->contactMessages->query())
                ->search(['name', 'phone', 'email', 'message'], null)
                ->filters(
                    AllowedFilter::callback('read', fn (Builder $query, mixed $value) => filter_var($value, FILTER_VALIDATE_BOOLEAN)
                        ? $query->whereNotNull('read_at')
                        : $query->whereNull('read_at')),
                    AdminFilter::text('subject'),
                    AdminFilter::exact('locale'),
                    AllowedFilter::callback('has_phone', fn (Builder $query, mixed $value) => filter_var($value, FILTER_VALIDATE_BOOLEAN)
                        ? $query->whereNotNull('phone')
                        : $query->whereNull('phone')),
                    AllowedFilter::callback('has_email', fn (Builder $query, mixed $value) => filter_var($value, FILTER_VALIDATE_BOOLEAN)
                        ? $query->whereNotNull('email')
                        : $query->whereNull('email')),
                    ...AdminFilter::dateRange('created_at'),
                )
                ->sorts('name', 'created_at', 'id')
                ->rows(fn (ContactMessage $message) => [
                    'id' => $message->id,
                    'name' => $message->name,
                    'phone' => $message->phone,
                    'email' => $message->email,
                    'subject' => $message->subject,
                    'message' => $message->message,
                    'is_read' => $message->isRead(),
                    'received' => $message->created_at?->diffForHumans(),
                    'received_at' => $message->created_at?->translatedFormat('j F Y, H:i'),
                ]),
        ]);
    }

    public function open(int $message): RedirectResponse
    {
        /** @var ContactMessage $model */
        $model = $this->contactMessages->findOrFail($message);

        $this->contactMessageService->markRead($model);

        return back();
    }

    public function unread(int $message): RedirectResponse
    {
        /** @var ContactMessage $model */
        $model = $this->contactMessages->findOrFail($message);

        $this->contactMessageService->markUnread($model);

        return back();
    }

    public function readMany(Request $request): RedirectResponse
    {
        $ids = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']])['ids'];

        $this->contactMessageService->markManyRead($ids);

        return back();
    }

    public function destroy(int $message): RedirectResponse
    {
        /** @var ContactMessage $model */
        $model = $this->contactMessages->findOrFail($message);

        $this->contactMessageService->delete($model);

        return back()->with('success', __('Message deleted successfully'));
    }
}
