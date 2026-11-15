@aware(['marker' => null])

@props([
    'label' => null,
    'inline' => false,
    'caption' => null,
    'required' => false,
    'error' => null,
    'for' => null,
    'labelId' => null,
    'marker' => null,
])

@php
/* "(optional)" at the end of a label is a note, not part of the name: it prints in the muted tone at the regular weight. The
   words stay in the label, so the field's accessible name is unchanged.
   `marker` (read from the form that holds the field, `config('atom.form.marker')` outside one; only `optional` or `required` count) says which
   fields are marked: `required` (the default) draws the asterisk on a required one; `optional` draws none and adds the same "(optional)" note
   to a field that is not required, unless the label already ends with it. A label slot is the host's own markup and is left as it is. */
$marker = in_array($marker, ['optional', 'required'], true) ? $marker : config('atom.form.marker', 'required');
$markOptional = $marker === 'optional';
$optional = null;
$labelText = $label ? t($label) : null;

if (is_string($labelText)) {
    if (preg_match('/^(.*?)\s*(\((?:optional|'.preg_quote(t('optional'), '/').')\))$/iu', $labelText, $found) && trim($found[1]) !== '') {
        [$labelText, $optional] = [$found[1], $found[2]];
    }
}

if ($markOptional && !$required && !$optional && $labelText) {
    $optional = '('.t('optional').')';
}
@endphp

<div {{ $attributes->class(['group/field', 'grid md:grid-cols-5' => $inline]) }} data-atom-input-field>
    @if (isset($label) && $label instanceof \Illuminate\View\ComponentSlot)
        <div class="{{ $inline ? 'py-2 md:col-span-2' : 'pb-2' }}">
            {{ $label }}
        </div>
    @elseif ($label)
        <div class="{{ $inline ? 'py-2 md:col-span-2' : 'pb-2' }}">
            <atom:label :for="$for" :id="$labelId">
                <div class="inline-flex items-center justify-center gap-2">
                    <span>{{ $labelText }}@if ($optional) <span class="font-normal text-fg-muted" data-atom-label-optional>{{ $optional }}</span>@endif</span>

                    @if ($required && !$markOptional)
                        <atom:icon.asterisk class="text-danger-fg shrink-0 size-3"/>
                    @endif
                </div>

                <x-slot:actions>
                    {{ $actions ?? '' }}
                </x-slot:actions>
            </atom:label>
        </div>
    @endif

    <div class="space-y-2 {{ $inline ? 'md:col-span-3' : '' }}">
        {{ $slot }}

        @if ($error)
            <atom:error>{{ t($error) }}</atom:error>
        @endif

        @if ($caption)
            <atom:caption>{{ t($caption) }}</atom:caption>
        @endif
    </div>
</div>
