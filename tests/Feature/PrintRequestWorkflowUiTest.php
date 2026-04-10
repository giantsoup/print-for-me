<?php

it('does not render redundant workflow copy in the shared workflow action component', function () {
    $component = file_get_contents(resource_path('js/components/luminous/PrintRequestStateActions.vue'));

    expect($component)
        ->toBeString()
        ->toContain('function isCompletedStep(index: number): boolean')
        ->toContain("props.status === 'complete'")
        ->not->toContain('Current state')
        ->not->toContain('No further state changes are available.')
        ->not->toContain('This request is currently')
        ->toContain('Completed')
        ->toContain('Upcoming');
});
