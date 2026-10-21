@php
// The restyled text, editor and chart (V4-3d) on the consumer's Tailwind: ?tw=floor|latest, ?density=compact|comfortable, ?dark=1
// (also emulate prefers-color-scheme: dark), ?accent=%23rrggbb (a host's brand colour; indigo here), ?section=text|links|editor|chart
// to draw one section. Invented, generic data. The calendar has its own sheet (calendar-look) and the docs chrome is read on /atom/docs.
$tw = in_array(request()->query('tw'), ['floor', 'latest'], true) ? request()->query('tw') : null;
$density = in_array(request()->query('density'), ['compact', 'comfortable'], true) ? request()->query('density') : 'compact';
$accent = preg_match('/^#[0-9a-f]{6}$/i', (string) request()->query('accent')) ? request()->query('accent') : '#4c55c9';
$section = request()->query('section');
$show = fn (string $name) => $section === null || $section === $name;

$text = fn (string $text, array $marks = []) => ['type' => 'text', 'text' => $text] + ($marks ? ['marks' => $marks] : []);
$doc = ['type' => 'doc', 'content' => [
    ['type' => 'heading', 'attrs' => ['level' => 1], 'content' => [$text('Quarterly summary')]],
    ['type' => 'paragraph', 'content' => [
        $text('Revenue grew in every region. Read the '),
        $text('full report', [['type' => 'link', 'attrs' => ['href' => 'https://example.com/report']]]),
        $text(' and ask '),
        ['type' => 'mention', 'attrs' => ['id' => '1', 'label' => 'Jane Cooper']],
        $text(' about the numbers, which are '),
        $text('bold', [['type' => 'bold']]),
        $text(' where they matter.'),
    ]],
    ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [$text('By region')]],
    ['type' => 'heading', 'attrs' => ['level' => 3], 'content' => [$text('North')]],
    ['type' => 'heading', 'attrs' => ['level' => 4], 'content' => [$text('Retail')]],
    ['type' => 'blockquote', 'content' => [['type' => 'paragraph', 'content' => [$text('A quoted line from a customer.')]]]],
    ['type' => 'bulletList', 'content' => [
        ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [$text('First point')]]]],
        ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [$text('Second point')]]]],
    ]],
    ['type' => 'horizontalRule'],
    ['type' => 'table', 'content' => [
        ['type' => 'tableRow', 'content' => [
            ['type' => 'tableHeader', 'content' => [['type' => 'paragraph', 'content' => [$text('Region')]]]],
            ['type' => 'tableHeader', 'content' => [['type' => 'paragraph', 'content' => [$text('Total')]]]],
        ]],
        ['type' => 'tableRow', 'content' => [
            ['type' => 'tableCell', 'content' => [['type' => 'paragraph', 'content' => [$text('North')]]]],
            ['type' => 'tableCell', 'content' => [['type' => 'paragraph', 'content' => [$text('1,280.00')]]]],
        ]],
    ]],
    ['type' => 'paragraph', 'content' => [$text('Use '), $text('the flag', [['type' => 'code']]), $text(' to turn it on.')]],
    ['type' => 'codeBlock', 'content' => [$text('php artisan atom:purge-editor-images')]],
]];
@endphp

<atom:html title="E2E: Text, editor and chart" :vite="[]" :dark="request()->query('dark') === '1'" :density="$density" fonts="geist" :styles="$tw ? ['/atom/e2e/consumer-css/'.$tw] : []">
<style>:root { --color-accent: {{ $accent }}; }</style>

<div class="mx-auto grid max-w-4xl gap-10 p-8" data-testid="sheet">
    @if ($show('text'))
    <section class="grid gap-4" data-testid="text">
        <h2 class="text-lg font-semibold">Text</h2>

        <div class="grid gap-2" data-testid="headings">
            <atom:heading size="xs" data-testid="h-xs">Extra small heading</atom:heading>
            <atom:heading size="sm" data-testid="h-sm">Small heading</atom:heading>
            <atom:heading data-testid="h-default">Default heading</atom:heading>
            <atom:heading size="lg" data-testid="h-lg">Large heading</atom:heading>
            <atom:heading size="xl" level="2" data-testid="h-xl">Extra large heading</atom:heading>
            <atom:heading size="lg" data-testid="h-sub">
                Heading with a subheading
            </atom:heading>
            <atom:subheading data-testid="subheading">The line under it, muted.</atom:subheading>
            <atom:heading size="lg" data-testid="h-actions">
                With actions
                <x-slot:actions><atom:button size="sm" data-testid="h-actions-button">Add</atom:button></x-slot:actions>
            </atom:heading>
        </div>

        <div class="grid max-w-sm gap-3" data-testid="field">
            <atom:label icon="calendar" data-testid="label-icon">Customer name</atom:label>
            <atom:input label="Email" caption="We only use it for receipts." error="Enter a valid email." data-testid="field-input"/>
            <atom:error data-testid="error-slot">A standalone error.</atom:error>
            <atom:error :errors="['The name is required', 'The date is in the past']" data-testid="error-list"/>
            <atom:caption data-testid="caption">A caption under a control.</atom:caption>
        </div>

        <div class="grid gap-3" data-testid="separators">
            <atom:separator data-testid="separator"/>
            <atom:separator data-testid="separator-label">or continue with</atom:separator>
            <atom:separator align="left" data-testid="separator-left">Left</atom:separator>
        </div>

        <div class="grid gap-3" data-testid="small">
            <atom:sharer url="https://example.com/post" title="A post" data-testid="sharer"/>
            <div class="flex items-center gap-2"><atom:copy value="INV-0042" data-testid="copy"/> <span class="font-id">INV-0042</span></div>
            <div class="h-24 w-24" data-testid="embed"><atom:embed/></div>
            <atom:whatsapp number="60123456789" text="Hello" class="[:where(&)]:absolute" data-testid="whatsapp"/>
        </div>
    </section>
    @endif

    @if ($show('links'))
    <section class="grid gap-4" data-testid="links">
        <h2 class="text-lg font-semibold">Links</h2>

        <p>Body text with <atom:link href="https://example.com" data-testid="link">a link</atom:link>, an
            <atom:link href="https://example.com" variant="accent" data-testid="link-accent">accent link</atom:link> and one with an
            <atom:link href="https://example.com" icon="link" icon-suffix="right" data-testid="link-icon">icon</atom:link>.
        </p>

        <div class="grid gap-3">
            @foreach (['info', 'success', 'warning', 'danger', null] as $tone)
                <atom:callout :variant="$tone" heading="A {{ $tone ?? 'neutral' }} callout" data-testid="callout-{{ $tone ?? 'neutral' }}">
                    <p>Read <atom:link href="https://example.com" data-testid="callout-link-{{ $tone ?? 'neutral' }}">the details</atom:link> before you go on.</p>
                </atom:callout>
            @endforeach
        </div>

        <atom:card data-testid="card-link">Inside a card: <atom:link href="https://example.com" data-testid="card-link-a">a link</atom:link>.</atom:card>
        <atom:card subtle data-testid="card-link-subtle">Inside a sunken card: <atom:link href="https://example.com" data-testid="card-link-subtle-a">a link</atom:link>.</atom:card>
    </section>
    @endif

    @if ($show('editor'))
    <section class="grid gap-4" data-testid="editor">
        <h2 class="text-lg font-semibold">Editor</h2>

        <atom:tiptap.content :content="$doc" class="rounded-panel border bg-surface p-4" data-testid="content"/>

        <atom:tiptap label="Notes" placeholder="Write the notes..." data-testid="tiptap"/>

        <atom:tiptap.chat label="Message" placeholder="Type a message..." data-testid="chat"/>
    </section>
    @endif

    @if ($show('chart'))
    <section class="grid gap-4" data-testid="chart">
        <h2 class="text-lg font-semibold">Chart</h2>

        @php
        $data = [
            ['label' => 'Mon', 'value' => 32, 'tooltip' => '32 sales'],
            ['label' => 'Tue', 'value' => 48, 'tooltip' => '48 sales'],
            ['label' => 'Wed', 'value' => 27, 'tooltip' => '27 sales'],
            ['label' => 'Thu', 'value' => 61, 'tooltip' => '61 sales'],
        ];
        @endphp

        <div class="grid gap-4 md:grid-cols-2">
            <div class="h-48"><atom:chart type="bar" :data="$data" color="success" class="h-full" data-testid="chart-bar"/></div>
            <div class="h-48"><atom:chart type="bar" :data="$data" class="h-full" data-testid="chart-bar-default"/></div>
            <div class="h-48"><atom:chart type="area" :data="$data" color="warning" :max="['value' => 60, 'label' => 'Goal']" class="h-full" data-testid="chart-area"/></div>
            <div class="h-24"><atom:chart type="trend" :data="[8, 12, 9, 14, 11, 18]" color="danger" class="h-full" data-testid="chart-trend"/></div>
        </div>

        <div class="grid grid-cols-3 gap-4">
            <atom:card variant="stats" heading="Revenue" data="1,280" :indicator="5" :trend="[3, 5, 4, 7, 6, 9]" data-testid="stat-up"/>
            <atom:card variant="stats" heading="Refunds" data="12" :indicator="-3" :trend="[9, 6, 7, 4, 5, 3]" data-testid="stat-down"/>
            <atom:card variant="stats" heading="Visits" data="900" :trend="[4, 4, 5, 4, 4, 5]" data-testid="stat-flat"/>
        </div>
    </section>
    @endif
</div>

@livewireScripts
</atom:html>
