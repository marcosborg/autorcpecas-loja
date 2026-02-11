<x-filament-panels::page>
    <div class="space-y-4" wire:poll.5s="refreshStatusTick">
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
        @if (!empty($dbStatus['queue'] ?? null))
            @php($q = $dbStatus['queue'])
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-2">Fila (queue)</div>
                <div class="text-sm text-gray-700 dark:text-gray-200">
                    <div><span class="font-semibold">Connection:</span> {{ $q['connection'] ?? 'n/a' }}</div>
                    <div><span class="font-semibold">Jobs pendentes:</span> {{ $q['pending'] ?? 0 }}</div>
                    <div><span class="font-semibold">Jobs falhados:</span> {{ $q['failed'] ?? 0 }}</div>
                    @if (!empty($q['warning'] ?? null))
                        <div class="mt-2 text-sm text-red-700 dark:text-red-300">{{ $q['warning'] }}</div>
                    @endif
                </div>
            </div>
        @endif
        @if (!empty($dbStatus['tpsoftware_index'] ?? null))
            @php($idx = $dbStatus['tpsoftware_index'])
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-2">TP Software - Index</div>

                @php($tone = $idx['status_tone'] ?? 'gray')
                @php($badgeClasses = match($tone) {
                    'success' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/20 dark:text-emerald-300',
                    'info' => 'bg-sky-100 text-sky-800 dark:bg-sky-500/20 dark:text-sky-300',
                    'warning' => 'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-300',
                    'danger' => 'bg-red-100 text-red-800 dark:bg-red-500/20 dark:text-red-300',
                    default => 'bg-gray-100 text-gray-700 dark:bg-gray-500/20 dark:text-gray-300',
                })

                <div class="text-sm text-gray-700 dark:text-gray-200">
                    <div class="mb-2">
                        <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-semibold {{ $badgeClasses }}">
                            {{ $idx['status_label'] ?? ($idx['status'] ?? 'n/a') }}
                        </span>
                    </div>
                    <div><span class="font-semibold">Status técnico:</span> {{ $idx['status'] ?? 'n/a' }}</div>
                    @if (!empty($idx['queued_at'] ?? null))
                        <div><span class="font-semibold">Em fila:</span> {{ $idx['queued_at'] }}</div>
                    @endif
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
                    @if (!empty($idx['stalled_warning'] ?? null))
                        <div class="mt-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-300">
                            {{ $idx['stalled_warning'] }}
                        </div>
                    @endif
                    <div class="mt-2 text-[11px] text-gray-500 dark:text-gray-400">
                        Atualização automática a cada 5s.
                    </div>
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
