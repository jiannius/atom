@php
// ?shell=sidebar|header puts the page in an app shell (default sidebar); ?tw=floor|latest is the consumer build; ?rail=0 leaves the rail out
// ?crumbs=1 puts a crumb in the sidebar shell's bar, so the bar is drawn (empty, it is not, and --atom-shell-bar-h is 0); ?sticky=1 makes the rail sticky; ?wide=1 puts a 12-column frameless table (about 1500px of cells) in a panel in the main column, ?wide=2 the same table framed and bare, a direct child of the main column; ?long=1 adds a block whose content cannot wrap (a 1800px line), which must not widen the main column; ?tall=1 makes the main column 2400px tall; ?railtall=1 adds twelve cards with a button each to the rail (taller than a short window); ?gap=8|4 puts `gap-8` or `gap-4` on the tag; ?widecard=1 adds a rail card holding a 700px block; ?nav=1 adds a nav slot (a navlist of four items, ?navitems=30 for thirty, ?navhost=1 puts `static` on the slot, ?navcurrent=N marks item N current, ?navgroups=1 splits the items into a group "Account" (the first six) and a collapsed expandable group "Workspace" (the rest)); ?host=1 puts host utilities on the tag (`gap-8`) and on the rail (`static`)
Auth::setUser(new \Illuminate\Auth\GenericUser(['id' => 1, 'name' => 'Alex Doe', 'email' => 'alex@example.com', 'avatar' => null]));
$tw = in_array(request()->query('tw'), ['floor', 'latest'], true) ? request()->query('tw') : 'latest';
$shell = request()->query('shell') === 'header' ? 'header' : 'sidebar';
$rail = request()->query('rail') !== '0';
$sticky = request()->query('sticky') === '1';
$wide = request()->query('wide') === '1';
$bare = request()->query('wide') === '2';
$long = request()->query('long') === '1';
$tall = request()->query('tall') === '1';
$crumbs = request()->query('crumbs') === '1';
$railtall = request()->query('railtall') === '1';
$host = request()->query('host') === '1';
$gap = match (request()->query('gap')) { '8' => 'gap-8', '4' => 'gap-4', default => '' };
$widecard = request()->query('widecard') === '1';
$nav = request()->query('nav') === '1';
$navItems = max(4, (int) request()->query('navitems', 4));
$navCurrent = (int) request()->query('navcurrent', 0);
$navGroups = request()->query('navgroups') === '1';
$navLabel = fn (int $item) => ['Profile', 'Security', 'Notifications', 'Billing'][$item - 1] ?? 'Section '.$item;
@endphp

<x-dynamic-component :component="'atom::layouts.'.$shell" title="E2E: page columns" :vite="[]" :styles="['/atom/e2e/consumer-css/'.$tw]">
    <x-slot:nav>
        <atom:navlist><atom:navlist.item href="?page=dashboard" icon="dashboard">Dashboard</atom:navlist.item></atom:navlist>
    </x-slot>

    @if ($crumbs)
        <x-slot:crumbs><span data-crumb>Work orders</span></x-slot>
    @endif

    <atom:page data-testid="page">
        <atom:page.header title="Work order WO-1042" />

        <atom:page.columns :class="$host ? 'gap-8' : $gap" data-testid="columns">
            @if ($nav)
                <x-slot:nav data-testid="nav" :class="request()->query('navhost') === '1' ? 'static' : ''">
                    <atom:navlist>
                        @if ($navGroups)
                            <atom:navlist.group heading="Account">
                                @foreach (range(1, min(6, $navItems)) as $item)
                                    <atom:navlist.item href="?nav=1#item-{{ $item }}" :current="$navCurrent === $item" data-testid="nav-item">{{ $navLabel($item) }}</atom:navlist.item>
                                @endforeach
                            </atom:navlist.group>
                            <atom:navlist.group heading="Workspace" expandable :expanded="false">
                                @foreach (range(7, max(7, $navItems)) as $item)
                                    <atom:navlist.item href="?nav=1#item-{{ $item }}" :current="$navCurrent === $item" data-testid="nav-item">{{ $navLabel($item) }}</atom:navlist.item>
                                @endforeach
                            </atom:navlist.group>
                        @else
                            @foreach (range(1, $navItems) as $item)
                                <atom:navlist.item href="?nav=1#item-{{ $item }}" :current="$navCurrent === $item" data-testid="nav-item">{{ $navLabel($item) }}</atom:navlist.item>
                            @endforeach
                        @endif
                    </atom:navlist>
                </x-slot:nav>
            @endif

            <div class="rounded-panel border bg-surface p-4" data-testid="main-block">Main column block</div>

            @if ($wide)
                <div class="min-w-0 overflow-clip rounded-panel border bg-surface" data-testid="wide-panel">
                    <atom:table frameless :empty="false" data-testid="wide-table">
                        <x-slot:columns>
                            @foreach (range(1, 12) as $c)
                                <atom:table.column>Column number {{ $c }}</atom:table.column>
                            @endforeach
                        </x-slot:columns>
                        <x-slot:rows>
                            @foreach (range(1, 3) as $r)
                                <atom:table.row>
                                    @foreach (range(1, 12) as $c)
                                        <atom:table.cell>Cell {{ $r }}-{{ $c }} text</atom:table.cell>
                                    @endforeach
                                </atom:table.row>
                            @endforeach
                        </x-slot:rows>
                    </atom:table>
                </div>
            @endif

            @if ($bare)
                <atom:table :empty="false" data-testid="wide-panel">
                    <x-slot:columns>
                        @foreach (range(1, 12) as $c)
                            <atom:table.column>Column number {{ $c }}</atom:table.column>
                        @endforeach
                    </x-slot:columns>
                    <x-slot:rows>
                        <atom:table.row>
                            @foreach (range(1, 12) as $c)
                                <atom:table.cell>Cell {{ $c }} text</atom:table.cell>
                            @endforeach
                        </atom:table.row>
                    </x-slot:rows>
                </atom:table>
            @endif

            @if ($long)
                <div class="rounded-panel border bg-surface p-4" data-testid="long-block"><span class="inline-block w-[1800px] whitespace-nowrap">An unbreakable line</span></div>
            @endif

            @if ($tall)
                <div class="h-[2400px] rounded-panel border bg-surface p-4" data-testid="tall-block">A long main column</div>
            @endif

            @if ($rail)
                <x-slot:rail label="Work order details" :sticky="$sticky" :class="$host ? 'static' : ''">
                    <atom:card data-testid="rail-card-1"><atom:dd.group><atom:dd label="Status">In progress</atom:dd></atom:dd.group></atom:card>
                    <atom:card data-testid="rail-card-2"><atom:dd.group><atom:dd label="Customer">Lakeside Foods</atom:dd></atom:dd.group></atom:card>
                    @if ($widecard)
                        <atom:card data-testid="rail-wide-card"><div class="h-4 w-[700px] bg-border" data-testid="rail-wide-block"></div></atom:card>
                    @endif

                    @if ($railtall)
                        @foreach (range(1, 12) as $n)
                            <atom:card data-testid="rail-tall-{{ $n }}"><atom:button size="sm" data-testid="rail-btn-{{ $n }}">Action {{ $n }}</atom:button></atom:card>
                        @endforeach
                    @endif
                </x-slot:rail>
            @endif
        </atom:page.columns>
    </atom:page>

    @livewireScripts
</x-dynamic-component>
