<?php

/*
|--------------------------------------------------------------------------
| Trusted proxies (rnc-4 F7)
|--------------------------------------------------------------------------
|
| Laravel's global `TrustProxies` middleware reads `trustedproxy.proxies` when nothing set it in
| code. Behind the production web server or load balancer the PHP peer is the proxy, so without
| this every `$request->ip()` — the view-as audit's `ip_address` (Platform, rnc-4 C18), the
| throttles' keys — is the proxy's address. A comma-separated list of the proxies' addresses or
| CIDR ranges; `*` trusts the calling peer (only when nothing else can reach PHP). Unset: no proxy
| is trusted and `X-Forwarded-For` is ignored — the right answer locally and for a direct peer.
|
*/

return [

    'proxies' => env('TRUSTED_PROXIES'),

];
