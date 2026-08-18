<?php

test('dashboard redirects to home', function () {
    $this->get(route('dashboard'))
        ->assertRedirect(route('home'));
});
