<?php

namespace App\Filament\Pages;

use App\Jobs\ReindexTpSoftware;
use App\Services\Database\DatabaseCopier;
use App\Services\Database\DbEnvironment;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;
use UnitEnum;

class Maintenance extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static ?string $navigationLabel = 'Manutencao';

    protected static string|UnitEnum|null $navigationGroup = 'Sistema';

    protected static ?int $navigationSort = 90;

    protected string $view = 'filament.pages.maintenance';

    public ?string $lastOutput = null;

    public array $dbStatus = [];

    public function mount(): void
    {
        $this->refreshDbStatus();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('storageLink')
                ->label('Criar storage link')
                ->icon('heroicon-o-link')
                ->requiresConfirmation()
                ->action(function (): void {
                    $this->runArtisanCommand(
                        titleOnSuccess: 'Storage link executado',
                        titleOnFail: 'Falha a criar storage link',
                        command: 'storage:link',
                    );
                }),

            Action::make('tpsoftwareIndex')
                ->label('Reindexar TP Software (fila)')
                ->icon('heroicon-o-arrow-path')
                ->form([
                    Toggle::make('force')
                        ->label('Forcar rebuild do indice')
                        ->default(false),
                ])
                ->requiresConfirmation()
                ->action(function (array $data): void {
                    $force = (bool) ($data['force'] ?? false);

                    Storage::disk('local')->put(
                        'maintenance/tpsoftware-index.json',
                        json_encode([
                            'status' => 'queued',
                            'force' => $force,
                            'queued_at' => now()->toISOString(),
                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                    );

                    ReindexTpSoftware::dispatch($force);

                    $this->lastOutput = trim(implode("\n", [
                        'Reindex enfileirado (background).',
                        'force: '.($force ? 'true' : 'false'),
                        'Usa os botoes de fila para processar jobs sem terminal.',
                    ]));
                    $this->refreshDbStatus();

                    Notification::make()
                        ->title('Reindex enfileirado')
                        ->body(Str::limit($this->lastOutput, 240))
                        ->color('info')
                        ->send();
                }),

            Action::make('tpsoftwareIndexNow')
                ->label('Reindexar TP (agora)')
                ->icon('heroicon-o-bolt')
                ->color('info')
                ->form([
                    Toggle::make('force')
                        ->label('Forcar rebuild do indice')
                        ->default(true),
                ])
                ->requiresConfirmation()
                ->action(function (array $data): void {
                    $args = [];
                    if (($data['force'] ?? false) === true) {
                        $args['--force'] = true;
                    }

                    $this->runArtisanCommand(
                        titleOnSuccess: 'Reindex TP Software concluido',
                        titleOnFail: 'Falha a reindexar TP Software',
                        command: 'tpsoftware:index',
                        arguments: $args,
                    );
                }),

            Action::make('queueWorkOnce')
                ->label('Processar 1 job')
                ->icon('heroicon-o-play')
                ->color('gray')
                ->requiresConfirmation()
                ->action(function (): void {
                    $this->runArtisanCommand(
                        titleOnSuccess: 'Fila processada (1 job)',
                        titleOnFail: 'Falha ao processar fila',
                        command: 'queue:work',
                        arguments: [
                            '--once' => true,
                            '--queue' => 'default',
                            '--tries' => 1,
                            '--timeout' => 120,
                        ],
                    );
                }),

            Action::make('queueWorkDrain')
                ->label('Esvaziar fila')
                ->icon('heroicon-o-forward')
                ->color('warning')
                ->requiresConfirmation()
                ->action(function (): void {
                    $this->runArtisanCommand(
                        titleOnSuccess: 'Fila processada ate ficar vazia',
                        titleOnFail: 'Falha ao esvaziar fila',
                        command: 'queue:work',
                        arguments: [
                            '--stop-when-empty' => true,
                            '--queue' => 'default',
                            '--tries' => 1,
                            '--timeout' => 120,
                        ],
                    );
                }),

            Action::make('clearAppCaches')
                ->label('Limpar caches')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->action(function (): void {
                    $outputs = [];
                    foreach (['optimize:clear', 'cache:clear', 'config:clear'] as $cmd) {
                        try {
                            $exit = Artisan::call($cmd);
                            $out = trim((string) Artisan::output());
                            $outputs[] = "== {$cmd} (exit {$exit}) ==\n".($out !== '' ? $out : 'sem output');
                        } catch (Throwable $e) {
                            $outputs[] = "== {$cmd} ==\nERRO: ".$e->getMessage();
                        }
                    }

                    $this->lastOutput = implode("\n\n", $outputs);
                    $this->refreshDbStatus();

                    Notification::make()
                        ->title('Caches limpas')
                        ->body(Str::limit($this->lastOutput, 240))
                        ->success()
                        ->send();
                }),

            Action::make('dbSwitchSandbox')
                ->label('Usar Sandbox DB')
                ->icon('heroicon-o-beaker')
                ->requiresConfirmation()
                ->action(function (): void {
                    $env = app(DbEnvironment::class);
                    $env->setMode(DbEnvironment::MODE_SANDBOX);
                    $env->apply();
                    DB::purge('content');

                    $this->lastOutput = 'DB mode: sandbox';
                    $this->refreshDbStatus();

                    Notification::make()
                        ->title('Sandbox ativado')
                        ->body('A app passa a usar a base de dados sandbox.')
                        ->success()
                        ->send();
                }),

            Action::make('dbSwitchProduction')
                ->label('Usar Production DB')
                ->icon('heroicon-o-cloud')
                ->color('warning')
                ->requiresConfirmation()
                ->action(function (): void {
                    $env = app(DbEnvironment::class);
                    $env->setMode(DbEnvironment::MODE_PRODUCTION);
                    $env->apply();
                    DB::purge('content');

                    $this->lastOutput = 'DB mode: production';
                    $this->refreshDbStatus();

                    Notification::make()
                        ->title('Production ativado')
                        ->body('A app passa a usar a base de dados production.')
                        ->warning()
                        ->send();
                }),

            Action::make('dbCopyProductionToSandbox')
                ->label('Copiar Production -> Sandbox')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('info')
                ->form([
                    Toggle::make('include_framework_tables')
                        ->label('Incluir tabelas de framework (sessions/cache/jobs)')
                        ->default(false),
                    TextInput::make('exclude_tables')
                        ->label('Excluir tabelas (csv)')
                        ->helperText('Ex.: logs,audit_trails')
                        ->default(''),
                    TextInput::make('confirm')
                        ->label('Confirmacao')
                        ->helperText('Escreve COPIAR para confirmar.')
                        ->required(),
                ])
                ->requiresConfirmation()
                ->action(function (array $data): void {
                    if (strtoupper(trim((string) ($data['confirm'] ?? ''))) !== 'COPIAR') {
                        Notification::make()->title('Confirmacao invalida')->danger()->send();
                        return;
                    }

                    $exclude = array_values(array_filter(array_map('trim', explode(',', (string) ($data['exclude_tables'] ?? '')))));
                    $exclude = array_values(array_unique([...$exclude, 'sessions']));

                    $copier = app(DatabaseCopier::class);
                    $this->lastOutput = $copier->copy('production', 'sandbox', [
                        'include_framework_tables' => (bool) ($data['include_framework_tables'] ?? false),
                        'exclude_tables' => $exclude,
                    ]);
                    $this->refreshDbStatus();

                    Notification::make()->title('Copia concluida')->body('Production -> Sandbox')->success()->send();
                }),

            Action::make('dbCopySandboxToProduction')
                ->label('Copiar Sandbox -> Production')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('danger')
                ->form([
                    Toggle::make('include_framework_tables')
                        ->label('Incluir tabelas de framework (sessions/cache/jobs)')
                        ->default(false),
                    TextInput::make('exclude_tables')
                        ->label('Excluir tabelas (csv)')
                        ->helperText('Ex.: logs,audit_trails')
                        ->default(''),
                    TextInput::make('confirm')
                        ->label('Confirmacao')
                        ->helperText('Escreve COPIAR-PRODUCTION para confirmar.')
                        ->required(),
                ])
                ->requiresConfirmation()
                ->action(function (array $data): void {
                    if (strtoupper(trim((string) ($data['confirm'] ?? ''))) !== 'COPIAR-PRODUCTION') {
                        Notification::make()->title('Confirmacao invalida')->danger()->send();
                        return;
                    }

                    if (! (bool) env('DB_ALLOW_PRODUCTION_COPY', false)) {
                        Notification::make()
                            ->title('Bloqueado por seguranca')
                            ->body('Define DB_ALLOW_PRODUCTION_COPY=true no .env para permitir Sandbox -> Production.')
                            ->danger()
                            ->send();
                        return;
                    }

                    $exclude = array_values(array_filter(array_map('trim', explode(',', (string) ($data['exclude_tables'] ?? '')))));
                    $exclude = array_values(array_unique([...$exclude, 'sessions']));

                    $copier = app(DatabaseCopier::class);
                    $this->lastOutput = $copier->copy('sandbox', 'production', [
                        'include_framework_tables' => (bool) ($data['include_framework_tables'] ?? false),
                        'exclude_tables' => $exclude,
                    ]);
                    $this->refreshDbStatus();

                    Notification::make()->title('Copia concluida')->body('Sandbox -> Production')->success()->send();
                }),
        ];
    }

    private function refreshDbStatus(): void
    {
        $env = app(DbEnvironment::class);

        $tpsoftwareIndex = $this->readLocalJson('maintenance/tpsoftware-index.json');
        $tpsoftwareIndex = $this->normalizeIndexStatus($tpsoftwareIndex);

        $this->dbStatus = [
            'mode' => $env->getMode(),
            'active_connection' => $env->activeConnectionName(),
            'sandbox' => $this->connectionSummary('sandbox'),
            'production' => $this->connectionSummary('production'),
            'tpsoftware_index' => $tpsoftwareIndex,
            'queue' => $this->queueSummary(),
        ];
    }

    public function refreshStatusTick(): void
    {
        $this->refreshDbStatus();
    }

    /**
     * @return array{driver: string, host: string, port: string, database: string, username: string}
     */
    private function connectionSummary(string $name): array
    {
        $cfg = (array) config('database.connections.'.$name, []);

        return [
            'driver' => (string) ($cfg['driver'] ?? ''),
            'host' => (string) ($cfg['host'] ?? ''),
            'port' => (string) ($cfg['port'] ?? ''),
            'database' => (string) ($cfg['database'] ?? ''),
            'username' => (string) ($cfg['username'] ?? ''),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readLocalJson(string $path): ?array
    {
        $disk = Storage::disk('local');

        if (! $disk->exists($path)) {
            return null;
        }

        $json = $disk->get($path);
        $data = json_decode($json, true);

        return is_array($data) ? $data : null;
    }

    /**
     * @param  array<string, mixed>|null  $status
     * @return array<string, mixed>|null
     */
    private function normalizeIndexStatus(?array $status): ?array
    {
        if (! is_array($status)) {
            return null;
        }

        $raw = (string) ($status['status'] ?? '');
        $raw = strtolower(trim($raw));
        if ($raw === '') {
            $raw = 'idle';
        }

        $label = match ($raw) {
            'queued' => 'Em fila',
            'running' => 'A correr',
            'ok' => 'Concluido',
            'error' => 'Erro',
            default => 'Parado',
        };

        $tone = match ($raw) {
            'queued' => 'warning',
            'running' => 'info',
            'ok' => 'success',
            'error' => 'danger',
            default => 'gray',
        };

        $runningSince = $this->minutesSince((string) ($status['started_at'] ?? ''));
        if ($raw === 'running' && $runningSince !== null && $runningSince >= 10) {
            $status['stalled_warning'] = 'Index em running ha '.$runningSince.' min. Verifica se o worker da queue esta ativo.';
        }

        $queuedSince = $this->minutesSince((string) ($status['queued_at'] ?? ''));
        if ($raw === 'queued' && $queuedSince !== null && $queuedSince >= 2) {
            $status['stalled_warning'] = 'Index em fila ha '.$queuedSince.' min. Processa a fila nos botoes acima.';
        }

        $status['status'] = $raw;
        $status['status_label'] = $label;
        $status['status_tone'] = $tone;

        return $status;
    }

    /**
     * @return array{pending:int, failed:int, connection:string, warning?:string|null}
     */
    private function queueSummary(): array
    {
        $connection = (string) config('database.default', 'n/a');
        $pending = 0;
        $failed = 0;
        $warning = null;

        try {
            if (DB::getSchemaBuilder()->hasTable('jobs')) {
                $pending = (int) DB::table('jobs')->count();
            }
            if (DB::getSchemaBuilder()->hasTable('failed_jobs')) {
                $failed = (int) DB::table('failed_jobs')->count();
            }
        } catch (Throwable $e) {
            $warning = $e->getMessage();
        }

        return [
            'pending' => $pending,
            'failed' => $failed,
            'connection' => $connection,
            'warning' => $warning,
        ];
    }

    private function minutesSince(string $iso): ?int
    {
        $iso = trim($iso);
        if ($iso === '') {
            return null;
        }

        try {
            $from = CarbonImmutable::parse($iso);
        } catch (Throwable) {
            return null;
        }

        return $from->diffInMinutes(now(), false);
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function runArtisanCommand(string $titleOnSuccess, string $titleOnFail, string $command, array $arguments = []): void
    {
        try {
            $exit = Artisan::call($command, $arguments);
            $output = trim((string) Artisan::output());
        } catch (Throwable $e) {
            Notification::make()->title($titleOnFail)->body($e->getMessage())->danger()->send();
            return;
        }

        $this->lastOutput = "== {$command} ==\n".($output !== '' ? $output : "Exit code: {$exit}");
        $this->refreshDbStatus();

        Notification::make()
            ->title($exit === 0 ? $titleOnSuccess : $titleOnFail)
            ->body(Str::limit($this->lastOutput, 240))
            ->color($exit === 0 ? 'success' : 'warning')
            ->send();
    }
}
