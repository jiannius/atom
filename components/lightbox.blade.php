<dialog
wire:ignore
x-cloak
x-data="lightbox()"
x-on:lightbox.window="open($event.target)"
x-on:keydown.left.prevent="prev()"
x-on:keydown.right.prevent="next()"
x-on:close="$el.removeAttribute('data-open')"
data-atom-lightbox>
    <div
    x-transition.opacity.duration.100
    x-on:click="close()"
    class="fixed inset-0 flex items-center justify-center overflow-auto">
        <button
        type="button"
        x-on:click.stop="prev()"
        aria-label="{{ t('Previous') }}"
        class="absolute top-0 bottom-0 left-10 w-14 flex items-center justify-center text-gray-100 cursor-pointer">
            <atom:icon.left class="size-12" />
        </button>

        <button
        type="button"
        x-on:click.stop="next()"
        aria-label="{{ t('Next') }}"
        class="absolute top-0 bottom-0 right-10 w-14 flex items-center justify-center text-gray-100 cursor-pointer">
            <atom:icon.right class="size-12" />
        </button>

        <button
        type="button"
        x-on:click="close()"
        aria-label="{{ t('Close') }}"
        class="absolute top-5 right-10 w-10 h-10 cursor-pointer flex items-center justify-center text-gray-100">
            <atom:icon.close class="size-12" />
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
                    <div class="flex flex-col items-center justify-center gap-3">
                        <div class="w-40 h-40 bg-gray-200 rounded-lg flex items-center justify-center">
                            <atom:icon.file class="size-10" />
                        </div>

                        <div x-show="item.name" x-text="item.name" class="text-zinc-100 font-medium"></div>

                        <atom:button icon="download" x-on:click="window.open(item.url, '_blank')">
                            {{ t('Download') }}
                        </atom:button>
                    </div>
                </template>
            </div>
        </template>
    </div>
</dialog>
