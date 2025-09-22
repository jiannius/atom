@php
$config = [
    'variant' => null,
    'heading' => t('Alert'),
    'subheading' => null,
    'message' => null,
    'html' => null,
    'button' => t('Ok'),
];
@endphp

<atom:modal name="atom-alert" class="max-w-lg min-w-sm">
    <div
    x-data="{
        config: {},

        showAlert (e) {
            this.config = { ...@js($config), ...e.detail }
            this.$nextTick(() => atom.modal('atom-alert').show())
        },

        dismiss () {
            if (typeof this.config.onDismissed === 'string') {
                Livewire.find(this.config.wireId).call(this.config.onDismissed)
            }
            else {
                this.config.onDismissed?.()
            }

            atom.modal('atom-alert').close()
        },
    }"
    x-on:atom-alert-show.window="showAlert"
    class="space-y-6">
        <div class="space-y-3">
            <template x-if="config.heading" hidden>
                <div class="flex items-center gap-3">
                    <atom:icon.error x-show="config.variant === 'danger'" class="size-8 text-red-500" variant="solid" />
                    <atom:icon.check-circle x-show="config.variant === 'success'" class="size-8 text-green-500" variant="solid" />
                    <atom:icon.warning x-show="config.variant === 'warning'" class="size-8 text-yellow-500" variant="solid" />
                    <div x-text="config.heading" class="text-lg font-medium"></div>
                </div>
            </template>

            <template x-if="config.subheading" hidden>
                <div x-text="config.subheading" class="text-zinc-400"></div>
            </template>

            <template x-if="typeof config.message === 'string'" hidden>l
                <div x-text="config.message" class="text-zinc-400"></div>
            </template>

            <template x-if="Array.isArray(config.message) && config.message.length === 1" hidden>l
                <div x-text="config.message[0]" class="text-zinc-400"></div>
            </template>

            <template x-if="Array.isArray(config.message) && config.message.length > 1" hidden>
                <ul class="list-disc list-outside ml-6 text-zinc-400">
                    <template x-for="message in config.message">
                        <li x-text="message"></li>
                    </template>
                </ul>
            </template>

            <template x-if="config.html" hidden>
                <div x-html="config.html"></div>
            </template>
        </div>

        <div class="flex justify-end space-x-2 rtl:space-x-reverse">
            <atom:button x-on:click="dismiss"><span x-text="config.button"></span></atom:button>
        </div>
    </div>
</atom:modal>
