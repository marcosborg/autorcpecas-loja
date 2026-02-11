<?php

use App\Mail\ConsultPriceLeadMail;
use App\Services\Catalog\CatalogProvider;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

test('submits consult request and sends email with product and customer data', function () {
    config()->set('storefront.consult_email', 'marketing@autorcpecas.pt');
    RateLimiter::clear('consult:ip:127.0.0.1');
    RateLimiter::clear('consult:email:marcos@example.com');

    $this->app->instance(CatalogProvider::class, new class implements CatalogProvider {
        public function categories(): array
        {
            return [];
        }

        public function totalProducts(): int
        {
            return 1;
        }

        public function productsByCategory(string $categorySlug, int $page = 1, int $perPage = 24): array
        {
            return ['categoryName' => 'Test', 'paginator' => new LengthAwarePaginator([], 0, $perPage, $page)];
        }

        public function product(string $idOrReference): ?array
        {
            return [
                'id' => 999,
                'title' => 'Transmissao esquerda',
                'reference' => '3M51-3B437-DAE',
                'tp_reference' => '14642723',
                'make_name' => 'FORD',
                'model_name' => 'FOCUS II',
            ];
        }

        public function search(string $query, int $page = 1, int $perPage = 24): LengthAwarePaginator
        {
            return new LengthAwarePaginator([], 0, $perPage, $page);
        }

        public function randomProducts(int $count = 24): array
        {
            return [];
        }

        public function modelsForMakeSlug(string $makeSlug): array
        {
            return [];
        }

        public function totalProductsBreakdown(): ?array
        {
            return null;
        }
    });

    Mail::fake();

    $response = $this
        ->withoutMiddleware(VerifyCsrfToken::class)
        ->from('/loja/produtos/3M51-3B437-DAE')
        ->post('/loja/produtos/3M51-3B437-DAE/consulta', [
            'name' => 'Marcos Borges',
            'email' => 'marcos@example.com',
            'phone' => '912345678',
            'message' => 'Quero saber disponibilidade e prazo.',
            'website' => '',
            'form_started_at' => now()->subSeconds(5)->timestamp,
        ]);

    $response
        ->assertRedirect('/loja/produtos/3M51-3B437-DAE')
        ->assertSessionHas('success');

    Mail::assertSent(ConsultPriceLeadMail::class, function (ConsultPriceLeadMail $mail): bool {
        return ($mail->payload['product_title'] ?? '') === 'Transmissao esquerda'
            && ($mail->payload['product_reference'] ?? '') === '3M51-3B437-DAE'
            && ($mail->payload['customer_name'] ?? '') === 'Marcos Borges'
            && ($mail->payload['customer_email'] ?? '') === 'marcos@example.com'
            && ($mail->payload['customer_phone'] ?? '') === '912345678';
    });
});

test('rejects consult request when honeypot is filled', function () {
    $this->app->instance(CatalogProvider::class, new class implements CatalogProvider {
        public function categories(): array { return []; }
        public function totalProducts(): int { return 1; }
        public function productsByCategory(string $categorySlug, int $page = 1, int $perPage = 24): array { return ['categoryName' => 'Test', 'paginator' => new LengthAwarePaginator([], 0, $perPage, $page)]; }
        public function product(string $idOrReference): ?array { return ['id' => 1, 'title' => 'Produto X', 'reference' => 'REF-X']; }
        public function search(string $query, int $page = 1, int $perPage = 24): LengthAwarePaginator { return new LengthAwarePaginator([], 0, $perPage, $page); }
        public function randomProducts(int $count = 24): array { return []; }
        public function modelsForMakeSlug(string $makeSlug): array { return []; }
        public function totalProductsBreakdown(): ?array { return null; }
    });

    Mail::fake();

    $response = $this
        ->withoutMiddleware(VerifyCsrfToken::class)
        ->from('/loja/produtos/REF-X')
        ->post('/loja/produtos/REF-X/consulta', [
            'name' => 'Bot User',
            'email' => 'bot@example.com',
            'phone' => '000',
            'website' => 'https://spam.example.com',
            'form_started_at' => now()->subSeconds(5)->timestamp,
        ]);

    $response
        ->assertRedirect('/loja/produtos/REF-X')
        ->assertSessionHasErrors('consult');

    Mail::assertNothingSent();
});

test('rejects consult request when submitted too quickly', function () {
    $this->app->instance(CatalogProvider::class, new class implements CatalogProvider {
        public function categories(): array { return []; }
        public function totalProducts(): int { return 1; }
        public function productsByCategory(string $categorySlug, int $page = 1, int $perPage = 24): array { return ['categoryName' => 'Test', 'paginator' => new LengthAwarePaginator([], 0, $perPage, $page)]; }
        public function product(string $idOrReference): ?array { return ['id' => 1, 'title' => 'Produto X', 'reference' => 'REF-X']; }
        public function search(string $query, int $page = 1, int $perPage = 24): LengthAwarePaginator { return new LengthAwarePaginator([], 0, $perPage, $page); }
        public function randomProducts(int $count = 24): array { return []; }
        public function modelsForMakeSlug(string $makeSlug): array { return []; }
        public function totalProductsBreakdown(): ?array { return null; }
    });

    Mail::fake();

    $response = $this
        ->withoutMiddleware(VerifyCsrfToken::class)
        ->from('/loja/produtos/REF-X')
        ->post('/loja/produtos/REF-X/consulta', [
            'name' => 'Marcos Borges',
            'email' => 'marcos.fast@example.com',
            'phone' => '912345678',
            'website' => '',
            'form_started_at' => now()->timestamp,
        ]);

    $response
        ->assertRedirect('/loja/produtos/REF-X')
        ->assertSessionHasErrors('consult');

    Mail::assertNothingSent();
});
