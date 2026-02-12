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
                                        @if ($address->phone)
                                            <div class="small">Tel: {{ $address->phone_country_code }} {{ $address->phone }}</div>
                                        @endif
                                        @if ($address->vat_number)
                                            <div class="small">NIF: {{ $address->vat_number }}</div>
                                            <div class="small mt-1">
                                                @if (mb_strtoupper((string) $address->country_iso2, 'UTF-8') === 'PT')
                                                    <span class="badge text-bg-info">NIF registado (sem isencao IVA em PT)</span>
                                                @elseif ($address->vat_is_valid === true)
                                                    <span class="badge text-bg-success">VAT elegivel para isencao</span>
                                                @elseif ($address->vat_is_valid === false)
                                                    <span class="badge text-bg-warning">Sem elegibilidade para isencao IVA</span>
                                                @else
                                                    <span class="badge text-bg-secondary">VAT por validar</span>
                                                @endif
                                                @if ($address->vat_validated_at)
                                                    <small class="text-muted ms-2">{{ $address->vat_validated_at->format('d/m/Y H:i') }}</small>
                                                @endif
                                            </div>
                                        @endif
                                        @if ($address->zone_code)
                                            <div class="small text-muted">
                                                Zona: {{ $address->zone_code === 'PT_ISLANDS' ? 'Acores / Madeira' : 'Portugal Continental' }}
                                            </div>
                                        @endif
                                        <div class="small text-muted mt-2">
                                            @if ($address->is_default_shipping) <span class="badge text-bg-secondary">Endereco de envio</span> @endif
                                            @if ($address->is_default_billing) <span class="badge text-bg-secondary">Endereco de faturacao</span> @endif
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
