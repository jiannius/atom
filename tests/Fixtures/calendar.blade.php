<div class="space-y-4">
    <div>
        {{ t('Renders') }}: <span data-renders>{{ $renders }}</span>
        <atom:button wire:click="bump" data-bump>Bump</atom:button>
        <atom:button wire:click="toggleExtra" data-toggle-extra>Toggle extra</atom:button>
        Date: <span data-date>{{ $date }}</span>
    </div>

    @if ($wired)
        {{-- the wiring the README documents: x-on on the tag, $wire methods, waitUntil --}}
        <atom:calendar
        wire:model="date"
        :views="['month', 'week', 'day']"
        :view="$view"
        selectable
        :items="fn ($from, $to) => $this->calendarItems($from, $to)"
        x-on:calendar-select="$event.detail.waitUntil($wire.create($event.detail.start, $event.detail.end, $event.detail.allDay))"
        x-on:calendar-item-move="$event.detail.waitUntil($wire.reschedule($event.detail.id, $event.detail.start, $event.detail.end))"
        x-on:calendar-item-resize="$event.detail.waitUntil($wire.reschedule($event.detail.id, $event.detail.start, $event.detail.end))"
        data-probe="calendar" />
    @else
        <atom:calendar
        wire:model="date"
        :views="['month', 'week', 'day']"
        :view="$view"
        :selectable="$selectable"
        :draggable="$draggable"
        :resizable="$draggable"
        :slot-duration="$slot"
        :week-start="$weekStart"
        :items="fn ($from, $to) => $this->calendarItems($from, $to)"
        data-probe="calendar">
            {{-- host toolbar content: it is server-rendered and morphs with the page, unlike the calendar's body --}}
            <span data-host-slot>Slot renders: {{ $renders }}</span>
        </atom:calendar>
    @endif

    <div data-created>{{ json_encode($created) }}</div>
    <div data-rescheduled>{{ json_encode($rescheduled) }}</div>
</div>
