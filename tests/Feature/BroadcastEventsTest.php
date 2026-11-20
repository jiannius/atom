<?php

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Jiannius\Atom\Events\SendBroadcast;
use Jiannius\Atom\Events\SendBroadcastNow;

$config = ['name' => 'invoice.paid', 'private' => ['user.1', null], 'public' => 'orders', 'with' => ['id' => 7], 'queue' => 'sockets'];

it('broadcasts the same channels, name, queue and data whether queued or not', function (string $event, string $contract) use ($config) {
    $instance = new $event($config);

    expect($instance)->toBeInstanceOf($contract)
        ->and($instance->broadcastAs())->toBe('invoice.paid')
        ->and($instance->broadcastQueue())->toBe('sockets')
        ->and($instance->broadcastWith())->toBe(['id' => 7]);

    $channels = $instance->broadcastOn();

    expect($channels)->toHaveCount(2)
        ->and($channels[0])->toBeInstanceOf(PrivateChannel::class)->and($channels[0]->name)->toBe('private-user.1')
        ->and($channels[1])->toBeInstanceOf(Channel::class)->and($channels[1]->name)->toBe('orders');
})->with([
    'queued' => [SendBroadcast::class, ShouldBroadcast::class],
    'now' => [SendBroadcastNow::class, ShouldBroadcastNow::class],
]);

it('dispatches through the Broadcast service', function () {
    Event::fake([SendBroadcast::class, SendBroadcastNow::class]);

    app('atom')->broadcast()->name('x')->private('a')->send();
    app('atom')->broadcast()->name('y')->public('b')->sendNow();

    Event::assertDispatched(SendBroadcast::class, fn ($e) => $e->broadcastAs() === 'x');
    Event::assertDispatched(SendBroadcastNow::class, fn ($e) => $e->broadcastAs() === 'y');
});
