<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    Illuminate\Support\Facades\Mail::raw('Teste SMTP '.date('c'), function ($m): void {
        $m->to('info@autorcpecas.pt')->subject('Teste SMTP Auto RC');
    });
    echo "OK\n";
} catch (\Throwable $e) {
    echo "FAIL: ".$e->getMessage()."\n";
}
