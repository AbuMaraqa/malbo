<?php

namespace Modules\Store\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;

/**
 * The reset mail of a storefront account: Laravel's, with a link that opens the storefront's reset
 * page in the language the buyer asked in (staff keep Fortify's link to /reset-password).
 */
class CustomerResetPassword extends ResetPassword
{
    public function __construct(string $token, public readonly string $urlLocale)
    {
        parent::__construct($token);
    }

    /**
     * @param  mixed  $notifiable
     */
    protected function resetUrl($notifiable): string
    {
        return route('store.account.password.reset', [
            'locale' => $this->urlLocale,
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);
    }
}
