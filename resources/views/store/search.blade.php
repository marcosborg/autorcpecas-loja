@extends('store.layout', ['title' => 'Pesquisa'])

@section('content')
    <div class="container-xl">
        <div class="d-flex flex-wrap gap-2 align-items-end justify-content-between mb-3">
            <div>
                <h3 class="mb-0">Pesquisa</h3>
                <div class="text-muted small mt-1">Query: "{{ $q }}"</div>
            </div>
            <div class="text-muted small">
                A mostrar {{ $results->count() }} de {{ $results->total() }}
            </div>
        </div>

        @if ($results->total() === 0)
            <div class="alert alert-secondary">Sem resultados.</div>
        @else
            <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3">
                @foreach ($results as $p)
                    @php($img = $p['images'][0] ?? null)
                    @php($productKey = (string) (($p['id'] ?? null) ?: ($p['reference'] ?? '')))
                    <div class="col">
                        <div class="card h-100">
                            @if (is_string($img) && $img !== '')
                                <img
                                    class="card-img-top store-img"
                                    src="{{ $img }}"
                                    alt=""
                                    loading="lazy"
                                    decoding="async"
                                    onerror="this.onerror=null;this.src='data:image/svg+xml;utf8,<svg xmlns=&quot;http://www.w3.org/2000/svg&quot; width=&quot;600&quot; height=&quot;400&quot;><rect width=&quot;100%&quot; height=&quot;100%&quot; fill=&quot;%23f2f2f2&quot;/><text x=&quot;50%&quot; y=&quot;50%&quot; dominant-baseline=&quot;middle&quot; text-anchor=&quot;middle&quot; fill=&quot;%23666&quot; font-family=&quot;Arial&quot; font-size=&quot;20&quot;>Sem imagem</text></svg>';"
                                >
                            @else
                                <div class="store-img"></div>
                            @endif
                            <div class="card-body">
                                <h6 class="card-title mb-1">
                                    <a class="link-primary text-decoration-none fw-semibold" href="{{ url('/loja/produtos/'.urlencode($productKey)) }}">{{ $p['title'] ?? 'Produto' }}</a>
                                </h6>
                                <div class="text-muted small">{{ $p['reference'] ?? '' }}</div>
                                @if (!empty($p['category'] ?? ''))
                                    <div class="text-muted small">{{ $p['category'] }}</div>
                                @endif
                            </div>
                            <div class="card-footer bg-white border-top-0 pt-0">
                                <div class="d-flex flex-wrap gap-2">
                                    @if (!is_null($p['price'] ?? null))
                                        <span class="badge text-bg-primary">{{ $p['price'] }}&nbsp;&euro;</span>
                                    @endif
                                    @if (!is_null($p['stock'] ?? null))
                                        <span class="badge text-bg-secondary">Stock: {{ $p['stock'] }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-4">
                {{ $results->links() }}
            </div>
        @endif
    </div>
@endsection
