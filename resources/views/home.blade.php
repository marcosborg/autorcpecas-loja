@extends('store.layout', ['title' => 'Home'])

@section('content')
    <style>
        .home-hero-img {
            width: 100%;
            height: clamp(260px, 40vw, 520px);
            object-fit: cover;
            display: block;
            background: #111;
        }

        .brand-marquee {
            overflow: hidden;
            border-radius: 1rem;
            background: #fff;
        }
        .brand-track {
            display: flex;
            gap: 2.25rem;
            align-items: center;
            padding: 1rem 1.25rem;
            width: max-content;
            animation: brand-scroll 28s linear infinite;
        }
        .brand-marquee:hover .brand-track { animation-play-state: paused; }
        .brand-logo {
            height: 44px;
            width: auto;
            max-width: 140px;
            object-fit: contain;
            filter: grayscale(10%);
            opacity: .95;
        }
        @keyframes brand-scroll {
            from { transform: translateX(0); }
            to { transform: translateX(-50%); }
        }
        @media (prefers-reduced-motion: reduce) {
            .brand-track { animation: none; }
        }

        .home-product-title {
            font-size: 1rem;
            line-height: 1.25;
        }
        .home-product-meta {
            font-size: .86rem;
            line-height: 1.3;
            color: #4a4a4a;
        }
        .home-product-meta strong {
            color: #222;
            font-weight: 700;
        }
    </style>

    @php($bannerUrl = isset($banner?->image_path) && $banner?->image_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($banner->image_path) : null)

    <div class="container-fluid px-0">
        @if ($bannerUrl)
            <img class="home-hero-img" src="{{ $bannerUrl }}" alt="" loading="eager" decoding="async">
        @else
            <div class="home-hero-img"></div>
        @endif
    </div>

    <div class="container-xl mt-4">
        <div class="row g-4">

            @if (($brands?->count() ?? 0) > 0)
                @php($brandItems = $brands->values())
                @php($loopItems = $brandItems->concat($brandItems))
                <div class="col-12">
                    <div class="brand-marquee border shadow-sm">
                        <div class="brand-track">
                            @foreach ($loopItems as $brand)
                                @php($logo = $brand->logo_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($brand->logo_path) : null)
                                @if ($logo)
                                    @if (!empty($brand->url))
                                        <a href="{{ $brand->url }}" class="d-inline-flex align-items-center" target="_blank" rel="noopener">
                                            <img class="brand-logo" src="{{ $logo }}" alt="{{ $brand->name }}" loading="lazy" decoding="async">
                                        </a>
                                    @else
                                        <img class="brand-logo" src="{{ $logo }}" alt="{{ $brand->name }}" loading="lazy" decoding="async">
                                    @endif
                                @endif
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            <div class="col-12">
                <div class="d-flex align-items-end justify-content-between gap-2 mb-2">
                    <h4 class="mb-0">Destaques</h4>
                    <a class="link-primary text-decoration-none" href="{{ url('/loja') }}">Ver todos</a>
                </div>

                @if (empty($products ?? []))
                    <div class="alert alert-secondary mb-0">Sem produtos para mostrar neste momento.</div>
                @else
                    <div class="row row-cols-1 row-cols-md-2 row-cols-xl-4 g-3">
                        @foreach ($products as $p)
                            @php($img = $p['cover_image'] ?? ($p['images'][0] ?? null))
                            @php($productKey = (string) (($p['id'] ?? null) ?: ($p['reference'] ?? '')))
                            @php($vehicleLine = trim((string) ($p['make_name'] ?? '').' '.(string) ($p['model_name'] ?? '')))
                            @php($fuelType = trim((string) ($p['fuel_type'] ?? '')))
                            @php($engineLine = trim((string) ($p['engine_label'] ?? '')))
                            @php($tpRef = trim((string) ($p['tp_reference'] ?? '')))
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

                                    <div class="card-body d-flex flex-column">
                                        <h6 class="card-title mb-1 home-product-title">
                                            <a class="link-primary text-decoration-none fw-semibold" href="{{ url('/loja/produtos/'.urlencode($productKey)) }}">
                                                {{ $p['title'] ?? 'Produto' }}
                                            </a>
                                        </h6>
                                        @if ($vehicleLine !== '')
                                            <div class="home-product-meta mb-1"><strong>{{ $vehicleLine }}</strong></div>
                                        @endif
                                        @if ($fuelType !== '')
                                            <div class="home-product-meta mb-1">{{ $fuelType }}</div>
                                        @endif
                                        @if ($engineLine !== '')
                                            <div class="home-product-meta mb-1"><strong>Motor:</strong> {{ $engineLine }}</div>
                                        @endif
                                        @if (!empty($p['reference'] ?? ''))
                                            <div class="home-product-meta mb-1"><strong>Ref.:</strong> {{ $p['reference'] }}</div>
                                        @endif
                                        @if ($tpRef !== '')
                                            <div class="home-product-meta"><strong>Ref. TP:</strong> {{ $tpRef }}</div>
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
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
