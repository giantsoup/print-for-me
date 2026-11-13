<?php

use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    config(['trustedproxy.proxies' => null]);
    Route::middleware('web')->get('/_test/scheme', fn (): string => request()->getScheme().'|'.url('/'));
});

it('trusts no proxy by default', function (): void {
    expect(config('trustedproxy.proxies'))->toBeNull();
    $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
        ->withHeaders(['X-Forwarded-Proto' => 'https'])
        ->get('http://print-for-me.example.ts.net/_test/scheme')
        ->assertOk()->assertSeeText('http|http://print-for-me.example.ts.net');
});

it('honors forwarded headers only from the configured proxy', function (string $remoteAddress, string $scheme): void {
    config(['trustedproxy.proxies' => '127.0.0.1']);
    $this->withServerVariables(['REMOTE_ADDR' => $remoteAddress])
        ->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'print-for-me.example.ts.net'])
        ->get('http://print-for-me.example.ts.net/_test/scheme')
        ->assertOk()->assertSeeText($scheme.'|'.$scheme.'://print-for-me.example.ts.net');
})->with([
    'loopback proxy' => ['127.0.0.1', 'https'],
    'untrusted source' => ['10.1.2.3', 'http'],
]);
