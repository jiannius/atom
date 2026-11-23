@php
$account = ['profile' => 'Profile', 'security' => 'Security', 'notifications' => 'Notifications', 'sessions' => 'Sessions', 'tokens' => 'API tokens'];
// ?links=1: each item is a link that wire:navigate-s to this page with `section` set (a real navigation), carrying the rest of the query
$href = fn (string $key) => '/atom/e2e/shell-settings?'.http_build_query([...$carry, 'section' => $key]);
$workspace = ['general' => 'General', 'members' => 'Members', 'billing' => 'Billing', 'integrations' => 'Integrations', 'audit' => 'Audit log', 'domains' => 'Domains'];
@endphp

{{-- the consumer build scans this file: [--atom-page-nav-offset:0px] ms-2 ms-[calc(var(--atom-page-nav-offset,0px)+1rem)] --}}
<atom:page data-testid="settings-page">
    <atom:page.header title="Settings" />

    <atom:page.columns data-testid="columns">
        <x-slot:nav>
            @if (! $navReady)
                <p data-testid="nav-pending">Loading sections</p>
            @else
            <atom:navlist aria-label="Settings sections" wire:key="settings-nav-{{ $navRevision }}">
                <atom:navlist.group heading="Account">
                    @foreach ($account as $key => $label)
                        @if ($links)
                            <atom:navlist.item :href="$href($key)" wire:navigate :current="$section === $key" data-testid="nav-{{ $key }}">{{ $label }}</atom:navlist.item>
                        @else
                            <atom:navlist.item as="button" wire:click="select('{{ $key }}')" :current="$section === $key" data-testid="nav-{{ $key }}">{{ $label }}</atom:navlist.item>
                        @endif
                    @endforeach
                </atom:navlist.group>

                <atom:navlist.group heading="Workspace" expandable :expanded="false">
                    @foreach ($workspace as $key => $label)
                        @if ($links)
                            <atom:navlist.item :href="$href($key)" wire:navigate :current="$section === $key" data-testid="nav-{{ $key }}">{{ $label }}</atom:navlist.item>
                        @else
                            <atom:navlist.item as="button" wire:click="select('{{ $key }}')" :current="$section === $key" data-testid="nav-{{ $key }}">{{ $label }}</atom:navlist.item>
                        @endif
                    @endforeach
                </atom:navlist.group>
            </atom:navlist>
            @endif
        </x-slot:nav>

        <atom:form wire:submit="save" data-testid="settings-form">
            <atom:card heading="Profile">
                <div class="grid gap-4">
                    <atom:input label="Full name" wire:model="name" data-testid="settings-name"/>
                    <atom:input label="Email" type="email" value="alex@example.com"/>
                </div>
            </atom:card>

            @foreach (range(1, 6) as $n)
                <atom:card :heading="'Section '.$n">
                    <div class="h-48" data-testid="settings-filler">Content of section {{ $n }}</div>
                </atom:card>
            @endforeach

            <atom:form.actions bar dirty data-testid="settings-bar">
                <x-slot:start><span data-testid="settings-start">Saved {{ $saves }} times</span></x-slot:start>
                <atom:button type="submit" data-testid="settings-save">Save changes</atom:button>
                <atom:button type="button" variant="ghost" data-testid="settings-cancel">Cancel</atom:button>
            </atom:form.actions>
        </atom:form>

        <atom:modal name="settings-modal" data-testid="settings-modal">
            <atom:form.actions bar data-testid="modal-bar">
                <x-slot:start><span data-testid="modal-start">In a modal</span></x-slot:start>
                <atom:button type="button" data-testid="modal-ok">Ok</atom:button>
            </atom:form.actions>
        </atom:modal>
    </atom:page.columns>
</atom:page>
