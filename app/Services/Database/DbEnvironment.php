<?php

namespace App\Services\Database;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

class DbEnvironment
{
    public const MODE_SANDBOX = 'sandbox';
    public const MODE_PRODUCTION = 'production';

    private function store(): Filesystem
    {
        return Storage::disk('local');
    }

    private function statePath(): string
    {
        return 'db/env.json';
    }

    public function getMode(): string
    {
        $mode = $this->readState()['mode'] ?? null;

        if (is_string($mode) && in_array($mode, [self::MODE_SANDBOX, self::MODE_PRODUCTION], true)) {
            return $mode;
        }

        $envMode = (string) env('DB_ENV', self::MODE_SANDBOX);

        return in_array($envMode, [self::MODE_SANDBOX, self::MODE_PRODUCTION], true)
            ? $envMode
            : self::MODE_SANDBOX;
    }

    public function setMode(string $mode): void
    {
        if (! in_array($mode, [self::MODE_SANDBOX, self::MODE_PRODUCTION], true)) {
            throw new \InvalidArgumentException("DB mode inválido: [{$mode}].");
        }

        $this->store()->put($this->statePath(), json_encode([
            'mode' => $mode,
            'updated_at' => now()->toISOString(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function activeConnectionName(): string
    {
        $mode = $this->getMode();
        $connections = (array) config('database.connections', []);

        if (array_key_exists($mode, $connections)) {
            return $mode;
        }

        return (string) config('database.default', env('DB_CONNECTION', 'mysql'));
    }

    public function apply(): void
    {
        $target = $this->activeConnectionName();
        $targetCfg = config('database.connections.'.$target);

        if (! is_array($targetCfg)) {
            return;
        }

        config([
            'database.connections.content' => $targetCfg,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function readState(): array
    {
        $path = $this->statePath();

        if (! $this->store()->exists($path)) {
            return [];
        }

        $json = $this->store()->get($path);
        $data = json_decode($json, true);

        return is_array($data) ? $data : [];
    }
}
