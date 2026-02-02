<?php

return [
    /*
     * Fonte de catalogo / stock para a vitrine.
     *
     * - telepecas: API TelePeças (implementacao atual)
     * - tpsoftware: API TP Software (api.tp.software)
     */
    'catalog_provider' => env('STOREFRONT_CATALOG_PROVIDER', 'telepecas'),
];
