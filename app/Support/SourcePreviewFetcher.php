<?php

namespace App\Support;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class SourcePreviewFetcher
{
    public function fetch(string $url): ?array
    {
        if (! $this->canFetch($url)) {
            return null;
        }

        try {
            $response = Http::accept('text/html,application/xhtml+xml')
                ->timeout(8)
                ->connectTimeout(4)
                ->retry([200, 500], throw: false)
                ->withHeaders([
                    'User-Agent' => sprintf('%s Source Preview Bot', config('app.name')),
                ])
                ->get($url);
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $contentType = strtolower((string) $response->header('Content-Type', ''));

        if ($contentType !== '' && ! str_contains($contentType, 'html')) {
            return null;
        }

        $html = mb_substr($response->body(), 0, 500_000);

        if (blank($html)) {
            return null;
        }

        return $this->extractPreview($url, $html);
    }

    private function canFetch(string $url): bool
    {
        $parts = parse_url($url);

        if (! is_array($parts)) {
            return false;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            return false;
        }

        if ($host === 'localhost' || str_ends_with($host, '.local')) {
            return false;
        }

        return $this->hostResolvesToPublicAddress($host);
    }

    private function hostResolvesToPublicAddress(string $host): bool
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return $this->isPublicIp($host);
        }

        $records = dns_get_record($host, DNS_A | DNS_AAAA);

        if ($records === false || $records === []) {
            return false;
        }

        foreach ($records as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;

            if (! is_string($ip) || ! $this->isPublicIp($ip)) {
                return false;
            }
        }

        return true;
    }

    private function isPublicIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    private function extractPreview(string $url, string $html): ?array
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);

        $loaded = $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            return null;
        }

        $xpath = new DOMXPath($document);
        $structuredNodes = $this->extractStructuredNodes($xpath);

        $preview = array_filter([
            'url' => $url,
            'domain' => $this->displayDomain($url),
            'site_name' => $this->extractFirst($xpath, [
                "//meta[@property='og:site_name' or @name='og:site_name']/@content",
                "//meta[@name='application-name']/@content",
            ], 80) ?? $this->extractStructuredString($structuredNodes, [
                'publisher.name',
                'brand.name',
                'provider.name',
                'isPartOf.name',
            ], 80),
            'title' => $this->extractFirst($xpath, [
                "//meta[@property='og:title' or @name='og:title']/@content",
                "//meta[@property='twitter:title' or @name='twitter:title']/@content",
                '//title/text()',
            ], 140) ?? $this->extractStructuredString($structuredNodes, [
                'headline',
                'name',
            ], 140),
            'description' => $this->extractFirst($xpath, [
                "//meta[@property='og:description' or @name='og:description']/@content",
                "//meta[@name='description']/@content",
                "//meta[@property='twitter:description' or @name='twitter:description']/@content",
            ], 1600) ?? $this->extractStructuredString($structuredNodes, [
                'description',
                'summary',
            ], 1600) ?? $this->extractFirst($xpath, [
                '//article//p/text()',
                '//main//p/text()',
                '//body//p/text()',
            ], 1600),
            'image_url' => $this->resolveUrl($url, $this->extractFirst($xpath, [
                "//meta[@property='og:image' or @name='og:image']/@content",
                "//meta[@property='twitter:image' or @name='twitter:image']/@content",
                "//meta[@itemprop='image']/@content",
                "//link[@rel='image_src']/@href",
            ], 2048) ?? $this->extractStructuredString($structuredNodes, [
                'image.url',
                'image',
                'thumbnailUrl',
                'primaryImageOfPage.url',
            ], 2048) ?? $this->extractFirst($xpath, [
                "//main//img[contains(@class, 'item-detail-image')]/@src",
                "//main//img[contains(@class, 'card-img-top')]/@src",
                "//main//img[not(starts-with(@src, 'data:'))]/@src",
                "//article//img[not(starts-with(@src, 'data:'))]/@src",
            ], 2048)),
        ], fn (mixed $value): bool => filled($value));

        return count($preview) > 2 ? $preview : null;
    }

    private function extractFirst(DOMXPath $xpath, array $queries, int $limit): ?string
    {
        foreach ($queries as $query) {
            $nodes = $xpath->query($query);

            if (! $nodes || $nodes->length === 0) {
                continue;
            }

            foreach ($nodes as $node) {
                $candidate = $this->normalizeText($node->nodeValue, $limit);

                if (filled($candidate)) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    /**
     * @return array<int, array<mixed>>
     */
    private function extractStructuredNodes(DOMXPath $xpath): array
    {
        $scripts = $xpath->query("//script[@type='application/ld+json']/text()");

        if (! $scripts || $scripts->length === 0) {
            return [];
        }

        $nodes = [];

        foreach ($scripts as $script) {
            $payload = trim((string) $script->nodeValue);

            if ($payload === '') {
                continue;
            }

            $decoded = json_decode($payload, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                continue;
            }

            $this->collectStructuredNodes($decoded, $nodes);
        }

        return $nodes;
    }

    /**
     * @param  array<int, array<mixed>>  $nodes
     * @param  array<int, string>  $paths
     */
    private function extractStructuredString(array $nodes, array $paths, int $limit): ?string
    {
        foreach ($nodes as $node) {
            foreach ($paths as $path) {
                $candidate = $this->firstStructuredString(data_get($node, $path));
                $normalized = $this->normalizeText($candidate, $limit);

                if (filled($normalized)) {
                    return $normalized;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<int, array<mixed>>  $nodes
     */
    private function collectStructuredNodes(mixed $value, array &$nodes): void
    {
        if (! is_array($value)) {
            return;
        }

        $nodes[] = $value;

        foreach ($value as $child) {
            $this->collectStructuredNodes($child, $nodes);
        }
    }

    private function firstStructuredString(mixed $value): ?string
    {
        if (is_string($value)) {
            return $value;
        }

        if (! is_array($value)) {
            return null;
        }

        foreach (['url', 'contentUrl', 'secureUrl'] as $preferredKey) {
            if (array_key_exists($preferredKey, $value)) {
                $candidate = $this->firstStructuredString($value[$preferredKey]);

                if (filled($candidate)) {
                    return $candidate;
                }
            }
        }

        foreach ($value as $child) {
            $candidate = $this->firstStructuredString($child);

            if (filled($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function normalizeText(?string $value, int $limit): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = html_entity_decode($value, ENT_QUOTES | ENT_HTML5);
        $text = Str::squish(strip_tags($text));

        if ($text === '') {
            return null;
        }

        return Str::limit($text, $limit, '');
    }

    private function displayDomain(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return null;
        }

        return preg_replace('/^www\./i', '', $host) ?: $host;
    }

    private function resolveUrl(string $pageUrl, ?string $candidate): ?string
    {
        if (! filled($candidate)) {
            return null;
        }

        $candidate = str_replace(' ', '%20', trim($candidate));

        if ($candidate === '') {
            return null;
        }

        if (filter_var($candidate, FILTER_VALIDATE_URL) !== false) {
            return $candidate;
        }

        $base = parse_url($pageUrl);

        if (! is_array($base) || ! isset($base['scheme'], $base['host'])) {
            return null;
        }

        $origin = $base['scheme'].'://'.$base['host'].(isset($base['port']) ? ':'.$base['port'] : '');

        if (str_starts_with($candidate, '//')) {
            return $base['scheme'].':'.$candidate;
        }

        if (str_starts_with($candidate, '/')) {
            return $origin.$candidate;
        }

        $path = (string) ($base['path'] ?? '/');
        $directory = trim(dirname($path), '.');

        return rtrim($origin.($directory === '/' ? '' : $directory), '/').'/'.ltrim($candidate, '/');
    }
}
