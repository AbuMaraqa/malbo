<?php

namespace Tests\Feature\Wholesale;

use Illuminate\Validation\ValidationException;
use Modules\Core\Entities\User;
use Modules\Wholesale\Enums\MerchantStatus;
use Modules\Wholesale\Services\MerchantService;

/**
 * pending → approved | rejected, approved ↔ suspended; each change logged with its note and author,
 * the merchant notified; a merchant never reaches the admin.
 */
class MerchantStatusTest extends WholesaleTestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->staff(['Merchant View List', 'Merchant Update']);
    }

    public function test_transitions_follow_the_rules(): void
    {
        $allowed = [
            'pending' => ['approved', 'rejected'],
            'approved' => ['suspended'],
            'suspended' => ['approved'],
            'rejected' => [],
        ];

        foreach (MerchantStatus::cases() as $from) {
            foreach (MerchantStatus::cases() as $to) {
                $this->assertSame(in_array($to->value, $allowed[$from->value], true), $from->canTransitionTo($to), "{$from->value} → {$to->value}");
            }
        }

        $merchant = $this->merchant(MerchantStatus::REJECTED);

        try {
            app(MerchantService::class)->changeStatus($merchant, MerchantStatus::APPROVED, null, $this->admin->id);
            $this->fail('A rejected merchant was approved.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('status', $e->errors());
        }

        $this->assertSame(MerchantStatus::REJECTED, $merchant->fresh()->status);
    }

    public function test_each_change_is_logged_with_its_note_and_author(): void
    {
        $merchant = $this->merchant(MerchantStatus::PENDING);
        $service = app(MerchantService::class);

        $service->changeStatus($merchant, MerchantStatus::APPROVED, 'أوراق سليمة', $this->admin->id);
        $service->changeStatus($merchant, MerchantStatus::SUSPENDED, 'تأخر في الدفع', $this->admin->id);
        $service->changeStatus($merchant, MerchantStatus::APPROVED, null, $this->admin->id);

        $merchant->refresh();
        $this->assertSame(MerchantStatus::APPROVED, $merchant->status);
        $this->assertNotNull($merchant->approved_at);
        $this->assertSame([
            ['suspended', 'approved', null],
            ['approved', 'suspended', 'تأخر في الدفع'],
            ['pending', 'approved', 'أوراق سليمة'],
        ], $merchant->statusChanges->map(fn ($change) => [$change->from_status->value, $change->to_status->value, $change->note])->all());
        $this->assertSame([$this->admin->id], $merchant->statusChanges->pluck('created_by')->unique()->values()->all());
    }

    public function test_reject_and_suspend_need_a_note(): void
    {
        $merchant = $this->merchant(MerchantStatus::PENDING);

        $this->expectException(ValidationException::class);

        app(MerchantService::class)->changeStatus($merchant, MerchantStatus::REJECTED, '  ', $this->admin->id);
    }

    public function test_the_merchant_is_notified(): void
    {
        $merchant = $this->merchant(MerchantStatus::PENDING);

        app(MerchantService::class)->changeStatus($merchant, MerchantStatus::APPROVED, null, $this->admin->id);

        $notification = $merchant->user->notifications()->sole();
        $this->assertStringContainsString(MerchantStatus::APPROVED->getLabel(), $notification->data['title']);
        $this->assertSame(route('store.wholesale.status'), $notification->data['url']);
    }

    public function test_only_a_rejection_or_suspension_note_reaches_the_merchant(): void
    {
        $merchant = $this->merchant(MerchantStatus::PENDING);
        $service = app(MerchantService::class);

        $service->changeStatus($merchant, MerchantStatus::APPROVED, 'ملاحظة داخلية للموظفين', $this->admin->id);
        $service->changeStatus($merchant, MerchantStatus::SUSPENDED, 'تأخر في الدفع', $this->admin->id);

        $messages = $merchant->user->notifications()->orderBy('created_at')->get()->map(fn ($notification) => $notification->data['message'])->implode(' | ');

        $this->assertStringNotContainsString('ملاحظة داخلية', $messages);
        $this->assertStringContainsString('تأخر في الدفع', $messages);
        // Staff still see the approval note in the history.
        $this->assertSame('ملاحظة داخلية للموظفين', $merchant->statusChanges()->where('to_status', 'approved')->value('note'));
    }

    public function test_a_merchant_is_kept_out_of_the_admin(): void
    {
        $merchant = $this->merchant();

        $this->actingAs($merchant->user)->get($this->siteUrl('/ar/admin'))->assertRedirect(route('home'));
        $this->actingAs($merchant->user)->get($this->siteUrl('/ar/admin/wholesale/merchants'))->assertRedirect(route('home'));
    }
}
