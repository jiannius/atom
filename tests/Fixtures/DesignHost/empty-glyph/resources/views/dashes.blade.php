{{-- empty-glyph: a dash typed for a missing value. A line marked `expect:` is a finding; every other line must stay clean. --}}
<atom:page>
    <atom:dd.group>
        <atom:dd label="Due">{{ $invoice->due ?? '—' }}</atom:dd> {{-- expect: empty-glyph --}}
        <atom:dd label="Site">{{ $site ?: '--' }}</atom:dd> {{-- expect: empty-glyph --}}
        <atom:dd label="Owner">{{ $owner ?: '-' }}</atom:dd> {{-- expect: empty-glyph --}}
        <atom:dd label="Ref">{!! $ref ?? '–' !!}</atom:dd> {{-- expect: empty-glyph --}}
        <atom:dd label="Site">—</atom:dd> {{-- expect: empty-glyph --}}
        <atom:dd label="Site">&mdash;</atom:dd> {{-- expect: empty-glyph --}}
        <atom:dd label="Site" filler="--">{{ $site }}</atom:dd> {{-- expect: empty-glyph --}}
        <atom:dd label="Site" :filler="'—'">{{ $site }}</atom:dd> {{-- expect: empty-glyph --}}
    </atom:dd.group>

    <atom:table.cell>-</atom:table.cell> {{-- expect: empty-glyph --}}
    <atom:table.cell> <span>--</span> </atom:table.cell> {{-- expect: empty-glyph --}}
    <atom:table.cell filler="-">{{ $x }}</atom:table.cell> {{-- expect: empty-glyph --}}

    <atom:page.header title="Job" :facts="[['label' => 'Site', 'value' => '—']]" /> {{-- expect: empty-glyph --}}
    <atom:page.header title="Job" :facts="[['label' => 'Site', 'value' => $site ?: '--']]" /> {{-- expect: empty-glyph --}}

    <p>{{ $a ?? '&mdash;' }}</p> {{-- expect: empty-glyph --}}
    <p>{{ $a ?: '&#8212;' }}</p> {{-- expect: empty-glyph --}}
    <p>@if ($a) {{ $a }} @else — @endif</p> {{-- expect: empty-glyph --}}
    <p>@if ($a) {{ $a }} @else <span class="text-sm">--</span> @endif</p> {{-- expect: empty-glyph --}}
    <p>@if ($a) {!! $a !!} @else &mdash; @endif</p> {{-- expect: empty-glyph --}}

    <p>{{ $sign ?: '-' }} {{ $amount }}</p> {{-- expect: empty-glyph --}}
    <p>{{ $sign ?: '-' }}<b>{{ $amount }}</b></p> {{-- expect: empty-glyph --}}
    <p>{{ $sign ?: '--' }}{{ $amount }}</p> {{-- expect: empty-glyph --}}
    <p>{{ $sign ?: '—' }}5</p> {{-- expect: empty-glyph --}}

    {{-- look-alikes: nothing here is a missing value typed in --}}
    <p>{{ $sign ?: '-' }}{{ $amount }}</p>
    <p>{!! $sign ?: '-' !!}{{ $amount }}</p>
    <p>{{ $sign ?: '-' }}{!! $amount !!}</p>
    <p>{{ $sign ?: '-' }}5.00</p>
    <p>@if ($a) yes @else — @endif</p>
    <p>@if ($a) {{ $a }} @else n/a @endif</p>
    <p>@if ($a) {{ $a }} @else - @endif</p>
    <p>@if ($a) @if ($b) {{ $b }} @endif @else — @endif</p>
    <p>@if ($a) {{ $a }} @elseif ($b) {{ $b }} @else — @endif</p>
    <p>{{ $a ?? '&hellip;' }}</p>
    <p>{{ $from }}–{{ $to }}</p>
    <p>{{ $a - $b }} and {{ $balance ?? 0 }}</p>
    <p>Takes 2–3 days -- or 4 - 5 on a holiday</p>
    <p>{{ implode(' - ', $parts) }}</p>
    <p>{{ $a ? $a : '—' }}</p>
    <atom:dd label="Site" filler="n/a">{{ $site }}</atom:dd>
    <atom:dd label="Site" :filler="false">{{ $site }}</atom:dd>
    <atom:dd label="Site">{{ $site }}</atom:dd>
    <atom:table.cell>-5</atom:table.cell>
    <atom:table.cell>Total -</atom:table.cell>
    <atom:table.cell>{{ $x ?? 'n/a' }}</atom:table.cell>
    <atom:table.cell>&nbsp;</atom:table.cell>
    <atom:table.cell>@if ($x) - @endif</atom:table.cell>
    <atom:button>-</atom:button>
    <atom:page.header title="Job" :facts="[['label' => 'Site', 'value' => 'n/a'], ['label' => 'Gap', 'value' => $a - $b]]" />
    <p>Write <code>$x ?? '—'</code> never.</p>
    <pre>
        <atom:table.cell>-</atom:table.cell>
    </pre>

    @php
        $separator = $custom ?: '-';
        $labels = ['sep' => '-', 'dash' => '–'];
    @endphp
</atom:page>
