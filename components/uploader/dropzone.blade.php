@props([
    'label' => 'Drop files here, or click to browse',
])

<div
x-cloak
x-data="{
    uploading: false,
    progress: 0,
    dragging: false,
    drop (e) {
        this.dragging = false

        if (! e.dataTransfer?.files?.length) {
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
x-on:dragover.prevent="dragging = true"
x-on:dragleave.prevent="dragging = false"
x-on:drop.prevent="drop($event)"
{{ $attributes->only('class')->class('group/uploader [:where(&)]:relative') }}>
    <input type="file" x-ref="fileinput" class="hidden" {{ $attributes->except('class') }}>

    @if ($slot->isNotEmpty())
        {{ $slot }}
    @else
        <button
        type="button"
        x-on:click="$refs.fileinput.click()"
        x-bind:class="{
            'opacity-50 pointer-events-none': uploading,
            'border-accent-ink bg-accent-soft': dragging,
            'border-control-border': !dragging,
        }"
        x-bind:aria-busy="uploading"
        class="relative flex w-full flex-col items-center justify-center gap-2 overflow-hidden rounded-control border border-dashed p-6 text-center text-sm text-fg-muted transition-colors hover:bg-surface-sunken">
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
</div>
