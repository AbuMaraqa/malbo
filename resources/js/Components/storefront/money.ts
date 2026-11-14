const amount = new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

/** An amount as the server writes prices (Modules\Store\Support\Price::format): "1,250.50 ₪". */
export function formatMoney(value: number): string {
    return `${amount.format(value)} ₪`;
}
