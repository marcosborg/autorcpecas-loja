<?php

namespace App\Services\Seo;

use App\Models\CmsPage;
use App\Support\ProductUrl;
use App\Services\Catalog\CatalogProvider;
use App\Services\TpSoftware\TpSoftwareIndexStore;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class SitemapGenerator
{
    public function __construct(
        private readonly Filesystem $files,
        private readonly CatalogProvider $catalog,
        private readonly TpSoftwareIndexStore $tpIndexStore,
    ) {
    }

    /**
     * @return array{path:string,total_urls:int,product_urls:int,category_urls:int,cms_urls:int}
     */
    public function generate(int $maxProducts = 50000): array
    {
        $maxProducts = max(100, min(100000, $maxProducts));
        $baseUrl = rtrim((string) config('app.url', ''), '/');
        $now = now()->toAtomString();

        /** @var array<string, array{loc:string,lastmod:string,changefreq:string,priority:string}> $entries */
        $entries = [];
        $categoryUrls = 0;
        $productUrls = 0;
        $cmsUrls = 0;

        $push = function (string $path, string $changefreq = 'daily', string $priority = '0.6', ?string $lastmod = null) use (&$entries, $baseUrl, $now): void {
            $path = '/'.ltrim($path, '/');
            $loc = $baseUrl.$path;
            $entries[$loc] = [
                'loc' => $loc,
                'lastmod' => $lastmod ?: $now,
                'changefreq' => $changefreq,
                'priority' => $priority,
            ];
        };

        $push('/', 'daily', '1.0');
        $push('/loja', 'hourly', '0.95');
        $push('/loja/categorias', 'daily', '0.9');
        $push('/marcas', 'weekly', '0.6');

        $cmsPages = CmsPage::query()
            ->where('is_published', true)
            ->select(['slug', 'updated_at'])
            ->get();

        foreach ($cmsPages as $page) {
            $slug = trim((string) ($page->slug ?? ''));
            if ($slug === '') {
                continue;
            }

            $lastmod = optional($page->updated_at)->toAtomString();
            $push('/pagina/'.rawurlencode($slug), 'weekly', '0.7', $lastmod);
            $cmsUrls++;
        }

        $provider = (string) config('storefront.catalog_provider', 'telepecas');

        if ($provider === 'tpsoftware' && (bool) config('tpsoftware.catalog.index_enabled', true)) {
            $indexed = $this->tpIndexStore->load() ?? [];

            /** @var array<string, true> $cats */
            $cats = [];

            foreach ($indexed as $product) {
                if (! is_array($product)) {
                    continue;
                }

                $make = trim((string) ($product['make_name'] ?? $product['category'] ?? ''));
                if ($make !== '') {
                    $slug = Str::slug($make);
                    if ($slug !== '') {
                        $cats[$slug] = true;
                    }
                }
            }

            foreach (array_keys($cats) as $slug) {
                $push('/loja/categorias/'.rawurlencode($slug), 'daily', '0.8');
                $categoryUrls++;
            }

            $count = 0;
            foreach ($indexed as $product) {
                if (! is_array($product)) {
                    continue;
                }

                if ($count >= $maxProducts) {
                    break;
                }

                $segment = ProductUrl::pathSegment($product);
                if ($segment === '') {
                    continue;
                }

                $push('/loja/produtos/'.rawurlencode($segment), 'daily', '0.7');
                $productUrls++;
                $count++;
            }
        } else {
            $categories = $this->catalog->categories();
            foreach ($categories as $cat) {
                $slug = trim((string) ($cat['slug'] ?? ''));
                if ($slug === '') {
                    continue;
                }

                $push('/loja/categorias/'.rawurlencode($slug), 'daily', '0.8');
                $categoryUrls++;
            }
        }

        $urlset = [];
        $urlset[] = '<?xml version="1.0" encoding="UTF-8"?>';
        $urlset[] = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        foreach ($entries as $entry) {
            $loc = htmlspecialchars($entry['loc'], ENT_XML1);
            $lastmod = htmlspecialchars($entry['lastmod'], ENT_XML1);
            $changefreq = htmlspecialchars($entry['changefreq'], ENT_XML1);
            $priority = htmlspecialchars($entry['priority'], ENT_XML1);

            $urlset[] = '  <url>';
            $urlset[] = "    <loc>{$loc}</loc>";
            $urlset[] = "    <lastmod>{$lastmod}</lastmod>";
            $urlset[] = "    <changefreq>{$changefreq}</changefreq>";
            $urlset[] = "    <priority>{$priority}</priority>";
            $urlset[] = '  </url>';
        }

        $urlset[] = '</urlset>';

        $path = public_path('sitemap.xml');
        $this->files->put($path, implode("\n", $urlset)."\n");

        return [
            'path' => $path,
            'total_urls' => count($entries),
            'product_urls' => $productUrls,
            'category_urls' => $categoryUrls,
            'cms_urls' => $cmsUrls,
        ];
    }
}
