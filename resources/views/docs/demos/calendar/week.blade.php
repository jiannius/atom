@php
$monday = \Jiannius\Atom\Services\Calendar::now()->startOfWeek();
$at = fn (int $day, int $hour, int $minute = 0) => $monday->copy()->addDays($day)->setTime($hour, $minute);

$items = [
    ['id' => 1, 'title' => 'Offsite', 'start' => $monday->copy()->addDays(1)->toDateString(), 'end' => $monday->copy()->addDays(3)->toDateString(), 'color' => 'purple'],
    ['id' => 2, 'title' => 'Invoice overdue', 'start' => $monday->copy()->addDays(4)->toDateString(), 'danger' => true],
    ['id' => 3, 'title' => 'Team standup', 'start' => $at(0, 9, 30), 'end' => $at(0, 10), 'color' => 'blue'],
    ['id' => 4, 'title' => 'Planning', 'start' => $at(2, 9), 'end' => $at(2, 10, 30), 'color' => 'blue'],
    ['id' => 5, 'title' => 'Design review', 'start' => $at(2, 10), 'end' => $at(2, 11, 30), 'color' => 'orange'],
    ['id' => 6, 'title' => 'Client call', 'start' => $at(2, 10), 'end' => $at(2, 11), 'color' => 'red'],
    ['id' => 7, 'title' => 'Lunch with Sam', 'start' => $at(3, 12, 30), 'end' => $at(3, 13, 30), 'color' => 'yellow'],
    ['id' => 8, 'title' => 'Call the bank', 'start' => $at(3, 15)],
    ['id' => 9, 'title' => 'Quick check-in', 'start' => $at(4, 16), 'end' => $at(4, 16, 10), 'icon' => 'calendar'],
];
@endphp

<atom:calendar :views="['month', 'week', 'day']" view="week" :items="$items" />
