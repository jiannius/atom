<atom:html title="E2E: Attribute sweep" :vite="[]" :dark="false" class="min-h-screen bg-white">
{{-- Controls that take a caller class: it lands on the visible root while the
     behaviour (name, value, keyboard) stays on the hidden input. --}}
<div class="p-4 space-y-4">
    <atom:checkbox label="Agree" name="agree" id="sweep-checkbox" class="mt-3" data-sweep="checkbox" />
    <atom:toggle label="Notify" name="notify" id="sweep-toggle" class="mt-3" data-sweep="toggle" />

    <div role="radiogroup" aria-label="Plan">
        <atom:radio label="Free" name="plan" value="free" class="mt-3" data-sweep="radio-free" />
        <atom:radio label="Pro" name="plan" value="pro" class="mt-3" data-sweep="radio-pro" />
    </div>
</div>
</atom:html>
