@extends('store.layout', ['title' => 'Pesquisa'])

@section('content')
    <div class="container-xl">
        <style>
            .search-result-item .thumb {
                width: 200px;
                height: 150px;
                object-fit: cover;
                border-radius: .25rem;
                background: #f2f2f2;
            }
            .search-result-item .title-link {
                font-size: 1.2rem;
                line-height: 1.2;
                text-decoration: underline;
                text-underline-offset: 2px;
            }
            .search-result-item .meta-line {
                font-size: .98rem;
                line-height: 1.35;
                color: #1f1f1f;
            }
            .search-result-item .meta-line strong {
                font-weight: 700;
            }
            @media (max-width: 767.98px) {
                .search-result-item .thumb {
                    width: 100%;
                    height: 190px;
                }
                .search-result-item .title-link { font-size: 1.05rem; }
                .search-result-item .meta-line { font-size: .92rem; }
            }
        </style>

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
            <div class="d-flex flex-column gap-3">
                @foreach ($results as $p)
                    @php($img = $p['cover_image'] ?? ($p['images'][0] ?? null))
                    @php($productKey = (string) (($p['id'] ?? null) ?: ($p['reference'] ?? '')))
                    @php($vehicleLine = trim((string) ($p['make_name'] ?? '').' '.(string) ($p['model_name'] ?? '')))
                    @php($fuelType = trim((string) ($p['fuel_type'] ?? '')))
                    @php($engineLine = trim((string) ($p['engine_label'] ?? '')))
                    @php($tpRef = trim((string) ($p['tp_reference'] ?? '')))
                    @php($priceExVat = $p['price_ex_vat'] ?? ($p['price'] ?? null))
                    @php($isConsultPrice = is_numeric($priceExVat) && (float) $priceExVat <= 0)
                    <article class="card search-result-item">
                        <div class="card-body">
                            <div class="d-flex flex-column flex-md-row gap-3">
                                <div class="flex-shrink-0">
                                    @if (is_string($img) && $img !== '')
                                        <div class="tp-image-frame">
                                            <span class="tp-image-spinner" aria-hidden="true"></span>
                                            <img
                                                class="thumb tp-preload-img"
                                                src="data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='600' height='400'><rect width='100%' height='100%' fill='%23f2f2f2'/></svg>"
                                                data-tp-src="{{ $img }}"
                                                alt=""
                                                loading="lazy"
                                                decoding="async"
                                                onerror="this.onerror=null;this.src='data:image/svg+xml;utf8,<svg xmlns=&quot;http://www.w3.org/2000/svg&quot; width=&quot;600&quot; height=&quot;400&quot;><rect width=&quot;100%&quot; height=&quot;100%&quot; fill=&quot;%23f2f2f2&quot;/><text x=&quot;50%&quot; y=&quot;50%&quot; dominant-baseline=&quot;middle&quot; text-anchor=&quot;middle&quot; fill=&quot;%23666&quot; font-family=&quot;Arial&quot; font-size=&quot;20&quot;>Sem imagem</text></svg>';"
                                            >
                                        </div>
                                    @else
                                        <div class="thumb"></div>
                                    @endif
                                </div>
                                <div class="flex-grow-1">
                                    <a class="title-link link-primary fw-semibold" href="{{ url('/loja/produtos/'.urlencode($productKey)) }}">{{ $p['title'] ?? 'Produto' }}</a>
                                    @if ($vehicleLine !== '')
                                        <div class="meta-line mt-1"><strong>{{ $vehicleLine }}</strong></div>
                                    @endif
                                    @if ($fuelType !== '')
                                        <div class="meta-line mt-1"><strong>{{ $fuelType }}</strong></div>
                                    @endif
                                    @if ($engineLine !== '')
                                        <div class="meta-line mt-1"><strong>MOTOR:</strong> {{ $engineLine }}</div>
                                    @endif
                                    @if (!empty($p['reference'] ?? ''))
                                        <div class="meta-line mt-1"><strong>Ref.:</strong> {{ $p['reference'] }}</div>
                                    @endif
                                    @if ($tpRef !== '')
                                        <div class="meta-line mt-1"><strong>Ref. TP:</strong> {{ $tpRef }}</div>
                                    @endif
                                    @if (is_numeric($priceExVat))
                                        <div class="mt-2 d-flex flex-wrap align-items-center gap-2">
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
                                    @endif
                                </div>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="mt-4">
                {{ $results->links() }}
            </div>
        @endif
    </div>
@endsection
