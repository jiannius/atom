@props([
    'label' => 'Drop files here, or click to browse',
])

@php
/* Content under the zone (a size or type hint). A slot made of false @if blocks is not content. */
$hasEnd = isset($end) && $end->hasActualContent();

/* 'self', 'inherited' from a disabled form, or null. The file input is really disabled then, a drop is ignored, and the zone takes the disabled field's look. The root is the drop target and carries the not-allowed cursor; the disabled button takes no pointer events (through the class binding, which Alpine would otherwise strip from the static class), so no engine's handling of events on a disabled button matters. */
$disabledBy = \Jiannius\Atom\Services\Attributes::disabledBy($attributes);
@endphp

<div
x-cloak
x-data="{
    uploading: false,
    progress: 0,
    dragging: false,

    /** Read from the markup when the user acts: the server rewrites the marker on a morph. */
    get disabled () {
        return this.$root.hasAttribute('data-atom-disabled')
    },

    /** The cursor over a disabled zone must not advertise a drop; the event is still cancelled so the file cannot navigate the page. */
    over (e) {
        this.dragging = ! this.disabled

        if (this.disabled && e.dataTransfer) {
            e.dataTransfer.dropEffect = 'none'
        }
    },

    drop (e) {
        this.dragging = false

        if (this.disabled || ! e.dataTransfer?.files?.length) {
            return
        }

        this.$refs.fileinput.files = e.dataTransfer.files
        this.$refs.fileinput.dispatchEvent(new Event('change', { bubbles: true }))
    },
}"
x-on:livewire-upload-start="uploading = true"
x-on:livewire-upload-finish="uploading = false; progress = 0"
x-on:livewire-upload-cancel="uploading = false; progress = 0"
x-on:livewire-upload-error="uploading = false; progress = 0"
x-on:livewire-upload-progress="progress = $event.detail.progress"
x-on:dragover.prevent="over($event)"
x-on:dragleave.prevent="dragging = false"
x-on:drop.prevent="drop($event)"
@if ($disabledBy)
data-atom-disabled="{{ $disabledBy }}"
@endif
{{ $attributes->only('class')->class(['group/uploader [:where(&)]:relative', 'cursor-not-allowed' => $disabledBy && ! $slot->hasActualContent()]) }}>
    <input type="file" x-ref="fileinput" class="hidden" {{ $attributes->except(['class', 'disabled']) }} @if ($disabledBy) disabled @endif>

    {{-- a slot of false @if blocks is not a custom zone: under Livewire it holds only morph markers --}}
    @if ($slot->hasActualContent())
        {{ $slot }}
    @else
        <button
        type="button"
        @if ($disabledBy) disabled @endif
        x-on:click="$refs.fileinput.click()"
        x-bind:class="{
            'opacity-50': uploading,
            'pointer-events-none': {{ $disabledBy ? 'true' : 'uploading' }},
            'border-accent-ink bg-accent-soft': dragging,
            '{{ $disabledBy ? 'border-border' : 'border-control-border' }}': !dragging,
        }"
        x-bind:aria-busy="uploading"
        class="{{ Arr::toCssClasses([
            'relative flex w-full flex-col items-center justify-center gap-2 overflow-hidden rounded-control border border-dashed p-6 text-center text-sm transition-colors',
            $disabledBy ? 'bg-surface-sunken text-fg-subtle' : 'text-fg-muted hover:bg-surface-sunken',
        ]) }}">
            <span x-show="!uploading" class="flex flex-col items-center gap-2">
                <atom:icon.upload class="size-5" />
                <span>{{ t($label) }}</span>
            </span>

            <span x-show="uploading" class="flex items-center gap-2">
                <atom:icon.loading class="size-4" /> {{ t('Uploading') }}...
            </span>

            <span x-show="uploading" class="absolute inset-x-0 bottom-0 h-1 bg-accent-ink" x-bind:style="`width: ${progress}%`"></span>
        </button>
    @endif

    @if ($hasEnd)
        <div {{ $end->attributes->class('mt-2 text-sm text-fg-muted') }} data-atom-uploader-end>{{ $end }}</div>
    @endif
</div>
