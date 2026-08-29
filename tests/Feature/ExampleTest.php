<?php

test('the public entry point redirects to the catalogue', function () {
    $response = $this->get('/');

    $response->assertRedirect('/catalogue');
});
