<x-mail::message>
<div dir="{{ \App\Support\Locales::isRtl() ? 'rtl' : 'ltr' }}" style="text-align: start;">

# {{ __('Your cart is waiting for you') }}

{{ __('You left these items in your cart at :store. They are still here for you.', ['store' => $store]) }}

<x-mail::table>
| {{ __('Item') }} | {{ __('Quantity') }} | {{ __('Total') }} |
|:--|:--:|--:|
@foreach ($lines as $line)
| {{ str_replace('|', '/', $line['name']) }}@if ($line['options'] !== []) ({{ collect($line['options'])->pluck('value')->implode('، ') }})@endif | {{ $line['quantity'] }} | {{ $line['total_label'] }} |
@endforeach
</x-mail::table>

**{{ __('Subtotal') }}: {{ $total }}**

<x-mail::button :url="$restoreUrl">
{{ __('Back to my cart') }}
</x-mail::button>

{{ __('Prices and stock may change until you order.') }}

<small>{{ __('You get this message because you asked us to remind you about your cart.') }} [{{ __('Stop these messages') }}]({{ $unsubscribeUrl }})</small>

</div>
</x-mail::message>
