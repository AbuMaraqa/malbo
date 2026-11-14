<?php

namespace Modules\Wholesale\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\URL;
use Modules\Items\Entities\Category;

/**
 * A link a merchant shares to their PDF catalog (CatalogShareService writes it). It opens without
 * login while it is signed, not expired and not revoked, and while the merchant is approved.
 */
class CatalogShare extends Model
{
    /** How long a link works. */
    public const DAYS = 7;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('wholesale.table_prefix').'catalog_shares');
    }

    protected $fillable = [
        'merchant_id',
        'token',
        'category_id',
        'locale',
        'prices',
        'expires_at',
        'revoked_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null && $this->expires_at->isFuture();
    }

    /**
     * The signed link, valid until the share expires.
     */
    public function url(): string
    {
        return URL::temporarySignedRoute('store.wholesale.catalog-pdf.shared', $this->expires_at, ['locale' => $this->locale, 'token' => $this->token]);
    }
}
