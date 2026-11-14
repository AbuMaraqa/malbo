<x-mail::message>
<div dir="{{ \App\Support\Locales::isRtl() ? 'rtl' : 'ltr' }}" style="text-align: start;">

# {{ __(':product is back in stock', ['product' => $name]) }}

{{ __('Good news: the item you asked about can be ordered again at :store.', ['store' => $store]) }}

**{{ $name }}**@if ($options !== '') — {{ $options }}@endif


<x-mail::button :url="$url">
{{ __('Order it now') }}
</x-mail::button>

{{ __('Stock is limited and is not held for you.') }}

<small>{{ __('You get this message because you asked to be told when this item is available.') }} [{{ __('Stop these messages') }}]({{ $unsubscribeUrl }})</small>

</div>
</x-mail::message>
