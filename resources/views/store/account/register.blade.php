@extends('store.layout', ['title' => 'Criar Conta'])

@section('content')
    <div class="container-xl">
        <div class="row justify-content-center">
            <div class="col-12 col-lg-7">
                <div class="card">
                    <div class="card-header fw-semibold">Criar conta de cliente</div>
                    <div class="card-body">
                        <form method="post" action="{{ url('/loja/conta/registo') }}">
                            @csrf
                            <div class="row g-3">
                                <div class="col-12 col-md-6">
                                    <label class="form-label">Nome</label>
                                    <input type="text" class="form-control" name="name" value="{{ old('name') }}" required>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" name="email" value="{{ old('email') }}" required>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label">Telefone</label>
                                    <input type="text" class="form-control" name="phone" value="{{ old('phone') }}">
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label">NIF</label>
                                    <input type="text" class="form-control" name="nif" value="{{ old('nif') }}">
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label">Password</label>
                                    <input type="password" class="form-control" name="password" required>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label">Confirmar password</label>
                                    <input type="password" class="form-control" name="password_confirmation" required>
                                </div>
                            </div>
                            <button class="btn btn-primary mt-3 w-100" type="submit">Criar conta</button>
                        </form>
                    </div>
                    <div class="card-footer text-muted small">
                        Ja tens conta? <a href="{{ url('/loja/conta/login') }}">Entrar</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

