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
                    @php($logo = $brand->logo_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($brand->logo_path) : null)
                    <div class="col">
                        <div class="card h-100">
                            <div class="card-body d-flex align-items-center justify-content-center" style="min-height: 110px;">
                                @if ($logo)
                                    @if (!empty($brand->url))
                                        <a href="{{ $brand->url }}" target="_blank" rel="noopener">
                                            <img src="{{ $logo }}" alt="{{ $brand->name }}" style="height:52px;width:auto;max-width:160px;object-fit:contain;" loading="lazy" decoding="async">
                                        </a>
                                    @else
                                        <img src="{{ $logo }}" alt="{{ $brand->name }}" style="height:52px;width:auto;max-width:160px;object-fit:contain;" loading="lazy" decoding="async">
                                    @endif
                                @else
                                    <div class="text-muted small">{{ $brand->name }}</div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endsection

