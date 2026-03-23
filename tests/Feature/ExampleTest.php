<?php

test('the application returns a successful response', function () {
    $response = $this->get('/feed.xml');

    $response->assertStatus(200);
});
