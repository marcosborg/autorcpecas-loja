<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\CustomerAddress;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StoreAddressController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user, 401);

        $addresses = $user->addresses()
            ->orderByDesc('is_default_shipping')
            ->orderByDesc('is_default_billing')
            ->latest('id')
            ->get();

        return view('store.account.addresses.index', compact('addresses'));
    }

    public function create(): View
    {
        return view('store.account.addresses.form', [
            'address' => new CustomerAddress(),
            'action' => url('/loja/conta/moradas'),
            'method' => 'POST',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user, 401);
        $data = $this->validated($request);
        $address = $user->addresses()->create($data);
        $this->applyDefaults($address, $request);

        return redirect(url('/loja/conta/moradas'))->with('success', 'Morada criada.');
    }

    public function edit(Request $request, CustomerAddress $address): View
    {
        $this->authorizeOwned($request, $address);

        return view('store.account.addresses.form', [
            'address' => $address,
            'action' => url('/loja/conta/moradas/'.$address->id),
            'method' => 'PUT',
        ]);
    }

    public function update(Request $request, CustomerAddress $address): RedirectResponse
    {
        $this->authorizeOwned($request, $address);
        $address->update($this->validated($request));
        $this->applyDefaults($address, $request);

        return redirect(url('/loja/conta/moradas'))->with('success', 'Morada atualizada.');
    }

    public function destroy(Request $request, CustomerAddress $address): RedirectResponse
    {
        $this->authorizeOwned($request, $address);
        $address->delete();

        return redirect(url('/loja/conta/moradas'))->with('success', 'Morada removida.');
    }

    private function authorizeOwned(Request $request, CustomerAddress $address): void
    {
        $user = $request->user();
        abort_unless($user, 401);
        abort_if((int) $address->user_id !== (int) $user->id, 404);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:80'],
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'company' => ['nullable', 'string', 'max:180'],
            'vat_number' => ['nullable', 'string', 'max:60'],
            'address_line1' => ['required', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['required', 'string', 'max:30'],
            'city' => ['required', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'country_iso2' => ['required', 'string', 'size:2'],
        ]);

        $data['country_iso2'] = mb_strtoupper((string) $data['country_iso2'], 'UTF-8');

        return $data;
    }

    private function applyDefaults(CustomerAddress $address, Request $request): void
    {
        $shippingDefault = $request->boolean('is_default_shipping');
        $billingDefault = $request->boolean('is_default_billing');

        if ($shippingDefault) {
            CustomerAddress::query()
                ->where('user_id', $address->user_id)
                ->where('id', '!=', $address->id)
                ->update(['is_default_shipping' => false]);
            $address->is_default_shipping = true;
        }

        if ($billingDefault) {
            CustomerAddress::query()
                ->where('user_id', $address->user_id)
                ->where('id', '!=', $address->id)
                ->update(['is_default_billing' => false]);
            $address->is_default_billing = true;
        }

        if ($shippingDefault || $billingDefault) {
            $address->save();
        }
    }
}

