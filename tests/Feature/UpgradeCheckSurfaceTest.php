<?php

use Jiannius\Atom\Services\UpgradeCheck;

/**
 * UpgradeCheck's surface, pinned: its public methods (with their parameters,
 * return types and static-ness), its protected methods, its properties and the
 * value of every constant. The reading half of the class moves into a base
 * class (HostScanner), and the codemod (Services\Upgrade) and the commands
 * reach UpgradeCheck through exactly this surface, so a move must not change
 * it. Reflection reports inherited members too, so a method that moves up
 * stays on the list.
 */
function upgradeCheckSurface(string $visibility): array
{
    $reflection = new ReflectionClass(UpgradeCheck::class);

    if ($visibility === 'constants') {
        $constants = [];

        foreach ($reflection->getConstants() as $name => $value) {
            $constants[] = $name.' = '.md5(serialize($value));
        }

        sort($constants);

        return $constants;
    }

    if ($visibility === 'properties') {
        $properties = array_map(fn (ReflectionProperty $property) => $property->getName(), $reflection->getProperties());

        sort($properties);

        return $properties;
    }

    $methods = $reflection->getMethods($visibility === 'public' ? ReflectionMethod::IS_PUBLIC : ReflectionMethod::IS_PROTECTED);

    $signatures = array_map(function (ReflectionMethod $method) use ($visibility) {
        $name = ($method->isStatic() ? 'static ' : '').$method->getName();

        if ($visibility !== 'public') {
            return $name;
        }

        $parameters = implode(', ', array_map(
            fn (ReflectionParameter $parameter) => ($parameter->getType() ? $parameter->getType().' ' : '')
                .($parameter->isPassedByReference() ? '&' : '')
                .'$'.$parameter->getName()
                .($parameter->isDefaultValueAvailable() ? ' = '.str_replace(["\n", 'NULL', 'array (  )'], ['', 'null', '[]'], var_export($parameter->getDefaultValue(), true)) : ''),
            $method->getParameters(),
        ));

        return $name.'('.$parameters.')'.($method->getReturnType() ? ': '.$method->getReturnType() : '');
    }, $methods);

    sort($signatures);

    return $signatures;
}

it('keeps the public methods of UpgradeCheck', function () {
    expect(upgradeCheckSurface('public'))->toBe([
        '__construct(?string $guidelinePath = null)',
        'codeText(string $kind, string $source): string',
        'commentFree(string $kind, string $source): string',
        'format(array $result, ?int $limit = 10): string',
        'guideline(string $root): array',
        'guidelineBlock(string $markdown): ?string',
        'inUnquotedAttribute(string $markup, int $offset): bool',
        'markupText(string $kind, string $source): string',
        'parseTags(string $blade): array',
        'renderedGuideline(): ?string',
        'scan(string $root, array $overlay = array ()): array',
        'static atomComponentUses(string $source, ?array $structure = null): array',
        'static checks(): array',
        'static currencyOwnerAt(string $text, int $dot): ?string',
        'static has(array $attributes, string $name): bool',
        'static hostAtomComponentUses(string $source, ?array $structure = null): array',
        'static tone(string $name): string',
        'static value(array $attributes, string $name): ?string',
        'tagText(string $source): string',
    ]);
});

it('keeps the protected methods of UpgradeCheck', function () {
    expect(upgradeCheckSurface('protected'))->toBe([
        'arrayLiterals',
        'atomComponentSites',
        'bladeJsSegments',
        'blank',
        'blankJsComments',
        'blankPhpComments',
        'callsDateUse',
        'carbonTyped',
        'codeFromMarkup',
        'compareGuideline',
        'components',
        'directories',
        'disabledFormViewControls',
        'discover',
        'installedVersion',
        'isPublishedViewsDirectory',
        'isSkippedDirectory',
        'kindOf',
        'lineAt',
        'lineStarts',
        'maskBladePhp',
        'maskBoundAttributes',
        'maskJsLiterals',
        'matchingDelimiter',
        'matchingParenthesis',
        'orderTraits',
        'parseAttributes',
        'partialsOf',
        'prepare',
        'read',
        'readAttributes',
        'renderedViews',
        'resolveView',
        'skeletonTheme',
        'statementEnd',
        'static componentUses',
        'summarise',
        'tagReport',
        'traitEvidence',
        'vendorLibraryDirectory',
        'viewRoots',
        'walk',
        'wireHelperListeners',
    ]);
});

it('keeps the properties of UpgradeCheck', function () {
    expect(upgradeCheckSurface('properties'))->toBe([
        'guidelinePath',
        'overlay',
        'root',
        'skipped',
    ]);
});

it('keeps the constants of UpgradeCheck, name and value', function () {
    expect(upgradeCheckSurface('constants'))->toBe([
        'BAG_MACROS = 30d05afc0bb1c56ce443797dffea936c',
        'BREAKS = 5dad2db541174ea6a21c87f7ffdb97d2',
        'COVERED_BY_DATE_USE = 588a226c22ef63902e7069974c1f937d',
        'CURRENCY_OWNERS = f41f91882694ee2b197cc470a6e51b17',
        'DIRECTORIES = 132e9de754064f177da0e8482aa429a4',
        'GUIDE = 9d398f0e47a2ae28c796030c82c30592',
        'HELPERS = d61d23f39c930da184d5b48d9c88f4e3',
        'MAX_BYTES = b48676a4c5636a88f8fb157cb020aadc',
        'MINIFIED_LINE = a9ab29b02d7de53220876821ca764574',
        'RESTYLED = db4b4e94790ef1a61f4f0ecbcb7d457f',
        'REVIEW = fad31e75ba52610f1c58d1a0c0ae149f',
        'SKIP_DIRECTORIES = e3e961a5375ce390a95f716787b1e05e',
        'SKIP_PATHS = 4655eb36ad35276b974c889f057ce2a2',
        'TEST_CLASSES = 8dbfb36a902572504436610423c7b118',
        'TONES = 31885f67fcdfb1d8a7223496b69dd499',
        'TRAITS = 239ac38105a14fe4e2756de11bdd19d9',
        'VENDOR_DIRECTORY = 3ec2051e98436fcd2945c9411732f63f',
        'VISUAL = b10a542351a3b8961ec792e5686158a5',
    ]);
});
