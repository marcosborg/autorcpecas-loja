@extends('store.layout', ['title' => 'Encomendas'])

@section('content')
    <div class="container-xl">
        <div class="row g-4">
            <div class="col-12 col-lg-3">
                @include('store.account._nav')
            </div>
            <div class="col-12 col-lg-9">
                <h3 class="mb-3">Encomendas</h3>

                @if ($orders->count() === 0)
                    <div class="alert alert-secondary">Ainda nao tens encomendas.</div>
                @else
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th>Numero</th>
                                    <th>Data</th>
                                    <th>Estado</th>
                                    <th class="text-end">Total</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($orders as $order)
                                    <tr>
                                        <td>{{ $order->order_number }}</td>
                                        <td>{{ optional($order->placed_at)->format('d/m/Y H:i') }}</td>
                                        <td><span class="badge text-bg-secondary">{{ $order->status }}</span></td>
                                        <td class="text-end">{{ number_format((float) $order->total_inc_vat, 2, ',', ' ') }} EUR</td>
                                        <td class="text-end"><a href="{{ url('/loja/conta/encomendas/'.$order->id) }}">Ver</a></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{ $orders->links() }}
                @endif
            </div>
        </div>
    </div>
@endsection

