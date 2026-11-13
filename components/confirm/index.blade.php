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
    'passphraseLabel' => t('Please type :passphrase to continue'),
    'reason' => false,
    'reasonLabel' => t('Reason'),
    'reasonPlaceholder' => null,
];
@endphp

<atom:modal name="atom-confirm" class="max-w-lg min-w-sm">
    <form
    x-data="{
        config: {},
        password: '',
        passphrase: '',
        reason: '',
        passphraseError: null,
        passphraseMismatch: @js(t('The text you typed does not match')),

        showConfirm (e) {
            this.config = { ...@js($config), ...e.detail }
            {{-- `phrase` is the documented alias of `passphrase` (the menu item's prop name) --}}
            if (! this.config.passphrase && this.config.phrase) this.config.passphrase = this.config.phrase
            this.password = ''
            this.passphrase = ''
            this.reason = ''
            this.passphraseError = null
            this.$nextTick(() => atom.modal('atom-confirm').show())
        },

        accept () {
            {{-- the typed text has to be the passphrase (case counts, whitespace around it does not), and the
                 trimmed text is what is handed on, so a host comparing it sees what the dialog accepted --}}
            let passphrase = this.passphrase.trim()

            if (this.config.passphrase && passphrase !== String(this.config.passphrase).trim()) {
                this.passphraseError = this.passphraseMismatch
                this.$nextTick(() => document.getElementById('atom-confirm-passphrase')?.focus())
                return
            }

            if (typeof this.config.onAccepted === 'string') {
                Livewire.find(this.config.wireId).call(this.config.onAccepted, {
                    password: this.password,
                    passphrase,
                    reason: this.reason,
                })
            }
            else {
                this.config.onAccepted?.(this.password, passphrase, this.reason)
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
                    <atom:icon.error x-show="config.variant === 'danger'" class="size-5 text-danger-fg" variant="solid" />
                    <atom:icon.check-circle x-show="config.variant === 'success'" class="size-5 text-success-fg" variant="solid" />
                    <atom:icon.warning x-show="config.variant === 'warning'" class="size-5 text-warning-fg" variant="solid" />
                    <div x-text="config.heading" class="text-lg font-semibold"></div>
                </div>
            </template>

            <template x-if="config.subheading" hidden>
                <div x-text="config.subheading" class="text-fg-muted"></div>
            </template>

            <template x-if="typeof config.message === 'string'" hidden>
                <div x-text="config.message" class="text-fg-muted"></div>
            </template>

            <template x-if="Array.isArray(config.message) && config.message.length === 1" hidden>
                <div x-text="config.message[0]" class="text-fg-muted"></div>
            </template>

            <template x-if="Array.isArray(config.message) && config.message.length > 1" hidden>
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

        <template x-if="config.password" hidden>
            <atom:input x-model="password" type="password" label="Password" required />
        </template>

        <template x-if="config.passphrase" hidden>
            <atom:input.field>
                {{-- the label is built here rather than through the `label` prop, so the
                     association has to be made by hand too. Static ids: the dialog renders
                     once per page, so they are unique, and stable across a morph. The passphrase
                     goes in through a function, so a `$$` or `$&` in it is printed as typed.
                     `data-atom-error` is only on the error while it shows: the field draws its
                     border red for any [data-atom-error] inside it, hidden or not. --}}
                <atom:label for="atom-confirm-passphrase"><span x-text="config.passphraseLabel.replace(':passphrase', () => `&quot;${config.passphrase}&quot;`)"></span></atom:label>
                <atom:input id="atom-confirm-passphrase" x-model="passphrase" x-on:input="passphraseError = null" x-bind:aria-invalid="passphraseError ? 'true' : null" aria-describedby="atom-confirm-passphrase-error" required />
                <div x-show="passphraseError" x-text="passphraseError" x-bind:data-atom-error="passphraseError ? '' : null" id="atom-confirm-passphrase-error" role="alert" class="error text-danger-fg text-sm font-medium"></div>
            </atom:input.field>
        </template>

        <template x-if="config.reason" hidden>
            <atom:input.field>
                <atom:label for="atom-confirm-reason"><span x-text="config.reasonLabel"></span></atom:label>
                <atom:textarea id="atom-confirm-reason" x-model="reason" x-bind:placeholder="config.reasonPlaceholder" rows="3" />
            </atom:input.field>
        </template>

        <div class="flex justify-end gap-2">
            <atom:button x-on:click="cancel"><span x-text="config.buttonCancel"></span></atom:button>

            {{-- the button is drawn in the dialog's own tone (the icon above is its tone too): danger, warning and success have their
                 own button, anything else is the accent one --}}
            @foreach ([
                'danger' => "config.variant === 'danger'",
                'warning' => "config.variant === 'warning'",
                'success' => "config.variant === 'success'",
                'accent' => "! ['danger', 'warning', 'success'].includes(config.variant)",
            ] as $tone => $condition)
                <template x-if="{{ $condition }}" hidden>
                    <atom:button type="submit" variant="{{ $tone }}" :icon="false"><span x-text="config.buttonConfirm"></span></atom:button>
                </template>
            @endforeach
        </div>
    </form>
</atom:modal>
