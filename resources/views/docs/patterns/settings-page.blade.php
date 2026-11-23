{{-- Pattern: settings page. A column of navigation beside the forms it opens (Account and Workspace settings), the way most
     products draw "Settings". Built from atom's components with invented data; copy the structure, not the data.

     The rules this page follows:
     - `<atom:page>` holds `<atom:page.header>` and ONE `<atom:page.columns>`. The navigation is its `nav` slot, a `<atom:navlist>`
       of `<atom:navlist.group heading>`s (an item is a section of settings: a link to its own URL). Nothing in the markup says
       how wide the nav is or when it stacks: the 200px column beside the forms from a row 768px wide, the sticky position under the shell's bar
       and the strip it becomes on a phone are page.columns' own. Never write a selector into `[data-atom-navlist-*]`, a
       `--atom-gutter-s` override or a grid of your own for it (`atom:design-check` flags them: hand-settings-nav).
     - Narrower than 768px of row the nav is a row of its own above the forms and the navlist a strip that scrolls sideways: the group
       labels are hidden, a collapsed group (`expandable`) shows its items, and the current item is scrolled into view. Open this page at
       390px to see it.
     - The forms are `<atom:card heading>`s in ONE `<atom:form wire:submit="save">`: the card's content is its fields, in a `grid`
       that goes two across when the card is wide enough (`@container`, `@md:grid-cols-2`).
     - The navlist is the landmark, so it carries the label (`aria-label`): the shell's own nav is a landmark too, and two
       unlabelled ones cannot be told apart.
     - The save bar is `<atom:form.actions bar dirty>`, the LAST thing in the form, as on the form page. In a page.columns with a nav beside
       the main column it spans nav and forms (it starts under the nav and ends with the forms) while its label and buttons sit where
       they would in a bar with no nav; on a phone it is the plain bar. Nothing to write for it: it reads `--atom-page-nav-offset`
       from the main column.
     - A section that saves on its own (its own button, its own request) is its own `<atom:form>` with a footer, not part of the page's
       bar; do not put two save bars on a page.
     - The current item follows the URL: an item is current when its href's path is the request's. This page has one URL, so it passes
       `:current` from ?section=.

     Open it with ?section=security|notifications|general|members|billing|integrations to move the current item along. --}}
@php
$styles = $styles ?? [];
// a host's own stylesheet when it has one (config atom.docs.vite), so the page is styled inside the host; none in the test rig
$vite = $vite ?? app(\Jiannius\Atom\Services\Docs::class)->vite();
$shell = ($shell ?? 'sidebar') === 'header' ? 'header' : 'sidebar';

$sections = [
    'Account' => ['profile' => 'Profile', 'security' => 'Security', 'notifications' => 'Notifications'],
    'Workspace' => ['general' => 'General', 'members' => 'Members', 'billing' => 'Billing', 'integrations' => 'Integrations'],
];

$section = request()->query('section', 'profile');
$section = is_string($section) && collect($sections)->flatMap(fn ($items) => array_keys($items))->contains($section) ? $section : 'profile';
$title = collect($sections)->flatMap(fn ($items) => $items)->get($section);
@endphp

<x-dynamic-component :component="'atom::layouts.'.$shell" title="Settings page" :vite="$vite" :styles="$styles" :dark="$dark ?? true">
    <x-slot:brand>
        <a href="?page=dashboard" @class([
            'flex items-center gap-[9px] px-2 pb-3 pt-1.5' => $shell === 'sidebar',
            'flex shrink-0 items-center gap-2 text-sm font-semibold tracking-tight' => $shell === 'header',
        ])>
            <div class="flex size-[26px] items-center justify-center rounded-control bg-accent text-accent-foreground">
                <atom:icon.blocks class="size-4"/>
            </div>
            <span class="truncate text-sm font-semibold">Fieldwork</span>
        </a>
    </x-slot>

    <x-slot:nav>
        @include('atom::docs.patterns.nav')
    </x-slot>

    <x-slot:dropdown>
        <atom:menu.item icon="settings">Account settings</atom:menu.item>
        <atom:menu.item icon="logout">Sign out</atom:menu.item>
    </x-slot>

    <atom:page data-testid="settings-page">
        <atom:page.header title="Settings" />

        <atom:page.columns>
            <x-slot:nav>
                <atom:navlist aria-label="Settings sections">
                    @foreach ($sections as $heading => $items)
                        <atom:navlist.group :heading="$heading">
                            @foreach ($items as $key => $label)
                                <atom:navlist.item :href="'?section='.$key" :current="$section === $key">{{ $label }}</atom:navlist.item>
                            @endforeach
                        </atom:navlist.group>
                    @endforeach
                </atom:navlist>
            </x-slot:nav>

            <atom:form marker="optional" x-data="{ saved: false }" x-on:submit.prevent="saved = true" :error-toast="false" data-testid="settings-form">
                <atom:card :heading="$title">
                    <div class="grid gap-4 @container">
                        <div class="grid gap-4 @md:grid-cols-2">
                            <atom:input label="Full name" value="Alex Doe" required/>
                            <atom:input label="Email" type="email" value="alex@example.com" required/>
                        </div>

                        <atom:input label="Job title" value="Operations lead"/>
                    </div>
                </atom:card>

                <atom:card heading="Language and region">
                    <div class="grid gap-4 @container">
                        <div class="grid gap-4 @md:grid-cols-2">
                            <atom:select label="Language" :clearable="false" value="en" :options="[
                                ['value' => 'en', 'label' => 'English'],
                                ['value' => 'ms', 'label' => 'Bahasa Melayu'],
                            ]"/>
                            <atom:select label="Time zone" :clearable="false" value="kl" :options="[
                                ['value' => 'kl', 'label' => 'Kuala Lumpur (GMT+8)'],
                                ['value' => 'utc', 'label' => 'UTC'],
                            ]"/>
                        </div>
                    </div>
                </atom:card>

                <atom:form.actions bar dirty>
                    <atom:button type="submit" data-testid="settings-primary">Save changes</atom:button>
                    <atom:button type="button" variant="ghost">Cancel</atom:button>
                </atom:form.actions>
            </atom:form>
        </atom:page.columns>
    </atom:page>

    @livewireScripts
</x-dynamic-component>
