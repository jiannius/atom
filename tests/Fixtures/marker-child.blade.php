<div data-child>
    <atom:input label="Child required" name="c" required/>
    <atom:input label="Child other" name="d"/>
    <button type="button" wire:click="bump">Bump</button>
    <span>{{ $renders }}</span>
</div>
