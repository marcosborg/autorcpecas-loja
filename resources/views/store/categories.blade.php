@extends('store.layout', ['title' => config('storefront.catalog_provider') === 'tpsoftware' ? 'Marcas' : 'Categorias'])

@section('content')
    <div class="container-xl">
        <div class="d-flex flex-wrap gap-2 align-items-end justify-content-between mb-3">
            <div>
                <h3 class="mb-0">{{ config('storefront.catalog_provider') === 'tpsoftware' ? 'Marcas' : 'Categorias' }}</h3>
                @if (isset($totalProducts))
                    <div class="text-muted small mt-1">Total de produtos: {{ $totalProducts }}</div>
                @endif
            </div>
        </div>

        @if (count($categories) === 0)
            <div class="alert alert-secondary">Sem categorias para mostrar.</div>
        @else
            <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3">
                @foreach ($categories as $cat)
                    <div class="col">
                        <div class="card h-100">
                            <div class="card-body">
                                <h6 class="card-title mb-1">
                                    <a class="link-primary text-decoration-none fw-semibold" href="{{ url('/loja/categorias/'.$cat['slug']) }}">{{ $cat['name'] }}</a>
                                </h6>
                                @if (!is_null($cat['count']))
                                    <div class="text-muted small">{{ $cat['count'] }} produto(s)</div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endsection
