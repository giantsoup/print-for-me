<?php

namespace App\Support;

use App\Enums\PrintRequestStatus;
use App\Models\PrintRequest;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

final class PrintRequestMailData
{
    public static function greetingFor(object $notifiable): string
    {
        $name = trim((string) data_get($notifiable, 'name', ''));

        if ($name !== '') {
            return "Hi {$name},";
        }

        return 'Hello,';
    }

    /**
     * @return list<array{label: string, value: string, url?: string}>
     */
    public static function requesterDetails(PrintRequest $printRequest): array
    {
        self::prepare($printRequest);

        $details = [
            self::detail('Current status', self::statusLabel((string) $printRequest->status)),
            self::detail('Submitted', self::formatDate($printRequest->created_at)),
            self::detail('Files attached', self::fileSummary($printRequest)),
        ];

        $sourceDetail = self::sourceDetail($printRequest->source_url);

        if ($sourceDetail !== null) {
            $details[] = $sourceDetail;
        }

        return $details;
    }

    /**
     * @return list<array{label: string, value: string, url?: string}>
     */
    public static function adminDetails(PrintRequest $printRequest): array
    {
        self::prepare($printRequest);

        $details = [
            self::detail('Requester', self::requesterLabel($printRequest)),
            self::detail('Submitted', self::formatDate($printRequest->created_at)),
            self::detail('Current status', self::statusLabel((string) $printRequest->status)),
            self::detail('Files attached', self::fileSummary($printRequest)),
        ];

        $sourceDetail = self::sourceDetail($printRequest->source_url);

        if ($sourceDetail !== null) {
            $details[] = $sourceDetail;
        }

        return $details;
    }

    /**
     * @return list<array{label: string, value: string, url?: string}>
     */
    public static function purgeDetails(PrintRequest $printRequest): array
    {
        self::prepare($printRequest);

        $details = [
            self::detail('Deleted on', self::formatDate($printRequest->deleted_at)),
            self::detail('Permanent removal date', self::formatDate($printRequest->deleted_at?->copy()->addDays(90))),
            self::detail('Files attached', self::fileSummary($printRequest)),
        ];

        $sourceDetail = self::sourceDetail($printRequest->source_url);

        if ($sourceDetail !== null) {
            $details[] = $sourceDetail;
        }

        return $details;
    }

    public static function instructionsExcerpt(?string $instructions): ?string
    {
        $excerpt = Str::of((string) $instructions)
            ->squish()
            ->limit(220)
            ->toString();

        return $excerpt !== '' ? $excerpt : null;
    }

    public static function requestUrl(PrintRequest $printRequest): string
    {
        return route('print-requests.show', $printRequest);
    }

    public static function queueUrl(): string
    {
        return route('print-requests.index', ['status' => PrintRequestStatus::PENDING]);
    }

    public static function createUrl(): string
    {
        return route('print-requests.create');
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            PrintRequestStatus::PENDING => 'Pending review',
            PrintRequestStatus::ACCEPTED => 'Accepted',
            PrintRequestStatus::PRINTING => 'Printing',
            PrintRequestStatus::COMPLETE => 'Completed',
            default => Str::headline($status),
        };
    }

    private static function prepare(PrintRequest $printRequest): void
    {
        $printRequest->loadMissing('user:id,name,email');
        $printRequest->loadCount('files');
    }

    /**
     * @return array{label: string, value: string, url?: string}|null
     */
    private static function sourceDetail(?string $sourceUrl): ?array
    {
        $sourceLabel = self::sourceLabel($sourceUrl);

        if ($sourceLabel === null || blank($sourceUrl)) {
            return null;
        }

        return self::detail('Source reference', $sourceLabel, $sourceUrl);
    }

    /**
     * @return array{label: string, value: string, url?: string}
     */
    private static function detail(string $label, string $value, ?string $url = null): array
    {
        $detail = [
            'label' => $label,
            'value' => $value,
        ];

        if ($url !== null) {
            $detail['url'] = $url;
        }

        return $detail;
    }

    private static function requesterLabel(PrintRequest $printRequest): string
    {
        $name = trim((string) ($printRequest->user?->name ?? ''));
        $email = trim((string) ($printRequest->user?->email ?? ''));

        if ($name !== '' && $email !== '') {
            return "{$name} ({$email})";
        }

        return $name !== '' ? $name : $email;
    }

    private static function fileSummary(PrintRequest $printRequest): string
    {
        $count = (int) ($printRequest->files_count ?? 0);

        if ($count === 0) {
            return 'No uploaded files';
        }

        return number_format($count).' attached '.Str::plural('file', $count);
    }

    private static function formatDate(?CarbonInterface $date): string
    {
        if ($date === null) {
            return 'Not available';
        }

        return $date->format('F j, Y \\a\\t g:i A');
    }

    private static function sourceLabel(?string $sourceUrl): ?string
    {
        if (blank($sourceUrl)) {
            return null;
        }

        $host = parse_url($sourceUrl, PHP_URL_HOST);
        $path = (string) parse_url($sourceUrl, PHP_URL_PATH);

        if (! is_string($host) || $host === '') {
            return Str::limit($sourceUrl, 48);
        }

        if ($path === '' || $path === '/') {
            return $host;
        }

        return Str::limit($host.$path, 48);
    }
}
