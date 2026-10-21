<?php

/**
 * What atom puts on a host's classes. Every macro whose closure was written inside
 * atom's src/ is collected from every macroable class the process has loaded, so a
 * macro added anywhere, on any class, fails here until it is listed on purpose.
 *
 * @return array<class-string, list<string>>
 */
function atomRegisteredMacros(): array
{
    $src = realpath(__DIR__.'/../../src').DIRECTORY_SEPARATOR;
    $found = [];

    foreach (get_declared_classes() as $class) {
        $reflection = new ReflectionClass($class);

        // Macroable classes and Eloquent's builder (which keeps its own list) all store them in a static $macros;
        // a subclass reads its parent's, so only the class that declares the list reports it
        if (! $reflection->hasProperty('macros') || $reflection->getProperty('macros')->getDeclaringClass()->getName() !== $class) {
            continue;
        }

        $property = $reflection->getProperty('macros');
        $property->setAccessible(true);

        $macros = $property->getValue();

        foreach ($macros as $name => $macro) {
            $file = $macro instanceof Closure ? (new ReflectionFunction($macro))->getFileName() : null;

            if ($file && str_starts_with(realpath($file), $src)) {
                $found[$class][] = $name;
            }
        }
    }

    ksort($found);

    return array_map(function (array $names) {
        sort($names);

        return $names;
    }, $found);
}

it('registers exactly the macros atom documents, on exactly the classes it documents', function () {
    $builder = ['breakdown', 'filter', 'randomCode', 'tableColumnType', 'tableColumns', 'toPage', 'toTable', 'whereDateBetween'];

    $expected = [
        \Illuminate\Database\Eloquent\Builder::class => $builder,
        \Illuminate\Database\Query\Builder::class => $builder,
        \Illuminate\Http\Request::class => ['portal'],
        \Illuminate\Support\Arr::class => ['pick'],
        \Illuminate\Support\Str::class => ['initials', 'interval', 'namespace'],
        \Illuminate\Support\Stringable::class => ['interval', 'namespace'],
        \Livewire\Component::class => ['action', 'alert', 'command', 'confirm', 'modal', 'toast', 'wirekey'],
    ];
    ksort($expected);

    expect(atomRegisteredMacros())->toBe($expected);
});

it('puts nothing on the component attribute bag, whose lookups are internal helpers', function () {
    expect(atomRegisteredMacros())->not->toHaveKey(\Illuminate\View\ComponentAttributeBag::class);

    foreach (['size', 'field', 'getAny', 'getLike', 'classes', 'styles', 'fieldId', 'modifier', 'hasLike'] as $name) {
        expect(\Illuminate\View\ComponentAttributeBag::hasMacro($name))->toBeFalse($name);
    }
});

it('no longer registers the macros no host used', function () {
    expect(\Illuminate\Http\Request::hasMacro('subdomain'))->toBeFalse()
        ->and(\Illuminate\Http\Request::hasMacro('hostWithoutSubdomain'))->toBeFalse()
        ->and(\Illuminate\Http\Request::hasMacro('isLivewireRequest'))->toBeFalse()
        ->and(\Illuminate\Support\Str::hasMacro('dotpath'))->toBeFalse()
        ->and(\Illuminate\Support\Stringable::hasMacro('dotpath'))->toBeFalse()
        ->and(\Illuminate\Database\Query\Builder::hasMacro('tableHasColumn'))->toBeFalse();
});
