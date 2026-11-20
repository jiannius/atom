@php
$items = [
    ['label' => 'Left', 'click' => new \Illuminate\Support\HtmlString('setTextAlign('.js('left').')'), 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" class="size-4" viewBox="0 0 24 24" fill="currentColor"><path d="M3 4H21V6H3V4ZM3 19H17V21H3V19ZM3 14H21V16H3V14ZM3 9H17V11H3V9Z"></path></svg>'],
    ['label' => 'Center', 'click' => new \Illuminate\Support\HtmlString('setTextAlign('.js('center').')'), 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" class="size-4" viewBox="0 0 24 24" fill="currentColor"><path d="M3 4H21V6H3V4ZM5 19H19V21H5V19ZM3 14H21V16H3V14ZM5 9H19V11H5V9Z"></path></svg>'],
    ['label' => 'Right', 'click' => new \Illuminate\Support\HtmlString('setTextAlign('.js('right').')'), 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" class="size-4" viewBox="0 0 24 24" fill="currentColor"><path d="M3 4H21V6H3V4ZM7 19H21V21H7V19ZM3 14H21V16H3V14ZM7 9H21V11H7V9Z"></path></svg>'],
    ['label' => 'Justify', 'click' => new \Illuminate\Support\HtmlString('setTextAlign('.js('justify').')'), 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" class="size-4" viewBox="0 0 24 24" fill="currentColor"><path d="M3 4H21V6H3V4ZM3 19H21V21H3V19ZM3 14H21V16H3V14ZM3 9H21V11H3V9Z"></path></svg>'],
];
@endphp

<atom:tiptap.toolbar._menu label="Text Align" :icon="$items[0]['icon']" :items="$items" />
