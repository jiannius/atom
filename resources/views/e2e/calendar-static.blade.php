<atom:html title="E2E: Calendar (static)" :vite="[]" :dark="false" class="min-h-screen bg-white">
{{-- A calendar with no bound property: plain Blade, so there is no Livewire component to navigate or re-render. --}}
<style>
    .flex { display: flex }
    .inline-flex { display: inline-flex }
    .items-center { align-items: center }
    .items-start { align-items: flex-start }
    .justify-between { justify-content: space-between }
    .justify-center { justify-content: center }
    .flex-wrap { flex-wrap: wrap }
    .shrink-0 { flex-shrink: 0 }
    .min-w-0 { min-width: 0 }
    .w-full { width: 100% }
    .w-64 { width: 16rem }
    .h-5 { height: 1.25rem }
    .h-6 { height: 1.5rem }
    .size-1\.5 { width: .375rem; height: .375rem }
    .size-2 { width: .5rem; height: .5rem }
    .size-3 { width: .75rem; height: .75rem }
    .size-4 { width: 1rem; height: 1rem }
    .size-5 { width: 1.25rem; height: 1.25rem }
    .size-6 { width: 1.5rem; height: 1.5rem }
    .size-8 { width: 2rem; height: 2rem }
    .size-9 { width: 2.25rem; height: 2.25rem }
    .truncate { overflow: hidden; text-overflow: ellipsis; white-space: nowrap }
    .gap-1 { gap: .25rem }
    .gap-1\.5 { gap: .375rem }
    .gap-3 { gap: .75rem }
    .p-3 { padding: .75rem }
    .p-4 { padding: 1rem }
    .px-1 { padding-inline: .25rem }
    .px-1\.5 { padding-inline: .375rem }
    .text-xs { font-size: .75rem }
    .text-left { text-align: left }
    .rounded-full { border-radius: 9999px }
    .cursor-pointer { cursor: pointer }
    .relative { position: relative }
    [data-atom-icon] svg { width: 100%; height: 100% }
</style>
<div class="p-4" x-data="{ clicked: [] }" x-on:calendar-item-click="clicked.push($event.detail.id)">
    <div>Clicked: <span data-clicked x-text="clicked.join(',')"></span></div>

    <atom:calendar
    month="2026-10"
    :items="[
        ['id' => 'a', 'title' => 'Static item', 'start' => '2026-10-09', 'color' => 'green'],
        ['id' => 'b', 'title' => 'Opens the docs', 'start' => '2026-10-12', 'href' => '/atom/docs'],
        ['id' => 'c', 'title' => 'Lunch', 'start' => '2026-10-12T12:30+08:00', 'end' => '2026-10-12T13:30+08:00'],
        ['id' => 'd', 'title' => 'Standup', 'start' => '2026-10-12T09:00+08:00'],
        ['id' => 'e', 'title' => 'Review', 'start' => '2026-10-12T15:00+08:00'],
        ['id' => 'f', 'title' => 'Dinner', 'start' => '2026-10-12T19:00+08:00'],
    ]"
    data-probe="static" />

    {{-- the same calendar as a flex item, where a container-type: inline-size element has no content width to offer --}}
    <div class="flex" style="width: 900px" data-flex-parent>
        <atom:calendar month="2026-10" data-probe="flex" />
    </div>

    {{-- a host's own width utility on the calendar, inside a flex parent; the test supplies the utility's rule --}}
    <div class="flex" style="width: 900px">
        <atom:calendar class="w-96" month="2026-10" data-probe="sized" />
    </div>

    {{-- one with a name of its own: its ids are built from it --}}
    <atom:calendar name="Team plan" month="2026-10" :items="[['id' => 't1', 'title' => 'Team item', 'start' => '2026-10-12']]" data-probe="named" />

    {{-- names that a slug alone would give one id: the same words in another case or with other punctuation, names with no letters of
         their own, and a number, which is what the engine numbers the calendars that have no name by --}}
    <atom:calendar name="team-plan" month="2026-10" data-probe="slug-team-plan" />
    <atom:calendar name="日本語" month="2026-10" data-probe="name-cjk-1" />
    <atom:calendar name="团队" month="2026-10" data-probe="name-cjk-2" />
    <atom:calendar name="!!!" month="2026-10" data-probe="name-punctuation" />
    <atom:calendar name="1" month="2026-10" data-probe="name-number" />

    {{-- selectable: a day is for selecting a range, so a key opens no list, as a click opens none --}}
    <atom:calendar selectable month="2026-10" :items="[['id' => 's1', 'title' => 'Selectable item', 'start' => '2026-10-12']]" data-probe="selectable" />

    {{-- a selectable calendar inside a form: a press in that form, outside the calendar, is a press elsewhere on the page for it --}}
    <form x-on:submit.prevent data-form>
        <atom:calendar selectable month="2026-10" :items="[['id' => 'f1', 'title' => 'In a form', 'start' => '2026-10-12']]" data-probe="in-form" />
        <button type="button" data-form-button>A button of the form</button>
    </form>

    {{-- a calendar inside an atom modal, a native dialog: Escape has to close the day list and leave the modal --}}
    <atom:button x-on:click="$dispatch('atom-modal-show', { name: 'calendar-modal' })" data-open-modal>Open calendar</atom:button>

    <atom:modal name="calendar-modal" style="width: 760px" data-modal>
        <atom:calendar
        month="2026-10"
        :items="[
            ['id' => 'm1', 'title' => 'First', 'start' => '2026-10-12'],
            ['id' => 'm2', 'title' => 'Second', 'start' => '2026-10-12'],
            ['id' => 'm3', 'title' => 'Third', 'start' => '2026-10-12'],
            ['id' => 'm4', 'title' => 'Fourth', 'start' => '2026-10-12'],
            ['id' => 'm5', 'title' => 'Fifth', 'start' => '2026-10-12'],
        ]"
        data-probe="modal" />
    </atom:modal>
</div>

@livewireScripts
</atom:html>
