@extends('store.layout', ['title' => 'Carrinho'])

@section('content')
    <div class="container-xl">
        <div class="row g-4">
            <div class="col-12 col-lg-8">
                <h3 class="mb-3">Carrinho</h3>
                @if ($cart->items->count() === 0)
                    <div class="alert alert-secondary">O carrinho esta vazio.</div>
                    <a class="btn btn-primary" href="{{ url('/loja') }}">Continuar a comprar</a>
                @else
                    <div class="card">
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table mb-0 align-middle">
                                    <thead>
                                        <tr>
                                            <th>Produto</th>
                                            <th class="text-end">Preco</th>
                                            <th class="text-end">Qtd</th>
                                            <th class="text-end">Total</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($cart->items as $item)
                                            <tr>
                                                <td>
                                                    <div class="fw-semibold">{{ $item->title }}</div>
                                                    @if ($item->reference)
                                                        <div class="small text-muted">Ref: {{ $item->reference }}</div>
                                                    @endif
                                                </td>
                                                <td class="text-end">{{ number_format((float) $item->unit_price_ex_vat, 2, ',', ' ') }} EUR</td>
                                                <td class="text-end">1</td>
                                                <td class="text-end">{{ number_format((float) $item->unit_price_ex_vat * (int) $item->quantity, 2, ',', ' ') }} EUR</td>
                                                <td class="text-end">
                                                    <form method="post" action="{{ url('/loja/carrinho/items/'.$item->id) }}">
                                                        @csrf
                                                        @method('delete')
                                                        <button class="btn btn-sm btn-outline-danger" type="submit">Remover</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
            <div class="col-12 col-lg-4">
                <div class="card">
                    <div class="card-header fw-semibold">Resumo</div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between small mb-1">
                            <span>Itens</span>
                            <strong>{{ $totals['total_qty'] }}</strong>
                        </div>
                        <div class="d-flex justify-content-between small mb-1">
                            <span>Peso total</span>
                            <strong>{{ number_format((float) $totals['total_weight_kg'], 3, ',', ' ') }} kg</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <span>Subtotal sem IVA</span>
                            <strong>{{ number_format((float) $totals['subtotal_ex_vat'], 2, ',', ' ') }} EUR</strong>
                        </div>
                        <a class="btn btn-primary w-100 @if($cart->items->count()===0) disabled @endif" href="{{ url('/loja/checkout') }}">Finalizar compra</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
