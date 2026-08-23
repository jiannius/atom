<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Jiannius\Atom\Actions\GetOptions;

/**
 * Stands in for a host app's App\Actions\GetOptions: a couple of declared option
 * sets plus a method that is NOT an option set and must stay unreachable.
 */
class GetOptionsSubclass extends GetOptions
{
    /**
     * Option sets any caller may read
     */
    protected array $guest = ['brands', 'ghost'];

    /**
     * Option sets only a signed-in caller may read
     */
    protected array $auth = ['contacts'];

    /**
     * Set when the canary method below is invoked
     */
    public bool $canaryWasCalled = false;

    /**
     * A declared, guest-readable option set
     */
    public function brands() : array
    {
        return [['value' => 1, 'label' => 'Acme']];
    }

    /**
     * A declared option set that holds customer data
     */
    public function contacts() : array
    {
        return [['value' => 1, 'label' => 'Jane']];
    }

    // "ghost" is declared in $guest but has neither a method nor a JSON file
    // behind it, so it reaches getFromJson() and finds nothing.

    /**
     * NOT an option set — a zero-arg helper of the kind a host app would add.
     * Reachable as the name "purge-cache" if the class dispatches on input.
     */
    public function purgeCache() : array
    {
        $this->canaryWasCalled = true;

        return [];
    }
}
