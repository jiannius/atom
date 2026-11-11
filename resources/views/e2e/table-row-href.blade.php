@php
// ?tw=floor|latest picks the consumer's Tailwind build (default latest): the focus ring is a utility, and the e2e rig has none of its own.
// ?go=<name> only marks which row opened this page, so a spec can tell a navigation from a click that did nothing.
$tw = in_array(request()->query('tw'), ['floor', 'latest'], true) ? request()->query('tw') : 'latest';
$go = preg_replace('/[^a-z-]/', '', (string) request()->query('go'));
@endphp

<atom:html title="E2E: Table Row Href" :vite="[]" :dark="false" :styles="['/atom/e2e/consumer-css/'.$tw]">
{{-- Rows that open a record (#207). A table row's x-on:click needs an Alpine scope; in a host it is the Livewire root. --}}
<div class="p-4" x-data>
    <div data-testid="opened">{{ $go }}</div>

    <button type="button" data-testid="before">Before the table</button>

    <table class="w-full">
        <tbody>
            <atom:table.row href="/atom/e2e/table-row-href?go=plain" data-row="plain">
                <td class="p-2">Plain row</td>
                <td class="p-2"><input type="checkbox" aria-label="Pick" data-testid="checkbox"></td>
                <td class="p-2"><button type="button" data-testid="button" x-on:click="$el.dataset.clicked = '1'">Act</button></td>
                <td class="p-2"><a href="/atom/e2e/table-row-href?go=link" data-testid="link">Link</a></td>
                <td class="p-2"><input type="text" aria-label="Note" data-testid="input"></td>
                <td class="p-2"><span data-testid="plain-text">Text</span></td>
                <atom:table.actions><atom:menu.item>Edit</atom:menu.item></atom:table.actions>
            </atom:table.row>
            <atom:table.row wire:navigate href="/atom/e2e/table-row-href?go=spa" data-row="spa">
                <td class="p-2">SPA row</td>
            </atom:table.row>
            <atom:table.row data-row="none">
                <td class="p-2">No href</td>
            </atom:table.row>
            <atom:table.row href="javascript:window.__pwned = 1" data-row="blocked">
                <td class="p-2">Blocked href</td>
            </atom:table.row>
        </tbody>
    </table>

    <button type="button" data-testid="after">After the table</button>
</div>

@livewireScripts
</atom:html>
