<atom:html title="E2E: Form Error Toast" :vite="[]" :dark="false" class="min-h-screen bg-white">
{{-- Hosts tests/Fixtures/FormErrorToastFixture.php so the E2E can fail and pass a real
     Livewire submit and watch the sticky error toast. `?error-toast=0`, `?disabled=1`
     and `?recaptcha=1` flip the main form's props; `?no-toast=1` leaves the page
     without an <atom:toast> to prove the hook is a no-op there. --}}
<div class="p-4">
    <livewire:atom-e2e-form-error-toast
        :error-toast="request('error-toast', '1') !== '0'"
        :disabled="request('disabled') === '1'"
        :recaptcha="request('recaptcha') === '1'" />
</div>

@unless (request('no-toast'))
    <atom:toast />
@endunless

@livewireScripts
</atom:html>
