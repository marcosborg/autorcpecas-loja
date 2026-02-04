<x-filament-panels::page>
    <div class="space-y-4">
        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="text-sm text-gray-600 dark:text-gray-300">
                Usa os botões no topo para correr tarefas de manutenção sem terminal.
            </div>
        </div>

        @if (!empty($lastOutput))
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-2">Último output</div>
                <pre class="text-xs whitespace-pre-wrap text-gray-800 dark:text-gray-100">{{ $lastOutput }}</pre>
            </div>
        @endif
    </div>
</x-filament-panels::page>

