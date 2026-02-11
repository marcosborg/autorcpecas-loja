<?php

namespace App\Http\Controllers;

use App\Mail\CmsPageContactMail;
use App\Models\CmsPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

class CmsPageController extends Controller
{
    public function show(string $slug)
    {
        $page = CmsPage::query()
            ->where('slug', $slug)
            ->where('is_published', true)
            ->firstOrFail();

        return view('pages.cms', [
            'page' => $page,
            'title' => $page->title,
        ]);
    }

    public function contact(Request $request, string $slug): RedirectResponse
    {
        $page = CmsPage::query()
            ->where('slug', $slug)
            ->where('is_published', true)
            ->firstOrFail();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['required', 'string', 'max:40'],
            'message' => ['nullable', 'string', 'max:1200'],
            'website' => ['nullable', 'string', 'max:255'],
            'form_started_at' => ['nullable', 'integer'],
        ]);

        if ($request->filled('website')) {
            return back()->withErrors(['cms_contact' => 'Pedido inválido.'])->withInput();
        }

        $formStartedAt = (int) $request->input('form_started_at', 0);
        if ($formStartedAt > 0) {
            $elapsed = now()->timestamp - $formStartedAt;
            if ($elapsed >= 0 && $elapsed < 3) {
                return back()->withErrors(['cms_contact' => 'Pedido inválido.'])->withInput();
            }
        }

        $ip = (string) ($request->ip() ?: 'unknown');
        $email = mb_strtolower(trim((string) $validated['email']));
        $ipKey = 'cms-contact:ip:'.$ip;
        $emailKey = 'cms-contact:email:'.$email;

        if (RateLimiter::tooManyAttempts($ipKey, 8) || RateLimiter::tooManyAttempts($emailKey, 4)) {
            return back()->withErrors(['cms_contact' => 'Demasiados pedidos. Tenta novamente dentro de alguns minutos.'])->withInput();
        }

        RateLimiter::hit($ipKey, 600);
        RateLimiter::hit($emailKey, 600);

        $to = (string) config('storefront.cms_contact_email', 'marketing@autorcpecas.pt');

        Mail::to($to)->send(new CmsPageContactMail([
            'page_title' => (string) $page->title,
            'page_slug' => (string) $page->slug,
            'page_url' => url('/pagina/'.$page->slug),
            'customer_name' => (string) $validated['name'],
            'customer_email' => (string) $validated['email'],
            'customer_phone' => (string) $validated['phone'],
            'customer_message' => (string) ($validated['message'] ?? ''),
        ]));

        return back()->with('success', 'Pedido enviado com sucesso. Vamos entrar em contacto brevemente.');
    }
}
