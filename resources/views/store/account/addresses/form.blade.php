@extends('store.layout', ['title' => 'Morada'])

@section('content')
    <div class="container-xl">
        <div class="row g-4">
            <div class="col-12 col-lg-3">
                @include('store.account._nav')
            </div>
            <div class="col-12 col-lg-9">
                <h3 class="mb-3">{{ $address->exists ? 'Editar morada' : 'Nova morada' }}</h3>

                <div class="card">
                    <div class="card-body">
                        <form method="post" action="{{ $action }}">
                            @csrf
                            @if ($method !== 'POST')
                                @method($method)
                            @endif
                            <div class="row g-3">
                                <div class="col-12 col-md-4">
                                    <label class="form-label">Etiqueta</label>
                                    <input class="form-control" name="label" value="{{ old('label', $address->label ?: 'Morada') }}" required>
                                </div>
                                @php($userFirstName = explode(' ', (string) auth()->user()->name)[0] ?? '')
                                <div class="col-12 col-md-4">
                                    <label class="form-label">Primeiro nome</label>
                                    <input class="form-control" name="first_name" value="{{ old('first_name', $address->first_name ?: $userFirstName) }}" required>
                                </div>
                                <div class="col-12 col-md-4">
                                    <label class="form-label">Ultimo nome</label>
                                    <input class="form-control" name="last_name" value="{{ old('last_name', $address->last_name ?: '') }}" required>
                                </div>
                                <div class="col-12 col-md-4">
                                    <label class="form-label">Telefone</label>
                                    <input class="form-control" name="phone" value="{{ old('phone', $address->phone ?: auth()->user()->phone) }}">
                                </div>
                                <div class="col-12 col-md-4">
                                    <label class="form-label">Empresa</label>
                                    <input class="form-control" name="company" value="{{ old('company', $address->company) }}">
                                </div>
                                <div class="col-12 col-md-4">
                                    <label class="form-label">NIF</label>
                                    <input class="form-control" name="vat_number" value="{{ old('vat_number', $address->vat_number ?: auth()->user()->nif) }}">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Morada</label>
                                    <input class="form-control" name="address_line1" value="{{ old('address_line1', $address->address_line1) }}" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Complemento</label>
                                    <input class="form-control" name="address_line2" value="{{ old('address_line2', $address->address_line2) }}">
                                </div>
                                <div class="col-12 col-md-3">
                                    <label class="form-label">Codigo postal</label>
                                    <input class="form-control" name="postal_code" value="{{ old('postal_code', $address->postal_code) }}" required>
                                </div>
                                <div class="col-12 col-md-4">
                                    <label class="form-label">Cidade</label>
                                    <input class="form-control" name="city" value="{{ old('city', $address->city) }}" required>
                                </div>
                                <div class="col-12 col-md-3">
                                    <label class="form-label">Distrito/Estado</label>
                                    <input class="form-control" name="state" value="{{ old('state', $address->state) }}">
                                </div>
                                <div class="col-12 col-md-2">
                                    <label class="form-label">Pais</label>
                                    <input class="form-control text-uppercase" maxlength="2" name="country_iso2" value="{{ old('country_iso2', $address->country_iso2 ?: 'PT') }}" required>
                                </div>
                            </div>
                            <div class="mt-3 d-flex gap-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="1" name="is_default_shipping" id="is_default_shipping" @checked(old('is_default_shipping', $address->is_default_shipping))>
                                    <label class="form-check-label" for="is_default_shipping">Default envio</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="1" name="is_default_billing" id="is_default_billing" @checked(old('is_default_billing', $address->is_default_billing))>
                                    <label class="form-check-label" for="is_default_billing">Default faturacao</label>
                                </div>
                            </div>
                            <div class="mt-3 d-flex gap-2">
                                <button class="btn btn-primary" type="submit">Guardar</button>
                                <a class="btn btn-outline-secondary" href="{{ url('/loja/conta/moradas') }}">Cancelar</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
