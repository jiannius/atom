@php
$config = [
    'variant' => null,
    'heading' => t('Are you sure?'),
    'subheading' => t('Please confirm to continue'),
    'message' => null,
    'html' => null,
    'buttonConfirm' => t('Confirm'),
    'buttonCancel' => t('Cancel'),
    'password' => false,
    'passphrase' => null,
    'passphraseLabel' => t('Please type passphrase to continue'),
];
@endphp

<atom:modal name="atom-confirm" class="max-w-lg min-w-sm">
    <form
    x-data="{
        config: {},
        password: '',
        passphrase: '',

        showConfirm (e) {
            this.config = { ...@js($config), ...e.detail }
            this.$nextTick(() => atom.modal('atom-confirm').show())
        },

        accept () {
            if (typeof this.config.onAccepted === 'string') {
                Livewire.find(this.config.wireId).call(this.config.onAccepted, {
                    password: this.password,
                    passphrase: this.passphrase,
                })
            }
            else {
                this.config.onAccepted?.(this.password, this.passphrase)
            }

            atom.modal('atom-confirm').close()
        },

        cancel () {
            if (typeof this.config.onRejected === 'string') {
                Livewire.find(this.config.wireId).call(this.config.onRejected)
            }
            else {
                this.config.onRejected?.()
            }

            atom.modal('atom-confirm').close()
        },
    }"
    x-on:atom-confirm-show.window="showConfirm"
    x-on:submit.prevent="accept"
    class="space-y-6">
        <div class="space-y-3">
            <template x-if="config.heading" hidden>
                <div class="flex items-center gap-3">
                    <atom:icon.error x-show="config.variant === 'danger'" class="text-red-500" variant="solid" />
                    <atom:icon.check-circle x-show="config.variant === 'success'" class="text-green-500" variant="solid" />
                    <atom:icon.warning x-show="config.variant === 'warning'" class="text-yellow-500" variant="solid" />
                    <div x-text="config.heading" class="text-lg font-medium"></div>
                </div>
            </template>

            <template x-if="config.subheading" hidden>
                <div x-text="config.subheading" class="text-zinc-400"></div>
            </template>

            <template x-if="typeof config.message === 'string'" hidden>
                <div x-text="config.message" class="text-zinc-400"></div>
            </template>

            <template x-if="Array.isArray(config.message) && config.message.length === 1" hidden>
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

        <template x-if="config.password" hidden>
            <atom:input x-model="password" type="password" label="Password" required />
        </template>

        <template x-if="config.passphrase" hidden>
            <atom:input.field>
                <atom:label><span x-text="config.passphraseLabel"></span></atom:label>
                <atom:input x-model="passphrase" required />
            </atom:input.field>
        </template>

        <div class="flex justify-end space-x-2 rtl:space-x-reverse">
            <atom:button x-on:click="cancel"><span x-text="config.buttonCancel"></span></atom:button>

            <template x-if="config.variant === 'danger'" hidden>
                <atom:button type="submit" variant="danger" :icon="false"><span x-text="config.buttonConfirm"></span></atom:button>
            </template>

            <template x-if="config.variant !== 'danger'" hidden>
                <atom:button type="submit" variant="accent" :icon="false"><span x-text="config.buttonConfirm"></span></atom:button>
            </template>
        </div>
    </form>
</atom:modal>
