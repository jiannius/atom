<?php

namespace Jiannius\Atom\Services;

class Broadcast
{
    public $config = [
        'name' => null,
        'private' => null,
        'public' => null,
        'with' => null,
        'queue' => 'default',
    ];

    // set name
    public function name($name)
    {
        $this->config['name'] = $name;
        
        return $this;
    }

    // set private channel
    public function private($channel)
    {
        $this->config['private'] = $channel;
        
        return $this;
    }

    // set public channel
    public function public($channel)
    {
        $this->config['public'] = $channel;

        return $this;
    }

    // set with
    public function with($with)
    {
        $this->config['with'] = $with;
        
        return $this;
    }

    // set queue
    public function queue($queue)
    {
        $this->config['queue'] = $queue;
        
        return $this;
    }

    // send
    public function send() : void
    {
        event(new \Jiannius\Atom\Events\SendBroadcast($this->config));
    }

    // send now
    public function sendNow() : void
    {
        event(new \Jiannius\Atom\Events\SendBroadcastNow($this->config));
    }
}