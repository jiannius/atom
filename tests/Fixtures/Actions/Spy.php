<?php

namespace App\Actions;

/**
 * An action that records that it ran, so a test can prove a refused browser call
 * never reached it.
 */
class Spy
{
    /** @var int */
    public static int $ran = 0;

    /**
     * Handle the action
     */
    public function handle($params) : array
    {
        static::$ran++;

        return ['ran' => 'spy'];
    }
}
