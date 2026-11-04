@php
$doc = ['type' => 'doc', 'content' => [
    ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Site notes']]],
    ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Gate code is on the door, ask for the foreman.']]],
]];
@endphp

<div class="max-w-md">
    <atom:prose :content="$doc"/>
</div>
