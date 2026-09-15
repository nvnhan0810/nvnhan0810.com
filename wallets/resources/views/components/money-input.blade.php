@props([
    'name' => null,
    'alpineModel' => null,
    'value' => null,
    'required' => false,
    'readonly' => false,
    'placeholder' => '0',
    'id' => null,
])

@if($alpineModel)
<input type="text" inputmode="numeric"
    {{ $attributes->merge(['class' => 'money-input']) }}
    @if($name) name="{{ $name }}" @endif
    @if($id) id="{{ $id }}" @endif
    x-init="$el.__moneyAlpine = true"
    :value="$money.format({{ $alpineModel }})"
    @input="$money.onInput($event.target, v => {{ $alpineModel }} = v)"
    @if($required) required @endif
    @if($readonly) readonly @endif
    placeholder="{{ $placeholder }}"
>
@else
<input type="text" inputmode="numeric"
    {{ $attributes->merge(['class' => 'money-input']) }}
    @if($name) name="{{ $name }}" @endif
    @if($id) id="{{ $id }}" @endif
    value="{{ $value !== null && $value !== '' ? number_format((float) $value, 0, ',', '.') : '' }}"
    @if($required) required @endif
    @if($readonly) readonly @endif
    placeholder="{{ $placeholder }}"
>
@endif
