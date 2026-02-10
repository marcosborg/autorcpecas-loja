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

        a { color: #700000; }
        a:hover { color: #5a0000; }
        a:visited { color: #700000; }
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
                    <li class="nav-item">
                        <a class="nav-link @if(request()->is('/')) active @endif" href="{{ url('/') }}">Início</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link @if(request()->is('sobre-nos')) active @endif" href="{{ url('/sobre-nos') }}">Sobre nós</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link @if(request()->is('marcas')) active @endif" href="{{ url('/marcas') }}">Todas as marcas</a>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-contact" href="{{ url('/contactos') }}">Contactos</a>
                    </li>
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
                            <input class="form-control" type="search" name="q" value="{{ request('q') }}" placeholder="Procurar por referência" aria-label="Procurar por referência" autocomplete="off">
                            <div class="autocomplete-menu" data-ref-suggestions role="listbox" aria-label="Sugestões de referência"></div>
                        </div>
                        <button class="btn btn-light px-3" type="submit" aria-label="Pesquisar referência">
                            Pesquisar
                        </button>
                    </form>
                </div>
            </div>

            <div class="ms-auto d-none d-lg-flex align-items-center gap-4">
                <div class="account d-flex align-items-center gap-2">
                    <div style="font-size: 28px; line-height: 1;">&#128100;</div>
                    <div>
                        <small>Criar Conta</small>
                        <div class="fw-semibold">Conta</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>

@yield('content')

<footer class="border-top py-4 mt-4">
    <div class="container text-muted small"></div>
</footer>

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
</script>
</body>
</html>
