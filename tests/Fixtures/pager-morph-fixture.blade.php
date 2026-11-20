<div>
    <button type="button" wire:click="show('one')" data-show="one">One page</button>
    <button type="button" wire:click="show('many')" data-show="many">Many pages</button>

    <div data-testid="standalone"><atom:pagination :paginator="$pager" /></div>
    <div data-testid="table-pager"><atom:table.pagination :paginate="$pager" /></div>
    <div data-testid="table-pager-bare"><atom:table.pagination :paginate="$pager" :max-rows="false" /></div>
</div>
