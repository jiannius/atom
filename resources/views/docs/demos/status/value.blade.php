@php
$states = [
    ['label' => 'Draft', 'color' => 'gray'],
    ['label' => 'In progress', 'tone' => 'info'],
    ['label' => 'Paid', 'color' => 'green'],
    ['label' => 'Waiting for parts', 'color' => 'yellow'],
    ['label' => 'Overdue', 'color' => 'red'],
];
@endphp

<div class="flex flex-wrap gap-2">
    @foreach ($states as $state)
        <atom:status :value="$state"/>
    @endforeach
</div>
