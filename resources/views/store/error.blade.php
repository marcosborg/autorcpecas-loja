@extends('store.layout', ['title' => 'Loja indisponivel'])

@section('content')
    <div class="container-xl">
        <div class="alert alert-warning">
            <div class="fw-semibold mb-1">Loja indisponivel</div>
            <div>{{ $message ?? 'Nao foi possivel contactar o servico externo.' }}</div>
            <div class="mt-2">
                <a class="btn btn-primary btn-sm" href="{{ url('/loja') }}">Tentar novamente</a>
            </div>
        </div>
    </div>
@endsection
