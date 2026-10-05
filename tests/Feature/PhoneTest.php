<?php

declare(strict_types=1);

use App\Support\Phone;

/*
 * Every way a couple can type the same Malaysian mobile collapses to one
 * canonical form — including the invisible variants that look identical on
 * screen and used to be rejected (a trailing space, the non-breaking hyphen
 * U+2011 that macOS/iOS smart punctuation and autofill insert).
 */
it('formats a mobile number however it was typed', function (string $typed) {
    expect(Phone::format($typed))->toBe('012-452 2344');
})->with([
    'digits only' => ['0124522344'],
    'already formatted' => ['012-452 2344'],
    'spaces' => ['012 452 2344'],
    'non-breaking hyphen (U+2011)' => ["012\u{2011}452 2344"],
    'non-breaking space (U+00A0)' => ["012-452\u{00A0}2344"],
    'trailing space' => ['012-452 2344 '],
    '+60 prefix' => ['+60124522344'],
    '60 prefix' => ['60124522344'],
    '+60 with spacing' => ['+60 12 452 2344'],
]);

it('groups an eleven-digit number 3-4-4', function () {
    expect(Phone::format('01123456789'))->toBe('011-2345 6789')
        ->and(Phone::format('+601123456789'))->toBe('011-2345 6789');
});

it('leaves something that is not a mobile number for validation to reject', function (string $typed) {
    expect(Phone::format($typed))->toBe(trim($typed));
})->with([
    'too short' => ['01245223'],
    'landline' => ['03-2345 6789'],
    'letters' => ['abc'],
]);

it('passes blank through as null', function () {
    expect(Phone::format(null))->toBeNull()
        ->and(Phone::format('   '))->toBeNull();
});
