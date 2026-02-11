@extends('store.layout', ['title' => 'Entrar'])

@section('content')
    <div class="container-xl">
        <div class="row justify-content-center">
            <div class="col-12 col-md-6 col-lg-5">
                <div class="card">
                    <div class="card-header fw-semibold">Entrar na conta</div>
                    <div class="card-body">
                        <form method="post" action="{{ url('/loja/conta/login') }}">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email" value="{{ old('email') }}" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Password</label>
                                <input type="password" class="form-control" name="password" required>
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="remember" value="1" id="remember">
                                <label class="form-check-label" for="remember">Manter sessao iniciada</label>
                            </div>
                            <button class="btn btn-primary w-100" type="submit">Entrar</button>
                        </form>
                    </div>
                    <div class="card-footer text-muted small">
                        Ainda nao tens conta? <a href="{{ url('/loja/conta/registo') }}">Criar conta</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

