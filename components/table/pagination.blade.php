@props([
    'paginate' => null,
    'maxRows' => [50, 100, 200, 400],
    'sticky' => true,
])

@php
app('atom')->requireTrait(\Jiannius\Atom\Traits\WithTable::class, '<atom:table.pagination>');
app('atom')->requireTrait(\Livewire\WithPagination::class, '<atom:table.pagination>');
@endphp

{{-- The table's pager is the standalone one, pinned by default, with the rows-per-page menu between the summary and the page links. --}}
<atom:pagination :paginator="$paginate" :sticky="$sticky" {{ $attributes->merge(['data-atom-table-pager' => true]) }}>
    <x-slot:rows>
        @if ($maxRows)
            <atom:dropdown>
                <atom:button variant="ghost" size="sm">
                    <span x-text="$wire._table.max_rows"></span> <span>{{ t('atom::pagination.rows') }}</span> <span>/ {{ t('atom::pagination.page') }}</span> <atom:icon.dropdown />
                </atom:button>

                <atom:menu popover>
                    @foreach ($maxRows as $maxRow)
                        <atom:menu.item wire:click="$set('_table.max_rows', {{ $maxRow }})">
                            {{ $maxRow }} {{ t('rows') }} / {{ t('atom::pagination.page') }}
                        </atom:menu.item>
                    @endforeach
                </atom:menu>
            </atom:dropdown>
        @endif
    </x-slot:rows>
</atom:pagination>
