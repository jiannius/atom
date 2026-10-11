@php
$day = fn (int $n, ?int $hour = null, int $minute = 0) => $hour === null
    ? \Jiannius\Atom\Services\Calendar::now()->startOfMonth()->addDays($n)->toDateString()
    : \Jiannius\Atom\Services\Calendar::now()->startOfMonth()->addDays($n)->setTime($hour, $minute);

$items = [
    ['id' => 1, 'title' => 'Sprint', 'start' => $day(3), 'end' => $day(8), 'color' => 'purple'],
    ['id' => 2, 'title' => 'Dentist', 'start' => $day(10, 9, 30), 'end' => $day(10, 10, 15), 'color' => 'red'],
    ['id' => 3, 'title' => 'Launch', 'start' => $day(14), 'color' => 'green', 'icon' => 'check'],
    ['id' => 4, 'title' => 'Invoice overdue', 'start' => $day(16), 'danger' => true],
    ['id' => 5, 'title' => 'Lunch with Sam', 'start' => $day(20, 12, 30), 'end' => $day(20, 13, 30)],
    ['id' => 6, 'title' => 'Open the docs', 'start' => $day(23), 'href' => '/atom/docs'],
];
@endphp

<atom:calendar :items="$items" />
