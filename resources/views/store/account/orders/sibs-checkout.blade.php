@extends('store.layout', ['title' => 'Pagamento SIBS - '.$order->order_number])

@section('content')
    <div class="container-xl">
        <div class="row g-4">
            <div class="col-12 col-lg-3">
                @include('store.account._nav')
            </div>
            <div class="col-12 col-lg-9">
                <div class="card">
                    <div class="card-header fw-semibold">Pagamento SIBS - {{ $order->order_number }}</div>
                    <div class="card-body">
                        @if (($widget['payment_method'] ?? '') === 'MBWAY')
                            <p class="mb-3">Mantem o telemovel por perto para aprovar o pagamento MB WAY.</p>
                        @endif

                        <input type="submit" value="Submit" style="display:none" />
                        <form class="paymentSPG"
                            spg-context="{{ $widget['form_context'] ?? '' }}"
                            spg-config='@json($widget["form_config"] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)'
                            spg-signature="{{ $widget['signature'] ?? '' }}"
                            spg-style="{}">
                        </form>

                        @if (!empty($widget['widget_url']))
                            <script src="{{ $widget['widget_url'] }}"></script>
                        @else
                            <div class="alert alert-warning mb-0">Nao foi possivel preparar o widget de pagamento.</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
