<?php

namespace App\Services\PrintRequests;

use App\Models\PrintRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class StoreCompletionPhotos
{
    private const int MAX_DIMENSION = 1600;

    private const int QUALITY = 78;

    private const int MIN_QUALITY = 54;

    private const int QUALITY_STEP = 8;

    private const int TARGET_SIZE_BYTES = 900 * 1024;

    public function handle(PrintRequest $printRequest, array $photos): void
    {
        if ($photos === []) {
            return;
        }

        $storedPaths = [];
        $sortOrder = (int) $printRequest->completionPhotos()->max('sort_order');

        try {
            foreach ($photos as $photo) {
                if (! $photo instanceof UploadedFile) {
                    continue;
                }

                $processed = $this->process($photo);

                if ($printRequest->completionPhotos()->where('sha256', $processed['sha256'])->exists()) {
                    continue;
                }

                $directory = 'prints/completions/'.now()->format('Y/m');
                $path = $directory.'/'.Str::uuid().'.'.$processed['extension'];

                if (! Storage::disk('local')->put($path, $processed['contents'])) {
                    throw new RuntimeException('Unable to store the optimized completion photo.');
                }

                $storedPaths[] = $path;
                $sortOrder++;

                $printRequest->completionPhotos()->create([
                    'disk' => 'local',
                    'path' => $path,
                    'original_name' => $photo->getClientOriginalName(),
                    'mime_type' => $processed['mime_type'],
                    'size_bytes' => $processed['size_bytes'],
                    'width' => $processed['width'],
                    'height' => $processed['height'],
                    'sort_order' => $sortOrder,
                    'sha256' => $processed['sha256'],
                ]);
            }
        } catch (Throwable $exception) {
            foreach ($storedPaths as $path) {
                Storage::disk('local')->delete($path);
            }

            throw $exception;
        }
    }

    /**
     * @return array{
     *     contents: string,
     *     extension: string,
     *     mime_type: string,
     *     size_bytes: int,
     *     width: int,
     *     height: int,
     *     sha256: string
     * }
     */
    private function process(UploadedFile $photo): array
    {
        if (class_exists(\Imagick::class)) {
            try {
                return $this->processWithImagick($photo);
            } catch (Throwable $exception) {
                if (! function_exists('imagecreatefromstring')) {
                    throw $exception;
                }
            }
        }

        if (! function_exists('imagecreatefromstring')) {
            throw new RuntimeException('No image processing driver is available for completion photos.');
        }

        return $this->processWithGd($photo);
    }

    /**
     * @return array{
     *     contents: string,
     *     extension: string,
     *     mime_type: string,
     *     size_bytes: int,
     *     width: int,
     *     height: int,
     *     sha256: string
     * }
     */
    private function processWithImagick(UploadedFile $photo): array
    {
        $image = new \Imagick;
        $image->readImage($photo->getRealPath());
        $image->setIteratorIndex(0);
        $image->autoOrient();
        $image->thumbnailImage(self::MAX_DIMENSION, self::MAX_DIMENSION, true);
        $image->stripImage();

        $format = $this->imagickSupportsWebp() ? 'webp' : 'jpeg';
        $contents = $this->optimizeEncodedContents(function (int $quality) use ($image, $format): string {
            $candidate = clone $image;
            $candidate->setImageFormat($format);
            $candidate->setImageCompressionQuality($quality);

            if ($format === 'jpeg') {
                $candidate->setImageCompression(\Imagick::COMPRESSION_JPEG);
            }

            $encodedContents = $candidate->getImagesBlob();
            $candidate->destroy();

            return $encodedContents;
        });

        $width = $image->getImageWidth();
        $height = $image->getImageHeight();
        $image->destroy();

        return [
            'contents' => $contents,
            'extension' => $format === 'webp' ? 'webp' : 'jpg',
            'mime_type' => $format === 'webp' ? 'image/webp' : 'image/jpeg',
            'size_bytes' => strlen($contents),
            'width' => $width,
            'height' => $height,
            'sha256' => hash('sha256', $contents),
        ];
    }

    /**
     * @return array{
     *     contents: string,
     *     extension: string,
     *     mime_type: string,
     *     size_bytes: int,
     *     width: int,
     *     height: int,
     *     sha256: string
     * }
     */
    private function processWithGd(UploadedFile $photo): array
    {
        $contents = file_get_contents($photo->getRealPath());

        if ($contents === false) {
            throw new RuntimeException('Unable to read the uploaded completion photo.');
        }

        $source = imagecreatefromstring($contents);

        if ($source === false) {
            throw new RuntimeException('Unable to decode the uploaded completion photo.');
        }

        $source = $this->applyExifOrientation($source, $photo);
        $width = imagesx($source);
        $height = imagesy($source);
        [$targetWidth, $targetHeight] = $this->constrainedDimensions($width, $height);

        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);

        if ($canvas === false) {
            throw new RuntimeException('Unable to prepare the optimized completion photo.');
        }

        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);

        $background = imagecolorallocatealpha($canvas, 255, 255, 255, 127);
        imagefilledrectangle($canvas, 0, 0, $targetWidth, $targetHeight, $background);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        $supportsWebp = function_exists('imagewebp');
        $optimizedContents = $this->optimizeEncodedContents(function (int $quality) use ($canvas, $supportsWebp): string {
            ob_start();

            $encoded = $supportsWebp
                ? imagewebp($canvas, null, $quality)
                : imagejpeg($canvas, null, $quality);

            $contents = (string) ob_get_clean();

            if (! $encoded) {
                return '';
            }

            return $contents;
        });

        return [
            'contents' => $optimizedContents,
            'extension' => $supportsWebp ? 'webp' : 'jpg',
            'mime_type' => $supportsWebp ? 'image/webp' : 'image/jpeg',
            'size_bytes' => strlen($optimizedContents),
            'width' => $targetWidth,
            'height' => $targetHeight,
            'sha256' => hash('sha256', $optimizedContents),
        ];
    }

    private function optimizeEncodedContents(callable $encoder): string
    {
        $bestContents = '';

        for ($quality = self::QUALITY; $quality >= self::MIN_QUALITY; $quality -= self::QUALITY_STEP) {
            $contents = $encoder($quality);

            if ($contents === '') {
                continue;
            }

            if ($bestContents === '' || strlen($contents) < strlen($bestContents)) {
                $bestContents = $contents;
            }

            if (strlen($contents) <= self::TARGET_SIZE_BYTES) {
                return $contents;
            }
        }

        if ($bestContents === '') {
            throw new RuntimeException('Unable to prepare the optimized completion photo.');
        }

        return $bestContents;
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function constrainedDimensions(int $width, int $height): array
    {
        $maxDimension = max($width, $height);

        if ($maxDimension <= self::MAX_DIMENSION) {
            return [$width, $height];
        }

        $scale = self::MAX_DIMENSION / $maxDimension;

        return [
            max(1, (int) round($width * $scale)),
            max(1, (int) round($height * $scale)),
        ];
    }

    private function applyExifOrientation(\GdImage $image, UploadedFile $photo): \GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }

        $extension = strtolower($photo->getClientOriginalExtension() ?: $photo->extension() ?: '');

        if (! in_array($extension, ['jpg', 'jpeg'], true)) {
            return $image;
        }

        $exif = @exif_read_data($photo->getRealPath());
        $orientation = (int) ($exif['Orientation'] ?? 1);

        return match ($orientation) {
            3 => imagerotate($image, 180, 0) ?: $image,
            6 => imagerotate($image, -90, 0) ?: $image,
            8 => imagerotate($image, 90, 0) ?: $image,
            default => $image,
        };
    }

    private function imagickSupportsWebp(): bool
    {
        return in_array('WEBP', \Imagick::queryFormats('WEBP'), true);
    }
}
