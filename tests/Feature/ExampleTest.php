<?php

test('the application returns a successful response', function () {
    login();

    $response = $this->get('/');

    $response->assertRedirect();
});
