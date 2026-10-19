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
            <div data-testid="f-input-error"><atom:input label="Error only" value="x" error="Not allowed"/></div>
            <div data-testid="f-tel-invalid"><atom:input label="Invalid phone" type="tel" invalid/></div>
            <div data-testid="f-color-invalid"><atom:input label="Invalid colour" type="color" invalid/></div>
            <div data-testid="f-email-invalid"><atom:input label="Invalid email" type="email" multiple invalid/></div>
            <div data-testid="f-date-invalid"><atom:date-picker label="Invalid date" invalid/></div>
            <div data-testid="f-date-range-invalid"><atom:date-picker variant="range" label="Invalid period" invalid/></div>
            <div data-testid="f-time-invalid"><atom:time-picker label="Invalid time" invalid/></div>
            <div data-testid="f-otp-invalid"><atom:input.otp length="4" invalid/></div>
        </div>
    </section>
    @endif

    @if ($show('choices'))
    <section class="grid gap-4" data-testid="choices">
        <h2 class="text-lg font-semibold">Choice controls</h2>

        <div class="grid gap-3 sm:grid-cols-3">
            <div class="grid gap-2">
                <div data-testid="c-checkbox"><atom:checkbox label="Unchecked" /></div>
                <div data-testid="c-checkbox-checked"><atom:checkbox label="Checked" checked /></div>
                <div data-testid="c-checkbox-disabled"><atom:checkbox label="Disabled" disabled /></div>
                <div data-testid="c-checkbox-disabled-checked"><atom:checkbox label="Disabled, checked" disabled checked /></div>
                <div data-testid="c-checkbox-caption"><atom:checkbox label="With a caption" caption="Helper text under it" checked /></div>
            </div>
            <div class="grid gap-2">
                <div data-testid="c-radio"><atom:radio name="r" label="Unchecked" value="a" /></div>
                <div data-testid="c-radio-checked"><atom:radio name="r" label="Checked" value="b" checked /></div>
                <div data-testid="c-radio-disabled"><atom:radio name="r2" label="Disabled" value="c" disabled /></div>
                <div data-testid="c-radio-disabled-checked"><atom:radio name="r3" label="Disabled, checked" value="d" disabled checked /></div>
            </div>
            <div class="grid gap-2">
                <div data-testid="c-toggle"><atom:toggle label="Off" /></div>
                <div data-testid="c-toggle-checked"><atom:toggle label="On" checked /></div>
                <div data-testid="c-toggle-disabled"><atom:toggle label="Disabled" disabled /></div>
                <div data-testid="c-toggle-disabled-checked"><atom:toggle label="Disabled, on" disabled checked /></div>
            </div>
        </div>

        <div data-testid="c-card-group">
            <atom:checkbox.group variant="card">
                <atom:checkbox label="Email me about invoices" checked />
                <atom:checkbox label="Email me about payments" />
            </atom:checkbox.group>
        </div>
    </section>
    @endif

    @if ($show('ranges'))
    <section class="grid gap-4" data-testid="ranges">
        <h2 class="text-lg font-semibold">Slider, rating, uploader</h2>

        <div class="grid gap-6 sm:grid-cols-2">
            <div data-testid="r-slider"><atom:slider label="Volume" name="vol" value="40" bubble labels /></div>
            <div data-testid="r-slider-disabled"><atom:slider label="Locked" name="vol2" value="70" disabled /></div>
            <div data-testid="r-rating"><atom:rating label="Rating" name="rate" :value="3" /></div>
            <div data-testid="r-rating-half"><atom:rating label="Half stars" name="rate2" :value="2.5" half /></div>
            <div data-testid="r-rating-readonly"><atom:rating label="Read only" name="rate3" :value="4" readonly /></div>
            <div data-testid="r-uploader"><atom:uploader.dropzone /></div>
            <div data-testid="r-uploader-button"><atom:uploader label="Upload" /></div>
        </div>
    </section>
    @endif

    @if ($show('more'))
    <section class="grid gap-4" data-testid="more">
        <h2 class="text-lg font-semibold">Variants the apps use</h2>

        @php
        $options = [['value' => 'a', 'label' => 'Draft'], ['value' => 'b', 'label' => 'Sent'], ['value' => 'c', 'label' => 'Paid']];
        @endphp

        <div class="grid gap-4 sm:grid-cols-2">
            <div data-testid="m-email"><atom:input label="Recipients" type="email" multiple/></div>
            <div data-testid="m-listbox-multiple"><atom:select label="Statuses" variant="listbox" multiple :options="$options"/></div>
            <div data-testid="m-listbox-searchable"><atom:select label="Searchable" variant="listbox" searchable :options="$options"/></div>
            <div data-testid="m-select-prefix"><atom:select label="With prefix" prefix="Status" :options="$options"/></div>
            <div data-testid="m-date-time"><atom:date-picker label="With time" time/></div>
            <div data-testid="m-textarea-readonly"><atom:textarea label="Read only" readonly>Locked text</atom:textarea></div>
            <div data-testid="m-textarea-autoresize"><atom:textarea label="Autoresize" autoresize>Grows with its text</atom:textarea></div>
            <div data-testid="m-file"><atom:input label="Attachment" type="file"/></div>
            <div data-testid="m-radio-group"><atom:radio.group label="Plan"><atom:radio name="plan" value="a" label="Monthly"/><atom:radio name="plan" value="b" label="Yearly" checked/></atom:radio.group></div>
            <div data-testid="m-radio-card"><atom:radio.group variant="card"><atom:radio name="card" value="a" label="Card one" checked/><atom:radio name="card" value="b" label="Card two"/></atom:radio.group></div>
            <div data-testid="m-checkbox-start"><atom:checkbox align="start" label="Aligned to the start" caption="A caption that runs under the label"/></div>
            <div class="flex flex-wrap items-center gap-2" data-testid="m-buttons">
                <atom:button variant="ghost" size="sm" icon="download" data-testid="m-ghost-sm">Download</atom:button>
                <atom:button variant="ghost" size="xs" icon="more" data-testid="m-ghost-xs"/>
                <atom:button variant="ghost" color="danger" size="sm" icon="delete" data-testid="m-ghost-danger"/>
                <atom:button type="delete" variant="ghost" color="danger" data-testid="m-delete-ghost">Delete</atom:button>
                <atom:button variant="secondary" data-testid="m-secondary">Secondary</atom:button>
                <atom:button variant="outline" data-testid="m-outline">Outline</atom:button>
            </div>
        </div>
    </section>
    @endif
</div>

@livewireScripts
</atom:html>
