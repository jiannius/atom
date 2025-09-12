<div class="youtube-menu" x-init="$el.shouldShow = (editor) => (editor?.isActive('youtube'))">
    <div class="p-1 flex items-center gap-1 flex-wrap">
        @foreach ([
            '320x240' => [320, 240],
            '640x480' => [640, 480],
            '960x720' => [960, 720],
            '1280x960' => [1280, 960],
        ] as $label => $dim)
            <atom:editor.menu.button x-on:click="commands().updateAttributes('youtube', {
                width: {{ js($dim[0]) }},
                height: {{ js($dim[1]) }},
            })">
                <div class="font-medium text-sm">
                    {{ $label }}
                </div>
            </atom:editor.menu.button>
        @endforeach

        <atom:editor.menu.button label="Remove" x-on:click="commands().deleteSelection()">
            <svg xmlns="http://www.w3.org/2000/svg" class="size-5" viewBox="0 0 24 24" fill="currentColor"><path d="M17 6H22V8H20V21C20 21.5523 19.5523 22 19 22H5C4.44772 22 4 21.5523 4 21V8H2V6H7V3C7 2.44772 7.44772 2 8 2H16C16.5523 2 17 2.44772 17 3V6ZM18 8H6V20H18V8ZM9 11H11V17H9V11ZM13 11H15V17H13V11ZM9 4V6H15V4H9Z"></path></svg>
        </atom:editor.menu.button>
    </div>
</div>

