@extends('store.layout', ['title' => 'Moradas'])

@section('content')
    <div class="container-xl">
        <div class="row g-4">
            <div class="col-12 col-lg-3">
                @include('store.account._nav')
            </div>
            <div class="col-12 col-lg-9">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3 class="mb-0">Moradas</h3>
                    <a class="btn btn-primary" href="{{ url('/loja/conta/moradas/create') }}">Nova morada</a>
                </div>

                @if ($addresses->count() === 0)
                    <div class="alert alert-secondary">Sem moradas registadas.</div>
                @else
                    <div class="row g-3">
                        @foreach ($addresses as $address)
                            <div class="col-12 col-md-6">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <div class="fw-semibold">{{ $address->label }}</div>
                                        <div>{{ $address->first_name }} {{ $address->last_name }}</div>
                                        <div>{{ $address->address_line1 }}</div>
                                        @if ($address->address_line2)
                                            <div>{{ $address->address_line2 }}</div>
                                        @endif
                                        <div>{{ $address->postal_code }} {{ $address->city }}</div>
                                        <div>{{ $address->country_iso2 }}</div>
                                        <div class="small text-muted mt-2">
                                            @if ($address->is_default_shipping) <span class="badge text-bg-secondary">Default envio</span> @endif
                                            @if ($address->is_default_billing) <span class="badge text-bg-secondary">Default faturacao</span> @endif
                                        </div>
                                    </div>
                                    <div class="card-footer d-flex gap-2">
                                        <a class="btn btn-outline-primary btn-sm" href="{{ url('/loja/conta/moradas/'.$address->id.'/edit') }}">Editar</a>
                                        <form method="post" action="{{ url('/loja/conta/moradas/'.$address->id) }}" onsubmit="return confirm('Remover morada?')">
                                            @csrf
                                            @method('delete')
                                            <button class="btn btn-outline-danger btn-sm" type="submit">Remover</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection

