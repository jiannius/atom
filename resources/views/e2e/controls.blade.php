@php
// The restyled controls on the consumer's Tailwind (V4-3a): ?tw=floor|latest, ?density=compact|comfortable, ?dark=1 (also emulate
// prefers-color-scheme: dark), ?accent=%23rrggbb (a host's brand colour; indigo here), ?section=buttons|fields|choices|ranges to
// draw one section, ?disabled=1 to render the form controls disabled. Every control is drawn in the states a browser cannot be
// put in by markup alone (disabled, invalid, checked); hover and focus are driven by the spec.
$tw = in_array(request()->query('tw'), ['floor', 'latest'], true) ? request()->query('tw') : null;
$density = in_array(request()->query('density'), ['compact', 'comfortable'], true) ? request()->query('density') : 'compact';
$accent = preg_match('/^#[0-9a-f]{6}$/i', (string) request()->query('accent')) ? request()->query('accent') : '#4c55c9';
$section = request()->query('section');
$show = fn (string $name) => $section === null || $section === $name;
@endphp

<atom:html title="E2E: Controls" :vite="[]" :dark="request()->query('dark') === '1'" :density="$density" fonts="geist" :styles="$tw ? ['/atom/e2e/consumer-css/'.$tw] : []">
<style>:root { --color-accent: {{ $accent }}; }</style>

<div class="mx-auto grid max-w-4xl gap-10 p-8" data-testid="sheet">
    @if ($show('buttons'))
    <section class="grid gap-4" data-testid="buttons">
        <h2 class="text-lg font-semibold">Buttons</h2>

        <div class="flex flex-wrap items-center gap-2" data-testid="button-tiers">
            <atom:button variant="primary" data-testid="btn-primary">Save</atom:button>
            <atom:button data-testid="btn-default">Cancel</atom:button>
            <atom:button variant="ghost" data-testid="btn-ghost">Ghost</atom:button>
            <atom:button variant="ghost" color="primary" data-testid="btn-ghost-accent">Accent</atom:button>
            <atom:button variant="danger" data-testid="btn-danger">Delete</atom:button>
            <atom:button type="delete" data-testid="btn-delete">Delete record</atom:button>
            <atom:button variant="warning" data-testid="btn-warning">Warning</atom:button>
            <atom:button variant="success" data-testid="btn-success">Success</atom:button>
            <atom:button variant="link" data-testid="btn-link">Link</atom:button>
            <atom:button variant="primary" icon="add" data-testid="btn-icon-text">New invoice</atom:button>
            <atom:button icon="settings" data-testid="btn-icon-only"/>
            <atom:button variant="ghost" icon="more" data-testid="btn-icon-ghost"/>
            <atom:button type="submit" data-testid="btn-submit">Submit</atom:button>
        </div>

        <div class="flex flex-wrap items-center gap-2" data-testid="button-sizes">
            <atom:button size="xs" data-testid="btn-xs">Extra small</atom:button>
            <atom:button size="sm" data-testid="btn-sm">Small</atom:button>
            <atom:button data-testid="btn-md-default">Default</atom:button>
            <atom:button size="md" data-testid="btn-md">Medium</atom:button>
            <atom:button size="lg" data-testid="btn-lg">Large</atom:button>
            <atom:button size="sm" icon="add" data-testid="btn-sm-icon"/>
        </div>

        <div class="flex flex-wrap items-center gap-2" data-testid="button-disabled">
            <atom:button variant="primary" disabled data-testid="btn-primary-disabled">Save</atom:button>
            <atom:button disabled data-testid="btn-default-disabled">Cancel</atom:button>
            <atom:button variant="ghost" disabled data-testid="btn-ghost-disabled">Ghost</atom:button>
        </div>

        <div class="flex flex-wrap items-center gap-2" data-testid="button-social">
            <atom:button variant="google" icon="google" data-testid="btn-google">Google</atom:button>
            <atom:button variant="facebook" icon="facebook" data-testid="btn-facebook">Facebook</atom:button>
        </div>

        <atom:button.group data-testid="btn-group">
            <atom:button>Day</atom:button>
            <atom:button>Week</atom:button>
            <atom:button>Month</atom:button>
        </atom:button.group>
    </section>
    @endif

    @if ($show('fields'))
    <section class="grid gap-4" data-testid="fields">
        <h2 class="text-lg font-semibold">Text fields</h2>

        @php
        $options = [['value' => 'a', 'label' => 'Draft'], ['value' => 'b', 'label' => 'Sent'], ['value' => 'c', 'label' => 'Paid']];
        @endphp

        <div class="grid gap-4 sm:grid-cols-2">
            <div data-testid="f-input"><atom:input label="Reference" placeholder="Quote 2512020001" value="INV-0042"/></div>
            <div data-testid="f-input-empty"><atom:input label="Empty" placeholder="Placeholder text"/></div>
            <div data-testid="f-input-disabled"><atom:input label="Disabled" value="Locked" disabled/></div>
            <div data-testid="f-input-invalid"><atom:input label="Invalid" value="oops" invalid error="Already taken"/></div>
            <div data-testid="f-input-icon"><atom:input label="With icon" icon="search" placeholder="Search"/></div>
            <div data-testid="f-input-clearable"><atom:input label="Clearable" clearable value="abc"/></div>
            <div data-testid="f-input-affix"><atom:input label="Affixes" prefix="RM" suffix="MYR" value="1,200.00"/></div>
            <div data-testid="f-password"><atom:input label="Password" type="password" value="secret"/></div>
            <div data-testid="f-number"><atom:input label="Number" type="number" value="42"/></div>
            <div data-testid="f-tel"><atom:input label="Phone" type="tel"/></div>
            <div data-testid="f-email"><atom:input label="Email" type="email" multiple/></div>
            <div data-testid="f-color"><atom:input label="Colour" type="color"/></div>
            <div data-testid="f-textarea"><atom:textarea label="Note" rows="3">Phase 2 of 2 as agreed.</atom:textarea></div>
            <div data-testid="f-textarea-disabled"><atom:textarea label="Disabled note" rows="3" disabled>Locked</atom:textarea></div>
            <div data-testid="f-textarea-invalid"><atom:textarea label="Invalid note" rows="3" invalid>Too short</atom:textarea></div>
            <div data-testid="f-select-native"><atom:select label="Status" :options="$options"/></div>
            <div data-testid="f-select-native-disabled"><atom:select label="Locked status" :options="$options" disabled/></div>
            <div data-testid="f-select-native-invalid"><atom:select label="Invalid status" :options="$options" invalid/></div>
            <div data-testid="f-select-listbox"><atom:select label="Listbox" variant="listbox" :options="$options"/></div>
            <div data-testid="f-select-listbox-multiple"><atom:select label="Multiple" variant="listbox" multiple :options="$options"/></div>
            <div data-testid="f-select-listbox-invalid"><atom:select label="Invalid listbox" variant="listbox" invalid :options="$options"/></div>
            <div data-testid="f-select-filter"><atom:select variant="filter" label="Status" :options="$options"/></div>
            <div data-testid="f-date"><atom:date-picker label="Issue date"/></div>
            <div data-testid="f-date-disabled"><atom:date-picker label="Locked date" disabled/></div>
            <div data-testid="f-date-range"><atom:date-picker variant="range" label="Period"/></div>
            <div data-testid="f-time"><atom:time-picker label="Time"/></div>
            <div data-testid="f-otp"><atom:input.otp length="6"/></div>
        </div>
    </section>
    @endif
</div>

@livewireScripts
</atom:html>
