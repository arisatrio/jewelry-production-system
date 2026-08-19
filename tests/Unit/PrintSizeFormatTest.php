<?php

test('print size formatting trims unnecessary trailing zeros', function (float $value, string $expected) {
    $formatted = rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');

    expect($formatted !== '' ? $formatted : '0')->toBe($expected);
})->with([
    [20.0, '20'],
    [3.2, '3.2'],
    [2.35, '2.35'],
    [0.0, '0'],
]);
