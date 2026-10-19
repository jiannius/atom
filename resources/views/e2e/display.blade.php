@php
// The restyled data-display components (V4-3b) on the consumer's Tailwind: ?tw=floor|latest, ?density=compact|comfortable,
// ?dark=1 (also emulate prefers-color-scheme: dark), ?accent=%23rrggbb (a host's brand colour; indigo here), ?section=badges|panels|lists|people|meters|pager|table
// to draw one section. Every colour name, size and variant is drawn, so the spec can measure each; hover and focus are
// driven by the spec. Invented, generic data.
$tw = in_array(request()->query('tw'), ['floor', 'latest'], true) ? request()->query('tw') : null;
$density = in_array(request()->query('density'), ['compact', 'comfortable'], true) ? request()->query('density') : 'compact';
$accent = preg_match('/^#[0-9a-f]{6}$/i', (string) request()->query('accent')) ? request()->query('accent') : '#4c55c9';
$section = request()->query('section');
$show = fn (string $name) => $section === null || $section === $name;

$names = ['gray', 'grey', 'zinc', 'slate', 'stone', 'neutral', 'white', 'blue', 'sky', 'cyan', 'indigo', 'info', 'green', 'emerald', 'teal', 'lime', 'success', 'yellow', 'amber', 'orange', 'warning', 'red', 'rose', 'danger', 'error', 'purple', 'violet', 'fuchsia', 'pink', 'accent', 'primary', 'black', 'nonsense'];
$hexes = ['#f5c518', '#ff0000', '#00ff00', '#0000ff', '#808080', '#ffffff', '#000000', '#3e496b', '#9f5afd'];

// a tiny photo for the avatar: an inline SVG data URI, so the sheet loads nothing from the network
$photo = 'data:image/svg+xml;utf8,'.rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 40"><rect width="40" height="40" fill="#e8d9b5"/><circle cx="20" cy="16" r="7" fill="#8a6a2f"/><rect x="8" y="26" width="24" height="14" rx="7" fill="#8a6a2f"/></svg>');

$pager = fn (int $page) => new \Illuminate\Pagination\LengthAwarePaginator(collect(range(1, 20)), 200, 20, $page);
@endphp

<atom:html title="E2E: Data display" :vite="[]" :dark="request()->query('dark') === '1'" :density="$density" fonts="geist" :styles="$tw ? ['/atom/e2e/consumer-css/'.$tw] : []">
<style>:root { --color-accent: {{ $accent }}; }</style>

<div class="mx-auto grid max-w-4xl gap-10 p-8" data-testid="sheet">
    @if ($show('badges'))
    <section class="grid gap-4" data-testid="badges">
        <h2 class="text-lg font-semibold">Badges</h2>

        <div class="flex flex-wrap items-center gap-2" data-testid="badge-names">
            @foreach ($names as $name)
                <atom:badge :color="$name" :label="$name" :data-testid="'badge-'.$name"/>
            @endforeach
        </div>

        <div class="flex flex-wrap items-center gap-2" data-testid="badge-sizes">
            <atom:badge color="blue" label="Extra small" size="xs" data-testid="badge-size-xs"/>
            <atom:badge color="blue" label="Small" size="sm" data-testid="badge-size-sm"/>
            <atom:badge color="blue" label="Default" data-testid="badge-size-default"/>
            <atom:badge color="blue" label="Large" size="lg" data-testid="badge-size-lg"/>
        </div>

        <div class="flex flex-wrap items-center gap-2" data-testid="badge-hexes">
            @foreach ($hexes as $hex)
                <atom:badge :color="$hex" :label="$hex" :data-testid="'badge-hex-'.ltrim($hex, '#')"/>
            @endforeach
        </div>

        <div class="flex flex-wrap items-center gap-2" data-testid="badge-status">
            <atom:badge :status="['color' => 'amber', 'label' => 'From a status']" data-testid="badge-from-status"/>
            <atom:badge.group max="2" data-testid="badge-group">
                <atom:badge color="green" label="One"/>
                <atom:badge color="blue" label="Two"/>
                <atom:badge color="red" label="Three"/>
            </atom:badge.group>
        </div>

        <div class="flex flex-wrap items-center gap-2" data-testid="badge-vs-status">
            <atom:badge color="green" label="Paid" data-testid="vs-badge"/>
            <atom:status tone="success" label="Paid" data-testid="vs-status"/>
        </div>

        {{-- on a sunken and on a raised surface: a badge is read on whatever it sits on --}}
        <div class="flex flex-wrap items-center gap-2 rounded-panel bg-surface-sunken p-3" data-testid="badge-on-sunken">
            @foreach (['neutral', 'info', 'success', 'warning', 'danger', 'accent', 'black'] as $tone)
                <atom:badge :color="$tone" :label="$tone" :data-testid="'sunken-'.$tone"/>
            @endforeach
        </div>

        <div class="flex flex-wrap items-center gap-2" data-testid="navlist-badges">
            @foreach (['gray', 'amber', 'indigo', 'red', 'green', 'purple', 'black'] as $name)
                <atom:navlist.badge :color="$name" :data-testid="'navbadge-'.$name">12</atom:navlist.badge>
            @endforeach
        </div>
    </section>
    @endif

    @if ($show('panels'))
    <section class="grid gap-4" data-testid="panels">
        <h2 class="text-lg font-semibold">Cards and callouts</h2>

        <div class="grid gap-4 sm:grid-cols-2">
            <atom:card data-testid="card"><p>Default card.</p></atom:card>
            <atom:card subtle data-testid="card-subtle"><p>Subtle card.</p></atom:card>
            <atom:card divided data-testid="card-divided"><div>First</div><div>Second</div></atom:card>
            <atom:card inset data-testid="card-inset"><div class="p-4">Inset</div></atom:card>
            <atom:card data-testid="card-cover">
                <x-slot:cover><div class="h-20"></div></x-slot:cover>
                <p>With a cover.</p>
            </atom:card>
            <atom:card variant="stats" heading="Revenue" data="RM 48,200" :indicator="12.4" data-testid="card-stats-up"/>
            <atom:card variant="stats" heading="Churn" data="2.1%" :indicator="-0.4" data-testid="card-stats-down"/>
            <atom:card variant="stats" heading="Plain" data="42" data-testid="card-stats-plain"/>
        </div>

        <div class="grid gap-3" data-testid="callouts">
            <atom:callout variant="info" heading="Heads up" content="A new version is available." data-testid="callout-info"/>
            <atom:callout variant="success" heading="Saved" content="Your changes have been saved." data-testid="callout-success"/>
            <atom:callout variant="warning" heading="Careful" content="This affects your billing." data-testid="callout-warning"/>
            <atom:callout variant="danger" heading="Something went wrong" content="We could not process your request." data-testid="callout-danger"/>
            <atom:callout heading="A plain note" content="No variant: the neutral tint." data-testid="callout-default"/>
            <atom:callout variant="warning" heading="Dismissible" closeable data-testid="callout-closeable">
                A callout the reader can close. <atom:link href="#">A link inside</atom:link> stays readable.
            </atom:callout>
            <atom:callout variant="info" data-testid="callout-slot">
                <x-slot:heading><span data-testid="callout-slot-heading">Heading from a slot</span></x-slot:heading>
                Body text under it.
            </atom:callout>
        </div>
    </section>
    @endif

    @if ($show('lists'))
    <section class="grid gap-4" data-testid="lists">
        <h2 class="text-lg font-semibold">Description list, list and keys</h2>

        <div class="grid gap-6 sm:grid-cols-2">
            <atom:card data-testid="dd-card">
                <atom:dd.group>
                    <atom:dd label="Email" data-testid="dd-email">ada@example.com</atom:dd>
                    <atom:dd label="Phone" data-testid="dd-phone">+60 12 345 6789</atom:dd>
                    <atom:dd label="Notes" data-testid="dd-empty"/>
                    <atom:dd label="Address" data-testid="dd-long">A very long address line with a-very-long-unbroken-token-1234567890123456789012345678901234567890</atom:dd>
                </atom:dd.group>
            </atom:card>

            <atom:card subtle data-testid="dd-card-subtle">
                <atom:dd.group cols="2">
                    <atom:dd label="Status" block data-testid="dd-block">Active</atom:dd>
                    <atom:dd label="Plan" block>Team</atom:dd>
                </atom:dd.group>
            </atom:card>
        </div>

        <div class="grid gap-6 sm:grid-cols-2">
            <atom:list heading="Recent files" data-testid="list">
                <atom:list.item href="/f/1" data-testid="list-item-link">
                    <div class="font-medium">Quarterly figures.pdf</div>
                    <div class="text-xs text-fg-muted">2.4 MB</div>
                </atom:list.item>
                <atom:list.item data-testid="list-item-plain">Cover.png</atom:list.item>
                <atom:list.item wire:remove="x" data-testid="list-item-remove">Proposal.docx</atom:list.item>
            </atom:list>

            <div class="grid content-start gap-3">
                <div class="flex items-center gap-2" data-testid="kbd-row">
                    <atom:kbd keys="cmd k" data-testid="kbd-combo"/>
                    <atom:kbd data-testid="kbd-single">Esc</atom:kbd>
                    <atom:kbd keys="ctrl shift p" data-testid="kbd-three"/>
                </div>
                <div class="flex items-center gap-2 rounded-panel bg-surface-sunken p-3" data-testid="kbd-on-sunken">
                    <atom:kbd keys="cmd k"/>
                </div>
            </div>
        </div>
    </section>
    @endif

    @if ($show('people'))
    <section class="grid gap-4" data-testid="people">
        <h2 class="text-lg font-semibold">Avatars</h2>

        <div class="flex flex-wrap items-end gap-3" data-testid="avatar-sizes">
            @foreach (['xs', 'sm', null, 'lg', 'xl'] as $size)
                <atom:avatar name="Ada Lovelace" :size="$size" :data-testid="'avatar-'.($size ?? 'default')"/>
            @endforeach
        </div>

        <div class="flex flex-wrap items-end gap-3" data-testid="avatar-round">
            @foreach (['xs', 'sm', null, 'lg'] as $size)
                <atom:avatar name="Grace Hopper" :size="$size" :square="false" :data-testid="'avatar-round-'.($size ?? 'default')"/>
            @endforeach
        </div>

        <div class="flex flex-wrap items-end gap-3" data-testid="avatar-photos">
            <atom:avatar name="Photo" :src="$photo" data-testid="avatar-photo"/>
            <atom:avatar name="Photo" :src="$photo" size="lg" data-testid="avatar-photo-lg"/>
            <atom:avatar name="Photo" :src="$photo" :square="false" data-testid="avatar-photo-round"/>
        </div>

        <div class="flex flex-wrap items-center gap-6">
            <atom:avatar.group max="3" data-testid="avatar-group">
                <atom:avatar name="Ada Lovelace" size="sm"/>
                <atom:avatar name="Grace Hopper" size="sm"/>
                <atom:avatar name="Alan Turing" size="sm"/>
                <atom:avatar name="Edsger Dijkstra" size="sm"/>
                <atom:avatar name="Barbara Liskov" size="sm"/>
            </atom:avatar.group>

            <div class="rounded-panel border bg-surface-sunken p-3" data-testid="avatar-group-sunken">
                <atom:avatar.group max="2">
                    <atom:avatar name="Ada Lovelace" size="sm"/>
                    <atom:avatar name="Grace Hopper" size="sm"/>
                    <atom:avatar name="Alan Turing" size="sm"/>
                </atom:avatar.group>
            </div>

            <atom:profile name="Ada Lovelace" email="ada@example.com" data-testid="profile"/>
            <atom:profile name="Grace Hopper" email="grace@example.com" size="lg" data-testid="profile-lg"/>
        </div>
    </section>
    @endif

    @if ($show('meters'))
    <section class="grid gap-4" data-testid="meters">
        <h2 class="text-lg font-semibold">Progress and placeholders</h2>

        <div class="grid gap-4 sm:grid-cols-2" data-testid="progress-grid">
            <atom:progress :value="60" label data-testid="progress-default"/>
            <atom:progress :value="60" size="sm" label data-testid="progress-sm"/>
            @foreach (['success', 'warning', 'danger', 'info'] as $variant)
                <atom:progress :value="70" :variant="$variant" label :data-testid="'progress-'.$variant"/>
            @endforeach
            <atom:progress :value="100" variant="success" label="Complete" data-testid="progress-full"/>
            <atom:progress :value="0" label data-testid="progress-empty"/>
            <atom:progress indeterminate data-testid="progress-indeterminate"/>
        </div>

        <div class="rounded-panel border bg-surface-sunken p-4" data-testid="progress-on-sunken">
            <atom:progress :value="45" variant="success" label/>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <atom:skeleton data-testid="skeleton"/>
            <div class="grid gap-3" data-testid="placeholder-bars">
                <atom:placeholder-bar size="60%x10" data-testid="placeholder-60"/>
                <atom:placeholder-bar size="100%x10"/>
                <atom:placeholder-bar size="40%x10"/>
            </div>
        </div>
    </section>
    @endif

    @if ($show('pager'))
    <section class="grid gap-4" data-testid="pager">
        <h2 class="text-lg font-semibold">Pagination</h2>

        <div class="rounded-panel border bg-surface" data-testid="pager-middle"><atom:pagination :paginator="$pager(5)"/></div>
        <div class="rounded-panel border bg-surface" data-testid="pager-first"><atom:pagination :paginator="$pager(1)"/></div>
        <div class="rounded-panel border bg-surface" data-testid="pager-last"><atom:pagination :paginator="$pager(10)"/></div>
        <div class="rounded-panel border bg-surface" data-testid="pager-simple"><atom:pagination :paginator="$pager(3)" simple/></div>
        <div class="rounded-panel border bg-surface" data-testid="pager-bare"><atom:pagination :paginator="$pager(2)" :summary="false"/></div>
        <div class="rounded-panel border bg-surface" data-testid="pager-table"><atom:table.pagination :paginate="$pager(4)" :max-rows="false"/></div>
    </section>
    @endif

    @if ($show('table'))
    <section class="grid gap-4" data-testid="table">
        <h2 class="text-lg font-semibold">Table parts</h2>

        <div class="flex items-center gap-6" data-testid="table-checkboxes">
            <atom:table.checkbox data-testid="tcb-off"/>
            <atom:table.checkbox data-checked data-testid="tcb-on"/>
        </div>

        <atom:table.filters data-testid="filters" overflow="card">
            <atom:button variant="ghost">Status</atom:button>
            <x-slot:more><span>More filter controls</span></x-slot:more>
        </atom:table.filters>

        <atom:table data-testid="table-plain">
            <x-slot:columns>
                <atom:table.column>Customer</atom:table.column>
                <atom:table.column>State</atom:table.column>
                <atom:table.column numeric>Amount (RM)</atom:table.column>
            </x-slot:columns>
            <x-slot:rows>
                <atom:table.row data-testid="trow-1">
                    <atom:table.cell>Northwind Traders</atom:table.cell>
                    <atom:table.cell><atom:badge color="amber" size="xs" label="Due soon"/></atom:table.cell>
                    <atom:table.cell numeric>1,200.00</atom:table.cell>
                </atom:table.row>
                <atom:table.row data-testid="trow-2">
                    <atom:table.cell>Contoso Ltd</atom:table.cell>
                    <atom:table.cell><atom:badge color="green" size="xs" label="Paid"/></atom:table.cell>
                    <atom:table.cell numeric>640.50</atom:table.cell>
                </atom:table.row>
            </x-slot:rows>
        </atom:table>

        <atom:table skeleton data-testid="table-skeleton">
            <x-slot:columns><atom:table.column>Name</atom:table.column></x-slot:columns>
        </atom:table>
    </section>
    @endif
</div>

@livewireScripts
</atom:html>
