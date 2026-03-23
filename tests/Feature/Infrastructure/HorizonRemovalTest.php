<?php

test('horizon is not registered in application providers', function () {
    expect(file_get_contents(base_path('bootstrap/providers.php')))
        ->not->toContain('HorizonServiceProvider');
});
