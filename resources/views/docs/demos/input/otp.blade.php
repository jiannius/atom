<div class="space-y-6">
    <div x-data="{ code: '' }" x-on:otp-completed="code = $event.detail" class="space-y-2">
        <atom:label>6-digit code</atom:label>
        <atom:input.otp/>
        <atom:caption>
            Completed code: <span class="font-medium" data-atom-otp-result x-text="code || '—'"></span>
        </atom:caption>
    </div>

    <div class="space-y-2">
        <atom:label>Grouped (3-3)</atom:label>
        <atom:input.otp :groups="3"/>
    </div>

    <div class="space-y-2">
        <atom:label>Masked, 4 digits</atom:label>
        <atom:input.otp :length="4" masked/>
    </div>
</div>
