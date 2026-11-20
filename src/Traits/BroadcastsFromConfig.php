<?php

namespace Jiannius\Atom\Traits;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Queue\SerializesModels;

/**
 * The body of the `SendBroadcast` and `SendBroadcastNow` events, which differ only in whether they queue: everything comes from the config
 * `Services\Broadcast` builds (`private` and `public` channel names, `name`, `queue`, `with`).
 */
trait BroadcastsFromConfig
{
    use SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public $config)
    {
        //
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        $private = collect(data_get($this->config, 'private'))->filter()->map(fn ($channel) => new PrivateChannel($channel));
        $public = collect(data_get($this->config, 'public'))->filter()->map(fn ($channel) => new Channel($channel));

        return $private->concat($public)->values()->all();
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return data_get($this->config, 'name');
    }

    /**
     * The name of the queue on which to place the broadcasting job.
     */
    public function broadcastQueue(): string
    {
        return data_get($this->config, 'queue');
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return data_get($this->config, 'with');
    }
}
