<atom:html title="E2E: Floating Panel Height" :vite="[]" :dark="false" class="min-h-screen bg-white">
{{-- Plain-Blade page (no Livewire) for the floating-panel height spec. ?top=N pushes the
     fields N px down the page, so the spec can put a field at the y the smgdms report
     had it (about 284) and the page spacer below lets it scroll for the below-the-fold
     checks. Tailwind utilities are stood in for by the spec (the rig compiles none). --}}
<style>
    /* A panel's own cap, as a consumer's class would give it. */
    [data-probe="capped"] [data-atom-menu] { max-height: 120px; overflow-y: auto; }
</style>

<div data-probe-stack style="padding-top: {{ (int) request('top', 0) }}px">
    <div data-probe="date" style="width: 300px">
        <atom:date-picker time />
    </div>

    <div data-probe="dropdown" style="margin-top: 24px">
        <atom:dropdown>
            <button type="button">Many items</button>

            <atom:menu popover>
                @foreach (range(1, 40) as $n)
                    <atom:menu.item>Item {{ $n }}</atom:menu.item>
                @endforeach
            </atom:menu>
        </atom:dropdown>
    </div>

    <div data-probe="small" style="margin-top: 24px">
        <atom:dropdown>
            <button type="button">Few items</button>

            <atom:menu popover>
                @foreach (range(1, 3) as $n)
                    <atom:menu.item>Item {{ $n }}</atom:menu.item>
                @endforeach
            </atom:menu>
        </atom:dropdown>
    </div>

    <div data-probe="capped" style="margin-top: 24px">
        <atom:dropdown>
            <button type="button">Class cap</button>

            <atom:menu popover>
                @foreach (range(1, 40) as $n)
                    <atom:menu.item>Item {{ $n }}</atom:menu.item>
                @endforeach
            </atom:menu>
        </atom:dropdown>
    </div>

    <div data-probe="inline" style="margin-top: 24px">
        <atom:dropdown>
            <button type="button">Inline cap</button>

            <atom:menu popover style="max-height: 120px; overflow-y: auto">
                @foreach (range(1, 40) as $n)
                    <atom:menu.item>Item {{ $n }}</atom:menu.item>
                @endforeach
            </atom:menu>
        </atom:dropdown>
    </div>

    <div data-probe="select" style="margin-top: 24px; width: 300px">
        <atom:select variant="listbox" :options="collect(range(1, 60))->map(fn ($n) => ['value' => $n, 'label' => 'Option '.$n])->all()" />
    </div>

    {{-- Panels with a search row over their own scroll region. Each is hidden but
         the one under test, then put near the top of a short window, so the whole
         panel is capped well below its natural height. --}}
    <div data-probe="select-searchable" style="margin-top: 24px; width: 300px">
        <atom:select variant="listbox" searchable :options="collect(range(1, 80))->map(fn ($n) => ['value' => $n, 'label' => 'Option '.$n])->all()" />
    </div>

    <div data-probe="filter-searchable" style="margin-top: 24px; width: 300px">
        <atom:select variant="filter" searchable :options="collect(range(1, 80))->map(fn ($n) => ['value' => $n, 'label' => 'Choice '.$n])->all()" />
    </div>

    <div data-probe="color" style="margin-top: 24px; width: 300px">
        <atom:input.color />
    </div>

    <div data-probe="mention" style="margin-top: 24px; width: 300px">
        <button type="button" data-mention-anchor>Mention anchor</button>
        <atom:tiptap.mention :options="collect(range(1, 60))->map(fn ($n) => 'Person '.$n)->all()" />
    </div>
</div>

<div data-spacer style="height: 2000px"></div>

@livewireScripts
</atom:html>
