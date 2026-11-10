@props([
    'label' => 'Upload',
    'variant' => null,
    'size' => null,
    'ariaLabelledby' => null,
])

@php
// The real <input type="file"> is hidden, so the trigger button is the only thing a
// screen reader can reach. On its own it announces "Upload" — identical for every file
// field on a form. Pointing at its own id first and the field's label second reads as
// "Upload Attachment" without composing two translated strings by hand. Derived from the
// anchor it was given, so it stays stable across a Livewire morph like every other id.
$triggerId = $ariaLabelledby ? $ariaLabelledby.'-trigger' : null;

/* Content beside the button (a size or type hint, a link to the rules). A slot made of false @if blocks is not content. */
$hasEnd = isset($end) && $end->hasActualContent();

/* 'self', 'inherited' from a disabled form, or null. The file input is really disabled then, so no chooser opens, whoever clicks it: the button below, or a trigger the host slots in. */
$disabledBy = \Jiannius\Atom\Services\Attributes::disabledBy($attributes);
@endphp

<div
x-cloak
x-data="{ uploading: false, progress: 0 }"
x-on:livewire-upload-start="uploading = true"
x-on:livewire-upload-finish="uploading = false"
x-on:livewire-upload-cancel="uploading = false"
x-on:livewire-upload-error="uploading = false"
x-on:livewire-upload-progress="progress = $event.detail.progress"
@if ($disabledBy)
data-atom-disabled="{{ $disabledBy }}"
@endif
{{ $attributes->only('class')->class('group/uploader [:where(&)]:relative') }}>
    <input  type="file" x-ref="fileinput" class="hidden" {{ $attributes->except(['class', 'disabled']) }} @if ($disabledBy) disabled @endif>

    @if ($slot->isNotEmpty())
        {{ $slot }}
    @else
        <div class="flex items-center gap-2">
            {{-- the small size's 16px icon is the button's rule: it must not reach into what the end slot holds --}}
            <atom:button
            :variant="$variant"
            :size="$size"
            :class="$size === 'sm' ? '[&_[data-atom-icon]]:size-4' : null"
            :disabled="$disabledBy ? true : null"
            :id="$triggerId"
            :aria-labelledby="$triggerId ? $triggerId.' '.$ariaLabelledby : null"
            x-bind:class="uploading && 'opacity-50 pointer-events-none'"
            x-on:click="$refs.fileinput.click()">
                <div x-show="!uploading" class="flex items-center gap-2">
                    <atom:icon.upload class="size-4" /> {{ t($label) }}
                </div>

                <div x-show="uploading" class="flex items-center gap-2 opacity-50">
                    <atom:icon.loading class="size-4" /> {{ t('Uploading') }}...
                </div>

                <div x-show="uploading" class="absolute inset-0 bg-accent opacity-20" x-bind:style="`width: ${progress}%`"></div>
            </atom:button>

            <atom:tooltip x-show="uploading" content="Cancel Upload" class="shrink-0 flex items-center justify-center text-fg-muted">
                <button type="button" x-on:click="$wire.cancelUpload({{ js($attributes->wire('model')->value()) }})" aria-label="{{ t('Cancel upload') }}" class="flex items-center justify-center">
                    <atom:icon.stop class="size-4" />
                </button>
            </atom:tooltip>

            @if ($hasEnd)
                <div {{ $end->attributes->class('min-w-0 text-sm text-fg-muted') }} data-atom-uploader-end>{{ $end }}</div>
            @endif
        </div>
    @endif

    @if ($hasEnd && $slot->isNotEmpty())
        <div {{ $end->attributes->class('mt-1 min-w-0 text-sm text-fg-muted') }} data-atom-uploader-end>{{ $end }}</div>
    @endif
</div>
