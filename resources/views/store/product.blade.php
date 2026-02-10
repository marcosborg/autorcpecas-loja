@extends('store.layout', ['title' => $product['title'] ?? 'Produto'])

@section('content')
    @php($make = (string) ($product['make_name'] ?? $product['category'] ?? ''))
    @php($model = (string) ($product['model_name'] ?? ''))
    @php($images = array_values(array_filter($product['images'] ?? [], fn ($u) => is_string($u) && $u !== '')))
    @php($cover = (string) ($product['cover_image'] ?? ''))
    @php($carouselImages = $images)
    @if ($cover !== '' && in_array($cover, $images, true))
        @php($carouselImages = array_values(array_filter($images, fn ($u) => $u !== $cover)))
        @php(array_unshift($carouselImages, $cover))
    @endif

    <style>
        .product-breadcrumb-wrap {
            background: #faf7f7;
            border: 1px solid #eadede;
            border-radius: .75rem;
            padding: .8rem 1rem;
        }
        .product-breadcrumb {
            margin-bottom: 0;
            font-size: .98rem;
            line-height: 1.35;
        }
        .product-breadcrumb .breadcrumb-item + .breadcrumb-item::before {
            color: #9b6a6a;
            font-weight: 600;
        }
        .product-breadcrumb .breadcrumb-item a {
            color: #700000;
            font-weight: 500;
        }
        .product-breadcrumb .breadcrumb-item.active {
            color: #7a7a7a;
            font-weight: 600;
        }
    </style>

    <div class="container-xl">
        <nav aria-label="breadcrumb" class="mb-3 product-breadcrumb-wrap">
            <ol class="breadcrumb product-breadcrumb">
                <li class="breadcrumb-item"><a class="link-primary text-decoration-none" href="{{ url('/loja') }}">Loja</a></li>
                <li class="breadcrumb-item"><a class="link-primary text-decoration-none" href="{{ url('/loja/categorias') }}">Marcas</a></li>
                @if ($make !== '')
                    <li class="breadcrumb-item">
                        <a class="link-primary text-decoration-none" href="{{ url('/loja/categorias/'.\Illuminate\Support\Str::slug($make)) }}">{{ $make }}</a>
                    </li>
                @endif
                @if ($make !== '' && $model !== '')
                    <li class="breadcrumb-item">
                        <a class="link-primary text-decoration-none" href="{{ url('/loja/categorias/'.\Illuminate\Support\Str::slug($make)).'?model='.urlencode(\Illuminate\Support\Str::slug($model)) }}">{{ $model }}</a>
                    </li>
                @endif
                <li class="breadcrumb-item active" aria-current="page">Produto</li>
            </ol>
        </nav>

        <div class="row g-4">
            <div class="col-12 col-lg-5">
                <div class="card overflow-hidden">
                    @php($carouselId = 'productCarousel')

                    <div id="{{ $carouselId }}" class="carousel slide" data-bs-ride="false">
                        <div class="carousel-inner">
                            @if (count($carouselImages) === 0)
                                <div class="carousel-item active">
                                    <img
                                        class="d-block w-100 product-main-img"
                                        src="data:image/svg+xml;utf8,<svg xmlns=&quot;http://www.w3.org/2000/svg&quot; width=&quot;800&quot; height=&quot;600&quot;><rect width=&quot;100%&quot; height=&quot;100%&quot; fill=&quot;%23f2f2f2&quot;/><text x=&quot;50%&quot; y=&quot;50%&quot; dominant-baseline=&quot;middle&quot; text-anchor=&quot;middle&quot; fill=&quot;%23666&quot; font-family=&quot;Arial&quot; font-size=&quot;20&quot;>Sem imagem</text></svg>"
                                        alt=""
                                    >
                            </div>
                            @else
                                @foreach ($carouselImages as $img)
                                    <div class="carousel-item @if($loop->first) active @endif">
                                        <img
                                            class="d-block w-100 product-main-img"
                                            src="{{ $img }}"
                                            alt=""
                                            loading="{{ $loop->first ? 'eager' : 'lazy' }}"
                                            fetchpriority="{{ $loop->first ? 'high' : 'auto' }}"
                                            decoding="async"
                                            onerror="this.onerror=null;this.src='data:image/svg+xml;utf8,<svg xmlns=&quot;http://www.w3.org/2000/svg&quot; width=&quot;800&quot; height=&quot;600&quot;><rect width=&quot;100%&quot; height=&quot;100%&quot; fill=&quot;%23f2f2f2&quot;/><text x=&quot;50%&quot; y=&quot;50%&quot; dominant-baseline=&quot;middle&quot; text-anchor=&quot;middle&quot; fill=&quot;%23666&quot; font-family=&quot;Arial&quot; font-size=&quot;20&quot;>Sem imagem</text></svg>';"
                                        >
                                    </div>
                                @endforeach
                            @endif
                        </div>

                        @if (count($carouselImages) > 1)
                            <button class="carousel-control-prev" type="button" data-bs-target="#{{ $carouselId }}" data-bs-slide="prev">
                                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                                <span class="visually-hidden">Anterior</span>
                            </button>
                            <button class="carousel-control-next" type="button" data-bs-target="#{{ $carouselId }}" data-bs-slide="next">
                                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                                <span class="visually-hidden">Seguinte</span>
                            </button>
                        @endif
                    </div>

                    @if (count($carouselImages) > 1)
                        <div class="card-body">
                            <div class="d-flex flex-wrap gap-2">
                                @foreach ($carouselImages as $thumb)
                                    <button
                                        type="button"
                                        class="product-thumb-btn @if($loop->first) is-active @endif"
                                        data-bs-target="#{{ $carouselId }}"
                                        data-bs-slide-to="{{ $loop->index }}"
                                        aria-label="Imagem {{ $loop->iteration }}"
                                    >
                                        <img src="{{ $thumb }}" alt="" loading="lazy" decoding="async" onerror="this.remove();">
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <div class="col-12 col-lg-7">
                <h2 class="mb-2">{{ $product['title'] ?? 'Produto' }}</h2>

                <div class="text-muted small mb-3">
                    @if (!empty($product['reference']))
                        Ref: <span class="fw-semibold">{{ $product['reference'] }}</span>
                    @endif
                    @if ($make !== '')
                        <span class="mx-2">•</span>
                        Marca: <a class="link-primary text-decoration-none" href="{{ url('/loja/categorias/'.\Illuminate\Support\Str::slug($make)) }}">{{ $make }}</a>
                    @endif
                    @if ($model !== '')
                        <span class="mx-2">•</span>
                        Modelo: <a class="link-primary text-decoration-none" href="{{ url('/loja/categorias/'.\Illuminate\Support\Str::slug($make)).'?model='.urlencode(\Illuminate\Support\Str::slug($model)) }}">{{ $model }}</a>
                    @endif
                </div>

                <div class="card mb-3">
                    <div class="card-body">
                        <div class="d-flex flex-wrap gap-2">
                            @if (!is_null($product['price'] ?? null))
                                <span class="badge text-bg-primary fs-6">{{ $product['price'] }}&nbsp;&euro;</span>
                            @endif
                            @if (!is_null($product['stock'] ?? null))
                                <span class="badge text-bg-secondary fs-6">Stock: {{ $product['stock'] }}</span>
                            @endif
                            @if (!empty($product['state_name'] ?? ''))
                                <span class="badge text-bg-light border fs-6">{{ $product['state_name'] }}</span>
                            @endif
                            @if (!empty($product['condition_name'] ?? ''))
                                <span class="badge text-bg-light border fs-6">{{ $product['condition_name'] }}</span>
                            @endif
                        </div>

                        <div class="mt-3 d-flex gap-2">
                            <button class="btn btn-primary" type="button" disabled>Adicionar ao carrinho</button>
                            <button class="btn btn-outline-primary" type="button" disabled>Comprar agora</button>
                        </div>
                        <div class="text-muted small mt-2">Carrinho e checkout serao adicionados a seguir.</div>
                    </div>
                </div>

                <div class="accordion" id="productDetails">
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingRaw">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseRaw" aria-expanded="false" aria-controls="collapseRaw">
                                Detalhes tecnicos (raw)
                            </button>
                        </h2>
                        <div id="collapseRaw" class="accordion-collapse collapse" aria-labelledby="headingRaw" data-bs-parent="#productDetails">
                            <div class="accordion-body">
                                <pre class="mb-0" style="white-space: pre-wrap;">{{ json_encode($product['raw'] ?? [], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) }}</pre>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            var el = document.getElementById('productCarousel');
            if (!el || typeof window.bootstrap === 'undefined') return;

            el.addEventListener('slid.bs.carousel', function (e) {
                var index = (e && typeof e.to === 'number') ? e.to : null;
                if (index === null) return;

                var buttons = document.querySelectorAll('[data-bs-target="#productCarousel"][data-bs-slide-to]');
                buttons.forEach(function (b) { b.classList.remove('is-active'); });
                var active = document.querySelector('[data-bs-target="#productCarousel"][data-bs-slide-to="' + index + '"]');
                if (active) active.classList.add('is-active');
            });
        })();
    </script>

    @if (config('app.debug'))
        <script>
            (function () {
                try {
                    console.log('[Auto RC Peças] product.normalized', @json($product ?? null));
                    console.log('[Auto RC Peças] product.raw', @json($product['raw'] ?? null));
                    console.log('[Auto RC Peças] product.raw.images', @json(data_get($product['raw'] ?? [], 'images')));
                    console.log('[Auto RC Peças] product.raw.image_list', @json(data_get($product['raw'] ?? [], 'image_list')));
                } catch (e) {
                    console.warn('[Auto RC Peças] Falha a logar JSON do produto', e);
                }
            })();
        </script>
    @endif
@endsection
