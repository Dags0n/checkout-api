# API de Checkout (Laravel)

Backend de checkout para uma loja virtual. Recebe pedidos via REST, processa pagamento através de um gateway (simulado por padrão), confirma via webhook com idempotência, e mantém consistência de estoque sob `SELECT FOR UPDATE`.

## 1. Visão geral

- **Stack:** PHP 8.3, Laravel 13.8, PostgreSQL 16 (Docker), Sanctum, PHPUnit 12.
- **Arquitetura:** DDD — `src/<Context>/{Domain,Application,Infrastructure}` com `app/` (Controllers, Requests, Resources) reduzido ao transporte.
- **Versionamento da API:** `/api/v1/*`.
- **Gateway padrão:** `fake` (substituível por `mercadopago` via env var, sem mudar código, porém, a implementação da integração não foi finalizada).
- **Decisões-chave:** estoque só decrementa na confirmação do pagamento, com lock de linha; webhook idempotente em duas camadas (`transaction_id` + status final).

## 2. Arquitetura

```
┌─────────────────────────────────────────────────────────┐
│  HTTP Layer (Controllers + FormRequests + Resources)    │  Transporte, validação
├─────────────────────────────────────────────────────────┤
│  Application Layer (Services + DTOs)                    │  Orquestração
├─────────────────────────────────────────────────────────┤
│  Domain Layer (Eloquent Models + Enums + VOs)           │  Modelo de negócio
├─────────────────────────────────────────────────────────┤
│  Infrastructure Layer (Repositories + Gateways)         │  Persistência, integrações
└─────────────────────────────────────────────────────────┘
```

**Contextos:**

- `src/Catalog/` — Product, estoque.
- `src/Order/` — Order, OrderItem, CheckoutService.
- `src/Payment/` — Payment, gateways (Fake + MercadoPago), webhook processing.
- `src/Shared/` — DomainException, HasUuid trait, ApiExceptionRenderer.

## 3. Fluxo de pagamento

```
Cliente                  App/Backend                Gateway              Webhook
   │                          │                       │                     │
   │  POST /api/v1/checkout   │                       │                     │
   ├─────────────────────────►│                       │                     │
   │                          │  charge()             │                     │
   │                          ├──────────────────────►│                     │
   │                          │  tx_id (Pending)      │                     │
   │                          │◄──────────────────────┤                     │
   │  201 { data, meta }      │                       │                     │
   │◄─────────────────────────┤                       │                     │
   │                          │                       │                     │
   │                          │                       │   POST /webhooks    │
   │                          │                       │◄────────────────────┤
   │                          │  process(tx, status)  │                     │
   │                          │◄──────────────────────┤                     │
   │                          │  [stock decrement     │                     │
   │                          │   sob SELECT FOR      │                     │
   │                          │   UPDATE]             │                     │
   │                          │  200 { processed }    │                     │
   │                          ├──────────────────────►│                     │
```

## 4. Setup (Como rodar)

### Pré-requisitos

- Docker + Docker Compose
- Git

### Subir o ambiente

```bash
git clone https://github.com/Dags0n/checkout-api.git
cd checkout

# Copie e ajuste as variáveis (opcional; defaults funcionam)
cp .env.example .env

# Sobe app + PostgreSQL e roda migrations + seeds automaticamente
# Caso queira rodar os testes com PHPUnit, pode subir também o postgres_test (explicado no ponto 8)
docker compose up -d postgres app

# A aplicação responde em http://localhost:8000
```

### Setup local sem Docker

```bash
composer install
cp .env.example .env # Mude o DB_HOST para seu banco local
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

### Variáveis de ambiente relevantes

```env
APP_KEY=                              # base64:... — gerado por key:generate
PAYMENT_GATEWAY=fake                  # "fake" | "mercadopago"
WEBHOOK_SECRET=local-dev-secret...    # HMAC secret compartilhado com o gateway
WEBHOOK_TOLERANCE=300                 # janela de tolerância de timestamp (segundos)
DB_CONNECTION=pgsql                   # pgsql
```

## 5. Docker

Serviços definidos em `docker-compose.yml`:

| Serviço          | Porta host | Descrição                              |
|------------------|------------|----------------------------------------|
| `app`            | 8000       | PHP 8.3 CLI + `artisan serve`          |
| `postgres`       | 5432       | Banco principal (`checkout`)           |
| `postgres_test`  | 5433       | Banco de testes (`checkout_test`)      |

```bash
docker compose up -d                  # subir tudo
docker compose down -v                # parar e remover volumes
docker compose exec app sh            # shell no container
docker compose logs -f app            # logs do app
```

## 6. Migrations e Seeders

```bash
php artisan migrate                   # aplica migrations
php artisan migrate:fresh --seed      # reset + seed
php artisan db:seed                   # só seed
```

**Tabelas:**

- `users` (com `personal_access_tokens` do Sanctum)
- `products` (UUID, sku, price_cents, stock)
- `orders` (UUID, customer, status, total_cents)
- `order_items` (order_id, product_id, quantity, unit_price_cents, subtotal_cents)
- `payments` (UUID, order_id, gateway, transaction_id UNIQUE, status, amount_cents, card_last4, gateway_metadata JSONB)

O seeder popula 10 produtos de exemplo (roupas/acessórios fitness) e um usuário de teste (`test@example.com` / `password`).

## 7. Scheduler e Command

### `app:simulate-payment-webhook`

Simula o gateway enviando webhooks. Reusa **exatamente** o mesmo `PaymentWebhookService` usado pelo endpoint HTTP — zero duplicação de lógica.

**Regra:** último dígito de `card_last4` par → `approved`, ímpar → `declined`.

```bash
php artisan app:simulate-payment-webhook          # roda 1x
php artisan app:simulate-payment-webhook --limit=50
```

### Agendamento

Registrado em `routes/console.php` para rodar **a cada minuto**:

```php
Schedule::command('app:simulate-payment-webhook')
    ->everyMinute()
    ->withoutOverlapping(10)
    ->runInBackground();
```

### Configurar cron do sistema (servidor de produção)

```cron
* * * * * cd /caminho/do/projeto && php artisan schedule:run >> /dev/null 2>&1
```

Adicione via `crontab -e` no servidor.

### Para executar localmente para testes basta 

```bash
php artisan schedule:work
```

## 8. Testes

```bash
# Sobe o banco de testes
docker compose up -d postgres_test

# Roda tudo
./vendor/bin/phpunit

# Por suite
./vendor/bin/phpunit --testsuite=Unit
./vendor/bin/phpunit --testsuite=Feature

# Teste específico
./vendor/bin/phpunit --filter=CheckoutServiceTest
```

**Suíte cobre:**

| Suite | Arquivo | Foco |
|---|---|---|
| Unit | `Catalog/ProductTest` | Stock has/decrement + OutOfStockException |
| Unit | `Order/OrderTest` | Transições, cálculo, exceptions |
| Unit | `Order/CheckoutServiceTest` | Criação, total, gateway, idempotência |
| Unit | `Payment/PaymentTest` | markPending/Approved/Declined |
| Unit | `Payment/CreditCardTest` | Validação de formato, lastDigit/last4 |
| Unit | `Payment/FakePaymentGatewayTest` | Pending sempre, tx_id fake_*, delay ≥1s |
| Unit | `Payment/EnumsTest` | isFinal, valores |
| Unit | `Payment/PaymentWebhookServiceTest` | Aprovação, recusa, idempotência, lock concorrente |
| Feature | `Api/AuthTest` | (placeholder) |
| Feature | `Api/CheckoutTest` | 201, 401, 422 validação, 422 estoque |
| Feature | `Api/OrderShowTest` | 200, 401, 404 |
| Feature | `Api/WebhookTest` | Assinatura válida/inválida/expirada, idempotência |
| Feature | `Console/SimulatePaymentWebhookCommandTest` | Regra par/ímpar, skip finalizados |

**Total: 72 testes, 155 assertions.**

## 9. Endpoints

Base: `http://localhost:8000/api/v1`

### `POST /api/v1/auth/register` · `POST /api/v1/auth/login`

Autenticação Sanctum (token bearer).

Registro:
```bash
curl -X POST http://localhost:8000/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{"name":"Dagson","email":"dagson@example.com","password":"secret123"}'
```

Login:
```bash
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"dagson@example.com","password":"secret123"}'
```

Response:
```json
{ "data": { "user": {...}, "token": "..." }, "meta": {} }
```

### `POST /api/v1/checkout` 🔒

Cria pedido, chama gateway, retorna com `transaction_id` (status `pending`).

```bash
TOKEN=$(curl -s -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"password"}' | jq -r .data.token)

curl -X POST http://localhost:8000/api/v1/checkout \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "credit_card": {
      "number": "4111111111111111",
      "holder_name": "DAGSON GABRIEL",
      "expiry": "12/27",
      "cvv": "123"
    },
    "items": [{"product_id":"<uuid>","quantity":1}]
  }'
```

Resposta 201:

```json
{
  "data": {
    "order": { "id": "...", "status": "pending", "total_amount": {...}, ... },
    "payment": { "id": "...", "transaction_id": "fake_...", "status": "pending", ... }
  },
  "meta": {}
}
```

### `GET /api/v1/orders/{id}` 🔒

Retorna pedido + items + último payment.

```bash
curl http://localhost:8000/api/v1/orders/<order-uuid> \
  -H "Authorization: Bearer $TOKEN"
```

### `POST /api/v1/webhooks/payment` 🔐 (HMAC)

Endpoint público, autenticado por assinatura.

**Header obrigatório:** `X-Signature: t=<unix_ts>,v1=<hex_hmac_sha256>`

```bash
BODY='{"transaction_id":"fake_abc","status":"approved","gateway":"fake"}'
TS=$(date +%s)
SECRET="local-dev-secret-change-me"
HMAC=$(printf "%s.%s" "$TS" "$BODY" | openssl dgst -sha256 -hmac "$SECRET" | awk '{print $2}')

curl -X POST http://localhost:8000/api/v1/webhooks/payment \
  -H "Content-Type: application/json" \
  -H "X-Signature-Timestamp: $TS" \
  -H "X-Signature: t=$TS,v1=$HMAC" \
  -d "$BODY"
```

### Para disparar via simulação: `php artisan app:simulate-payment-webhook`.

## 10. Gateways de pagamento

### Trocar gateway

```env
PAYMENT_GATEWAY=fake          # default
# ou
PAYMENT_GATEWAY=mercadopago   # não finalizei a implementação
MP_ACCESS_TOKEN=APP_USR-...
```

Nenhuma mudança de código. O `AppServiceProvider` resolve o driver certo.

### Adicionar novo gateway

1. Implementar `Payment\Domain\Contracts\PaymentGatewayContract` e `WebhookSignatureVerifierContract`.
2. Adicionar entrada em `config/payment.php`:

```php
'stripe' => [
    'driver' => \App\Gateways\Stripe\StripePaymentGateway::class,
    'signature_verifier' => \App\Gateways\Stripe\StripeSignatureVerifier::class,
],
```

3. `PAYMENT_GATEWAY=stripe`.

## 11. Decisões Arquiteturais

1. **Eloquent Models como entidades.** Evita mapeamento Model↔Entity. O Domain tem regras (`Order::confirmPayment`, `Product::decrementStock`) sem precisar de DTOs auxiliares.

2. **Estoque decrementa SOMENTE no webhook de aprovação.** Checkout faz só soft check (`hasStock`). Decremento sob `SELECT FOR UPDATE` para evitar oversell em webhooks concorrentes.

3. **Gateway retorna sempre `Pending` + `transaction_id`.** Decisão final (`approved`/`declined`) vem do webhook (real ou simulado).

4. **Idempotência em duas camadas no webhook:**
   - `transaction_id UNIQUE` + check de `Payment::status->isFinal()`.
   - `Order::status->isFinal()` (replay do webhook após order já processada).

5. **Duas transações no checkout:** uma curta para persistir o esqueleto, outra curta para atualizar com a resposta do gateway. Nada de segurar lock durante a chamada HTTP do gateway.

6. **Sanctum para autenticação do cliente.** Token bearer via header `Authorization`. Webhook público, autenticado por HMAC.

7. **`Order → hasMany Payment` + helper `latestPayment()`.** Modela desde já o caso de retries / troca de gateway sem migração disruptiva.

## 12. Critérios atendidos

| Critério | Onde |
|---|---|
| Organização de código e boas práticas | Estrutura `src/<Context>/{Domain,Application,Infrastructure}` + `app/` enxuto |
| Migrations, models e relationships | 4 migrations, Models com `$fillable`, `HasUuid`, casts, relationships |
| Validações nos endpoints | `CheckoutRequest` com regras estritas; `WebhookRequest` para payload |
| Tratamento de erros e respostas consistentes | `ApiExceptionRenderer` + padrão `{ data, meta }` em todos os endpoints |
| Lógica do gateway simulado | `FakePaymentGateway` + `FakeSignatureVerifier` + `SimulatePaymentWebhookCommand` |
| Command + Scheduler | `app:simulate-payment-webhook` + `Schedule::command()->everyMinute()` |
| Idempotência no webhook | UNIQUE constraint + `isFinal()` duplo |
| README claro | Este documento |

## 13. Limitações conhecidas

- **Oversell entre checkout e webhook:** em caso de concorrência muito alta dois pedidos podem ser aceitos no checkout com estoque agregado > disponível. O `SELECT FOR UPDATE` no webhook garante que **no máximo um** será aprovado, mantendo o invariante de estoque. O cliente do pedido perdedor fica com o pedido `pending` e o pagamento `paid` e precisaria de compensação (reembolso).
- **Webhook síncrono:** em produção real, deveria ir para uma fila (`ProcessPaymentWebhook`) com retry/backoff e DLQ.
- **Sem autenticação por cliente no webhook:** é por assinatura HMAC, mas o fluxo assume gateway confiável. Em produção, considerar IP allowlist.
- **Token Sanctum sem expiração configurada:** o padrão é "indefinido até revoke". Adicionar TTL se necessário.
- **Sem rate limiting:** `throttle:checkout` necessário em produção.

## 14. Comandos úteis

```bash
# App
php artisan route:list --path=api
php artisan schedule:list

# DB
php artisan migrate:fresh --seed
php artisan tinker

# Testes
./vendor/bin/phpunit
./vendor/bin/phpunit --testsuite=Unit
./vendor/bin/phpunit --filter=Webhook

# Docker
docker compose up -d
docker compose down -v
docker compose exec app php artisan migrate --seed
```
