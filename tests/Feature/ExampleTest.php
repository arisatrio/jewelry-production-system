<?php

test('returns a successful response', function () {
    $response = $this->get(route('home'));

    $response->assertOk();
});

test('home page renders the kanban inertia component with analytics', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('analytics/work-order-kanban')
            ->has('analytics')
            ->has('processTabs')
        );
});
