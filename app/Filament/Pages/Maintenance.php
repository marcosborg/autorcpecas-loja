<?php

namespace App\Filament\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use BackedEnum;
use Illuminate\Support\Facades\Artisan;
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

                    Notification::make()
                        ->title($exit === 0 ? 'Índice TP Software atualizado' : 'Comando executado com erros')
                        ->body(Str::limit($this->lastOutput, 240))
                        ->color($exit === 0 ? 'success' : 'warning')
                        ->send();
                }),
        ];
    }
}
