@php
$day = fn (int $n, int $hour, int $minute = 0) => \Jiannius\Atom\Services\Calendar::now()->startOfMonth()->addDays($n)->setTime($hour, $minute);

$items = [
    ['id' => 1, 'title' => 'Team lunch', 'start' => $day(4, 13), 'end' => $day(4, 13, 45), 'color' => 'green'],
    ['id' => 2, 'title' => 'Stock take', 'start' => $day(11, 8, 30), 'end' => $day(11, 11), 'color' => 'orange'],
    ['id' => 3, 'title' => 'Supplier call', 'start' => $day(18, 15, 15), 'color' => 'blue'],
];
@endphp

<atom:calendar
week-start="sunday"
:weekend="['friday', 'saturday']"
time-format="H:i"
date-format="D, d/m/Y"
:items="$items" />
