@extends('store.layout', ['title' => 'Encomenda '.$order->order_number])

@section('content')
    <div class="container-xl">
        <div class="row g-4">
            <div class="col-12 col-lg-3">
                @include('store.account._nav')
            </div>
            <div class="col-12 col-lg-9">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3 class="mb-0">Encomenda {{ $order->order_number }}</h3>
                    <span class="badge text-bg-secondary">{{ $order->status }}</span>
                </div>

                <div class="card mb-3">
                    <div class="card-header fw-semibold">Artigos</div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table align-middle">
                                <thead>
                                    <tr>
                                        <th>Produto</th>
                                        <th class="text-end">Qtd</th>
                                        <th class="text-end">Preco</th>
                                        <th class="text-end">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($order->items as $item)
                                        <tr>
                                            <td>
                                                <div class="fw-semibold">{{ $item->title }}</div>
                                                @if ($item->reference)
                                                    <div class="small text-muted">Ref: {{ $item->reference }}</div>
                                                @endif
                                            </td>
                                            <td class="text-end">{{ $item->quantity }}</td>
                                            <td class="text-end">{{ number_format((float) $item->unit_price_ex_vat, 2, ',', ' ') }} EUR</td>
                                            <td class="text-end">{{ number_format((float) $item->line_total_ex_vat, 2, ',', ' ') }} EUR</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="text-end small">
                            <div>Subtotal: {{ number_format((float) $order->subtotal_ex_vat, 2, ',', ' ') }} EUR (sem IVA)</div>
                            <div>Envio: {{ number_format((float) $order->shipping_ex_vat, 2, ',', ' ') }} EUR (sem IVA)</div>
                            <div>Taxa pagamento: {{ number_format((float) $order->payment_fee_ex_vat, 2, ',', ' ') }} EUR (sem IVA)</div>
                            <div class="fw-semibold fs-5 mt-1">Total: {{ number_format((float) $order->total_inc_vat, 2, ',', ' ') }} EUR</div>
                        </div>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <div class="card h-100">
                            <div class="card-header fw-semibold">Entrega</div>
                            <div class="card-body small">
                                @php($shippingAddress = $order->shipping_address_snapshot ?? [])
                                <div>{{ ($shippingAddress['first_name'] ?? '').' '.($shippingAddress['last_name'] ?? '') }}</div>
                                <div>{{ $shippingAddress['address_line1'] ?? '' }}</div>
                                @if (!empty($shippingAddress['address_line2']))
                                    <div>{{ $shippingAddress['address_line2'] }}</div>
                                @endif
                                <div>{{ $shippingAddress['postal_code'] ?? '' }} {{ $shippingAddress['city'] ?? '' }}</div>
                                <div>{{ $shippingAddress['country_iso2'] ?? '' }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="card h-100">
                            <div class="card-header fw-semibold">Pagamento</div>
                            <div class="card-body small">
                                @php($payment = $order->payment_method_snapshot ?? [])
                                @php($paymentInstructions = $payment['payment_instructions'] ?? [])
                                <div class="fw-semibold">{{ $payment['name'] ?? 'N/D' }}</div>
                                <div>{{ $payment['provider'] ?? '' }}</div>
                                @if (is_array($paymentInstructions) && !empty($paymentInstructions))
                                    <hr>
                                    <div><strong>Entidade MB:</strong> {{ $paymentInstructions['entity'] ?? 'N/D' }}</div>
                                    <div><strong>Referencia MB:</strong> {{ $paymentInstructions['reference_display'] ?? ($paymentInstructions['reference'] ?? 'N/D') }}</div>
                                    <div><strong>Montante:</strong> {{ number_format((float) ($paymentInstructions['amount'] ?? $order->total_inc_vat), 2, ',', ' ') }} {{ $paymentInstructions['currency'] ?? $order->currency }}</div>
                                @endif
                                @if (!empty($payment['meta']['gateway']) && ($payment['meta']['gateway'] === 'manual_bank_transfer'))
                                    <hr>
                                    <div><strong>Titular:</strong> {{ $payment['meta']['owner'] ?? 'N/D' }}</div>
                                    <div><strong>Dados:</strong> {{ $payment['meta']['details'] ?? 'N/D' }}</div>
                                @endif

                                @if (($order->status ?? '') === 'awaiting_payment')
                                    <hr>
                                    @if (!empty($availablePaymentMethods ?? []))
                                        <form method="post" action="{{ url('/loja/conta/encomendas/'.$order->id.'/payment-method') }}">
                                            @csrf
                                            <label class="form-label">Alterar metodo de pagamento</label>
                                            <div class="input-group mb-2">
                                                <select class="form-select" name="payment_method_id" required>
                                                    @foreach (($availablePaymentMethods ?? []) as $method)
                                                        <option value="{{ $method['id'] }}" @selected((string) ($payment['code'] ?? '') === (string) ($method['code'] ?? ''))>
                                                            {{ $method['name'] ?? ('Metodo #'.$method['id']) }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                <button class="btn btn-outline-primary" type="submit">Atualizar</button>
                                            </div>
                                            <button class="btn btn-primary w-100" formaction="{{ url('/loja/conta/encomendas/'.$order->id.'/pay') }}" type="submit">Executar pagamento</button>
                                            @error('payment_method_id')
                                                <div class="text-danger mt-2">{{ $message }}</div>
                                            @enderror
                                        </form>
                                    @else
                                        <div class="text-muted">Sem metodos alternativos disponiveis para esta encomenda.</div>
                                    @endif
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
