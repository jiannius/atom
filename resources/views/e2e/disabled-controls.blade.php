@php
// Every form control that has a disabled state, enabled beside disabled, empty and filled (issue #178), on the consumer's Tailwind:
// ?tw=floor|latest, ?dark=1 (also emulate prefers-color-scheme: dark). Each pair sits in a `data-testid="pair-<control>"` row
// holding one enabled and one disabled copy (the invalid-* and error-* rows are the same with `invalid`, or inside a field that has an error), so a spec compares the two.
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
    <div class="grid grid-cols-2 gap-4" data-testid="pair-textarea-transparent">
        <atom:textarea data-testid="on" variant="transparent" rows="2">Locked</atom:textarea>
        <atom:textarea data-testid="off" variant="transparent" rows="2" disabled>Locked</atom:textarea>
    </div>
    <div class="grid grid-cols-2 gap-4" data-testid="pair-form-textarea-transparent">
        <atom:textarea data-testid="on" variant="transparent" rows="2" disabled>Locked</atom:textarea>
        <atom:form disabled inset><atom:textarea data-testid="off" variant="transparent" rows="2">Locked</atom:textarea></atom:form>
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

    {{-- a disabled control that is also `invalid`, and one inside a field that has an error: both stay faint; the enabled copy is the danger tone --}}
    <div class="grid grid-cols-2 gap-4" data-testid="pair-invalid-input">
        <atom:input data-testid="on" invalid/>
        <atom:input data-testid="off" invalid disabled/>
    </div>
    <div class="grid grid-cols-2 gap-4" data-testid="pair-invalid-textarea">
        <atom:textarea data-testid="on" rows="2" invalid/>
        <atom:textarea data-testid="off" rows="2" invalid disabled/>
    </div>
    <div class="grid grid-cols-2 gap-4" data-testid="pair-invalid-select-native">
        <div data-testid="on"><atom:select :options="$options" invalid/></div>
        <div data-testid="off"><atom:select :options="$options" invalid disabled/></div>
    </div>
    <div class="grid grid-cols-2 gap-4" data-testid="pair-invalid-select-listbox">
        <div data-testid="on"><atom:select variant="listbox" :options="$options" invalid/></div>
        <div data-testid="off"><atom:select variant="listbox" :options="$options" invalid disabled/></div>
    </div>
    <div class="grid grid-cols-2 gap-4" data-testid="pair-invalid-date">
        <div data-testid="on"><atom:date-picker invalid/></div>
        <div data-testid="off"><atom:date-picker invalid disabled/></div>
    </div>
    <div class="grid grid-cols-2 gap-4" data-testid="pair-invalid-tel">
        <div data-testid="on"><atom:input type="tel" invalid/></div>
        <div data-testid="off"><atom:input type="tel" invalid disabled/></div>
    </div>
    <div class="grid grid-cols-2 gap-4" data-testid="pair-invalid-otp">
        <div data-testid="on"><atom:input.otp length="4" invalid/></div>
        <div data-testid="off"><atom:input.otp length="4" invalid disabled/></div>
    </div>
    <div class="grid grid-cols-2 gap-4" data-testid="pair-invalid-email">
        <div data-testid="on"><atom:input type="email" multiple invalid/></div>
        <div data-testid="off"><atom:input type="email" multiple invalid disabled/></div>
    </div>
    <div class="grid grid-cols-2 gap-4" data-testid="pair-error-input">
        <atom:input.field label="Field" error="Not allowed"><atom:input data-testid="on" /></atom:input.field>
        <atom:input.field label="Field" error="Not allowed"><atom:input data-testid="off" disabled/></atom:input.field>
    </div>
    <div class="grid grid-cols-2 gap-4" data-testid="pair-error-textarea">
        <atom:input.field label="Field" error="Not allowed"><atom:textarea data-testid="on" rows="2" /></atom:input.field>
        <atom:input.field label="Field" error="Not allowed"><atom:textarea data-testid="off" rows="2" disabled/></atom:input.field>
    </div>
    <div class="grid grid-cols-2 gap-4" data-testid="pair-error-select-native">
        <atom:input.field label="Field" error="Not allowed"><div data-testid="on"><atom:select :options="$options" /></div></atom:input.field>
        <atom:input.field label="Field" error="Not allowed"><div data-testid="off"><atom:select :options="$options" disabled/></div></atom:input.field>
    </div>
    <div class="grid grid-cols-2 gap-4" data-testid="pair-error-select-listbox">
        <atom:input.field label="Field" error="Not allowed"><div data-testid="on"><atom:select variant="listbox" :options="$options" /></div></atom:input.field>
        <atom:input.field label="Field" error="Not allowed"><div data-testid="off"><atom:select variant="listbox" :options="$options" disabled/></div></atom:input.field>
    </div>
    <div class="grid grid-cols-2 gap-4" data-testid="pair-error-date">
        <atom:input.field label="Field" error="Not allowed"><div data-testid="on"><atom:date-picker /></div></atom:input.field>
        <atom:input.field label="Field" error="Not allowed"><div data-testid="off"><atom:date-picker disabled/></div></atom:input.field>
    </div>
    <div class="grid grid-cols-2 gap-4" data-testid="pair-error-tel">
        <atom:input.field label="Field" error="Not allowed"><div data-testid="on"><atom:input type="tel" /></div></atom:input.field>
        <atom:input.field label="Field" error="Not allowed"><div data-testid="off"><atom:input type="tel" disabled/></div></atom:input.field>
    </div>
    <div class="grid grid-cols-2 gap-4" data-testid="pair-error-otp">
        <atom:input.field label="Field" error="Not allowed"><div data-testid="on"><atom:input.otp length="4" /></div></atom:input.field>
        <atom:input.field label="Field" error="Not allowed"><div data-testid="off"><atom:input.otp length="4" disabled/></div></atom:input.field>
    </div>
    <div class="grid grid-cols-2 gap-4" data-testid="pair-error-email">
        <atom:input.field label="Field" error="Not allowed"><div data-testid="on"><atom:input type="email" multiple /></div></atom:input.field>
        <atom:input.field label="Field" error="Not allowed"><div data-testid="off"><atom:input type="email" multiple disabled/></div></atom:input.field>
    </div>

    {{-- an enabled native select that holds a disabled option is not a disabled select --}}
    <div class="grid grid-cols-2 gap-4" data-testid="pair-native-option-disabled">
        <div data-testid="with-disabled-option"><atom:select><atom:select.option value="a">Draft</atom:select.option><atom:select.option value="b" disabled>Locked</atom:select.option></atom:select></div>
        <div data-testid="plain"><atom:select><atom:select.option value="a">Draft</atom:select.option><atom:select.option value="b">Locked</atom:select.option></atom:select></div>
    </div>

    {{-- controls with no disabled look today (the ledger in tests/e2e/disabled-ledger.js names the pull request that gives each one): enabled beside disabled --}}
    <div class="grid grid-cols-2 gap-4" data-testid="pair-time">
        <div data-testid="on"><atom:time-picker/></div>
        <div data-testid="off"><atom:time-picker disabled/></div>
    </div>
    <div class="grid grid-cols-2 gap-4" data-testid="pair-range">
        <div data-testid="on"><atom:date-picker variant="range"/></div>
        <div data-testid="off"><atom:date-picker variant="range" disabled/></div>
    </div>
    <div class="grid grid-cols-2 gap-4" data-testid="pair-filter">
        <div data-testid="on"><atom:select variant="filter" label="Status" :options="$options"/></div>
        <div data-testid="off"><atom:select variant="filter" label="Status" :options="$options" disabled/></div>
    </div>
    <div class="grid grid-cols-2 gap-4" data-testid="pair-color">
        <div data-testid="on"><atom:input type="color"/></div>
        <div data-testid="off"><atom:input type="color" disabled/></div>
    </div>
    <div class="grid grid-cols-2 gap-4" data-testid="pair-rating">
        <div data-testid="on"><atom:rating label="Rate" value="3"/></div>
        <div data-testid="off"><atom:rating label="Rate" value="3" disabled/></div>
    </div>
    <div class="grid grid-cols-2 gap-4" data-testid="pair-uploader">
        <div data-testid="on"><atom:uploader/></div>
        <div data-testid="off"><atom:uploader disabled/></div>
    </div>
    <div class="grid grid-cols-2 gap-4" data-testid="pair-dropzone">
        <div data-testid="on"><atom:uploader.dropzone/></div>
        <div data-testid="off"><atom:uploader.dropzone disabled/></div>
    </div>
    <div class="grid grid-cols-2 gap-4" data-testid="pair-tiptap">
        <div data-testid="on"><atom:tiptap/></div>
        <div data-testid="off"><atom:tiptap disabled/></div>
    </div>
    <div class="grid grid-cols-2 gap-4" data-testid="pair-chat">
        <div data-testid="on"><atom:tiptap.chat/></div>
        <div data-testid="off"><atom:tiptap.chat disabled/></div>
    </div>

    {{-- a field inside a disabled <atom:form> beside the same field with its own `disabled`: "on" is the reference, "off" is the form's --}}
    <div class="grid grid-cols-2 gap-4" data-testid="pair-form-input">
        <atom:input data-testid="on" value="Locked value" disabled/>
        <atom:form disabled inset><atom:input data-testid="off" value="Locked value"/></atom:form>
    </div>
    <div class="grid grid-cols-2 gap-4" data-testid="pair-form-textarea">
        <atom:textarea data-testid="on" rows="2" disabled>Locked</atom:textarea>
        <atom:form disabled inset><atom:textarea data-testid="off" rows="2">Locked</atom:textarea></atom:form>
    </div>
    <div class="grid grid-cols-2 gap-4" data-testid="pair-form-select-native">
        <div data-testid="on"><atom:select :options="$options" disabled/></div>
        <atom:form disabled inset><div data-testid="off"><atom:select :options="$options"/></div></atom:form>
    </div>
    <div class="grid grid-cols-2 gap-4" data-testid="pair-form-select-listbox">
        <div data-testid="on"><atom:select variant="listbox" :options="$options" disabled/></div>
        <atom:form disabled inset><div data-testid="off"><atom:select variant="listbox" :options="$options"/></div></atom:form>
    </div>

    {{-- the prefix box beside a disabled field, and a host's own `readonly` (which is not a disabled state) against an enabled field --}}
    <div class="grid grid-cols-2 gap-4" data-testid="pair-prefix">
        <div data-testid="on"><atom:input prefix="https://" suffix=".test" value="Locked value" disabled/></div>
        <atom:form disabled inset><div data-testid="off"><atom:input prefix="https://" suffix=".test" value="Locked value"/></div></atom:form>
    </div>
    <div class="grid grid-cols-2 gap-4" data-testid="pair-readonly-input">
        <atom:input data-testid="on" value="Host value"/>
        <atom:input data-testid="off" value="Host value" readonly/>
    </div>
    <div class="grid grid-cols-2 gap-4" data-testid="pair-readonly-textarea">
        <atom:textarea data-testid="on" rows="2">Host value</atom:textarea>
        <atom:textarea data-testid="off" rows="2" readonly>Host value</atom:textarea>
    </div>
    <atom:form disabled inset data-testid="form-buttons" class="flex gap-4">
        <atom:button type="submit" data-testid="submit">Save</atom:button>
        <atom:button data-testid="plain">Download</atom:button>
        <atom:button type="submit" :disabled="false" data-testid="own">Save anyway</atom:button>
    </atom:form>
    <div class="grid grid-cols-2 gap-4" data-testid="pair-form-label">
        <div data-testid="on"><atom:input label="Name" caption="A caption" value="Locked value"/></div>
        <atom:form disabled inset><div data-testid="off"><atom:input label="Name" caption="A caption" value="Locked value"/></div></atom:form>
    </div>

    {{-- listboxes holding a value: one, and several as chips --}}
    <div class="grid grid-cols-2 gap-4" x-data="{ one: 'a', many: ['a', 'b'] }">
        <div data-testid="listbox-one-on"><atom:select variant="listbox" :options="$options" x-model="one"/></div>
        <div data-testid="listbox-one-off"><atom:select variant="listbox" :options="$options" x-model="one" disabled/></div>
        <div data-testid="listbox-many-on"><atom:select variant="listbox" multiple :options="$options" x-model="many"/></div>
        <div data-testid="listbox-many-off"><atom:select variant="listbox" multiple :options="$options" x-model="many" disabled/></div>
    </div>
</div>
@livewireScripts
</atom:html>
