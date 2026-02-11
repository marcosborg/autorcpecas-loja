@extends('store.layout', ['title' => $categoryName])

@section('content')
    <style>
        .cat-product-title {
            font-size: 1rem;
            line-height: 1.25;
        }
        .cat-product-meta {
            font-size: .86rem;
            line-height: 1.3;
            color: #4a4a4a;
        }
        .cat-product-meta strong {
            color: #222;
            font-weight: 700;
        }
    </style>

    <div class="container-xl">
        <div class="d-flex flex-wrap gap-2 align-items-end justify-content-between mb-3">
            <div>
                <h3 class="mb-0">{{ $categoryName }}</h3>
                <div class="text-muted small mt-1">Categoria: {{ $categorySlug }}</div>
            </div>
            <div class="text-muted small">
                A mostrar {{ $products->count() }} de {{ $products->total() }}
            </div>
        </div>

        @if (!empty($models ?? []))
            <div class="card mb-3">
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <div class="text-muted small">Modelos:</div>
                        <a class="btn btn-sm @if(empty($selectedModel ?? '')) btn-primary @else btn-outline-primary @endif" href="{{ url('/loja/categorias/'.$categorySlug) }}">Todos</a>
                        @foreach ($models as $m)
                            <a class="btn btn-sm @if(($selectedModel ?? '') === $m['slug']) btn-primary @else btn-outline-primary @endif" href="{{ url('/loja/categorias/'.$categorySlug).'?model='.urlencode($m['slug']) }}">{{ $m['name'] }}</a>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        @if ($products->total() === 0)
            <div class="alert alert-secondary">Sem produtos nesta categoria.</div>
        @else
            <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3">
                @foreach ($products as $p)
                    @php($img = $p['cover_image'] ?? ($p['images'][0] ?? null))
                    @php($productKey = (string) (($p['id'] ?? null) ?: ($p['reference'] ?? '')))
                    @php($vehicleLine = trim((string) ($p['make_name'] ?? '').' '.(string) ($p['model_name'] ?? '')))
                    @php($fuelType = trim((string) ($p['fuel_type'] ?? '')))
                    @php($engineLine = trim((string) ($p['engine_label'] ?? '')))
                    @php($tpRef = trim((string) ($p['tp_reference'] ?? '')))
                    <div class="col">
                        <div class="card h-100 product-card">
                            <a
                                href="{{ url('/loja/produtos/'.urlencode($productKey)) }}"
                                class="product-card-link"
                                aria-label="Ver produto {{ $p['title'] ?? 'Produto' }}"
                            ></a>
                            @if (is_string($img) && $img !== '')
                                <div class="tp-image-frame tp-image-frame-block">
                                    <span class="tp-image-spinner" aria-hidden="true"></span>
                                    <img
                                        class="card-img-top store-img tp-preload-img"
                                        src="data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='600' height='400'><rect width='100%' height='100%' fill='%23f2f2f2'/></svg>"
                                        data-tp-src="{{ $img }}"
                                        alt=""
                                        loading="lazy"
                                        decoding="async"
                                        onerror="this.onerror=null;this.src='data:image/svg+xml;utf8,<svg xmlns=&quot;http://www.w3.org/2000/svg&quot; width=&quot;600&quot; height=&quot;400&quot;><rect width=&quot;100%&quot; height=&quot;100%&quot; fill=&quot;%23f2f2f2&quot;/><text x=&quot;50%&quot; y=&quot;50%&quot; dominant-baseline=&quot;middle&quot; text-anchor=&quot;middle&quot; fill=&quot;%23666&quot; font-family=&quot;Arial&quot; font-size=&quot;20&quot;>Sem imagem</text></svg>';"
                                    >
                                </div>
                            @else
                                <div class="store-img"></div>
                            @endif
                            <div class="card-body d-flex flex-column">
                                <h6 class="card-title mb-1 cat-product-title">
                                    <a class="link-primary text-decoration-none fw-semibold" href="{{ url('/loja/produtos/'.urlencode($productKey)) }}">{{ $p['title'] ?? 'Produto' }}</a>
                                </h6>
                                @if ($vehicleLine !== '')
                                    <div class="cat-product-meta mb-1"><strong>{{ $vehicleLine }}</strong></div>
                                @endif
                                @if ($fuelType !== '')
                                    <div class="cat-product-meta mb-1">{{ $fuelType }}</div>
                                @endif
                                @if ($engineLine !== '')
                                    <div class="cat-product-meta mb-1"><strong>Motor:</strong> {{ $engineLine }}</div>
                                @endif
                                @if (!empty($p['reference'] ?? ''))
                                    <div class="cat-product-meta mb-1"><strong>Ref.:</strong> {{ $p['reference'] }}</div>
                                @endif
                                @if ($tpRef !== '')
                                    <div class="cat-product-meta"><strong>Ref. TP:</strong> {{ $tpRef }}</div>
                                @endif
                            </div>
                            <div class="card-footer bg-white border-top-0 pt-0">
                                @php($priceExVat = $p['price_ex_vat'] ?? ($p['price'] ?? null))
                                @php($isConsultPrice = is_numeric($priceExVat) && (float) $priceExVat <= 0)
                                <div class="d-flex flex-wrap align-items-center gap-2">
                                    @if (is_numeric($priceExVat))
                                        <div class="store-price-box">
                                            @if ($isConsultPrice)
                                                <div>
                                                    <span class="price-amount" style="font-size: 1rem;">Sob consulta</span>
                                                </div>
                                            @else
                                                <div>
                                                    <span class="price-amount">{{ number_format((float) $priceExVat, 2, ',', ' ') }}</span>
                                                    <span class="price-currency">EUR</span>
                                                </div>
                                                <div class="price-note">sem IVA</div>
                                            @endif
                                        </div>
                                    @endif
                                    @if (!is_null($p['stock'] ?? null))
                                        <span class="badge rounded-pill text-bg-secondary px-2 py-1">Stock: {{ $p['stock'] }}</span>
                                    @endif
                                    @if ($isConsultPrice)
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-primary"
                                            data-bs-toggle="modal"
                                            data-bs-target="#consultPriceModal"
                                            data-consult-trigger
                                            data-consult-action="{{ url('/loja/produtos/'.urlencode($productKey).'/consulta') }}"
                                            data-consult-title="{{ $p['title'] ?? 'Produto' }}"
                                            data-consult-reference="{{ $p['reference'] ?? '' }}"
                                        >
                                            Pedir contacto
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-4">
                {{ $products->links() }}
            </div>
        @endif
    </div>
@endsection
