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
                            @if ($errors->any())
                                <div class="alert alert-danger">
                                    <ul class="mb-0 ps-3">
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
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
                                    @php($selectedCountryIso2 = old('country_iso2', $address->country_iso2 ?: ($defaultCountryIso2 ?? 'PT')))
                                    @php($selectedCountry = collect($countries ?? [])->firstWhere('iso2', $selectedCountryIso2))
                                    @php($defaultPhoneCode = $selectedCountry['phone_code'] ?? '+351')
                                    <div class="input-group">
                                        <input class="form-control" style="max-width: 110px" name="phone_country_code" value="{{ old('phone_country_code', $address->phone_country_code ?: $defaultPhoneCode) }}" placeholder="+351" required>
                                        <input class="form-control" name="phone" value="{{ old('phone', $address->phone ?: auth()->user()->phone) }}" placeholder="912345678" required>
                                    </div>
                                </div>
                                <div class="col-12 col-md-4">
                                    <label class="form-label">Empresa</label>
                                    <input class="form-control" name="company" value="{{ old('company', $address->company) }}">
                                </div>
                                <div class="col-12 col-md-4">
                                    <label class="form-label">NIF</label>
                                    <input class="form-control" name="vat_number" value="{{ old('vat_number', $address->vat_number ?: auth()->user()->nif) }}">
                                    @if ($address->exists || old('vat_number'))
                                        @php($vatIsValid = old('vat_is_valid', $address->vat_is_valid))
                                        @php($vatCheckedAt = old('vat_validated_at', optional($address->vat_validated_at)->format('Y-m-d H:i')))
                                        <div class="mt-2">
                                            @if ($vatIsValid === true || $vatIsValid === 1 || $vatIsValid === '1')
                                                <span class="badge text-bg-success">VAT validado</span>
                                            @elseif ($vatIsValid === false || $vatIsValid === 0 || $vatIsValid === '0')
                                                <span class="badge text-bg-danger">VAT invalido</span>
                                            @else
                                                <span class="badge text-bg-secondary">VAT por validar</span>
                                            @endif
                                            @if (!empty($vatCheckedAt))
                                                <small class="text-muted ms-2">Ultima validacao: {{ $vatCheckedAt }}</small>
                                            @endif
                                        </div>
                                    @endif
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
                                    <select class="form-select" name="country_iso2" required>
                                        @foreach (($countries ?? []) as $country)
                                            <option value="{{ $country['iso2'] }}" @selected($selectedCountryIso2 === $country['iso2'])>
                                                {{ $country['name'] }} ({{ $country['iso2'] }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-12 col-md-4">
                                    <label class="form-label">Zona de envio</label>
                                    @php($selectedZoneCode = old('zone_code', $address->zone_code ?: 'PT_MAINLAND'))
                                    <select class="form-select" name="zone_code">
                                        <option value="">Automatica</option>
                                        <option value="PT_MAINLAND" @selected($selectedZoneCode === 'PT_MAINLAND')>Portugal Continental</option>
                                        <option value="PT_ISLANDS" @selected($selectedZoneCode === 'PT_ISLANDS')>Acores / Madeira</option>
                                    </select>
                                    <div class="form-text">Obrigatoria para Portugal. Para outros paises fica automatica.</div>
                                </div>
                            </div>
                            <div class="mt-3 d-flex gap-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="1" name="is_default_shipping" id="is_default_shipping" @checked(old('is_default_shipping', $address->is_default_shipping))>
                                    <label class="form-check-label" for="is_default_shipping">Endereco de envio</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="1" name="is_default_billing" id="is_default_billing" @checked(old('is_default_billing', $address->is_default_billing))>
                                    <label class="form-check-label" for="is_default_billing">Endereco de faturacao</label>
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
