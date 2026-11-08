<?php

namespace Modules\Store\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Core\Support\AdminFilter;
use Modules\Core\Support\AdminTable;
use Modules\Store\Entities\Member;
use Modules\Store\Repositories\Contracts\MemberRepositoryInterface;
use Modules\Store\Services\MemberService;
use Modules\Store\Support\WhatsAppOrder;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * Visitors who joined the store's members list from the home page.
 */
class MemberController extends Controller
{
    public function __construct(
        private readonly MemberRepositoryInterface $members,
        private readonly MemberService $memberService,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Store/Member/Index', [
            'table' => AdminTable::for($this->members->query())
                ->search(['name', 'phone', 'email'], null)
                ->filters(
                    AdminFilter::exact('locale'),
                    AllowedFilter::callback('has_email', fn (Builder $query, mixed $value) => filter_var($value, FILTER_VALIDATE_BOOLEAN)
                        ? $query->whereNotNull('email')
                        : $query->whereNull('email')),
                    ...AdminFilter::dateRange('created_at'),
                )
                ->sorts('name', 'created_at', 'id')
                ->rows(fn (Member $member) => [
                    'id' => $member->id,
                    'name' => $member->name,
                    'phone' => $member->phone,
                    'email' => $member->email,
                    'locale' => $member->locale,
                    'joined' => $member->created_at?->diffForHumans(),
                    'joined_at' => $member->created_at?->translatedFormat('j F Y, H:i'),
                    'whatsapp_url' => WhatsAppOrder::chat($member->phone),
                ]),
        ]);
    }

    public function destroy(int $member): RedirectResponse
    {
        /** @var Member $model */
        $model = $this->members->findOrFail($member);

        $this->memberService->delete($model);

        return back()->with('success', __('Member deleted successfully'));
    }
}
