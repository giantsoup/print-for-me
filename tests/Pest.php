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
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
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

function realisticImageData(int $width = 1600, int $height = 1200, string $format = 'jpeg'): string
{
    $image = imagecreatetruecolor($width, $height);

    if ($image === false) {
        throw new RuntimeException('Unable to create the sample image canvas.');
    }

    for ($row = 0; $row < $height; $row += 6) {
        $ratio = $row / max(1, $height - 1);
        $red = (int) round(22 + (90 * $ratio));
        $green = (int) round(54 + (110 * (1 - $ratio)));
        $blue = (int) round(108 + (80 * $ratio));
        $color = imagecolorallocate($image, $red, $green, $blue);
        imagefilledrectangle($image, 0, $row, $width, min($height - 1, $row + 5), $color);
    }

    $surface = imagecolorallocate($image, 228, 232, 238);
    $shadow = imagecolorallocate($image, 52, 65, 85);
    $accent = imagecolorallocate($image, 15, 118, 110);
    $highlight = imagecolorallocate($image, 248, 250, 252);

    imagefilledrectangle($image, (int) round($width * 0.18), (int) round($height * 0.18), (int) round($width * 0.82), (int) round($height * 0.84), $surface);
    imagefilledellipse($image, (int) round($width * 0.34), (int) round($height * 0.44), (int) round($width * 0.18), (int) round($height * 0.18), $accent);
    imagefilledrectangle($image, (int) round($width * 0.5), (int) round($height * 0.32), (int) round($width * 0.72), (int) round($height * 0.7), $shadow);
    imagerectangle($image, (int) round($width * 0.18), (int) round($height * 0.18), (int) round($width * 0.82), (int) round($height * 0.84), $highlight);

    imagesetthickness($image, 10);
    imageline($image, (int) round($width * 0.12), (int) round($height * 0.9), (int) round($width * 0.88), (int) round($height * 0.12), $highlight);

    ob_start();

    $encoded = match ($format) {
        'png' => imagepng($image, null, 4),
        'webp' => function_exists('imagewebp') ? imagewebp($image, null, 86) : false,
        default => imagejpeg($image, null, 92),
    };

    $contents = (string) ob_get_clean();

    if (! $encoded || $contents === '') {
        throw new RuntimeException('Unable to generate the sample image data.');
    }

    return $contents;
}
