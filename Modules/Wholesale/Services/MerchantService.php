<?php

namespace Modules\Wholesale\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Modules\Core\Notifications\GeneralNotification;
use Modules\Store\Support\Phone;
use Modules\Wholesale\Entities\Merchant;
use Modules\Wholesale\Enums\MerchantStatus;
use Modules\Wholesale\Repositories\Contracts\MerchantRepositoryInterface;
use Throwable;

/**
 * The staff's changes to a merchant: its profile, tier, sales rep and minimum order (`update`), and
 * where its application stands (`changeStatus`). A status changes on the locked row, only along the
 * allowed paths, with a line in its history (who, when, why); the merchant is told after the commit.
 */
class MerchantService
{
    public function __construct(private readonly MerchantRepositoryInterface $merchants) {}

    /**
     * @param  array<string, mixed>  $data  shop_name, owner_name, city_id, phone, commercial_register, shop_address, tier_id, sales_rep_id, minimum_order_value, minimum_order_pieces; credit_enabled and credit_limit when sent
     *
     * @throws ValidationException on `sales_rep_id` when the user is not staff
     */
    public function update(Merchant $merchant, array $data): Merchant
    {
        $rep = filled($data['sales_rep_id'] ?? null) ? (int) $data['sales_rep_id'] : null;

        if ($rep !== null && ! $this->merchants->isStaff($rep)) {
            throw ValidationException::withMessages(['sales_rep_id' => __('A sales rep must be a staff member.')]);
        }

        return $this->merchants->update($merchant, [
            'shop_name' => trim((string) $data['shop_name']),
            'owner_name' => trim((string) $data['owner_name']),
            'city_id' => filled($data['city_id'] ?? null) ? (int) $data['city_id'] : null,
            'phone' => Phone::canonical((string) $data['phone']),
            'commercial_register' => trim((string) $data['commercial_register']),
            'shop_address' => filled($data['shop_address'] ?? null) ? trim((string) $data['shop_address']) : null,
            'tier_id' => filled($data['tier_id'] ?? null) ? (int) $data['tier_id'] : null,
            'sales_rep_id' => $rep,
            'minimum_order_value' => filled($data['minimum_order_value'] ?? null) ? round((float) $data['minimum_order_value'], 2) : null,
            'minimum_order_pieces' => filled($data['minimum_order_pieces'] ?? null) ? (int) $data['minimum_order_pieces'] : null,
            ...(array_key_exists('credit_enabled', $data) ? [
                'credit_enabled' => (bool) $data['credit_enabled'],
                'credit_limit' => filled($data['credit_limit'] ?? null) ? round((float) $data['credit_limit'], 2) : null,
            ] : []),
        ]);
    }

    /**
     * @throws ValidationException on `status` (not allowed from here) or `note` (rejecting or suspending without one)
     */
    public function changeStatus(Merchant $merchant, MerchantStatus $to, ?string $note, int $userId): Merchant
    {
        $note = trim((string) $note) === '' ? null : trim((string) $note);

        if ($note === null && $to->needsNote()) {
            throw ValidationException::withMessages(['note' => __('Write the reason: the merchant is told.')]);
        }

        return DB::transaction(function () use ($merchant, $to, $note, $userId): Merchant {
            $locked = $this->merchants->lock($merchant->id);
            $from = $locked->status;

            if (! $from->canTransitionTo($to)) {
                throw ValidationException::withMessages(['status' => __('A merchant cannot go from :from to :to.', [
                    'from' => $from->getLabel(),
                    'to' => $to->getLabel(),
                ])]);
            }

            $locked->status = $to;

            if ($to === MerchantStatus::APPROVED && $locked->approved_at === null) {
                $locked->approved_at = Carbon::now();
            }

            $locked->save();

            $locked->statusChanges()->create([
                'from_status' => $from,
                'to_status' => $to,
                'note' => $note,
                'created_by' => $userId,
            ]);

            DB::afterCommit(fn () => $this->notify($locked, $to, $note));

            return $locked;
        });
    }

    /**
     * Never fatal: the status has changed whether or not the message can go.
     */
    private function notify(Merchant $merchant, MerchantStatus $status, ?string $note): void
    {
        try {
            $merchant->loadMissing('user');

            $message = match ($status) {
                MerchantStatus::APPROVED => __('Your wholesale account is approved. You can now see wholesale prices and order.'),
                MerchantStatus::REJECTED => __('Your wholesale application was not approved.'),
                MerchantStatus::SUSPENDED => __('Your wholesale account is suspended.'),
                MerchantStatus::PENDING => __('Your wholesale application is pending review.'),
            };

            $merchant->user?->notify(new GeneralNotification(
                title: __('Wholesale account: :status', ['status' => $status->getLabel()]),
                // Only the reason for a rejection or a suspension is the merchant's to read; any
                // other note stays in the staff's history.
                message: $note && $status->needsNote() ? $message.' '.$note : $message,
                url: route('store.wholesale.status', ['locale' => app()->getLocale()]),
            ));
        } catch (Throwable $e) {
            Log::warning('Merchant status notification not sent: '.$e->getMessage(), ['merchant' => $merchant->id]);
        }
    }
}
