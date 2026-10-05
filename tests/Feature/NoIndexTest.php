<?php

declare(strict_types=1);

it('lets search engines index the real site by default', function () {
    $this->get('/')->assertOk()->assertHeaderMissing('X-Robots-Tag');
});

it('keeps a review copy out of search engines', function () {
    config()->set('app.noindex', true);

    $this->get('/')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    $this->get(route('packages'))->assertHeader('X-Robots-Tag', 'noindex, nofollow');
});
