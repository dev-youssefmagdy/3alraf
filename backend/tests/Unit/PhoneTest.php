<?php

use App\Support\Phone;

it('normalises every accepted Egyptian input form to the national number', function (string $in) {
    expect(Phone::normalize($in, 'EG'))->toBe(['01008489183', null]);
})->with(['01008489183', '1008489183', '+201008489183', '00201008489183', '201008489183', '0100 848-9183', '٠١٠٠٨٤٨٩١٨٣', '+20 100 848 9183']);

it('rejects bad Egyptian numbers', function (string $in, string $err) {
    expect(Phone::normalize($in, 'EG'))->toBe([null, $err]);
})->with([['+966501234567', 'wrong_country'], ['00966501234567', 'wrong_country'], ['0100848', 'invalid'], ['010084891833', 'invalid'], ['01a08489183', 'invalid'], ['0300848918', 'invalid']]);

it('handles other tenant countries', function () {
    expect(Phone::normalize('0501234567', 'SA'))->toBe(['0501234567', null])
        ->and(Phone::normalize('+966501234567', 'SA'))->toBe(['0501234567', null])
        ->and(Phone::normalize('+201008489183', 'SA'))->toBe([null, 'wrong_country'])
        ->and(Phone::normalize('55123456', 'KW'))->toBe(['55123456', null])
        ->and(Phone::normalize('123456789', 'DE'))->toBe(['123456789', null])
        ->and(Phone::normalize('123', 'DE'))->toBe([null, 'invalid'])
        ->and(Phone::normalize('0100848918', null)[0])->toBe('0100848918');
});

it('builds e164 and display forms without the trunk zero', function () {
    expect(Phone::e164('+20', '01008489183'))->toBe('+201008489183')
        ->and(Phone::display('+20', '01008489183'))->toBe('+20 100 848 9183')
        ->and(Phone::e164('+965', '55123456'))->toBe('+96555123456')
        ->and(Phone::e164('+20', null))->toBeNull();
});
