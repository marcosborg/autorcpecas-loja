@extends('store.layout', ['title' => $page->title])

@section('content')
    @php($featured = $page->featured_image_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($page->featured_image_path) : null)
    @php($mapRaw = trim((string) ($page->google_maps_embed_url ?? '')))
    @php($mapUrl = $mapRaw)
    @if ($mapRaw !== '' && preg_match('/<iframe[^>]*src=[\"\']([^\"\']+)[\"\']/i', $mapRaw, $mapMatch))
        @php($mapUrl = trim((string) ($mapMatch[1] ?? '')))
    @endif
    @php($showContactButton = (bool) ($page->show_contact_button ?? false))
    @php($contactLabel = trim((string) ($page->contact_button_label ?? 'Falar connosco')))
    @if ($contactLabel === '')
        @php($contactLabel = 'Falar connosco')
    @endif

    <div class="container-xl py-4">
        <article class="card border-0 shadow-sm">
            @if ($featured)
                <img src="{{ $featured }}" alt="{{ $page->title }}" class="w-100" style="max-height: 420px; object-fit: cover;">
            @endif
            <div class="card-body p-4 p-lg-5">
                <h1 class="h3 mb-3">{{ $page->title }}</h1>
                <div class="cms-page-content">
                    {!! $page->content !!}
                </div>

                @if ($showContactButton)
                    <div class="mt-4">
                        <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#cmsContactModal">
                            {{ $contactLabel }}
                        </button>
                    </div>
                @endif
            </div>
        </article>

        @if ($mapUrl !== '')
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-body p-0">
                    <iframe
                        src="{{ $mapUrl }}"
                        title="Mapa"
                        width="100%"
                        height="420"
                        style="border:0; display:block;"
                        loading="lazy"
                        referrerpolicy="no-referrer-when-downgrade"
                        allowfullscreen
                    ></iframe>
                </div>
            </div>
        @endif
    </div>

    @if ($showContactButton)
        <div class="modal fade" id="cmsContactModal" tabindex="-1" aria-labelledby="cmsContactModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="cmsContactModalLabel">Pedido de contacto</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                    </div>
                    <form method="post" action="{{ route('cms.page.contact', ['slug' => $page->slug]) }}">
                        @csrf
                        <input type="hidden" name="form_started_at" value="{{ old('form_started_at', now()->timestamp) }}">
                        <div style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden;" aria-hidden="true">
                            <label for="cms-website">Website</label>
                            <input type="text" id="cms-website" name="website" value="{{ old('website', '') }}" tabindex="-1" autocomplete="off">
                        </div>
                        <div class="modal-body">
                            <div class="small text-muted mb-3">Pagina: <strong>{{ $page->title }}</strong></div>

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
                                <textarea name="message" class="form-control @error('message') is-invalid @enderror" rows="4">{{ old('message') }}</textarea>
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

        <script>
            (function () {
                if (typeof window.bootstrap === 'undefined') return;
                var shouldOpen = {{ $errors->has('cms_contact') || $errors->has('name') || $errors->has('email') || $errors->has('phone') || $errors->has('message') ? 'true' : 'false' }};
                if (!shouldOpen) return;
                var modalEl = document.getElementById('cmsContactModal');
                if (!modalEl) return;
                var modal = new window.bootstrap.Modal(modalEl);
                modal.show();
            })();
        </script>
    @endif
@endsection
