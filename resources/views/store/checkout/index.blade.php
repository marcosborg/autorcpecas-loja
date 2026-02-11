@extends('store.layout', ['title' => 'Checkout'])

@section('content')
    <div class="container-xl">
        <h3 class="mb-3">Checkout</h3>

        <form method="get" action="{{ url('/loja/checkout') }}" class="card mb-3">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label">Morada de entrega</label>
                        <select class="form-select" name="shipping_address_id">
                            @foreach ($addresses as $address)
                                <option value="{{ $address->id }}" @selected((int) $shippingAddressId === (int) $address->id)>{{ $address->label }} - {{ $address->address_line1 }} ({{ $address->country_iso2 }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label">Morada de faturacao</label>
                        <select class="form-select" name="billing_address_id">
                            @foreach ($addresses as $address)
                                <option value="{{ $address->id }}" @selected((int) $billingAddressId === (int) $address->id)>{{ $address->label }} - {{ $address->address_line1 }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-outline-secondary" type="submit">Atualizar opcoes de envio/pagamento</button>
                    </div>
                </div>
            </div>
        </form>

        <form method="post" action="{{ url('/loja/checkout') }}">
            @csrf
            <input type="hidden" name="shipping_address_id" value="{{ $shippingAddressId }}">
            <input type="hidden" name="billing_address_id" value="{{ $billingAddressId }}">

            <div class="row g-4">
                <div class="col-12 col-lg-8">
                    <div class="card mb-3">
                        <div class="card-header fw-semibold">Transportadoras</div>
                        <div class="card-body">
                            @forelse (($quote['carriers'] ?? []) as $carrier)
                                <label class="border rounded p-2 d-flex justify-content-between align-items-center mb-2">
                                    <span>
                                        <input class="form-check-input me-2" type="radio" name="shipping_carrier_id" value="{{ $carrier['id'] }}" @checked($loop->first)>
                                        <strong>{{ $carrier['name'] }}</strong>
                                        <small class="text-muted ms-2">{{ $carrier['delay'] ?? '' }}</small>
                                    </span>
                                    <span>{{ number_format((float) ($carrier['price_ex_vat'] ?? 0), 2, ',', ' ') }} EUR <small class="text-muted">sem IVA</small></span>
                                </label>
                            @empty
                                <div class="alert alert-warning mb-0">Sem transportadoras disponiveis para esta morada.</div>
                            @endforelse
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-header fw-semibold">Metodos de pagamento</div>
                        <div class="card-body">
                            @forelse (($quote['payment_methods'] ?? []) as $method)
                                <label class="border rounded p-2 d-flex justify-content-between align-items-center mb-2">
                                    <span>
                                        <input class="form-check-input me-2" type="radio" name="payment_method_id" value="{{ $method['id'] }}" @checked($loop->first)>
                                        <strong>{{ $method['name'] }}</strong>
                                        <small class="text-muted ms-2">{{ $method['provider'] }}</small>
                                    </span>
                                    <span>{{ number_format((float) ($method['fee_ex_vat'] ?? 0), 2, ',', ' ') }} EUR <small class="text-muted">taxa</small></span>
                                </label>
                            @empty
                                <div class="alert alert-warning mb-0">Sem metodos de pagamento disponiveis.</div>
                            @endforelse
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header fw-semibold">Nota da encomenda</div>
                        <div class="card-body">
                            <textarea class="form-control" rows="3" name="customer_note" placeholder="Observacoes opcionais"></textarea>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-lg-4">
                    <div class="card">
                        <div class="card-header fw-semibold">Resumo</div>
                        <div class="card-body">
                            <div class="small text-muted mb-2">Itens: {{ $totals['total_qty'] }}</div>
                            <div class="small text-muted mb-2">Peso: {{ number_format((float) $totals['total_weight_kg'], 3, ',', ' ') }} kg</div>
                            <div class="d-flex justify-content-between mb-1">
                                <span>Subtotal sem IVA</span>
                                <strong>{{ number_format((float) $totals['subtotal_ex_vat'], 2, ',', ' ') }} EUR</strong>
                            </div>
                            <div class="text-muted small mb-3">Envio e taxa de pagamento calculados apos selecao.</div>
                            <button class="btn btn-primary w-100" type="submit">Finalizar encomenda</button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection

