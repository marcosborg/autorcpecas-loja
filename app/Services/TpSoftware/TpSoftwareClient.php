<?php

namespace App\Services\TpSoftware;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class TpSoftwareClient
{
    private function cache()
    {
        $store = (string) config('tpsoftware.cache_store', '');

        return $store !== '' ? Cache::store($store) : Cache::store();
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{ok: bool, status: int|null, data: mixed, raw: mixed}
     */
    public function get(string $path, array $params = [], ?int $cacheTtlSeconds = null, bool $useCache = true): array
    {
        $path = ltrim($path, '/');

        $useCache = $useCache;
        $cacheTtlSeconds ??= (int) config('tpsoftware.cache_ttl_seconds', 600);

        if ($useCache && $cacheTtlSeconds > 0) {
            $key = $this->cacheKey('GET', $path, $params);

            /** @var array{ok: bool, status: int|null, data: mixed, raw: mixed} */
            return $this->cache()->remember($key, $cacheTtlSeconds, fn () => $this->getNoCache($path, $params));
        }

        return $this->getNoCache($path, $params);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, status: int|null, data: mixed, raw: mixed}
     */
    public function post(string $path, array $payload = [], ?int $cacheTtlSeconds = null, bool $useCache = true): array
    {
        $path = ltrim($path, '/');

        $cacheTtlSeconds ??= (int) config('tpsoftware.cache_ttl_seconds', 600);

        if ($useCache && $cacheTtlSeconds > 0) {
            $key = $this->cacheKey('POST', $path, $payload);

            /** @var array{ok: bool, status: int|null, data: mixed, raw: mixed} */
            return $this->cache()->remember($key, $cacheTtlSeconds, fn () => $this->postNoCache($path, $payload));
        }

        return $this->postNoCache($path, $payload);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{ok: bool, status: int|null, data: mixed, raw: mixed}
     */
    private function getNoCache(string $path, array $params): array
    {
        $response = $this->request()
            ->get($this->url($path), $this->withTokenParam($params));

        $json = $response->json();

        if (is_array($json)) {
            return [
                'ok' => $response->successful(),
                'status' => $response->status(),
                'data' => $json,
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

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, status: int|null, data: mixed, raw: mixed}
     */
    private function postNoCache(string $path, array $payload): array
    {
        $url = $this->urlWithToken($path);

        $response = $this->request()
            ->post($url, $payload);

        $json = $response->json();

        if (is_array($json)) {
            return [
                'ok' => $response->successful(),
                'status' => $response->status(),
                'data' => $json,
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
        $token = (string) config('tpsoftware.token');

        if ($token === '') {
            throw new \RuntimeException('TP Software token em falta: define TPSOFTWARE_TOKEN no .env.');
        }

        $timeoutSeconds = (int) config('tpsoftware.timeout_seconds', 15);
        $retries = (int) config('tpsoftware.retries', 1);
        $retrySleepMs = (int) config('tpsoftware.retry_sleep_ms', 250);

        $req = Http::acceptJson()
            ->timeout($timeoutSeconds)
            ->retry($retries, $retrySleepMs, function ($exception) {
                if ($exception instanceof RequestException) {
                    $status = $exception->response?->status();

                    return in_array($status, [429, 500, 502, 503, 504], true);
                }

                return true;
            });

        if ((bool) config('tpsoftware.use_auth_header', false)) {
            $req = $req->withToken($token);
        }

        return $req;
    }

    private function url(string $path): string
    {
        $baseUrl = rtrim((string) config('tpsoftware.base_url'), '/');

        return $baseUrl.'/'.$path;
    }

    private function urlWithToken(string $path): string
    {
        $base = $this->url($path);
        $token = (string) config('tpsoftware.token');
        $param = (string) config('tpsoftware.token_param', 'tokens');

        if ($token === '') {
            return $base;
        }

        if ($param === '') {
            $param = 'tokens';
        }

        $sep = str_contains($base, '?') ? '&' : '?';

        return $base.$sep.rawurlencode($param).'='.rawurlencode($token);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function withTokenParam(array $params): array
    {
        $token = (string) config('tpsoftware.token');
        $param = (string) config('tpsoftware.token_param', 'tokens');

        if ($param === '') {
            $param = 'tokens';
        }

        return [
            $param => $token,
            ...$params,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function cacheKey(string $method, string $path, array $data): string
    {
        $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return 'tpsoftware:'.sha1($method.'|'.$path.'|'.$json);
    }
}
