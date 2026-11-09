@php
// Every form control that has a disabled state, enabled beside disabled, empty and filled (issue #178), on the consumer's Tailwind:
// ?tw=floor|latest, ?dark=1 (also emulate prefers-color-scheme: dark). Each pair sits in a `data-testid="pair-<control>"` row
// holding one enabled and one disabled copy, so a spec compares the two.
$tw = in_array(request()->query('tw'), ['floor', 'latest'], true) ? request()->query('tw') : null;
$options = [['value' => 'a', 'label' => 'Draft'], ['value' => 'b', 'label' => 'Sent']];
@endphp

<atom:html title="E2E: Disabled controls" :vite="[]" :dark="request()->query('dark') === '1'" fonts="geist" :styles="$tw ? ['/atom/e2e/consumer-css/'.$tw] : []">
<div class="mx-auto grid max-w-3xl gap-6 bg-surface p-8" data-testid="sheet">
    <div class="grid grid-cols-2 gap-4" data-testid="pair-input-empty">
        <atom:input data-testid="on" placeholder="Name"/>
        <atom:input data-testid="off" placeholder="Name" disabled/>
    </div>
    <div class="grid grid-cols-2 gap-4" data-testid="pair-input-filled">
        <atom:input data-testid="on" value="Locked value"/>
        <atom:input data-testid="off" value="Locked value" disabled/>
    </div>
    <div class="grid grid-cols-2 gap-4" data-testid="pair-password">
        <atom:input data-testid="on" type="password"/>
        <atom:input data-testid="off" type="password" disabled/>
    </div>
    <div class="grid grid-cols-2 gap-4" data-testid="pair-textarea-empty">
        <atom:textarea data-testid="on" rows="2"/>
        <atom:textarea data-testid="off" rows="2" disabled/>
    </div>
    <div class="grid grid-cols-2 gap-4" data-testid="pair-textarea-filled">
        <atom:textarea data-testid="on" rows="2">Locked</atom:textarea>
        <atom:textarea data-testid="off" rows="2" disabled>Locked</atom:textarea>
    </div>
    <div class="grid grid-cols-2 gap-4" data-testid="pair-select-native">
        <div data-testid="on"><atom:select placeholder="Pick one" :options="$options"/></div>
        <div data-testid="off"><atom:select placeholder="Pick one" :options="$options" disabled/></div>
    </div>
    <div class="grid grid-cols-2 gap-4" data-testid="pair-select-listbox">
        <div data-testid="on"><atom:select variant="listbox" placeholder="Pick one" :options="$options"/></div>
        <div data-testid="off"><atom:select variant="listbox" placeholder="Pick one" :options="$options" disabled/></div>
    </div>
    <div class="grid grid-cols-2 gap-4" data-testid="pair-date">
        <div data-testid="on"><atom:date-picker/></div>
        <div data-testid="off"><atom:date-picker disabled/></div>
    </div>
    <div class="grid grid-cols-2 gap-4" data-testid="pair-tel">
        <div data-testid="on"><atom:input type="tel"/></div>
        <div data-testid="off"><atom:input type="tel" disabled/></div>
    </div>
    <div class="grid grid-cols-2 gap-4" data-testid="pair-otp">
        <div data-testid="on"><atom:input.otp length="4"/></div>
        <div data-testid="off"><atom:input.otp length="4" disabled/></div>
    </div>
    <div class="grid grid-cols-2 gap-4" data-testid="pair-email">
        <div data-testid="on"><atom:input type="email" multiple/></div>
        <div data-testid="off"><atom:input type="email" multiple disabled/></div>
    </div>
    <div class="grid grid-cols-2 gap-4" data-testid="pair-checkbox">
        <div data-testid="on"><atom:checkbox label="Option"/></div>
        <div data-testid="off"><atom:checkbox label="Option" disabled/></div>
    </div>
    <div class="grid grid-cols-2 gap-4" data-testid="pair-radio">
        <div data-testid="on"><atom:radio name="ra" label="Option" value="1"/></div>
        <div data-testid="off"><atom:radio name="rb" label="Option" value="1" disabled/></div>
    </div>
    <div class="grid grid-cols-2 gap-4" data-testid="pair-toggle">
        <div data-testid="on"><atom:toggle label="Option"/></div>
        <div data-testid="off"><atom:toggle label="Option" disabled/></div>
    </div>
    <div class="grid grid-cols-2 gap-4" data-testid="pair-slider">
        <div data-testid="on"><atom:slider value="40"/></div>
        <div data-testid="off"><atom:slider value="40" disabled/></div>
    </div>
</div>
@livewireScripts
</atom:html>
