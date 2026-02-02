# Relatorio tecnico - TelePecas API (loja sem base de dados local)

## Objetivo

Criar um site proprio (vitrine -> loja) que expoe produtos e categorias a partir da TelePecas, sem persistir catalogo/stock/precos em base de dados local (apenas cache temporaria).

## Fontes oficiais (validar)

```text
Documentacao API: https://api.telepecas.com/docs
Portal developer: https://api.telepecas.com/
Registo developer: https://api.telepecas.com/register
Login developer: https://api.telepecas.com/login
Licenca/termos: https://api.telepecas.com/license
```

## Autenticacao (2 cenarios)

Este projeto suporta dois modelos via `TELEPECAS_AUTH_DRIVER`:

1) `oauth2` (docs oficiais)
- Obter `access_token` em `/auth/token`
- Chamar endpoints com `Authorization: Bearer <token>`
- (Opcional) usar `TELEPECAS_SELLER_TOKEN` como parametro (ex.: `token=...`) quando aplicavel ao modo de integracao

2) `basic_token_body` (manual do modulo)
- Header: `Authorization: Basic {BASIC_AUTH_TOKEN}` (fixo)
- Body JSON: incluir sempre `{"token":"TELEPECAS_PUBLIC_KEY"}`

## Arquitetura recomendada

Frontend -> Backend (Laravel) -> API TelePecas

Regras:
- Nao chamar TelePecas do browser (segredos ficam no backend).
- Cache curta (Redis/DB cache/file) para reduzir chamadas e melhorar performance.
- Fallback: se a API falhar, mostrar erro amigavel e/ou ultima cache quando existir.

## Vitrine no Laravel (sem BD)

Rotas web:

- `/loja/categorias` lista categorias (derivadas do stock)
- `/loja/categorias/{slug}` lista produtos por categoria
- `/loja/produtos/{idOuReferencia}` detalhe
- `/loja/pesquisa?q=...` pesquisa

Como as categorias sao obtidas:
- Sao extraidas do payload do stock via paths configuraveis em `TELEPECAS_CATEGORY_PATHS`
- O indice e categorizacao sao guardados em cache temporaria (sem DB)

## Variaveis de ambiente (exemplo)

```text
TELEPECAS_BASE_URL=https://api.telepecas.com
TELEPECAS_AUTH_DRIVER=basic_token_body

# oauth2
TELEPECAS_CLIENT_ID=
TELEPECAS_CLIENT_SECRET=
TELEPECAS_SELLER_TOKEN=
TELEPECAS_SELLER_TOKEN_PARAM=token

# basic_token_body
TELEPECAS_BASIC_AUTH_TOKEN=
TELEPECAS_PUBLIC_KEY=

# cache / resiliencia
TELEPECAS_CACHE_ENABLED=true
TELEPECAS_CACHE_TTL_SECONDS=600
TELEPECAS_TIMEOUT_SECONDS=15
TELEPECAS_RETRIES=1
TELEPECAS_RETRY_SLEEP_MS=250

# vitrine
TELEPECAS_CATALOG_PAGE_SIZE=100
TELEPECAS_CATALOG_MAX_ITEMS=2000
TELEPECAS_CATEGORY_PATHS=partInfo.partGroup,partInfo.group,category,partInfo.partLocal
```

