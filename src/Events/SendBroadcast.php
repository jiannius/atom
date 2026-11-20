<?php

namespace Jiannius\Atom\Events;

use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Jiannius\Atom\Traits\BroadcastsFromConfig;

class SendBroadcast implements ShouldBroadcast
{
    use BroadcastsFromConfig;
}
