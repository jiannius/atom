<div class="p-4">
    <atom:calendar
    wire:model="date"
    :views="['month', 'week', 'day']"
    :view="$view"
    :scroll-time="request('scroll', '07:00')"
    :items="fn ($from, $to) => $this->calendarItems($from, $to)"
    data-probe="look" />
</div>
