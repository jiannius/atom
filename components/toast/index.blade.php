@php
$config = [
    'message' => t('Saved'),
    'variant' => 'success',
    'delay' => 3000,
    'position' => 'bottom',
    'align' => 'center',
];
@endphp

{{-- atom-toast-close with a source (onClose) closes only a toast that was shown with that same
     source, so a form closing its own error toast can't take the app's own Saved toast with it.
     With no source it closes whatever is open. role=status: the toast is a live region, so a
     screen reader announces a saved or failed message without it taking focus. --}}
<div
role="status"
aria-live="polite"
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

    onClose (e) {
        let source = e.detail?.source

        if (source && this.config.source !== source) return

        this.closeToast()
    },

    onClick () {
        let navigate = atom.safeUrl(this.config.navigate)
        let url = atom.safeUrl(this.config.url)

        if (navigate) Livewire.navigate(navigate)
        else if (url) window.open(url, '_blank')
    },
}"
x-on:atom-toast-show.window="showToast"
x-on:atom-toast-close.window="onClose"
x-on:click="onClick"
x-bind:class="{
    'mt-auto': config.position === 'bottom',
    'my-auto': config.position === 'center',
    'ml-auto': config.align === 'right',
    'mr-auto': config.align === 'left',
    'mx-auto': config.align === 'center',
    'cursor-pointer': atom.safeUrl(config.navigate) || atom.safeUrl(config.url),
}"
class="p-6 bg-transparent opacity-0 scale-75 transition-all duration-100"
data-atom-toast
popover="manual">
    {{-- A raised layer, like every overlay: the raised surface, the strong hairline, the one shadow token. The tone is the icon's
         alone, so the text keeps full contrast whatever the variant. --}}
    <div class="py-3 ps-4 pe-3 max-w-lg min-w-xs flex gap-3 rounded-panel bg-surface-raised text-fg text-sm shadow-pop border border-border-strong">
        <div class="grow">
            <template x-if="config.heading" hidden>
                <div class="flex flex-col gap-2">
                    <div class="flex items-center gap-3">
                        <div class="shrink-0 size-4 flex items-center justify-center">
                            <atom:icon.error x-show="config.variant === 'danger'" class="size-4 text-danger-fg" variant="solid" />
                            <atom:icon.check-circle x-show="config.variant === 'success'" class="size-4 text-success-fg" variant="solid" />
                            <atom:icon.warning x-show="config.variant === 'warning'" class="size-4 text-warning-fg" variant="solid" />
                        </div>
                        <div x-text="config.heading" class="font-semibold"></div>
                    </div>

                    <template x-if="config.subheading" hidden>
                        <div x-text="config.subheading" class="text-fg-muted"></div>
                    </template>
        
                    <template x-if="typeof config.message === 'string'" hidden>
                        <div x-text="config.message" class="text-fg-muted"></div>
                    </template>
        
                    <template x-if="Array.isArray(config.message) && config.message.length" hidden>
                        <ul class="list-disc list-outside ms-6 text-fg-muted">
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
                    <div class="shrink-0 size-4 flex items-center justify-center">
                        <atom:icon.error x-show="config.variant === 'danger'" class="size-4 text-danger-fg" variant="solid" />
                        <atom:icon.check-circle x-show="config.variant === 'success'" class="size-4 text-success-fg" variant="solid" />
                        <atom:icon.warning x-show="config.variant === 'warning'" class="size-4 text-warning-fg" variant="solid" />
                    </div>

                    <template x-if="config.subheading || config.message" hidden>
                        <div x-text="config.subheading || config.message"></div>
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
            class="flex size-7 items-center justify-center rounded-control text-fg-subtle transition-colors hover:bg-fg/[0.06] hover:text-fg">
                <atom:icon.close class="size-4"/>
            </button>
        </div>
    </div>
</div>
