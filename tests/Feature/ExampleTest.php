<?php

test('returns a successful redirect to login', function () {
    $response = $this->get(route('home'));

    $response->assertRedirect(route('login'));
});
