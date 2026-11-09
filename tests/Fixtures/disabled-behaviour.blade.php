{{-- One case per page. The two sentinels bracket it so a tab walk knows where the case starts and ends; the page changes nothing about
     the control inside it. `data-state` is what the server rendered last, so a spec can wait for a morph to land. --}}
<div data-testid="root" data-state="{{ $off ? 'disabled' : 'enabled' }}" data-route="{{ $route }}" class="space-y-6">
    <button type="button" data-sentinel="before" class="rounded border px-2 py-1 text-sm">Before</button>

    <div data-case="{{ $def['id'] }}" class="max-w-xl">
        @if ($xmodel)
            <div x-data="{ pv: {{ \Illuminate\Support\Js::from($v) }} }" data-xmodel>{!! $html !!}</div>
        @else
            {!! $html !!}
        @endif
    </div>

    <button type="button" data-sentinel="after" class="rounded border px-2 py-1 text-sm">After</button>
    <span data-renders class="text-sm">{{ $renders }}</span>
    <span data-submits class="text-sm">{{ $submits }}</span>
</div>
