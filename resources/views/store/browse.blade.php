@extends('store.layout', ['title' => 'Loja'])

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

    <div class="container mt-3">
        <div class="row g-3">
            <aside class="col-12 col-lg-4">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <h5 class="mb-0">Filtros</h5>
                    @if (isset($totalProducts))
                        <span class="badge text-bg-secondary">Total: {{ $totalProducts }}</span>
                    @endif
                </div>

                <div class="card mb-3">
                    <div class="card-header fw-semibold">
                        {{ config('storefront.catalog_provider') === 'tpsoftware' ? 'Marcas' : 'Categorias' }}
                    </div>
                    <div class="card-body">
                        <label for="makeSelect" class="form-label mb-1">Pesquisar</label>
                        <select id="makeSelect" class="form-select store-filter-select">
                            <option value="{{ url('/loja/categorias') }}" @if(empty($selectedCategorySlug ?? '')) selected @endif>Todos</option>
                            @foreach (($categories ?? []) as $cat)
                                <option value="{{ url('/loja/categorias/'.$cat['slug']) }}" @if(($selectedCategorySlug ?? '') === $cat['slug']) selected @endif>
                                    {{ $cat['name'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                @if (!empty($models ?? []))
                    <div class="card">
                        <div class="card-header fw-semibold">Modelos</div>
                        <div class="card-body">
                            <label for="modelSelect" class="form-label mb-1">Pesquisar</label>
                            <select id="modelSelect" class="form-select store-filter-select">
                                <option value="{{ url('/loja/categorias/'.$selectedCategorySlug) }}" @if(empty($selectedModel ?? '')) selected @endif>Todos</option>
                                @foreach ($models as $m)
                                    <option value="{{ url('/loja/categorias/'.$selectedCategorySlug).'?model='.urlencode($m['slug']) }}" @if(($selectedModel ?? '') === $m['slug']) selected @endif>
                                        {{ $m['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                @endif

                @php($facets = $facets ?? [])

                @if (!empty($facets['piece_categories'] ?? []))
                    <div class="card mt-3">
                    <div class="card-header fw-semibold">Categoria da peça</div>
                        <div class="card-body">
                            <select id="pieceCategorySelect" class="form-select store-filter-select">
                                <option value="{{ $facets['piece_categories_all_url'] ?? url('/loja/categorias/'.$selectedCategorySlug) }}" @if(empty($selectedPiece ?? '')) selected @endif>Todos</option>
                                @foreach (($facets['piece_categories'] ?? []) as $opt)
                                    <option value="{{ $opt['url'] ?? '#' }}" @if(($selectedPiece ?? '') === ($opt['slug'] ?? '')) selected @endif>
                                        {{ $opt['name'] }} ({{ $opt['count'] }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                @endif

            </aside>

            <main class="col-12 col-lg-8">
                <div class="d-flex flex-wrap gap-2 align-items-end justify-content-between mb-3">
                    <div>
                        <h3 class="mb-0">
                            @if(!empty($categoryName ?? ''))
                                {{ $categoryName }}
                                @if(!empty($modelName ?? ''))
                                    <small class="text-muted">/ {{ $modelName }}</small>
                                @endif
                            @else
                                Produtos
                            @endif
                        </h3>
                        @if (!empty($hint ?? ''))
                            <div class="text-muted small mt-1">{{ $hint }}</div>
                        @endif
                    </div>

                    @if (isset($products) && method_exists($products, 'total'))
                        <div class="text-muted small">
                            A mostrar {{ $products->count() }} de {{ $products->total() }}
                        </div>
                    @endif
                </div>

                @if (isset($products) && method_exists($products, 'total'))
                    @if ($products->total() === 0)
                        <div class="alert alert-secondary">Sem produtos para os filtros selecionados.</div>
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
                                                <a class="link-primary text-decoration-none fw-semibold" href="{{ url('/loja/produtos/'.urlencode($productKey)) }}">
                                                    {{ $p['title'] ?? 'Produto' }}
                                                </a>
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
                @else
                    <div class="alert alert-info">
                        Seleciona uma {{ config('storefront.catalog_provider') === 'tpsoftware' ? 'marca' : 'categoria' }} Ã  esquerda para veres produtos.
                    </div>
                @endif
            </main>
        </div>
    </div>
@endsection
