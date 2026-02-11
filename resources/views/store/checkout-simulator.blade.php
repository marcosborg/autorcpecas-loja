@extends('store.layout', ['title' => 'Checkout (Simulador)'])

@section('content')
    <div class="container-xl mt-3">
        <div class="d-flex align-items-end justify-content-between mb-3">
            <div>
                <h3 class="mb-0">Checkout (simulador)</h3>
                <div class="text-muted small">Modelo inspirado em PrestaShop 8.1 (transportadoras + pagamentos).</div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <form class="row g-2 align-items-end" method="get" action="{{ url('/loja/checkout/simulador') }}">
                    <div class="col-12 col-md-3">
                        <label class="form-label mb-1">Subtotal (sem IVA)</label>
                        <input class="form-control" type="number" step="0.01" min="0" name="subtotal" value="{{ number_format((float) $subtotal, 2, '.', '') }}">
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label mb-1">Peso total (kg)</label>
                        <input class="form-control" type="number" step="0.01" min="0" name="weight" value="{{ number_format((float) $weight, 2, '.', '') }}">
                    </div>
                    <div class="col-12 col-md-2">
                        <label class="form-label mb-1">Pais (ISO2)</label>
                        <input class="form-control text-uppercase" maxlength="2" name="country" value="{{ $country }}">
                    </div>
                    <div class="col-12 col-md-2">
                        <button class="btn btn-primary w-100" type="submit">Simular</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-12 col-lg-6">
                <div class="card h-100">
                    <div class="card-header fw-semibold">Transportadoras</div>
                    <div class="card-body">
                        <div class="text-muted small mb-2">Zona resolvida: <strong>{{ $quote['zone']['name'] ?? 'N/D' }}</strong></div>
                        @if (count($quote['carriers'] ?? []) === 0)
                            <div class="alert alert-secondary mb-0">Sem transportadoras disponiveis.</div>
                        @else
                            <div class="list-group">
                                @foreach (($quote['carriers'] ?? []) as $c)
                                    <div class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="fw-semibold">{{ $c['name'] }}</div>
                                            <div class="small text-muted">{{ $c['delay'] ?? 'Prazo nao definido' }}</div>
                                        </div>
                                        <div class="text-end">
                                            <div class="fw-semibold">{{ number_format((float) ($c['price_ex_vat'] ?? 0), 2, ',', ' ') }} EUR</div>
                                            <div class="small text-muted">sem IVA</div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-12 col-lg-6">
                <div class="card h-100">
                    <div class="card-header fw-semibold">Metodos de pagamento</div>
                    <div class="card-body">
                        @if (count($quote['payment_methods'] ?? []) === 0)
                            <div class="alert alert-secondary mb-0">Sem metodos disponiveis para as transportadoras selecionadas.</div>
                        @else
                            <div class="list-group">
                                @foreach (($quote['payment_methods'] ?? []) as $m)
                                    <div class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="fw-semibold">{{ $m['name'] }}</div>
                                            <div class="small text-muted">{{ $m['provider'] ?: 'Gateway interno' }}</div>
                                            @if (($m['meta']['gateway'] ?? null) === 'sibs')
                                                <div class="small mt-1">
                                                    <span class="badge {{ ($m['meta']['integration_ready'] ?? false) ? 'text-bg-success' : 'text-bg-warning' }}">
                                                        {{ ($m['meta']['integration_ready'] ?? false) ? 'SIBS configurado' : 'SIBS por configurar' }}
                                                    </span>
                                                </div>
                                            @endif
                                            @if (($m['meta']['gateway'] ?? null) === 'manual_bank_transfer')
                                                <div class="small text-muted mt-1">
                                                    <div><strong>Titular:</strong> {{ $m['meta']['owner'] ?: 'N/D' }}</div>
                                                    <div><strong>Dados:</strong> {{ $m['meta']['details'] ?: 'N/D' }}</div>
                                                </div>
                                            @endif
                                        </div>
                                        <div class="text-end">
                                            <div class="fw-semibold">{{ number_format((float) ($m['fee_ex_vat'] ?? 0), 2, ',', ' ') }} EUR</div>
                                            <div class="small text-muted">taxa sem IVA</div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

