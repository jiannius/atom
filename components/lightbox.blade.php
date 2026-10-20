<dialog
wire:ignore
x-cloak
x-data="lightbox()"
x-on:lightbox.window="open($event.target)"
x-on:keydown.left.prevent="prev()"
x-on:keydown.right.prevent="next()"
x-on:close="$el.removeAttribute('data-open')"
{{ $attributes }}
data-atom-lightbox>
    <div
    x-transition.opacity.duration.100
    x-on:click="close()"
    class="fixed inset-0 flex items-center justify-center overflow-auto">
        <button
        type="button"
        x-on:click.stop="prev()"
        aria-label="{{ t('Previous') }}"
        class="absolute top-1/2 left-6 -translate-y-1/2 size-10 flex items-center justify-center rounded-pill border border-border-strong bg-surface-raised text-fg shadow-pop transition-colors hover:bg-surface-sunken cursor-pointer">
            <atom:icon.left class="size-5" />
        </button>

        <button
        type="button"
        x-on:click.stop="next()"
        aria-label="{{ t('Next') }}"
        class="absolute top-1/2 right-6 -translate-y-1/2 size-10 flex items-center justify-center rounded-pill border border-border-strong bg-surface-raised text-fg shadow-pop transition-colors hover:bg-surface-sunken cursor-pointer">
            <atom:icon.right class="size-5" />
        </button>

        <button
        type="button"
        x-on:click="close()"
        aria-label="{{ t('Close') }}"
        class="absolute top-5 right-6 size-10 flex items-center justify-center rounded-pill border border-border-strong bg-surface-raised text-fg shadow-pop transition-colors hover:bg-surface-sunken cursor-pointer">
            <atom:icon.close class="size-5" />
        </button>

        <template x-for="(item, i) in gallery" hidden>
            <div
            x-show="i === pointer"
            x-transition.opacity.100
            x-on:click.stop
            class="flex items-center justify-center">
                <template x-if="item.isImage" hidden>
                    <img x-bind:src="item.url" style="max-width: 75vw;">
                </template>

                <template x-if="!item.isImage" hidden>
                    {{-- not a photo, so it is a panel of its own: the controls and the name read against the raised surface, not the scrim --}}
                    <div class="flex flex-col items-center justify-center gap-3 rounded-modal border border-border-strong bg-surface-raised p-6 text-fg shadow-pop">
                        <div class="size-32 rounded-panel bg-surface-sunken text-fg-subtle flex items-center justify-center">
                            <atom:icon.file class="size-8" />
                        </div>

                        <div x-show="item.name" x-text="item.name" class="text-sm font-medium"></div>

                        <atom:button icon="download" x-on:click="atom.safeUrl(item.url) && window.open(item.url, '_blank')">
                            {{ t('Download') }}
                        </atom:button>
                    </div>
                </template>
            </div>
        </template>
    </div>
</dialog>
