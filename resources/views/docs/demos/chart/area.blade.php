@php
$data = [
    ['label' => 'Mon', 'value' => 32, 'tooltip' => '32 sales'],
    ['label' => 'Tue', 'value' => 48, 'tooltip' => '48 sales'],
    ['label' => 'Wed', 'value' => 27, 'tooltip' => '27 sales'],
    ['label' => 'Thu', 'value' => 61, 'tooltip' => '61 sales'],
    ['label' => 'Fri', 'value' => 44, 'tooltip' => '44 sales'],
];
@endphp

<atom:chart type="area" :data="$data" color="orange" :max="['value' => 60, 'label' => 'Goal']" :min="['value' => 0]"/>
