<?php

namespace App\Services\Telepecas;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class TelepecasClient
{
    private function cache()
    {
        $store = (string) config('telepecas.cache_store', '');

        return $store !== '' ? Cache::store($store) : Cache::store();
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{ok: bool, status: int|null, data: mixed, raw: mixed}
     */
    public function get(string $endpoint, array $params = [], ?int $cacheTtlSeconds = null, bool $useCache = true): array
    {
        return $this->requestJson('GET', $endpoint, $params, $cacheTtlSeconds, $useCache);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, status: int|null, data: mixed, raw: mixed}
     */
    public function post(string $endpoint, array $payload = [], ?int $cacheTtlSeconds = null, bool $useCache = true): array
    {
        return $this->requestJson('POST', $endpoint, $payload, $cacheTtlSeconds, $useCache);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{ok: bool, status: int|null, data: mixed, raw: mixed}
     */
    public function requestJson(string $method, string $endpoint, array $data = [], ?int $cacheTtlSeconds = null, bool $useCache = true): array
    {
        $method = strtoupper($method);
        $endpoint = ltrim($endpoint, '/');

        $useCache = $useCache && (bool) config('telepecas.cache_enabled', true);
        $cacheTtlSeconds ??= (int) config('telepecas.cache_ttl_seconds', 600);

        if ($useCache && $cacheTtlSeconds > 0) {
            $cacheKey = $this->cacheKey($method, $endpoint, $data);

            /** @var array{ok: bool, status: int|null, data: mixed, raw: mixed} */
            return $this->cache()->remember($cacheKey, $cacheTtlSeconds, fn () => $this->requestJsonNoCache($method, $endpoint, $data));
        }

        return $this->requestJsonNoCache($method, $endpoint, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{ok: bool, status: int|null, data: mixed, raw: mixed}
     */
    private function requestJsonNoCache(string $method, string $endpoint, array $data): array
    {
        $driver = (string) config('telepecas.auth_driver', 'basic_token_body');

        if ($driver === 'basic_token_body' && $method === 'GET') {
            throw new \RuntimeException('TelePeças (basic_token_body) não suporta GET: use POST com body {"token": "..."} conforme o manual.');
        }

        $request = $this->request();
        $url = $this->url($endpoint);

        if ($method === 'GET') {
            $response = $request->get($url, $this->withSellerToken($data));
        } elseif ($method === 'POST') {
            $payload = $this->withPublicKey($data);
            $response = $request->post($url, $payload);
        } else {
            throw new \InvalidArgumentException("Unsupported method [{$method}].");
        }

        $json = $response->json();

        if (is_array($json)) {
            return [
                'ok' => $response->successful() && (($json['success'] ?? true) === true),
                'status' => $response->status(),
                'data' => $json['data'] ?? $json,
                'raw' => $json,
            ];
        }

        return [
            'ok' => $response->successful(),
            'status' => $response->status(),
            'data' => $json,
            'raw' => $response->body(),
        ];
    }

    private function request(): PendingRequest
    {
        $timeoutSeconds = (int) config('telepecas.timeout_seconds', 15);
        $retries = (int) config('telepecas.retries', 1);
        $retrySleepMs = (int) config('telepecas.retry_sleep_ms', 250);

        return Http::asJson()
            ->acceptJson()
            ->timeout($timeoutSeconds)
            ->retry($retries, $retrySleepMs, function ($exception) {
                if ($exception instanceof RequestException) {
                    $status = $exception->response?->status();

                    return in_array($status, [429, 500, 502, 503, 504], true);
                }

                return true;
            })
            ->withHeaders($this->authHeaders());
    }

    /**
     * @return array<string, string>
     */
    private function authHeaders(): array
    {
        $driver = (string) config('telepecas.auth_driver', 'basic_token_body');

        if ($driver === 'basic_token_body') {
            $basic = (string) config('telepecas.basic_auth_token');
            $publicKey = (string) config('telepecas.public_key');

            if ($basic === '' || $publicKey === '') {
                throw new \RuntimeException('TelePeças credentials em falta: define TELEPECAS_BASIC_AUTH_TOKEN e TELEPECAS_PUBLIC_KEY no .env.');
            }

            return [
                'Authorization' => 'Basic '.$basic,
            ];
        }

        if ($driver === 'oauth2') {
            $accessToken = $this->getOAuthAccessToken();

            return [
                'Authorization' => 'Bearer '.$accessToken,
            ];
        }

        throw new \RuntimeException("TelePeças auth_driver inválido: [{$driver}].");
    }

    private function getOAuthAccessToken(): string
    {
        /** @var array{access_token?: string, expires_in?: int} $cached */
        $cached = $this->cache()->get('telepecas:oauth2:token', []);

        if (is_array($cached) && isset($cached['access_token']) && is_string($cached['access_token']) && $cached['access_token'] !== '') {
            return $cached['access_token'];
        }

        $basic = (string) config('telepecas.oauth_basic_auth');

        if ($basic === '') {
            $clientId = (string) config('telepecas.client_id');
            $clientSecret = (string) config('telepecas.client_secret');

            if ($clientId === '' || $clientSecret === '') {
                throw new \RuntimeException('TelePeças OAuth credentials em falta: define TELEPECAS_OAUTH_BASIC_AUTH (ou TELEPECAS_CLIENT_ID e TELEPECAS_CLIENT_SECRET) no .env.');
            }

            $basic = base64_encode($clientId.':'.$clientSecret);
        }

        $url = rtrim((string) config('telepecas.base_url'), '/').'/auth/token';

        $response = Http::asForm()
            ->acceptJson()
            ->timeout((int) config('telepecas.timeout_seconds', 15))
            ->withHeaders([
                'Authorization' => 'Basic '.$basic,
            ])
            ->post($url, [
                'grant_type' => 'client_credentials',
            ]);

        $json = $response->json();

        if (! $response->successful() || ! is_array($json) || ! is_string($json['access_token'] ?? null)) {
            throw new \RuntimeException('Falha a obter access_token OAuth2 da TelePeças.');
        }

        $accessToken = $json['access_token'];
        $expiresIn = (int) ($json['expires_in'] ?? 3600);
        $ttl = max(60, $expiresIn - 60);

        $this->cache()->put('telepecas:oauth2:token', ['access_token' => $accessToken], $ttl);

        return $accessToken;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withPublicKey(array $data): array
    {
        $driver = (string) config('telepecas.auth_driver', 'basic_token_body');

        if ($driver !== 'basic_token_body') {
            return $data;
        }

        $publicKey = (string) config('telepecas.public_key');

        return [
            ...$data,
            'token' => $publicKey,
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function withSellerToken(array $params): array
    {
        $driver = (string) config('telepecas.auth_driver', 'basic_token_body');

        if ($driver !== 'oauth2') {
            return $params;
        }

        $sellerToken = (string) config('telepecas.seller_token');

        if ($sellerToken === '') {
            return $params;
        }

        $paramName = (string) config('telepecas.seller_token_param', 'token');

        if ($paramName === '') {
            $paramName = 'token';
        }

        return [
            ...$params,
            $paramName => $sellerToken,
        ];
    }

    private function url(string $endpoint): string
    {
        $baseUrl = rtrim((string) config('telepecas.base_url'), '/');

        return $baseUrl.'/'.$endpoint;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function cacheKey(string $method, string $endpoint, array $data): string
    {
        $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return 'telepecas:'.sha1($method.'|'.$endpoint.'|'.$json);
    }
}
