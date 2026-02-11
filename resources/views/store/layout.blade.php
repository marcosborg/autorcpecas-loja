<!doctype html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Loja' }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/css/select2.min.css" rel="stylesheet">
    <style>
        :root {
            --bs-primary: #700000;
            --bs-primary-rgb: 112, 0, 0;
            --bs-link-color: #700000;
            --bs-link-hover-color: #5a0000;
        }

        a:not(.btn) { color: #700000; }
        a:not(.btn):hover { color: #5a0000; }
        a:not(.btn):visited { color: #700000; }
        .link-primary { color: #700000 !important; }
        .link-primary:hover { color: #5a0000 !important; }

        .btn-primary {
            --bs-btn-bg: #700000;
            --bs-btn-border-color: #700000;
            --bs-btn-hover-bg: #5a0000;
            --bs-btn-hover-border-color: #5a0000;
            --bs-btn-active-bg: #5a0000;
            --bs-btn-active-border-color: #5a0000;
        }

        .btn-outline-primary {
            --bs-btn-color: #700000;
            --bs-btn-border-color: #700000;
            --bs-btn-hover-bg: #700000;
            --bs-btn-hover-border-color: #700000;
            --bs-btn-active-bg: #5a0000;
            --bs-btn-active-border-color: #5a0000;
        }

        .text-bg-primary { background-color: #700000 !important; }
        .page-item.active .page-link {
            background-color: #700000;
            border-color: #700000;
        }
        .page-link { color: #700000; }
        .page-link:hover { color: #5a0000; }

        .store-img {
            width: 100%;
            aspect-ratio: 4 / 3;
            height: auto;
            object-fit: cover;
            object-position: center bottom;
            background: #f2f2f2;
            display: block;
        }
        .tp-preload-img {
            opacity: 0;
            transition: opacity .2s ease;
        }
        .tp-preload-img.is-loaded {
            opacity: 1;
        }
        .tp-image-frame {
            position: relative;
            display: inline-block;
            background: #f2f2f2;
        }
        .tp-image-frame.tp-image-frame-block {
            display: block;
            width: 100%;
        }
        .tp-image-spinner {
            position: absolute;
            top: 50%;
            left: 50%;
            width: 26px;
            height: 26px;
            margin-top: -13px;
            margin-left: -13px;
            border-radius: 50%;
            border: 3px solid rgba(112, 0, 0, .18);
            border-top-color: #700000;
            animation: tp-spin .75s linear infinite;
            pointer-events: none;
            z-index: 2;
        }
        .tp-image-frame.is-loaded .tp-image-spinner {
            display: none;
        }
        @keyframes tp-spin {
            to { transform: rotate(360deg); }
        }
        .store-list-scroll { max-height: 380px; overflow: auto; }
        .select2-container--default .select2-selection--single { height: 38px; border: 1px solid #dee2e6; border-radius: .375rem; }
        .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 36px; padding-left: 12px; }
        .select2-container--default .select2-selection--single .select2-selection__arrow { height: 36px; }
        .select2-container--default .select2-results__option--highlighted.select2-results__option--selectable { background-color: #700000; }

        .store-navbar { background: #fff; }
        .store-navbar .navbar-brand img { height: 52px; width: auto; }
        .store-navbar .nav-link { padding-left: .75rem; padding-right: .75rem; }
        .store-navbar .nav-link.active { font-weight: 600; color: var(--bs-primary) !important; }

        .store-topnav { background: #fff; }
        .store-topnav .nav-link { font-weight: 600; letter-spacing: .04em; text-transform: uppercase; font-size: .8rem; color: #8b8b8b; }
        .store-topnav .nav-link:hover { color: var(--bs-primary); }
        .store-topnav .nav-link.active { color: var(--bs-primary) !important; }
        .store-topnav .btn-contact { background: #700000; border-color: #700000; color: #fff; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; font-size: .8rem; padding: .45rem .9rem; }
        .store-topnav .btn-contact:hover { background: #5a0000; border-color: #5a0000; color: #fff; }

        .store-searchbar { background: linear-gradient(90deg, #5a0000, #700000); }
        .store-searchbar .form-select { border: 0; border-radius: .5rem; height: 44px; }
        .store-searchbar .form-control { border: 0; border-radius: .5rem; height: 44px; }
        .store-searchbar .select2-container .select2-selection--single { height: 44px; border: 0; border-radius: .5rem; }
        .store-searchbar .select2-container .select2-selection--single .select2-selection__rendered { line-height: 44px; }
        .store-searchbar .select2-container .select2-selection--single .select2-selection__arrow { height: 44px; }
        .store-searchbar .search-split > * { flex: 1 1 0; min-width: 0; }
        .store-searchbar .search-split > .store-filter-select { flex: 0 0 42%; }
        .store-searchbar .search-split > .select2-container { flex: 0 0 42% !important; width: auto !important; min-width: 0; }
        .store-searchbar .search-split > .search-form { flex: 0 0 58%; min-width: 0; }
        .store-searchbar .search-form .btn { height: 44px; min-width: 92px; white-space: nowrap; }
        .store-searchbar .autocomplete-wrap { position: relative; min-width: 0; }
        .store-searchbar .autocomplete-menu { position: absolute; top: calc(100% + .25rem); left: 0; right: 0; z-index: 1080; background: #fff; border: 1px solid rgba(0, 0, 0, .12); border-radius: .5rem; box-shadow: 0 .5rem 1rem rgba(0, 0, 0, .15); max-height: 320px; overflow-y: auto; display: none; }
        .store-searchbar .autocomplete-menu.is-visible { display: block; }
        .store-searchbar .autocomplete-item { width: 100%; text-align: left; background: #fff; border: 0; padding: .55rem .75rem; line-height: 1.25; }
        .store-searchbar .autocomplete-item:hover { background: #f8f9fa; }
        .store-searchbar .autocomplete-title { display: block; font-weight: 600; color: #212529; }
        .store-searchbar .autocomplete-ref { display: block; font-size: .82rem; color: #6c757d; }
        .store-searchbar .account { color: #fff; font-size: .9rem; }
        .store-searchbar .account small { display: block; opacity: .85; }
        .store-searchbar a.account,
        .store-searchbar a.account:visited,
        .store-searchbar a.account:hover,
        .store-searchbar a.account:focus {
            color: #fff !important;
            text-decoration: none;
        }

        .store-price-box {
            display: inline-flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-width: 110px;
            padding: .4rem .65rem;
            border-radius: .55rem;
            border: 1px solid #e9dada;
            background: #fff;
            line-height: 1.1;
        }
        .store-price-box .price-amount {
            font-size: 1.2rem;
            font-weight: 800;
            color: #111;
            letter-spacing: .02em;
        }
        .store-price-box .price-currency {
            font-size: .72rem;
            font-weight: 700;
            color: #5d5d5d;
            letter-spacing: .08em;
            text-transform: uppercase;
            margin-left: .2rem;
        }
        .store-price-box .price-note {
            margin-top: .2rem;
            font-size: .78rem;
            color: #6a6a6a;
        }

        .product-main-img { height: 360px; object-fit: cover; background: #f2f2f2; }
        .product-thumb-btn { border: 1px solid #dee2e6; border-radius: .5rem; overflow: hidden; padding: 0; background: #fff; }
        .product-thumb-btn img { width: 72px; height: 72px; object-fit: cover; display: block; background: #f2f2f2; }
        .product-thumb-btn.is-active { border-color: #700000; box-shadow: 0 0 0 .2rem rgba(112, 0, 0, .15); }

        .store-footer {
            margin-top: 2rem;
            color: #fff;
        }
        .store-footer-top {
            position: relative;
            background-image: url('{{ asset('assets/img/background3.jpg') }}');
            background-position: center;
            background-size: cover;
            background-repeat: no-repeat;
            padding: 3rem 0 2.5rem;
        }
        .store-footer-title {
            font-size: 1.35rem;
            font-weight: 700;
            letter-spacing: .02em;
            margin-bottom: 1rem;
        }
        .store-footer-subtitle {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: .9rem;
        }
        .store-footer-links {
            list-style: none;
            margin: 0;
            padding: 0;
        }
        .store-footer-links li { margin-bottom: .5rem; }
        .store-footer-links a {
            color: rgba(255, 255, 255, .9) !important;
            text-decoration: none;
        }
        .store-footer-links a:hover { color: #fff !important; text-decoration: underline; }
        .store-footer-links li::before {
            content: ">";
            display: inline-block;
            margin-right: .55rem;
            color: #fff;
            opacity: .9;
            font-weight: 700;
        }
        .store-footer-contact-item {
            display: flex;
            align-items: flex-start;
            gap: .6rem;
            margin-bottom: .7rem;
        }
        .store-footer-contact-icon {
            width: 1.15rem;
            text-align: center;
            opacity: .9;
        }
        .store-footer-bottom {
            background: #700000;
            padding: .95rem 0;
            border-top: 1px solid rgba(255, 255, 255, .12);
        }
        .store-footer-bottom .copy {
            color: rgba(255, 255, 255, .86);
            font-size: .92rem;
        }
        .store-footer-payments {
            display: flex;
            flex-wrap: wrap;
            justify-content: end;
            gap: .45rem;
        }
        .store-footer-pay-badge {
            background: rgba(255, 255, 255, .14);
            border: 1px solid rgba(255, 255, 255, .22);
            color: #fff;
            border-radius: .35rem;
            padding: .28rem .52rem;
            font-size: .72rem;
            letter-spacing: .04em;
            text-transform: uppercase;
            font-weight: 700;
        }
        @media (max-width: 991.98px) {
            .store-footer-top { padding: 2rem 0 1.6rem; }
            .store-footer-payments { justify-content: start; margin-top: .8rem; }
        }
    </style>
</head>
<body>
<header class="border-bottom">
    <nav class="navbar navbar-expand-lg store-topnav py-3">
        <div class="container-xl align-items-center">
            <a class="navbar-brand fw-semibold d-flex align-items-center gap-2" href="{{ url('/') }}">
                <img src="{{ asset('assets/img/logo.png') }}" alt="{{ config('app.name', 'Loja') }}" style="height: 70px; width:auto;">
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#topNav" aria-controls="topNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="topNav">
                <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2">
                    @foreach (($headerMenuItems ?? []) as $menuItem)
                        <li class="nav-item">
                            @if (!empty($menuItem['is_button']))
                                <a
                                    class="btn btn-contact"
                                    href="{{ $menuItem['href'] ?? '#' }}"
                                    @if(!empty($menuItem['open_in_new_tab'])) target="_blank" rel="noopener" @endif
                                >
                                    {{ $menuItem['label'] ?? '' }}
                                </a>
                            @else
                                <a
                                    class="nav-link @if(!empty($menuItem['is_current'])) active @endif"
                                    href="{{ $menuItem['href'] ?? '#' }}"
                                    @if(!empty($menuItem['open_in_new_tab'])) target="_blank" rel="noopener" @endif
                                >
                                    {{ $menuItem['label'] ?? '' }}
                                </a>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </nav>

    <div class="store-searchbar py-3">
        <div class="container-xl d-flex align-items-center gap-3">
            <div class="search-wrap">
                <div class="search-split d-flex flex-column flex-md-row gap-2">
                    @php($headerCategories = $headerCategories ?? ($categories ?? []))
                    <select class="form-select store-filter-select w-100">
                        <option value="{{ url('/loja/categorias') }}">{{ config('storefront.catalog_provider') === 'tpsoftware' ? 'Procurar por marca' : 'Procurar por categoria' }}</option>
                        @foreach (($headerCategories ?? []) as $cat)
                            <option value="{{ url('/loja/categorias/'.$cat['slug']) }}">{{ $cat['name'] }}</option>
                        @endforeach
                    </select>

                    <form class="search-form d-flex flex-grow-1 gap-2" action="{{ url('/loja/pesquisa') }}" method="get" role="search" data-autocomplete-url="{{ url('/loja/pesquisa/sugestoes') }}">
                        <div class="autocomplete-wrap flex-grow-1">
                            <input class="form-control" type="search" name="q" value="{{ request('q') }}" placeholder="Procurar por referÃƒÂªncia" aria-label="Procurar por referÃƒÂªncia" autocomplete="off">
                            <div class="autocomplete-menu" data-ref-suggestions role="listbox" aria-label="SugestÃƒÂµes de referÃƒÂªncia"></div>
                        </div>
                        <button class="btn btn-light px-3" type="submit" aria-label="Pesquisar referÃƒÂªncia">
                            Pesquisar
                        </button>
                    </form>
                </div>
            </div>

            <div class="ms-auto d-none d-lg-flex align-items-center gap-4">
                <a class="account d-flex align-items-center gap-2 text-decoration-none" href="{{ url('/loja/carrinho') }}">
                    <div style="font-size: 28px; line-height: 1;">&#128722;</div>
                    <div>
                        <small>Carrinho</small>
                        <div class="fw-semibold">{{ (int) ($storeCartCount ?? 0) }} item(ns)</div>
                    </div>
                </a>
                @auth
                    <a class="account d-flex align-items-center gap-2 text-decoration-none" href="{{ url('/loja/conta') }}">
                        <div style="font-size: 28px; line-height: 1;">&#128100;</div>
                        <div>
                            <small>Minha conta</small>
                            <div class="fw-semibold">{{ auth()->user()->name }}</div>
                        </div>
                    </a>
                @else
                    <a class="account d-flex align-items-center gap-2 text-decoration-none" href="{{ url('/loja/conta/login') }}">
                        <div style="font-size: 28px; line-height: 1;">&#128100;</div>
                        <div>
                            <small>Criar Conta</small>
                            <div class="fw-semibold">Conta</div>
                        </div>
                    </a>
                @endauth
            </div>
        </div>
    </div>
</header>

<main class="@if(!request()->is('/')) mt-3 @endif">
    @if (session('success') || $errors->any())
        <div class="container-xl">
            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if ($errors->any())
                <div class="alert alert-danger">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif
        </div>
    @endif
@yield('content')
</main>

<footer class="store-footer">
    <div class="store-footer-top">
        <div class="container-xl">
            <div class="row g-4">
                <div class="col-12 col-lg-3">
                    <div class="store-footer-title">Auto RC Pecas</div>
                    <div class="small text-white-50">Pecas usadas e salvadas para a tua viatura, com envio rapido e apoio dedicado.</div>
                </div>
                <div class="col-12 col-md-4 col-lg-2">
                    <div class="store-footer-subtitle">Sua Conta</div>
                    <ul class="store-footer-links">
                        <li><a href="{{ url('/loja/conta/login') }}">Entrar</a></li>
                        <li><a href="{{ url('/loja/conta/registo') }}">Criar conta</a></li>
                        <li><a href="{{ url('/loja/carrinho') }}">Carrinho</a></li>
                        <li><a href="{{ url('/loja/conta/encomendas') }}">Encomendas</a></li>
                    </ul>
                </div>
                <div class="col-12 col-md-4 col-lg-2">
                    <div class="store-footer-subtitle">Produtos</div>
                    <ul class="store-footer-links">
                        <li><a href="{{ url('/loja') }}">Destaques</a></li>
                        <li><a href="{{ url('/loja/categorias') }}">Marcas</a></li>
                        <li><a href="{{ url('/loja/pesquisa') }}">Pesquisar referencia</a></li>
                    </ul>
                </div>
                <div class="col-12 col-md-4 col-lg-2">
                    <div class="store-footer-subtitle">Empresa</div>
                    <ul class="store-footer-links">
                        <li><a href="{{ url('/sobre-nos') }}">Sobre nos</a></li>
                        <li><a href="{{ url('/contactos') }}">Contactos</a></li>
                        <li><a href="{{ url('/marcas') }}">Todas as marcas</a></li>
                    </ul>
                </div>
                <div class="col-12 col-lg-3">
                    <div class="store-footer-subtitle">Guardar Informacao</div>
                    <div class="store-footer-contact-item">
                        <div class="store-footer-contact-icon">&#128205;</div>
                        <div>Auto RC Pecas<br>Rua Alto do Capitao, 327<br>3880-728 Ovar, Portugal</div>
                    </div>
                    <div class="store-footer-contact-item">
                        <div class="store-footer-contact-icon">&#128222;</div>
                        <div>+351 914 401 299</div>
                    </div>
                    <div class="store-footer-contact-item">
                        <div class="store-footer-contact-icon">&#9993;</div>
                        <div>marketing@autorcpecas.pt</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="store-footer-bottom">
        <div class="container-xl">
            <div class="row align-items-center g-2">
                <div class="col-12 col-lg-7 copy">
                    &copy; {{ date('Y') }} Auto RC Pecas. Plataforma de comercio eletronico.
                </div>
                <div class="col-12 col-lg-5">
                    <div class="store-footer-payments">
                        <span class="store-footer-pay-badge">MB</span>
                        <span class="store-footer-pay-badge">MB Way</span>
                        <span class="store-footer-pay-badge">Visa</span>
                        <span class="store-footer-pay-badge">Mastercard</span>
                        <span class="store-footer-pay-badge">Transferencia</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</footer>

<div class="modal fade" id="consultPriceModal" tabindex="-1" aria-labelledby="consultPriceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="consultPriceModalLabel">Pedido de contacto</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <form method="post" action="{{ old('consult_action', '#') }}" data-consult-form>
                @csrf
                <input type="hidden" name="consult_action" value="{{ old('consult_action', '') }}" data-consult-action-input>
                <input type="hidden" name="product_title" value="{{ old('product_title', '') }}" data-consult-product-title-input>
                <input type="hidden" name="product_reference" value="{{ old('product_reference', '') }}" data-consult-product-ref-input>
                <input type="hidden" name="form_started_at" value="{{ old('form_started_at', now()->timestamp) }}" data-consult-started-at>
                <div style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden;" aria-hidden="true">
                    <label for="consult-website">Website</label>
                    <input type="text" id="consult-website" name="website" value="{{ old('website', '') }}" tabindex="-1" autocomplete="off">
                </div>
                <div class="modal-body">
                    <div class="small text-muted mb-3" data-consult-product-summary>
                        Produto: <strong>{{ old('product_title', 'Produto') }}</strong>
                        @if (old('product_reference'))
                            <div>Ref.: {{ old('product_reference') }}</div>
                        @endif
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Nome</label>
                        <input
                            type="text"
                            name="name"
                            class="form-control @error('name') is-invalid @enderror"
                            value="{{ old('name', auth()->user()->name ?? '') }}"
                            required
                        >
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input
                            type="email"
                            name="email"
                            class="form-control @error('email') is-invalid @enderror"
                            value="{{ old('email', auth()->user()->email ?? '') }}"
                            required
                        >
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Telefone</label>
                        <input
                            type="text"
                            name="phone"
                            class="form-control @error('phone') is-invalid @enderror"
                            value="{{ old('phone', auth()->user()->phone ?? '') }}"
                            required
                        >
                        @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-0">
                        <label class="form-label">Mensagem (opcional)</label>
                        <textarea name="message" class="form-control @error('message') is-invalid @enderror" rows="4" placeholder="Indica se preferes contacto por email ou telefone.">{{ old('message') }}</textarea>
                        @error('message')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Enviar pedido</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    (function () {
        if (typeof window.jQuery === 'undefined') return;
        if (typeof jQuery.fn.select2 === 'undefined') return;

        var $filters = jQuery('select.store-filter-select');
        if ($filters.length) {
            $filters.select2({ width: '100%' });
        }

        jQuery(document).on('change', 'select.store-filter-select', function () {
            var value = jQuery(this).val();
            if (value) window.location.href = String(value);
        });

        var form = document.querySelector('form.search-form[data-autocomplete-url]');
        if (!form) return;

        var input = form.querySelector('input[name="q"]');
        var menu = form.querySelector('[data-ref-suggestions]');
        var endpoint = form.getAttribute('data-autocomplete-url') || '';
        if (!input || !menu || endpoint === '') return;

        var debounceTimer = null;
        var abortController = null;

        function hideMenu() {
            menu.classList.remove('is-visible');
            menu.innerHTML = '';
        }

        function renderMenu(items) {
            if (!Array.isArray(items) || items.length === 0) {
                hideMenu();
                return;
            }

            var html = items.map(function (item) {
                var title = String(item.title || 'Produto')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;');
                var reference = String(item.reference || '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;');
                var url = String(item.url || '');

                return '<button type="button" class="autocomplete-item" data-url="' + url + '">' +
                    '<span class="autocomplete-title">' + title + '</span>' +
                    (reference ? '<span class="autocomplete-ref">Ref: ' + reference + '</span>' : '') +
                    '</button>';
            }).join('');

            menu.innerHTML = html;
            menu.classList.add('is-visible');
        }

        function fetchSuggestions(query) {
            if (abortController) {
                abortController.abort();
            }
            abortController = new AbortController();

            fetch(endpoint + '?q=' + encodeURIComponent(query), {
                headers: { 'Accept': 'application/json' },
                signal: abortController.signal
            })
                .then(function (response) {
                    if (!response.ok) return { items: [] };
                    return response.json();
                })
                .then(function (data) {
                    renderMenu(data.items || []);
                })
                .catch(function () {
                    hideMenu();
                });
        }

        input.addEventListener('input', function () {
            var query = String(input.value || '').trim();

            if (debounceTimer) {
                window.clearTimeout(debounceTimer);
            }

            if (query.length < 3) {
                if (abortController) abortController.abort();
                hideMenu();
                return;
            }

            debounceTimer = window.setTimeout(function () {
                fetchSuggestions(query);
            }, 250);
        });

        menu.addEventListener('click', function (event) {
            var button = event.target.closest('button[data-url]');
            if (!button) return;

            var url = button.getAttribute('data-url');
            if (url) {
                window.location.href = url;
            }
        });

        document.addEventListener('click', function (event) {
            if (!form.contains(event.target)) {
                hideMenu();
            }
        });

        input.addEventListener('focus', function () {
            if (menu.children.length > 0) {
                menu.classList.add('is-visible');
            }
        });
    })();

    (function () {
        var fallbackSvg = "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='600' height='400'><rect width='100%' height='100%' fill='%23f2f2f2'/><text x='50%' y='50%' dominant-baseline='middle' text-anchor='middle' fill='%23666' font-family='Arial' font-size='20'>Sem imagem</text></svg>";
        var selector = 'img[data-tp-src]';

        function markLoaded(img) {
            img.classList.add('is-loaded');
            img.removeAttribute('data-tp-loading');
            var frame = img.closest('.tp-image-frame');
            if (frame) frame.classList.add('is-loaded');
        }

        function preloadInto(img) {
            if (!img || img.getAttribute('data-tp-loading') === '1') return;
            var targetSrc = String(img.getAttribute('data-tp-src') || '').trim();
            if (targetSrc === '') {
                markLoaded(img);
                return;
            }

            img.setAttribute('data-tp-loading', '1');
            var preloader = new Image();
            preloader.onload = function () {
                img.src = targetSrc;
                markLoaded(img);
            };
            preloader.onerror = function () {
                img.src = fallbackSvg;
                markLoaded(img);
            };
            preloader.src = targetSrc;
        }

        function initPreload() {
            var images = Array.prototype.slice.call(document.querySelectorAll(selector));
            if (images.length === 0) return;

            if (!('IntersectionObserver' in window)) {
                images.forEach(preloadInto);
                return;
            }

            var observer = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (!entry.isIntersecting) return;
                    observer.unobserve(entry.target);
                    preloadInto(entry.target);
                });
            }, { rootMargin: '280px 0px' });

            images.forEach(function (img) {
                if (img.getAttribute('data-tp-eager') === '1') {
                    preloadInto(img);
                    return;
                }
                observer.observe(img);
            });
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initPreload);
        } else {
            initPreload();
        }
    })();

    (function () {
        var modalEl = document.getElementById('consultPriceModal');
        if (!modalEl || typeof window.bootstrap === 'undefined') return;

        var form = modalEl.querySelector('form[data-consult-form]');
        var actionInput = modalEl.querySelector('[data-consult-action-input]');
        var titleInput = modalEl.querySelector('[data-consult-product-title-input]');
        var refInput = modalEl.querySelector('[data-consult-product-ref-input]');
        var summary = modalEl.querySelector('[data-consult-product-summary]');
        var fallbackAction = "{{ url('/loja') }}";

        function updateSummary(title, reference) {
            var safeTitle = String(title || 'Produto')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');
            var safeRef = String(reference || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');

            summary.innerHTML = 'Produto: <strong>' + safeTitle + '</strong>' + (safeRef ? '<div>Ref.: ' + safeRef + '</div>' : '');
        }

        document.addEventListener('click', function (event) {
            var trigger = event.target.closest('[data-consult-trigger]');
            if (!trigger) return;

            var action = String(trigger.getAttribute('data-consult-action') || '').trim();
            var title = String(trigger.getAttribute('data-consult-title') || '').trim();
            var reference = String(trigger.getAttribute('data-consult-reference') || '').trim();

            form.setAttribute('action', action || fallbackAction);
            if (actionInput) actionInput.value = action;
            if (titleInput) titleInput.value = title;
            if (refInput) refInput.value = reference;
            updateSummary(title, reference);
        });

        var shouldOpen = {{ $errors->has('name') || $errors->has('email') || $errors->has('phone') || $errors->has('message') ? 'true' : 'false' }};
        if (shouldOpen) {
            var openAction = "{{ old('consult_action', '') }}";
            var openTitle = "{{ old('product_title', '') }}";
            var openReference = "{{ old('product_reference', '') }}";
            if (openAction !== '') {
                form.setAttribute('action', openAction);
            }
            updateSummary(openTitle, openReference);
            var modal = new window.bootstrap.Modal(modalEl);
            modal.show();
        }
    })();
</script>
</body>
</html>
