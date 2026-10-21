<?php

/**
 * The file a macro's code lives in: a closure, an invokable object, an [object|class, method]
 * pair or a 'Class::method' / function-name string. Null when it cannot be resolved.
 */
function atomMacroSourceFile(mixed $macro): ?string
{
    try {
        $reflection = match (true) {
            $macro instanceof Closure => new ReflectionFunction($macro),
            is_object($macro) => new ReflectionMethod($macro, '__invoke'),
            is_array($macro) && count($macro) === 2 => new ReflectionMethod($macro[0], $macro[1]),
            is_string($macro) && str_contains($macro, '::') => new ReflectionMethod($macro),
            is_string($macro) => new ReflectionFunction($macro),
            default => null,
        };
    } catch (ReflectionException) {
        return null;
    }

    return $reflection?->getFileName() ?: null;
}

/**
 * What atom puts on a host's classes. Every macro whose code was written inside atom's
 * src/ (a closure, an invokable object or a callable method) is collected from every class
 * that declares a static $macros list and is loaded, so a macro added anywhere, on any
 * such class, fails here until it is listed on purpose.
 *
 * Out of scope: a macro registered lazily on a class that has not been loaded yet (it
 * appears only once the class is), and macros on other systems such as Carbon's own.
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

        foreach ($reflection->getProperty('macros')->getValue() as $name => $macro) {
            $file = atomMacroSourceFile($macro);

            if ($file && str_starts_with(realpath($file) ?: $file, $src)) {
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
