<?php

use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('user:admin {email} {--demote : Remove permissoes de admin}', function (string $email) {
    $user = User::query()->where('email', $email)->first();

    if (! $user) {
        $this->error("Utilizador nao encontrado: {$email}");

        return 1;
    }

    $user->is_admin = ! $this->option('demote');
    $user->save();

    $this->info($user->is_admin ? 'Utilizador promovido a admin.' : 'Utilizador removido de admin.');

    return 0;
})->purpose('Promove (ou remove) um utilizador como admin do Filament');

Artisan::command('tpsoftware:index {--force : Recria o indice}', function () {
    /** @var \App\Services\TpSoftware\TpSoftwareCatalogService $catalog */
    $catalog = app(\App\Services\TpSoftware\TpSoftwareCatalogService::class);

    $force = (bool) $this->option('force');

    $this->info('A construir indice TP Software (pode demorar alguns minutos)...');

    $result = $catalog->buildIndex($force);

    $this->info("Total (API): {$result['total']}");
    $this->info("Indexados: {$result['indexed']}");
    $this->info("Pages (estimado): {$result['pages']}");

    return 0;
})->purpose('Constroi indice local (cache) do inventario TP Software para a vitrine');
