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
        .product-zoom-wrap {
            position: relative;
        }
        .product-zoom-trigger {
            position: absolute;
            right: .7rem;
            bottom: .7rem;
            z-index: 4;
            border: 0;
            border-radius: 999px;
            width: 38px;
            height: 38px;
            background: rgba(17, 24, 39, .72);
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.05rem;
            cursor: pointer;
            transition: background .2s ease;
        }
        .product-zoom-trigger:hover {
            background: rgba(17, 24, 39, .9);
        }
        .product-main-img {
            cursor: default;
        }
        .product-zoom-modal .modal-content {
            background: #0f1116;
            border: 0;
        }
        .product-zoom-image {
            width: 100%;
            max-height: 80vh;
            object-fit: contain;
            display: block;
            margin: 0 auto;
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
                                        <div class="tp-image-frame tp-image-frame-block product-zoom-wrap">
                                            <span class="tp-image-spinner" aria-hidden="true"></span>
                                            <img
                                                class="d-block w-100 product-main-img tp-preload-img"
                                                src="data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='800' height='600'><rect width='100%' height='100%' fill='%23f2f2f2'/></svg>"
                                                data-tp-src="{{ $img }}"
                                                data-tp-eager="1"
                                                alt=""
                                                loading="{{ $loop->first ? 'eager' : 'lazy' }}"
                                                fetchpriority="{{ $loop->first ? 'high' : 'auto' }}"
                                                decoding="async"
                                                onerror="this.onerror=null;this.src='data:image/svg+xml;utf8,<svg xmlns=&quot;http://www.w3.org/2000/svg&quot; width=&quot;800&quot; height=&quot;600&quot;><rect width=&quot;100%&quot; height=&quot;100%&quot; fill=&quot;%23f2f2f2&quot;/><text x=&quot;50%&quot; y=&quot;50%&quot; dominant-baseline=&quot;middle&quot; text-anchor=&quot;middle&quot; fill=&quot;%23666&quot; font-family=&quot;Arial&quot; font-size=&quot;20&quot;>Sem imagem</text></svg>';"
                                            >
                                            <button
                                                type="button"
                                                class="product-zoom-trigger"
                                                data-zoom-trigger
                                                data-zoom-src="{{ $img }}"
                                                aria-label="Ampliar imagem"
                                                title="Ampliar imagem"
                                            >
                                                <i class="bi bi-zoom-in" aria-hidden="true"></i>
                                            </button>
                                        </div>
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
                                        <span class="tp-image-frame">
                                            <span class="tp-image-spinner" aria-hidden="true"></span>
                                            <img
                                                class="tp-preload-img"
                                                src="data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='72' height='72'><rect width='100%' height='100%' fill='%23f2f2f2'/></svg>"
                                                data-tp-src="{{ $thumb }}"
                                                alt=""
                                                loading="lazy"
                                                decoding="async"
                                                onerror="this.remove();"
                                            >
                                        </span>
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
                        @php($priceExVat = $product['price_ex_vat'] ?? ($product['price'] ?? null))
                        @php($isConsultPrice = is_numeric($priceExVat) && (float) $priceExVat <= 0)
                        @php($productKey = (string) (($product['id'] ?? null) ?: ($product['reference'] ?? '')))
                        @php($idOrReference = (string) (($product['id'] ?? null) ?: ($product['reference'] ?? '')))
                        @php($productTitle = (string) ($product['title'] ?? 'Produto'))
                        @php($productReference = (string) ($product['reference'] ?? ''))
                        @php($productUrl = (string) request()->fullUrl())
                        @php($waMessage = 'Olá! Tenho interesse neste produto: '.$productTitle.($productReference !== '' ? ' (Ref: '.$productReference.')' : '').'. Link: '.$productUrl)
                        @php($waLink = 'https://wa.me/351914401299?text='.rawurlencode($waMessage))
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
                            @if (!is_null($product['stock'] ?? null))
                                <span class="badge rounded-pill text-bg-secondary px-2 py-1">Stock: {{ $product['stock'] }}</span>
                            @endif
                            @if (!empty($product['state_name'] ?? ''))
                                <span class="badge text-bg-light border fs-6">{{ $product['state_name'] }}</span>
                            @endif
                            @if (!empty($product['condition_name'] ?? ''))
                                <span class="badge text-bg-light border fs-6">{{ $product['condition_name'] }}</span>
                            @endif
                        </div>

                        <div class="mt-3 d-flex gap-2">
                            @if ($isConsultPrice)
                                <button
                                    class="btn btn-primary"
                                    type="button"
                                    data-bs-toggle="modal"
                                    data-bs-target="#consultPriceModal"
                                    data-consult-trigger
                                    data-consult-action="{{ url('/loja/produtos/'.urlencode($idOrReference).'/consulta') }}"
                                    data-consult-title="{{ $product['title'] ?? 'Produto' }}"
                                    data-consult-reference="{{ $product['reference'] ?? '' }}"
                                >
                                    Pedir contacto
                                </button>
                            @else
                                @auth
                                    <form method="post" action="{{ url('/loja/carrinho/items') }}" class="d-inline">
                                        @csrf
                                        <input type="hidden" name="product_key" value="{{ $productKey }}">
                                        <input type="hidden" name="quantity" value="1">
                                        <button class="btn btn-primary" type="submit">Adicionar ao carrinho</button>
                                    </form>
                                    <a class="btn btn-outline-primary" href="{{ url('/loja/checkout') }}">Ir para checkout</a>
                                @else
                                    <a class="btn btn-primary" href="{{ url('/loja/conta/login') }}">Entrar para comprar</a>
                                @endauth
                            @endif
                        </div>
                        <div class="mt-3">
                            <a
                                class="btn btn-success"
                                href="{{ $waLink }}"
                                target="_blank"
                                rel="noopener"
                            >
                                Falar no WhatsApp
                            </a>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <div class="modal fade product-zoom-modal" id="productZoomModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body pt-2">
                    <img class="product-zoom-image" src="" alt="Imagem ampliada" data-zoom-image>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            var initialized = false;

            function initWhenBootstrapReady(callback) {
                var attempts = 0;
                var maxAttempts = 40;

                function tryInit() {
                    if (typeof window.bootstrap !== 'undefined') {
                        callback();
                        return;
                    }

                    attempts += 1;
                    if (attempts < maxAttempts) {
                        window.setTimeout(tryInit, 100);
                    }
                }

                tryInit();
            }

            function initCarouselThumbState() {
                var el = document.getElementById('productCarousel');
                if (!el) return;

                el.addEventListener('slid.bs.carousel', function (e) {
                    var index = (e && typeof e.to === 'number') ? e.to : null;
                    if (index === null) return;

                    var buttons = document.querySelectorAll('[data-bs-target="#productCarousel"][data-bs-slide-to]');
                    buttons.forEach(function (b) { b.classList.remove('is-active'); });
                    var active = document.querySelector('[data-bs-target="#productCarousel"][data-bs-slide-to="' + index + '"]');
                    if (active) active.classList.add('is-active');
                });
            }

            function initProductZoom() {
                var zoomModalEl = document.getElementById('productZoomModal');
                if (!zoomModalEl) return;

                var zoomImage = zoomModalEl.querySelector('[data-zoom-image]');
                if (!zoomImage) return;

                var zoomModal = new window.bootstrap.Modal(zoomModalEl);

                function openZoom(src) {
                    var imageSrc = String(src || '').trim();
                    if (imageSrc === '') return;
                    zoomImage.src = imageSrc;
                    zoomModal.show();
                }

                document.addEventListener('click', function (event) {
                    var trigger = event.target.closest('[data-zoom-trigger]');
                    if (!trigger) return;

                    var src = trigger.getAttribute('data-zoom-src') || '';
                    openZoom(src);
                });

                document.addEventListener('dblclick', function (event) {
                    var image = event.target.closest('.product-main-img[data-tp-src]');
                    if (!image) return;
                    openZoom(image.getAttribute('data-tp-src') || image.getAttribute('src') || '');
                });

                zoomModalEl.addEventListener('hidden.bs.modal', function () {
                    zoomImage.src = '';
                });
            }

            function init() {
                if (initialized) return;
                initialized = true;
                initCarouselThumbState();
                initProductZoom();
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function () {
                    initWhenBootstrapReady(init);
                });
            } else {
                initWhenBootstrapReady(init);
            }
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
