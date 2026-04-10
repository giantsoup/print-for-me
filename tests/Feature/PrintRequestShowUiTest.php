<?php

it('includes a direct action back to the requests index on the request show page', function () {
    $page = file_get_contents(resource_path('js/pages/prints/Show.vue'));

    expect($page)
        ->toBeString()
        ->toContain('<template #pageActions>')
        ->toContain(":href=\"route('print-requests.index')\"")
        ->toContain('prefetch')
        ->toContain("props.can.isAdmin ? 'Back to requests' : 'Back to my requests'")
        ->toContain('pill-button pill-button-secondary w-full sm:w-auto');
});
