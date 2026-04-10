<?php

it('uses a mobile-first visible grid for status filters on the request index page', function () {
    $page = file_get_contents(resource_path('js/pages/prints/Index.vue'));

    expect($page)
        ->toBeString()
        ->toContain('grid grid-cols-2 gap-2 sm:flex sm:flex-wrap sm:gap-2')
        ->toContain('min-h-12 w-full items-center justify-between')
        ->toContain('sm:w-auto sm:justify-start sm:px-5')
        ->not->toContain('no-scrollbar mt-6 flex gap-2 overflow-x-auto pb-2');
});

it('reuses the same mobile-first grid pattern for the urgency filters on the request index page', function () {
    $page = file_get_contents(resource_path('js/pages/prints/Index.vue'));

    expect(substr_count($page, 'grid grid-cols-2 gap-2 sm:flex sm:flex-wrap sm:gap-2'))->toBeGreaterThanOrEqual(2)
        ->and($page)->toContain('Scheduling')
        ->toContain('showUrgencyFilters');
});

it('uses a masked text input for the needed-by date on the create page', function () {
    $page = file_get_contents(resource_path('js/pages/prints/Create.vue'));

    expect($page)
        ->toContain('id="needed_by_date"')
        ->toContain('type="text"')
        ->toContain('inputmode="numeric"')
        ->toContain('maxlength="10"')
        ->toContain('placeholder="MM/DD/YYYY"')
        ->not->toContain('type="date"');
});
