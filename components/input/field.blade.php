@aware(['atomFormMarker' => null])

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
   Which fields are marked comes from the form that holds the field (`<atom:form marker>`, published by the tag compiler as `atomFormMarker`: see
   Services\FormMarker) and from `config('atom.form.marker')` outside one, or in a Livewire child, which a form's mode does not reach. `required`
   (the default) draws the asterisk on a required field; `optional` draws none and adds the same "(optional)" note to a field that is not required,
   unless the label already ends with it. The note is one translation key, "(optional)". A label that is HTML the host chose always gets it, and a
   label slot is the host's own markup and is left as it is. A `marker` of its own on this tag (`required` or `optional`) wins over the form's. */
$mode = \Jiannius\Atom\Services\FormMarker::normalise($marker) ?? \Jiannius\Atom\Services\FormMarker::resolve($atomFormMarker);
$markOptional = $mode === 'optional';
$optional = null;
$labelText = $label ? t($label) : null;
$note = t('(optional)');

/* what a typed note may look like: "(optional)", the translated key, or the older key "optional" in parentheses (a host that translated it) */
$noteForms = '(?:\(optional\)|'.preg_quote($note, '/').'|'.preg_quote('('.t('optional').')', '/').')';

if (is_string($labelText)) {
    $labelText = trim($labelText);

    if (preg_match('/^(.*?)\s*('.$noteForms.')$/iu', $labelText, $found) && trim($found[1]) !== '') {
        [$labelText, $optional] = [$found[1], $found[2]];
    }
}

/* a label that is only the note, or ends with it, is not marked again */
$endsWithNote = is_string($labelText) ? preg_match('/'.$noteForms.'$/iu', $labelText) === 1 : false;

if ($markOptional && !$required && !$optional && !$endsWithNote && $labelText) {
    $optional = $note;
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
