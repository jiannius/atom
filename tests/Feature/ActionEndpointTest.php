<?php

it('does not invoke an action that has not opted in to http', function () {
    $response = $this->postJson('/atom/action/closed');

    $response->assertNotFound();
    expect($response->json('ran'))->toBeNull();
});

it('invokes an action that opted in to http', function () {
    $response = $this->postJson('/atom/action/open');

    $response->assertOk();
    expect($response->json('ran'))->toBe('handle');
});

it('answers the same way for an unknown action as for one that did not opt in', function () {
    $unknown = $this->postJson('/atom/action/no-such-action');
    $closed = $this->postJson('/atom/action/closed');

    expect($unknown->status())->toBe($closed->status())
        ->and($unknown->json())->toBe($closed->json());
});

it('denies an opted-in action whose authorize rejects the caller', function () {
    $response = $this->postJson('/atom/action/guarded', ['pass' => 'no']);

    $response->assertForbidden();
    expect($response->json('ran'))->toBeNull();
});

it('runs an opted-in action whose authorize accepts the caller', function () {
    $response = $this->postJson('/atom/action/guarded', ['pass' => 'yes']);

    $response->assertOk();
    expect($response->json('ran'))->toBeTrue();
});

it('ignores a caller-supplied method and always runs handle', function () {
    $response = $this->postJson('/atom/action/open', ['method' => 'secret']);

    $response->assertOk();
    expect($response->json('ran'))->toBe('handle');
});

it('does not pass the reserved method key through to the action', function () {
    $response = $this->postJson('/atom/action/open', ['method' => 'secret', 'q' => 'jane']);

    expect($response->json('params'))->toBe(['q' => 'jane']);
});

it('answers denials as json so the front-end ajax helper can parse them', function () {
    $response = $this->postJson('/atom/action/closed');

    $response->assertHeader('content-type', 'application/json');
    expect($response->json('message'))->toBeString();
});
