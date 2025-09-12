<atom:dropdown>
    <atom:editor.button label="Font Size">
        <svg xmlns="http://www.w3.org/2000/svg" class="size-5" viewBox="0 0 24 24" fill="currentColor"><path d="M11.246 15H4.75416L2.75416 20H0.600098L7.0001 4H9.0001L15.4001 20H13.246L11.246 15ZM10.446 13L8.0001 6.88516L5.55416 13H10.446ZM21.0001 12.5351V12H23.0001V20H21.0001V19.4649C20.4118 19.8052 19.7287 20 19.0001 20C16.791 20 15.0001 18.2091 15.0001 16C15.0001 13.7909 16.791 12 19.0001 12C19.7287 12 20.4118 12.1948 21.0001 12.5351ZM19.0001 18C20.1047 18 21.0001 17.1046 21.0001 16C21.0001 14.8954 20.1047 14 19.0001 14C17.8955 14 17.0001 14.8954 17.0001 16C17.0001 17.1046 17.8955 18 19.0001 18Z"></path></svg>
    </atom:editor.button>

    <atom:menu popover>
        @foreach ([
            'smaller' => 'xs',
            'small' => 'sm',
            'medium' => 'md',
            'large' => 'lg',
            'extra-large' => 'xl',
        ] as $label => $size)
            <atom:menu.item x-on:click="commands().setFontSize({{ js($size) }})">
                {{ t($label) }}
            </atom:menu.item>
        @endforeach

        <atom:separator/>

        <div class="p-2" x-on:click.stop>
            <input
            type="number"
            class="appearance-none focus:outline-none text-sm w-full"
            placeholder="{{ t('Size') }}"
            step=".1"
            max="999"
            x-on:input.stop
            x-on:keydown.enter.prevent="() => {
                let val = +$event.target.value
                if (val < 999) {
                    commands().setFontSize(`${val}px`)
                    $event.target.value = ''
                }
            }">
        </div>
    </atom:menu>
</atom:dropdown>
