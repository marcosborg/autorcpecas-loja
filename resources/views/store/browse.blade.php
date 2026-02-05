@extends('store.layout', ['title' => 'Loja'])

@section('content')
    <div class="container">
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

                @if (!empty($selectedCategorySlug ?? '') && config('storefront.catalog_provider') === 'tpsoftware' && empty($facets['states'] ?? []) && empty($facets['conditions'] ?? []))
                    <div class="alert alert-light border mt-3 mb-0 small">
                        Dica: para ativar filtros de <strong>Estado</strong> e <strong>Condição</strong>, reconstrói o índice: <code>php artisan tpsoftware:index --force</code>
                    </div>
                @endif

                @if (!empty($facets['states'] ?? []))
                    <div class="card mt-3">
                        <div class="card-header fw-semibold">Estado</div>
                        <div class="card-body">
                            <select id="stateSelect" class="form-select store-filter-select">
                                <option value="{{ $facets['states_all_url'] ?? url('/loja/categorias/'.$selectedCategorySlug) }}" @if(empty($selectedState ?? '')) selected @endif>Todos</option>
                                @foreach (($facets['states'] ?? []) as $opt)
                                    <option value="{{ $opt['url'] ?? '#' }}" @if(($selectedState ?? '') === ($opt['slug'] ?? '')) selected @endif>
                                        {{ $opt['name'] }} ({{ $opt['count'] }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                @endif

                @if (!empty($facets['conditions'] ?? []))
                    <div class="card mt-3">
                        <div class="card-header fw-semibold">Condição</div>
                        <div class="card-body">
                            <select id="conditionSelect" class="form-select store-filter-select">
                                <option value="{{ $facets['conditions_all_url'] ?? url('/loja/categorias/'.$selectedCategorySlug) }}" @if(empty($selectedCondition ?? '')) selected @endif>Todos</option>
                                @foreach (($facets['conditions'] ?? []) as $opt)
                                    <option value="{{ $opt['url'] ?? '#' }}" @if(($selectedCondition ?? '') === ($opt['slug'] ?? '')) selected @endif>
                                        {{ $opt['name'] }} ({{ $opt['count'] }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                @endif

                @if (!empty($facets['prices'] ?? []))
                    <div class="card mt-3">
                        <div class="card-header fw-semibold">Preço</div>
                        <div class="card-body">
                            <select id="priceSelect" class="form-select store-filter-select">
                                <option value="{{ $facets['prices_all_url'] ?? url('/loja/categorias/'.$selectedCategorySlug) }}" @if(empty($selectedPrice ?? '')) selected @endif>Todos</option>
                                @foreach (($facets['prices'] ?? []) as $opt)
                                    <option value="{{ $opt['url'] ?? '#' }}" @if(($selectedPrice ?? '') === ($opt['key'] ?? '')) selected @endif>
                                        {{ $opt['label'] }} ({{ $opt['count'] }})
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
                                                <a class="link-primary text-decoration-none fw-semibold" href="{{ url('/loja/produtos/'.urlencode($productKey)) }}">
                                                    {{ $p['title'] ?? 'Produto' }}
                                                </a>
                                            </h6>
                                            <div class="text-muted small">{{ $p['reference'] ?? '' }}</div>
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
                            {{ $products->links() }}
                        </div>
                    @endif
                @else
                    <div class="alert alert-info">
                        Seleciona uma {{ config('storefront.catalog_provider') === 'tpsoftware' ? 'marca' : 'categoria' }} à esquerda para veres produtos.
                    </div>
                @endif
            </main>
        </div>
    </div>
@endsection
