{{-- extra-primary: a primary in a form is that form's own, so forms that save on their own are not a second primary on the screen. Nothing here is flagged. --}}
<atom:page width="narrow">
    <atom:page.header title="Settings">
        <x-slot:primary>
            <atom:button variant="primary" wire:click="invite">Invite a teammate</atom:button>
        </x-slot:primary>
    </atom:page.header>

    <atom:form wire:submit="saveProfile">
        <atom:form.section heading="Profile">
            <atom:input label="Name" wire:model="name" />
        </atom:form.section>
        <atom:form.actions>
            <atom:button type="submit">Save</atom:button>
        </atom:form.actions>
    </atom:form>

    <atom:form wire:submit="savePassword">
        <atom:input label="Password" wire:model="password" />
        <atom:button type="submit">Update password</atom:button>
    </atom:form>

    <form wire:submit="comment">
        <atom:textarea wire:model="body" />
        <atom:button type="submit">Post</atom:button>
    </form>

    <form wire:submit="remind">
        <atom:button variant="primary">Send a reminder</atom:button>
    </form>

    <atom:form.section heading="Billing">
        <atom:input label="Plan" wire:model="plan" />
        <x-slot:footer><atom:button type="submit" wire:click="saveBilling">Save</atom:button></x-slot:footer>
    </atom:form.section>

    <atom:form.section heading="Tax">
        <atom:input label="Number" wire:model="tax" />
        <x-slot:footer><atom:button type="submit" wire:click="saveTax">Save</atom:button></x-slot:footer>
    </atom:form.section>

    <div x-data="{ tab: 'a' }">
        <div x-show="tab === 'a'"><atom:form wire:submit="a"><atom:input wire:model="a" /><atom:button type="submit">Save A</atom:button></atom:form></div>
        <div x-show="tab === 'b'"><atom:form wire:submit="b"><atom:input wire:model="b" /><atom:button type="submit">Save B</atom:button></atom:form></div>
    </div>
</atom:page>
