@php
$busy = collect(range(1, 6))->map(fn (int $n) => [
    'id' => 'busy-'.$n,
    'title' => 'Appointment '.$n,
    'start' => \Jiannius\Atom\Services\Calendar::now()->startOfMonth()->addDays(11)->setTime(7 + $n * 2, 0),
    'end' => \Jiannius\Atom\Services\Calendar::now()->startOfMonth()->addDays(11)->setTime(8 + $n * 2, 0),
    'color' => ['blue', 'green', 'orange', 'red', 'purple', 'zinc'][$n - 1],
])->all();
@endphp

<atom:calendar week-start="sunday" time-format="H:i" date-format="D, d/m/Y" :items="$busy" />
