@props([
    'limit' => 5,
    'label' => 'name',
])

{{-- Local only, the same check routes/web.php makes before it registers the sign-in route; outside local nothing is printed and
     no query runs. Put it after the login form, never inside it: HTML drops a nested form and the buttons would submit the
     login form instead. Every button is the default tier, as the login form's own submit is the page's one primary. --}}
@if (app()->environment('local'))
    @php
    $accounts = \Jiannius\Atom\Services\DevLogin::users((int) $limit);
    @endphp

    @if ($accounts->isNotEmpty())
        <form method="POST" {{ $attributes->class('flex flex-col gap-2') }} data-atom-dev-login>
            @csrf

            <atom:separator>{{ t('Local development') }}</atom:separator>

            @foreach ($accounts as $account)
                @php
                $id = $account->getAuthIdentifier();
                $action = route('atom.dev.login', ['atomUser' => $id]);
                $name = data_get($account, $label);
                $name = is_scalar($name) && (string) $name !== '' ? (string) $name : (string) $id;
                @endphp

                <atom:button type="submit" variant="default" block :formaction="$action">{{ t('Log in as :name', ['name' => $name]) }}</atom:button>
            @endforeach
        </form>
    @endif
@endif
