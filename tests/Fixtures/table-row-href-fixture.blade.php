{{-- Rows that open a record (#207): a row with an href holding every kind of control, a wire:navigate one, and two that do nothing. --}}
<div>
    <table class="w-full">
        <tbody>
            <atom:table.row href="/atom/e2e/table-row-href?go=plain" data-row="plain">
                <td class="p-2">Plain row</td>
                <td class="p-2"><input type="checkbox" aria-label="Pick" data-testid="checkbox"></td>
                <td class="p-2"><button type="button" data-testid="button" x-on:click="$el.dataset.clicked = '1'">Act</button></td>
                <td class="p-2"><a href="/atom/e2e/table-row-href?go=link" data-testid="link">Link</a></td>
                <td class="p-2"><input type="text" aria-label="Note" data-testid="input"></td>
                <td class="p-2"><span data-testid="plain-text">Text</span></td>
                <td class="p-2" data-testid="plain-cell">Plain cell</td>
                <td class="p-2"><div wire:click="ping" data-testid="wire-div" class="p-2">Wire div</div></td>
                <td class="p-2"><div x-on:click="$el.dataset.clicked = '1'" data-testid="alpine-div" class="p-2">Alpine div</div></td>
                <td class="p-2"><div wire:click.prevent="ping" data-testid="wire-prevent-div" class="p-2">Wire prevent div</div></td>
                <td class="p-2"><div @click="$el.dataset.clicked = '1'" data-testid="at-click-div" class="p-2">At click div</div></td>
                <td class="p-2"><div x-on:click.stop="$el.dataset.clicked = '1'" data-testid="stop-div" class="p-2">Stop div</div></td>
                <td class="p-2"><div x-on:click="$el.dataset.clicked = '1'; $el.querySelector('span').remove()" data-testid="remove-div" class="p-2"><span data-testid="remove-span">Swap me</span></div></td>
                <td class="p-2"><atom-shadow-host data-testid="shadow-host"></atom-shadow-host></td>
                <td class="p-2"><div role="button" data-testid="role-div" class="p-2">Role button</div></td>
                <td class="p-2"><div tabindex="-1" data-testid="tabindex-div" class="p-2">Tabindex div</div></td>
                <td class="p-2"><div data-atom-row-ignore data-testid="ignore-div" class="p-2">Ignored div</div></td>
                <atom:table.cell checkbox="1" data-testid="checkbox-cell" />
                <atom:table.actions data-testid="actions-cell"><atom:menu.item>Edit</atom:menu.item></atom:table.actions>
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

    <div data-testid="pings">{{ $pings }}</div>
</div>
