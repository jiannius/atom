@props([
    'label' => 'Upload',
    'variant' => null,
    'size' => null,
])

<div
x-cloak
x-data="{ uploading: false, progress: 0 }"
x-on:livewire-upload-start="uploading = true"
x-on:livewire-upload-finish="uploading = false"
x-on:livewire-upload-cancel="uploading = false"
x-on:livewire-upload-error="uploading = false"
x-on:livewire-upload-progress="progress = $event.detail.progress"
class="group/uploader relative">
    <input  type="file" x-ref="fileinput" class="hidden" {{ $attributes }}>

    @if ($slot->isNotEmpty())
        {{ $slot }}
    @else
        <div @class([
            'flex items-center gap-2',
            '[&_[data-atom-icon]]:size-4' => $size === 'sm',
        ])>
            <atom:button
            :variant="$variant"
            :size="$size"
            x-bind:class="uploading && 'opacity-50 pointer-events-none'"
            x-on:click="$refs.fileinput.click()">
                <div x-show="!uploading" class="flex items-center gap-2">
                    <atom:icon.upload /> {{ t($label) }}
                </div>

                <div x-show="uploading" class="flex items-center gap-2 opacity-50">
                    <atom:icon.loading /> {{ t('Uploading') }}...
                </div>

                <div x-show="uploading" class="absolute inset-0 bg-zinc-400 opacity-30" x-bind:style="`width: ${progress}%`"></div>
            </atom:button>

            <atom:tooltip x-show="uploading" content="Cancel Upload" class="shrink-0 flex items-center justify-center text-muted">
                <button type="button" x-on:click="$wire.cancelUpload({{ js($attributes->wire('model')->value()) }})" class="flex items-center justify-center" />
                    <atom:icon.stop class="size-4" />
                </button>
            </atom:tooltip>
        </div>
    @endif
</div>
