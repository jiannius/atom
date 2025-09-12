<atom:dropdown>
    <atom:editor.button label="Heading">
        <svg xmlns="http://www.w3.org/2000/svg" class="size-5" viewBox="0 0 24 24" fill="currentColor"><path d="M17 11V4H19V21H17V13H7V21H5V4H7V11H17Z"></path></svg>
    </atom:editor.button>

    <atom:menu popover>
        @foreach ([
            [
                'level' => 1,
                'label' => 'Heading 1',
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" class="size-5" viewBox="0 0 24 24" fill="currentColor"><path d="M13 20H11V13H4V20H2V4H4V11H11V4H13V20ZM21.0005 8V20H19.0005L19 10.204L17 10.74V8.67L19.5005 8H21.0005Z"></path></svg>',
            ],
            [
                'level' => 2,
                'label' => 'Heading 2',
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" class="size-5" viewBox="0 0 24 24" fill="currentColor"><path d="M4 4V11H11V4H13V20H11V13H4V20H2V4H4ZM18.5 8C20.5711 8 22.25 9.67893 22.25 11.75C22.25 12.6074 21.9623 13.3976 21.4781 14.0292L21.3302 14.2102L18.0343 18H22V20H15L14.9993 18.444L19.8207 12.8981C20.0881 12.5908 20.25 12.1893 20.25 11.75C20.25 10.7835 19.4665 10 18.5 10C17.5818 10 16.8288 10.7071 16.7558 11.6065L16.75 11.75H14.75C14.75 9.67893 16.4289 8 18.5 8Z"></path></svg>',
            ],
            [
                'level' => 3,
                'label' => 'Heading 3',
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" class="size-5" viewBox="0 0 24 24" fill="currentColor"><path d="M22 8L21.9984 10L19.4934 12.883C21.0823 13.3184 22.25 14.7728 22.25 16.5C22.25 18.5711 20.5711 20.25 18.5 20.25C16.674 20.25 15.1528 18.9449 14.8184 17.2166L16.7821 16.8352C16.9384 17.6413 17.6481 18.25 18.5 18.25C19.4665 18.25 20.25 17.4665 20.25 16.5C20.25 15.5335 19.4665 14.75 18.5 14.75C18.214 14.75 17.944 14.8186 17.7056 14.9403L16.3992 13.3932L19.3484 10H15V8H22ZM4 4V11H11V4H13V20H11V13H4V20H2V4H4Z"></path></svg>',
            ],
            [
                'level' => 4,
                'label' => 'Heading 4',
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" class="size-5" viewBox="0 0 24 24" fill="currentColor"><path d="M13 20H11V13H4V20H2V4H4V11H11V4H13V20ZM22 8V16H23.5V18H22V20H20V18H14.5V16.66L19.5 8H22ZM20 11.133L17.19 16H20V11.133Z"></path></svg>',
            ],
        ] as $item)
            <atom:menu.item x-on:click="commands().toggleHeading({ level: {{ js(data_get($item, 'level')) }} })">
                <div class="flex items-center gap-3">
                    {!! data_get($item, 'icon') !!}
                    <div class="grow">{{ t(data_get($item, 'label')) }}</div>
                </div>
            </atom:menu.item>
        @endforeach

        <atom:menu.item x-on:click="commands().setParagraph()">
            <div class="flex items-center gap-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="size-5" viewBox="0 0 24 24" fill="currentColor"><path d="M12 6V21H10V16C6.68629 16 4 13.3137 4 10C4 6.68629 6.68629 4 10 4H20V6H17V21H15V6H12ZM10 6C7.79086 6 6 7.79086 6 10C6 12.2091 7.79086 14 10 14V6Z"></path></svg>
                <div class="grow">{{ t('Paragraph') }}</div>
            </div>
        </atom:menu.item>
    </atom:menu>
</atom:dropdown>
