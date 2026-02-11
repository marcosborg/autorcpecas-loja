@extends('store.layout', ['title' => $page->title])

@section('content')
    @php($featured = $page->featured_image_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($page->featured_image_path) : null)

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
            </div>
        </article>
    </div>
@endsection
