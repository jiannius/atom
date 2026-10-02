<div class="space-y-8">
    <div>Saves: <span data-saves>{{ $saves }}</span></div>

    <button type="button" wire:click="touch" data-touch>Touch</button>

    <atom:form wire:submit="save" data-form="main" :error-toast="$errorToast" :disabled="$disabled" :recaptcha="$recaptcha">
        <atom:input label="Name" wire:model="name" data-probe="name" />
        <atom:input label="Nickname" wire:model="nickname" data-probe="nickname" />
        <atom:input label="Email" wire:model="email" data-probe="email" />
        <atom:button type="submit" data-submit="main">Save</atom:button>
    </atom:form>

    <atom:form wire:submit="saveOther" data-form="other" :error-toast="$errorToast">
        <atom:input label="Other" wire:model="other" data-probe="other" />
        <atom:button type="submit" data-submit="other">Save other</atom:button>
    </atom:form>

    {{-- shares saveOther with the form above, for a call that names no origin --}}
    <atom:form wire:submit="saveOther" data-form="twin">
        <atom:input label="Twin" wire:model="twin" data-probe="twin" />
        <atom:button type="submit" data-submit="twin">Save twin</atom:button>
    </atom:form>

    <atom:form wire:submit="savePlain" data-form="plain">
        <atom:input label="Plain" wire:model="plain" data-probe="plain" />
        <atom:button type="submit" data-submit="plain">Save plain</atom:button>
    </atom:form>

    @unless ($gone)
        <atom:form wire:submit="saveGone" data-form="gone">
            <atom:input label="Gone field" wire:model="goneField" data-probe="gone-field" />
            <atom:button type="submit" data-submit="gone">Save gone</atom:button>
        </atom:form>
    @endunless

    <atom:form wire:submit="saveMany" data-form="many">
        @foreach (array_keys($rows) as $key)
            <atom:input :label="'Row '.$key" wire:model="rows.{{ $key }}" />
        @endforeach
        <atom:button type="submit" data-submit="many">Save many</atom:button>
    </atom:form>

    <atom:form wire:submit="saveOrphanWithField" data-form="orphan">
        <atom:input label="Orphan field" wire:model="orphanField" />
        <atom:button type="submit" data-submit="orphan">Save orphan</atom:button>
    </atom:form>

    {{-- plain inputs: the matcher knows each only by a wire:model modifier or a name, so a
         bracketed name has to be read as the dotted error key it fails under --}}
    <atom:form wire:submit="saveRaw" data-form="raw">
        <input wire:model.blur="blurOnly" data-probe="blur-only">
        <input wire:model.live.debounce.300ms="liveOnly" data-probe="live-only">
        <input name="nameOnly" data-probe="name-only">
        <input name="tags[]" data-probe="tags">
        <input name="rows[a]" data-probe="rows-a">
        <atom:button type="submit" data-submit="raw">Save raw</atom:button>
    </atom:form>

    {{-- one of every atom control, each bound to a property that fails: the e2e checks that
         the toast's matcher finds every one of them --}}
    <atom:form wire:submit="saveControls" data-form="controls">
        <atom:select variant="listbox" label="Listbox" wire:model="selectListbox" :options="[['value' => 'a', 'label' => 'A']]" />
        <atom:select label="Native" wire:model="selectNative">
            <atom:select.option value="a" label="A" />
        </atom:select>
        <atom:select variant="listbox" multiple label="Multiple" wire:model="selectMultiple" :options="[['value' => 'a', 'label' => 'A']]" />
        <atom:date-picker label="Date" wire:model="dateSingle" />
        <atom:date-picker variant="range" label="Range" wire:model="dateRange" />
        <atom:time-picker label="Time" wire:model="timePick" />
        <atom:tiptap label="Tiptap" wire:model="tiptapEager" />
        <atom:tiptap label="Tiptap lazy" wire:model.blur="tiptapLazy" />
        <atom:editor label="Editor" wire:model="editorField" />
        <atom:uploader wire:model="upload" />
        <atom:checkbox label="Agree" wire:model="agree" />
        <atom:checkbox.group>
            <atom:checkbox label="Email" value="email" wire:model="channels" />
            <atom:checkbox label="SMS" value="sms" wire:model="channels" />
        </atom:checkbox.group>
        <atom:radio.group label="Plan">
            <atom:radio label="Starter" value="starter" wire:model="plan" />
            <atom:radio label="Growth" value="growth" wire:model="plan" />
        </atom:radio.group>
        <atom:input type="tel" label="Phone" wire:model="phone" />
        <atom:input.otp wire:model="otp" />
        <atom:input type="email" label="Email" wire:model="mail" />
        <atom:input type="color" label="Color" wire:model="color" />
        <atom:input label="Text" wire:model="textField" />
        <atom:toggle label="Toggle" wire:model="toggled" />
        <atom:slider label="Volume" wire:model="volume" />
        <atom:rating label="Rating" wire:model="stars" />
        <atom:textarea label="Notes" wire:model="notes" />
        <atom:button type="submit" data-submit="controls">Save controls</atom:button>
    </atom:form>

    <atom:form.modal name="fixture-modal">
        <atom:input label="Modal field" wire:model="modalField" data-probe="modal-field" />
    </atom:form.modal>
</div>
