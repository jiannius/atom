<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Jiannius\Atom\Traits\AtomComponent;
use Livewire\Component;

/**
 * Hosts <atom:form>'s sticky error toast: two forms on one component (so the e2e can
 * check that only the submitter reacts), a modal form, and a reCAPTCHA form.
 * `?error-toast=0`, `?disabled=1` and `?recaptcha=1` on the page flip the form props.
 */
class FormErrorToastFixture extends Component
{
    use AtomComponent;

    public ?string $name = null;

    public ?string $nickname = null;

    public ?string $email = null;

    public ?string $other = null;

    public ?string $modalField = null;

    public bool $errorToast = true;

    public bool $disabled = false;

    public bool $recaptcha = false;

    public int $saves = 0;

    /**
     * Validate the first form; a pass raises the app's own "Saved" toast.
     */
    public function save(): void
    {
        $this->validate([
            'name' => 'required',
            'nickname' => 'required',
            'email' => 'required|email',
        ], [
            'name.required' => 'Name is required.',
            // the same sentence as `name`, to prove the toast lists each message once
            'nickname.required' => 'Name is required.',
            'email.required' => 'Email is required.',
            'email.email' => 'Email must be valid.',
        ]);

        $this->saves++;
        $this->toast('Saved');
    }

    /**
     * Validate the second form on the same component.
     */
    public function saveOther(): void
    {
        $this->validate(['other' => 'required'], ['other.required' => 'Other is required.']);

        $this->toast('Other saved');
    }

    /**
     * The default `submit` method of the modal's <atom:form>.
     */
    public function submit(): void
    {
        $this->validate(['modalField' => 'required'], ['modalField.required' => 'Modal field is required.']);

        $this->toast('Modal saved');
    }

    /**
     * Render the fixture view.
     */
    public function render()
    {
        return view('atom-test::form-error-toast');
    }
}
