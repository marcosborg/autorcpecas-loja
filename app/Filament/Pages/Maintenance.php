<?php

namespace App\Filament\Pages;

use App\Services\Database\DatabaseCopier;
use App\Services\Database\DbEnvironment;
use App\Jobs\ReindexTpSoftware;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use BackedEnum;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use UnitEnum;

class Maintenance extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static ?string $navigationLabel = 'Manutenção';

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
                    try {
                        $exit = Artisan::call('storage:link');
                        $output = trim((string) Artisan::output());
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Falha a criar storage link')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();

                        return;
                    }

                    $this->lastOutput = $output !== '' ? $output : "Exit code: {$exit}";
                    $this->refreshDbStatus();

                    Notification::make()
                        ->title('Storage link executado')
                        ->body(Str::limit($this->lastOutput, 240))
                        ->success()
                        ->send();
                }),

            Action::make('tpsoftwareIndex')
                ->label('Reindexar TP Software')
                ->icon('heroicon-o-arrow-path')
                ->form([
                    Toggle::make('force')
                        ->label('Forçar rebuild do índice')
                        ->default(false),
                ])
                ->requiresConfirmation()
                ->action(function (array $data): void {
                    $force = (bool) ($data['force'] ?? false);

                    ReindexTpSoftware::dispatch($force);

                    $this->lastOutput = trim(implode("\n", [
                        'Reindex enfileirado (background).',
                        'force: '.($force ? 'true' : 'false'),
                        'Se nao estiver a correr, inicia o worker: php artisan queue:work',
                    ]));
                    $this->refreshDbStatus();

                    Notification::make()
                        ->title('Reindex enfileirado')
                        ->body(Str::limit($this->lastOutput, 240))
                        ->color('info')
                        ->send();

                    return;

                    $args = [];
                    if (($data['force'] ?? false) === true) {
                        $args['--force'] = true;
                    }

                    try {
                        $exit = Artisan::call('tpsoftware:index', $args);
                        $output = trim((string) Artisan::output());
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Falha a reindexar TP Software')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();

                        return;
                    }

                    $this->lastOutput = $output !== '' ? $output : "Exit code: {$exit}";
                    $this->refreshDbStatus();

                    Notification::make()
                        ->title($exit === 0 ? 'Índice TP Software atualizado' : 'Comando executado com erros')
                        ->body(Str::limit($this->lastOutput, 240))
                        ->color($exit === 0 ? 'success' : 'warning')
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
                ->label('Copiar Production → Sandbox')
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
                        ->label('Confirmação')
                        ->helperText('Escreve COPIAR para confirmar.')
                        ->required(),
                ])
                ->requiresConfirmation()
                ->action(function (array $data): void {
                    if (strtoupper(trim((string) ($data['confirm'] ?? ''))) !== 'COPIAR') {
                        Notification::make()
                            ->title('Confirmação inválida')
                            ->danger()
                            ->send();

                        return;
                    }

                    $exclude = array_values(array_filter(array_map('trim', explode(',', (string) ($data['exclude_tables'] ?? '')))));
                    // Prevent CSRF/session invalidation during interactive admin copy.
                    $exclude = array_values(array_unique([...$exclude, 'sessions']));

                    $copier = app(DatabaseCopier::class);
                    $this->lastOutput = $copier->copy('production', 'sandbox', [
                        'include_framework_tables' => (bool) ($data['include_framework_tables'] ?? false),
                        'exclude_tables' => $exclude,
                    ]);
                    $this->refreshDbStatus();

                    Notification::make()
                        ->title('Cópia concluída')
                        ->body('Production → Sandbox')
                        ->success()
                        ->send();
                }),

            Action::make('dbCopySandboxToProduction')
                ->label('Copiar Sandbox → Production')
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
                        ->label('Confirmação')
                        ->helperText('Escreve COPIAR-PRODUCTION para confirmar.')
                        ->required(),
                ])
                ->requiresConfirmation()
                ->action(function (array $data): void {
                    if (strtoupper(trim((string) ($data['confirm'] ?? ''))) !== 'COPIAR-PRODUCTION') {
                        Notification::make()
                            ->title('Confirmação inválida')
                            ->danger()
                            ->send();

                        return;
                    }

                    if (! (bool) env('DB_ALLOW_PRODUCTION_COPY', false)) {
                        Notification::make()
                            ->title('Bloqueado por segurança')
                            ->body('Define DB_ALLOW_PRODUCTION_COPY=true no .env para permitir Sandbox → Production.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $exclude = array_values(array_filter(array_map('trim', explode(',', (string) ($data['exclude_tables'] ?? '')))));
                    // Prevent CSRF/session invalidation during interactive admin copy.
                    $exclude = array_values(array_unique([...$exclude, 'sessions']));

                    $copier = app(DatabaseCopier::class);
                    $this->lastOutput = $copier->copy('sandbox', 'production', [
                        'include_framework_tables' => (bool) ($data['include_framework_tables'] ?? false),
                        'exclude_tables' => $exclude,
                    ]);
                    $this->refreshDbStatus();

                    Notification::make()
                        ->title('Cópia concluída')
                        ->body('Sandbox → Production')
                        ->success()
                        ->send();
                }),
        ];
    }

    private function refreshDbStatus(): void
    {
        $env = app(DbEnvironment::class);

        $tpsoftwareIndex = $this->readLocalJson('maintenance/tpsoftware-index.json');

        $this->dbStatus = [
            'mode' => $env->getMode(),
            'active_connection' => $env->activeConnectionName(),
            'sandbox' => $this->connectionSummary('sandbox'),
            'production' => $this->connectionSummary('production'),
            'tpsoftware_index' => $tpsoftwareIndex,
        ];
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
}
