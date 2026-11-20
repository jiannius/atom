<?php

namespace Jiannius\Atom\Events;

use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Jiannius\Atom\Traits\BroadcastsFromConfig;

class SendBroadcastNow implements ShouldBroadcastNow
{
    use BroadcastsFromConfig;
}
