<?php

namespace App\Jobs;

use App\Services\TpSoftware\TpSoftwareCatalogService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class ReindexTpSoftware implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly bool $force = false)
    {
    }

    public function handle(TpSoftwareCatalogService $catalog): void
    {
        $path = 'maintenance/tpsoftware-index.json';
        $startedAt = now();

        $write = function (array $data) use ($path): void {
            Storage::disk('local')->put($path, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        };

        $write([
            'status' => 'running',
            'force' => $this->force,
            'started_at' => $startedAt->toISOString(),
        ]);

        try {
            $result = $catalog->buildIndex($this->force);

            $write([
                'status' => 'ok',
                'force' => $this->force,
                'started_at' => $startedAt->toISOString(),
                'finished_at' => now()->toISOString(),
                'result' => $result,
            ]);
        } catch (\Throwable $e) {
            $write([
                'status' => 'error',
                'force' => $this->force,
                'finished_at' => now()->toISOString(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
