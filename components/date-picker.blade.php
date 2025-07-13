@php
$utc = $attributes->get('utc', true);
$size = $attributes->get('size');
$time = $attributes->get('time', false);
$range = $attributes->get('range', false);
$toggler = $attributes->get('toggler', false);
$label = $attributes->get('label');
$inline = $attributes->get('inline');
$caption = $attributes->get('caption');
$prefix = $attributes->get('prefix');
$suffix = $attributes->get('suffix');
$placeholder = $attributes->get('placeholder', pick([
    'select-date-range' => $range,
    'select-date-time' => $time,
    'select-date' => true,
]));

$field = $attributes->get('field') ?? $attributes->wire('model')->value();
$required = $attributes->get('required') ?? $this->form['required'][$field] ?? false;
$error = $attributes->get('error') ?? $this->errors[$field] ?? null;

$classes = $attributes->classes()
    ->add('w-full py-2 pl-3 pr-10 text-zinc-700 text-left cursor-default')
    ->add('border border-zinc-200 border-b-zinc-300/80 rounded-lg shadow-sm bg-white')
    ->add('focus:outline-none focus:border-primary group-focus/input:border-primary hover:border-primary-300')
    ->add($size === 'sm' ? 'h-8 text-sm' : 'h-10')
    ->add('invalid:border-red-400 group-has-[[data-atom-error]]/field:border-red-400')
    ;

$attrs = $attributes
    ->class($classes)
    ->except(['size'])
    ;
@endphp

@if ($label || $caption)
    <atom:_input.field
        :label="$label"
        :caption="$caption"
        :inline="$inline"
        :required="$required"
        :error="$error">
        <atom:_date-picker :attributes="$attributes->except(['label', 'caption', 'error', 'inline'])"/>
    </atom:_input.field>
@elseif ($prefix || $suffix)
    <atom:_input.prefix :prefix="$prefix" :suffix="$suffix">
        <atom:_date-picker :attributes="$attributes->except(['prefix', 'suffix'])"/>
    </atom:_input.prefix>
@else
    <div
    wire:ignore
    x-data="datepicker({
        utc: {{ js($utc) }},
        time: {{ js($time) }},
        range: {{ js($range) }},
        toggler: {{ js($toggler) }},
        @if ($attributes->wire('model')->value())
        value: @entangle($attributes->wire('model')),
        @endif
    })"
    x-modelable="value"
    x-on:click.away="close()"
    x-on:keydown.down.prevent="open()"
    x-on:keydown.enter.prevent="open()"
    x-on:keydown.space.prevent="open()"
    x-on:keydown.esc.prevent="close()"
    class="group/datepicker relative w-full"
    data-atom-date-picker
    {{ $attrs->whereDoesntStartWith('wire:model')->except('class') }}>
        <div data-anchor class="relative">
            <input type="text" readonly
                x-bind:value="display"
                x-on:click="open()"
                placeholder="{{ t($placeholder) }}"
                {{ $attrs->only('class') }}>

            <div class="z-1 absolute top-0 bottom-0 flex items-center justify-center pr-3 right-0">
                <div
                    x-show="value"
                    x-on:click.stop="clear()"
                    class="flex items-center justify-center w-full h-full">
                    <atom:icon close size="14"/>
                </div>

                <div class="pointer-events-none flex items-center justify-center w-full h-full text-zinc-400">
                    <atom:icon calendar x-show="!value"/>
                </div>
            </div>
        </div>

        <atom:popover>
            <atom:menu class="max-w-screen-md">
                <div class="divide-y">
                    <div class="flex divide-x overflow-auto max-w-xl md:max-w-full">
                        <template x-if="config.range" hidden>
                            <div class="shrink-0 w-40 pb-2">
                                <template x-for="(item, key) in shortcuts" hidden>
                                    <atom:menu-item x-on:click="selectShortcut(item[0], item[1])">
                                        <div x-text="t(key)"></div>
                                    </atom:menu-item>
                                </template>
                            </div>
                        </template>

                        <div>
                            <div x-ref="from" class="w-[300px]"></div>
                            <div
                                x-show="config.time"
                                x-on:input.stop.debounce="config.time && select()">
                                <atom:separator/>
                                <div class="p-2">
                                    <atom:_time-picker x-model="picker.from.time"/>
                                </div>
                            </div>
                        </div>

                        <template x-if="config.range" hidden>
                            <div>
                                <div x-ref="to" class="w-[300px]"></div>
                                <div
                                    x-show="config.time"
                                    x-on:input.stop.debounce="config.time && select()"
                                    class="px-2 pb-2">
                                    <atom:separator/>
                                    <div class="p-2">
                                        <atom:_time-picker x-model="picker.to.time"/>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>

                    <template x-if="config.toggler" hidden>
                        <div class="pt-3 pb-2 px-3 flex items-center gap-4">
                            <atom:toggle x-model="config.time">@t('time')</atom:toggle>
                            <atom:toggle x-model="config.range" x-on:input.stop="toggleRange()">@t('date-range')</atom:toggle>
                        </div>
                    </template>
                </div>
            </atom:menu>
        </atom:popover>
    </div>
@endif
