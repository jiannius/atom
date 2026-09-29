<?php

// a plain file in app/Models that declares no class: the purge must skip it, not abort on it

if (! function_exists('purge_fixture_helper')) {
    function purge_fixture_helper(): string
    {
        return Foo::class;
    }
}
