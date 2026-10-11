<div class="space-y-4">
    <div>
        {{ t('Renders') }}: <span data-renders>{{ $renders }}</span>
        <atom:button wire:click="bump" data-bump>Bump</atom:button>
        <atom:button wire:click="toggleExtra" data-toggle-extra>Toggle extra</atom:button>
        Date: <span data-date>{{ $date }}</span>
    </div>

    <atom:calendar
    wire:model="date"
    :views="['month', 'week', 'day']"
    :view="$view"
    :selectable="$selectable"
    :week-start="$weekStart"
    :items="fn ($from, $to) => $this->calendarItems($from, $to)"
    data-probe="calendar">
        {{-- host toolbar content: it is server-rendered and morphs with the page, unlike the calendar's body --}}
        <span data-host-slot>Slot renders: {{ $renders }}</span>
    </atom:calendar>

    <div data-created>{{ json_encode($created) }}</div>
</div>
