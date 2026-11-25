<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
 // ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

function diagramPayload(): array
{
    return [
        'schema_version' => 1,
        'los' => 0.68,
        'players' => [
            ['id' => 'p-qb', 'type' => 'quarterback', 'label' => 'QB', 'x' => 0.5, 'y' => 0.78, 'color' => '#2563eb'],
            ['id' => 'p-x', 'type' => 'receiver', 'label' => 'X', 'x' => 0.1, 'y' => 0.68, 'color' => '#2563eb'],
        ],
        'routes' => [[
            'id' => 'r-x', 'player_id' => 'p-x', 'kind' => 'route', 'name' => 'Slant',
            'points' => [['x' => 0.1, 'y' => 0.68], ['x' => 0.3, 'y' => 0.5]],
            'style' => ['dash' => 'solid', 'end' => 'arrow', 'color' => '#facc15'],
        ]],
        'annotations' => [],
    ];
}

function playPayload(array $overrides = []): array
{
    return [
        'name' => 'Quick slant',
        'formation' => 'Bunch',
        'category' => 'pass',
        'difficulty' => 'easy',
        'beginner_friendly' => true,
        'notes' => 'Throw on time.',
        'diagram' => diagramPayload(),
        ...$overrides,
    ];
}
