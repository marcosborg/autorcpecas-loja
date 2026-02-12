<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\CustomerAddress;
use App\Services\Tax\VatValidationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StoreAddressController extends Controller
{
    public function __construct(
        private readonly VatValidationService $vatValidation,
    ) {
    }

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
            'countries' => $this->countriesForForm(),
            'defaultCountryIso2' => 'PT',
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
            'countries' => $this->countriesForForm(),
            'defaultCountryIso2' => 'PT',
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
        $countryIso2List = $this->countriesForValidation();

        $data = $request->validate([
            'label' => ['required', 'string', 'max:80'],
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'phone_country_code' => ['required', 'regex:/^\+\d{1,4}$/'],
            'phone' => ['required', 'regex:/^\d{6,15}$/'],
            'company' => ['nullable', 'string', 'max:180'],
            'vat_number' => ['nullable', 'string', 'max:60'],
            'address_line1' => ['required', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['required', 'string', 'max:30'],
            'city' => ['required', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'country_iso2' => ['required', 'string', 'size:2', Rule::in($countryIso2List)],
            'zone_code' => ['nullable', 'string', Rule::in(['PT_MAINLAND', 'PT_ISLANDS'])],
        ]);

        $data['country_iso2'] = mb_strtoupper((string) $data['country_iso2'], 'UTF-8');
        $data['phone_country_code'] = trim((string) $data['phone_country_code']);
        $data['phone'] = preg_replace('/\D+/', '', (string) $data['phone']) ?? '';
        $data['postal_code'] = trim((string) $data['postal_code']);

        if ($data['country_iso2'] === 'PT') {
            if (! preg_match('/^\d{4}-\d{3}$/', (string) $data['postal_code'])) {
                throw ValidationException::withMessages([
                    'postal_code' => 'Codigo postal invalido. Em Portugal deve usar o formato 0000-000.',
                ]);
            }
            $zoneCode = trim((string) ($data['zone_code'] ?? ''));
            $data['zone_code'] = $zoneCode !== '' ? $zoneCode : $this->inferPtZoneByPostalCode((string) $data['postal_code']);
        } else {
            $data['zone_code'] = null;
        }

        $vatInput = (string) ($data['vat_number'] ?? '');
        $vatResult = $this->vatValidation->validate((string) $data['country_iso2'], $vatInput);
        if (trim($vatInput) !== '' && $vatResult['is_valid'] === false) {
            throw ValidationException::withMessages([
                'vat_number' => $vatResult['error'] ?: 'NIF/VAT invalido.',
            ]);
        }
        $data['vat_number'] = $vatResult['vat_number'] !== '' ? $vatResult['vat_number'] : null;
        $data['vat_country_iso2'] = $vatResult['vat_country_iso2'];
        $data['vat_is_valid'] = $vatResult['is_valid'];
        $data['vat_validated_at'] = $vatResult['checked'] ? now() : null;

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

    /**
     * @return array<int, array{iso2: string, name: string, phone_code: string}>
     */
    private function countriesForForm(): array
    {
        return Country::query()
            ->where('active', true)
            ->orderBy('position')
            ->orderBy('name')
            ->get(['iso2', 'name', 'phone_code'])
            ->map(fn (Country $country): array => [
                'iso2' => (string) $country->iso2,
                'name' => (string) $country->name,
                'phone_code' => (string) $country->phone_code,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function countriesForValidation(): array
    {
        $countries = Country::query()
            ->where('active', true)
            ->pluck('iso2')
            ->map(fn ($iso): string => mb_strtoupper((string) $iso, 'UTF-8'))
            ->unique()
            ->values()
            ->all();

        if (count($countries) === 0) {
            return ['PT'];
        }

        return $countries;
    }

    private function inferPtZoneByPostalCode(string $postalCode): string
    {
        $prefix = (int) substr($postalCode, 0, 2);
        if ($prefix >= 90 && $prefix <= 99) {
            return 'PT_ISLANDS';
        }

        return 'PT_MAINLAND';
    }
}
