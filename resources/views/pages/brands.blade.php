@extends('store.layout', ['title' => 'Marcas'])

@section('content')
    <div class="container-xl py-4">
        <div class="d-flex align-items-end justify-content-between gap-2 mb-3">
            <h2 class="mb-0">Todas as marcas</h2>
            <a class="link-primary text-decoration-none" href="{{ url('/loja') }}">Ver loja</a>
        </div>

        @if (($brands?->count() ?? 0) === 0)
            <div class="alert alert-secondary mb-0">Sem marcas para mostrar.</div>
        @else
            <div class="row row-cols-2 row-cols-md-4 row-cols-xl-6 g-3">
                @foreach ($brands as $brand)
                    @php
                        $logo = null;
                        if (!empty($brand->logo_path)) {
                            if (\Illuminate\Support\Str::startsWith($brand->logo_path, ['http://', 'https://'])) {
                                $logo = $brand->logo_path;
                            } else {
                                $logo = \Illuminate\Support\Facades\Storage::disk('public')->url($brand->logo_path);
                            }
                        }
                    @endphp
                    <div class="col">
                        <div class="card h-100">
                            <div class="card-body d-flex flex-column align-items-center justify-content-center text-center gap-2" style="min-height: 110px;">
                                @if (!empty($brand->url))
                                    <a href="{{ $brand->url }}" target="_blank" rel="noopener" class="text-decoration-none d-flex flex-column align-items-center gap-2">
                                        @if ($logo)
                                            <img
                                                src="{{ $logo }}"
                                                alt="{{ $brand->name }}"
                                                style="height:52px;width:auto;max-width:160px;object-fit:contain;"
                                                loading="lazy"
                                                decoding="async"
                                                onerror="this.style.display='none'; if(this.nextElementSibling){ this.nextElementSibling.classList.remove('d-none'); }"
                                            >
                                            <span class="badge rounded-pill text-bg-light d-none">{{ $brand->name }}</span>
                                        @else
                                            <span class="text-dark small fw-semibold">{{ $brand->name }}</span>
                                        @endif
                                        @if ($logo)
                                            <span class="text-dark small fw-semibold">{{ $brand->name }}</span>
                                        @endif
                                    </a>
                                @else
                                    @if ($logo)
                                        <img
                                            src="{{ $logo }}"
                                            alt="{{ $brand->name }}"
                                            style="height:52px;width:auto;max-width:160px;object-fit:contain;"
                                            loading="lazy"
                                            decoding="async"
                                            onerror="this.style.display='none'; if(this.nextElementSibling){ this.nextElementSibling.classList.remove('d-none'); }"
                                        >
                                        <span class="badge rounded-pill text-bg-light d-none">{{ $brand->name }}</span>
                                    @else
                                        <span class="text-dark small fw-semibold">{{ $brand->name }}</span>
                                    @endif
                                    @if ($logo)
                                        <span class="text-dark small fw-semibold">{{ $brand->name }}</span>
                                    @endif
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endsection
