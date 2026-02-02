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

        .store-img { width: 100%; height: 180px; object-fit: cover; background: #f2f2f2; display: block; }
        .store-list-scroll { max-height: 380px; overflow: auto; }
        .select2-container--default .select2-selection--single { height: 38px; border: 1px solid #dee2e6; border-radius: .375rem; }
        .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 36px; padding-left: 12px; }
        .select2-container--default .select2-selection--single .select2-selection__arrow { height: 36px; }
        .select2-container--default .select2-results__option--highlighted.select2-results__option--selectable { background-color: #700000; }

        .store-navbar { background: #fff; }
        .store-navbar .navbar-brand img { height: 52px; width: auto; }
        .store-navbar .nav-link { padding-left: .75rem; padding-right: .75rem; }
        .store-navbar .nav-link.active { font-weight: 600; color: var(--bs-primary) !important; }

        .product-main-img { height: 360px; object-fit: cover; background: #f2f2f2; }
        .product-thumb-btn { border: 1px solid #dee2e6; border-radius: .5rem; overflow: hidden; padding: 0; background: #fff; }
        .product-thumb-btn img { width: 72px; height: 72px; object-fit: cover; display: block; background: #f2f2f2; }
        .product-thumb-btn.is-active { border-color: #700000; box-shadow: 0 0 0 .2rem rgba(112, 0, 0, .15); }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg store-navbar border-bottom shadow-sm mb-3 py-2">
    <div class="container-xl">
        <a class="navbar-brand fw-semibold d-flex align-items-center gap-2" href="{{ url('/loja') }}">
            <img src="{{ asset('assets/img/logo.png') }}" alt="{{ config('app.name', 'Loja') }}">
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#storeNav" aria-controls="storeNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="storeNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link @if(request()->is('loja/categorias*')) active @endif" href="{{ url('/loja/categorias') }}">
                        {{ config('storefront.catalog_provider') === 'tpsoftware' ? 'Marcas' : 'Categorias' }}
                    </a>
                </li>
            </ul>
            <form class="d-flex" action="{{ url('/loja/pesquisa') }}" method="get" role="search">
                <div class="input-group" style="max-width: 420px;">
                    <input class="form-control" type="search" name="q" value="{{ request('q') }}" placeholder="Pesquisar..." aria-label="Pesquisar">
                    <button class="btn btn-primary" type="submit">Pesquisar</button>
                </div>
            </form>
        </div>
    </div>
</nav>

@yield('content')

<footer class="border-top py-4 mt-4">
    <div class="container text-muted small">
        @if (config('storefront.catalog_provider') === 'tpsoftware')
            Dados servidos via API TP Software (sem base de dados local).
        @else
            Dados servidos via API TelePecas (sem base de dados local).
        @endif
    </div>
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
    })();
</script>
</body>
</html>
