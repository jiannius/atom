<?php

it('boots the atom service provider', function () {
    expect(app()->bound('atom'))->toBeTrue();
});
