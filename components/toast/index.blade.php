@php
$config = [
    'message' => t('Saved'),
    'variant' => 'success',
    'delay' => 3000,
    'position' => 'bottom',
    'align' => 'center',
];
@endphp

<div
x-data="{
    timer: null,
    config: {},

    showToast (e) {
        this.closeToast()

        if (typeof e.detail === 'string') this.config = { ...@js($config), message: e.detail }
        else this.config = { ...@js($config), ...e.detail }

        this.$nextTick(() => {
            this.$root.showPopover()
            this.$root.classList.add('scale-100')
            this.$root.classList.add('opacity-100')
            if (this.config.delay) this.timer = setTimeout(() => this.closeToast(), this.config.delay)
        })
    },

    closeToast () {
        clearTimeout(this.timer)

        this.$root.hidePopover()
        this.$root.classList.remove('scale-100')
        this.$root.classList.remove('opacity-100')
    },

    onClick () {
        if (this.config.navigate) Livewire.navigate(this.config.navigate)
        else if (this.config.url) window.open(this.config.url, '_blank')
    },
}"
x-on:atom-toast-show.window="showToast"
x-on:click="onClick"
x-bind:class="{
    'mt-auto': config.position === 'bottom',
    'my-auto': config.position === 'center',
    'ml-auto': config.align === 'right',
    'mr-auto': config.align === 'left',
    'mx-auto': config.align === 'center',
    'cursor-pointer': config.navigate || config.url,
}"
class="p-6 bg-transparent opacity-0 scale-75 transition-all duration-100"
data-atom-toast
popover="manual">
    <div class="py-4 px-6 max-w-lg min-w-xs flex gap-3 rounded-lg bg-zinc-900 dark:bg-zinc-600 shadow border border-zinc-200 dark:border-zinc-700">
        <div class="grow">
            <template x-if="config.heading" hidden>
                <div class="flex flex-col gap-2">
                    <div class="flex items-center gap-3">
                        <div class="shrink-0 size-5 flex items-center justify-center">
                            <atom:icon.error x-show="config.variant === 'danger'" class="text-red-500" variant="solid" />
                            <atom:icon.check-circle x-show="config.variant === 'success'" class="text-green-500" variant="solid" />
                            <atom:icon.warning x-show="config.variant === 'warning'" class="text-yellow-500" variant="solid" />
                        </div>
                        <div x-text="config.heading" class="text-zinc-100 font-medium"></div>
                    </div>

                    <template x-if="config.subheading" hidden>
                        <div x-text="config.subheading" class="text-sm text-zinc-300"></div>
                    </template>
        
                    <template x-if="typeof config.message === 'string'" hidden>
                        <div x-text="config.message" class="text-sm text-zinc-300"></div>
                    </template>
        
                    <template x-if="Array.isArray(config.message) && config.message.length" hidden>
                        <ul class="list-disc list-outside ml-6 text-sm text-zinc-300">
                            <template x-for="message in config.message">
                                <li x-text="message"></li>
                            </template>
                        </ul>
                    </template>
        
                    <template x-if="config.html" hidden>
                        <div x-html="config.html"></div>
                    </template>
                </div>
            </template>
            
            <template x-if="!config.heading && (config.subheading || config.message || config.html)" hidden>
                <div class="flex items-center gap-3">
                    <div class="shrink-0 size-5 flex items-center justify-center">
                        <atom:icon.error x-show="config.variant === 'danger'" class="text-red-500" variant="solid" />
                        <atom:icon.check-circle x-show="config.variant === 'success'" class="text-green-500" variant="solid" />
                        <atom:icon.warning x-show="config.variant === 'warning'" class="text-yellow-500" variant="solid" />
                    </div>

                    <template x-if="config.subheading || config.message" hidden>
                        <div x-text="config.subheading || config.message" class="text-zinc-100"></div>
                    </template>

                    <template x-if="config.html" hidden>
                        <div x-html="config.html"></div>
                    </template>
                </div>
            </template>
        </div>

        <div class="shrink-0">
            <button
            type="button"
            x-on:click.stop="closeToast"
            aria-label="{{ t('Close') }}"
            class="flex items-center justify-center text-zinc-400 hover:text-zinc-800 dark:hover:text-zinc-200">
                <atom:icon.close class="size-5"/>
            </button>
        </div>
    </div>
</div>
