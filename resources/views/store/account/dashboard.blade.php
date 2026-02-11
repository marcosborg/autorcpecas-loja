@extends('store.layout', ['title' => 'Minha Conta'])

@section('content')
    <div class="container-xl">
        <div class="row g-4">
            <div class="col-12 col-lg-3">
                @include('store.account._nav')
            </div>
            <div class="col-12 col-lg-9">
                <h3 class="mb-3">Ola, {{ auth()->user()->name }}</h3>

                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <div class="card h-100">
                            <div class="card-header fw-semibold">Ultimas encomendas</div>
                            <div class="card-body">
                                @if ($recentOrders->count() === 0)
                                    <div class="text-muted">Ainda sem encomendas.</div>
                                @else
                                    <ul class="list-group list-group-flush">
                                        @foreach ($recentOrders as $order)
                                            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                                <a href="{{ url('/loja/conta/encomendas/'.$order->id) }}">{{ $order->order_number }}</a>
                                                <span>{{ number_format((float) $order->total_inc_vat, 2, ',', ' ') }} EUR</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="card h-100">
                            <div class="card-header fw-semibold">Moradas</div>
                            <div class="card-body">
                                @if ($addresses->count() === 0)
                                    <div class="text-muted mb-2">Ainda sem moradas.</div>
                                @else
                                    @foreach ($addresses as $address)
                                        <div class="mb-2">
                                            <div class="fw-semibold">{{ $address->label }}</div>
                                            <div class="small">{{ $address->address_line1 }}, {{ $address->postal_code }} {{ $address->city }}</div>
                                        </div>
                                    @endforeach
                                @endif
                                <a class="btn btn-outline-primary btn-sm mt-2" href="{{ url('/loja/conta/moradas') }}">Gerir moradas</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

