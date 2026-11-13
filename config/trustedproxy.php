<?php

return [
    /*
    | Comma-separated IPs/CIDRs trusted for forwarded headers. Null trusts no
    | proxy. Windows production sets loopback for Tailscale Serve.
    */
    'proxies' => env('TRUSTED_PROXIES'),
];
