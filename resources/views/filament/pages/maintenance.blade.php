<x-filament-panels::page>
    <div class="space-y-4">
        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="text-sm text-gray-600 dark:text-gray-300">
                Usa os botões no topo para correr tarefas de manutenção sem terminal.
            </div>
        </div>

        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-2">Base de dados</div>

            @php($mode = $dbStatus['mode'] ?? null)
            @php($active = $dbStatus['active_connection'] ?? null)
            @php($sandbox = $dbStatus['sandbox'] ?? [])
            @php($production = $dbStatus['production'] ?? [])

            <div class="text-sm text-gray-700 dark:text-gray-200">
                <div><span class="font-semibold">Modo atual:</span> {{ $mode ?? 'n/a' }}</div>
                <div><span class="font-semibold">Connection ativa:</span> {{ $active ?? 'n/a' }}</div>
            </div>

            <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-2">
                <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
                    <div class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">Sandbox</div>
                    <div class="text-sm text-gray-700 dark:text-gray-200">
                        <div>{{ ($sandbox['host'] ?? '') }}:{{ ($sandbox['port'] ?? '') }}</div>
                        <div>{{ ($sandbox['database'] ?? '') }}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ ($sandbox['username'] ?? '') }}</div>
                    </div>
                </div>

                <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
                    <div class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">Production</div>
                    <div class="text-sm text-gray-700 dark:text-gray-200">
                        <div>{{ ($production['host'] ?? '') }}:{{ ($production['port'] ?? '') }}</div>
                        <div>{{ ($production['database'] ?? '') }}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ ($production['username'] ?? '') }}</div>
                    </div>
                </div>
            </div>

            <div class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                Nota: os botoes de copia recriam as tabelas no destino (DROP/CREATE) e depois copiam dados.
            </div>
        </div>
        @if (!empty($dbStatus['tpsoftware_index'] ?? null))
            @php($idx = $dbStatus['tpsoftware_index'])
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-2">TP Software - Index</div>
                <div class="text-sm text-gray-700 dark:text-gray-200">
                    <div><span class="font-semibold">Status:</span> {{ $idx['status'] ?? 'n/a' }}</div>
                    @if (!empty($idx['started_at'] ?? null))
                        <div><span class="font-semibold">Iniciado:</span> {{ $idx['started_at'] }}</div>
                    @endif
                    @if (!empty($idx['finished_at'] ?? null))
                        <div><span class="font-semibold">Terminado:</span> {{ $idx['finished_at'] }}</div>
                    @endif
                    @if (!empty($idx['result']['total'] ?? null))
                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            total: {{ $idx['result']['total'] }} | indexados: {{ $idx['result']['indexed'] ?? '?' }} | pages: {{ $idx['result']['pages'] ?? '?' }}
                        </div>
                    @endif
                    @if (!empty($idx['error'] ?? null))
                        <div class="mt-2 text-sm text-red-700 dark:text-red-300">{{ $idx['error'] }}</div>
                    @endif
                </div>
            </div>
        @endif

        @if (!empty($lastOutput))
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-2">Último output</div>
                <pre class="text-xs whitespace-pre-wrap text-gray-800 dark:text-gray-100">{{ $lastOutput }}</pre>
            </div>
        @endif
    </div>
</x-filament-panels::page>
