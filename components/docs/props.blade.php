@props([
    'props' => [],
])

@if (count($props))
    <atom:table :empty="false" class="mt-3">
        <x-slot:columns>
            <atom:table.column>Prop</atom:table.column>
            <atom:table.column>Default</atom:table.column>
        </x-slot:columns>

        <x-slot:rows>
            @foreach ($props as $prop)
                <atom:table.row>
                    <atom:table.cell><code class="text-sm">{{ $prop['name'] }}</code></atom:table.cell>
                    <atom:table.cell muted><code class="text-sm">{{ var_export($prop['default'], true) }}</code></atom:table.cell>
                </atom:table.row>
            @endforeach
        </x-slot:rows>
    </atom:table>
@else
    <atom:caption class="mt-3">No props declared — attributes pass through to the root element.</atom:caption>
@endif
