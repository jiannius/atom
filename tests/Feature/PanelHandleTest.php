<?php

/**
 * A stand-in for the current Livewire component that records what is dispatched on it.
 */
function dispatchRecorder(): object
{
    return new class {
        public array $dispatched = [];

        public function dispatch(string $event, ...$params): void
        {
            $this->dispatched[] = [$event, $params];
        }
    };
}

it('dispatches the modal events on the current component, with the name', function () {
    $fake = dispatchRecorder();

    withLivewireContext($fake, function () {
        $modal = app('atom')->modal('invoice');

        $modal->show();
        $modal->slide('right');
        $modal->slide();
        $modal->close();

        expect($modal->name)->toBe('invoice');
    });

    expect($fake->dispatched)->toBe([
        ['atom-modal-show', ['name' => 'invoice']],
        ['atom-modal-show', ['name' => 'invoice', 'variant' => 'slide', 'position' => 'right']],
        ['atom-modal-show', ['name' => 'invoice', 'variant' => 'slide', 'position' => null]],
        ['atom-modal-close', ['name' => 'invoice']],
    ]);
});

it('dispatches the command events on the current component, and has no slide()', function () {
    $fake = dispatchRecorder();

    withLivewireContext($fake, function () {
        $command = app('atom')->command('palette');

        $command->show();
        $command->close();

        expect($command->name)->toBe('palette')->and(method_exists($command, 'slide'))->toBeFalse();
    });

    expect($fake->dispatched)->toBe([
        ['atom-command-show', ['name' => 'palette']],
        ['atom-command-close', ['name' => 'palette']],
    ]);
});
