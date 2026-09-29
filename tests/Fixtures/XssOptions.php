<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Jiannius\Atom\Actions\GetOptions;

/**
 * Option sets whose labels hold markup, and one that carries the trusted `html`
 * key. Pest calls it directly. The served e2e app aliases it to
 * App\Actions\GetOptions (see E2EServiceProvider) so a remote select can reach it —
 * that alias must never exist in the Pest process, or every test that resolves
 * `get-options` would be running this class instead of the package's.
 */
class XssOptions extends GetOptions
{
    /**
     * Option sets any caller may read
     */
    protected array $guest = ['hostile-people', 'hostile-groups', 'trusted-html'];

    /**
     * Option labels, captions and colours a user typed — every one of them must
     * reach the browser as text.
     */
    public function hostilePeople() : array
    {
        return [
            [
                'value' => 1,
                'label' => '<img src=x onerror="window.__xss=\'label\'">Mallory',
                'caption' => '<script>window.__xss=\'caption\'</script><img src=x onerror="window.__xss=\'caption\'">note',
                'color' => 'red; position: fixed; inset: 0; z-index: 9999',
            ],
            [
                'value' => 2,
                'label' => 'Alice & Bob <b>bold</b>',
                'color' => '#22c55e',
            ],
        ];
    }

    /**
     * A grouped set whose option label holds markup — the native select prints
     * these through its own group branch.
     */
    public function hostileGroups() : array
    {
        return [
            [
                'group' => 'Team',
                'options' => [
                    ['value' => 1, 'label' => '</select><img src=x onerror="window.__xss=\'group\'">Eve'],
                ],
            ],
        ];
    }

    /**
     * An option carrying the trusted `html` key: the host built it, so it is
     * rendered as markup.
     */
    public function trustedHtml() : array
    {
        return [
            ['value' => 1, 'label' => 'Trusted', 'html' => '<strong data-trusted>Trusted</strong>'],
        ];
    }
}
