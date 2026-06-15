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
class="group/uploader relative">
    <input type="file" x-ref="fileinput" class="hidden" {{ $attributes }}>

    @if ($slot->isNotEmpty())
        {{ $slot }}
    @else
        <button
        type="button"
        x-on:click="$refs.fileinput.click()"
        x-bind:class="{
            'opacity-50 pointer-events-none': uploading,
            'border-zinc-400 bg-zinc-50 dark:border-zinc-500 dark:bg-zinc-700/30': dragging,
        }"
        x-bind:aria-busy="uploading"
        class="relative flex w-full flex-col items-center justify-center gap-2 overflow-hidden rounded-lg border border-dashed border-zinc-300 p-6 text-center text-muted transition-colors hover:bg-zinc-50 dark:border-zinc-600 dark:hover:bg-zinc-700/30">
            <span x-show="!uploading" class="flex flex-col items-center gap-2">
                <atom:icon.upload class="size-6" />
                <span>{{ t($label) }}</span>
            </span>

            <span x-show="uploading" class="flex items-center gap-2">
                <atom:icon.loading class="size-5" /> {{ t('Uploading') }}...
            </span>

            <span x-show="uploading" class="absolute inset-x-0 bottom-0 h-1 bg-zinc-400" x-bind:style="`width: ${progress}%`"></span>
        </button>
    @endif
</div>
