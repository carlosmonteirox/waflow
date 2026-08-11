# Waflow

Waflow is a multi-tenant WhatsApp messaging SaaS. Laravel owns the public API,
accounts, trials, quotas, audit records, queues and customer webhooks. OpenWA is
the private WhatsApp runtime and is configured exclusively with the Baileys
engine in the Waflow stack.

```text
Internet -> Nginx -> Laravel API -> OpenWA/Baileys
                        |              |
                        +-> Redis      +-> encrypted auth state
                        +-------------> PostgreSQL
```

The OpenWA port is never published. WhatsApp credentials and Signal keys are
encrypted with AES-256-GCM and persisted in PostgreSQL; the Baileys adapter does
not use session files. The OpenWA volume remains for media, plugins and its
internal administration database.

## Start

Requirements: Docker Engine, Docker Compose v2 and OpenSSL.

```bash
./infra/scripts/init-env.sh
docker compose --env-file infra/.env -f infra/compose.yml up -d --build
curl http://localhost:8080/api/v1/health
```

The panel and API are available at `http://localhost:8080`. The initializer is
idempotent and adds newly required secrets to an existing `infra/.env` without
replacing configured values.

Use a real SMTP provider before production so account verification messages are
delivered. With the local `MAIL_MAILER=log` setting, links are written to the
Laravel container logs.

```bash
docker compose --env-file infra/.env -f infra/compose.yml logs -f laravel
docker compose --env-file infra/.env -f infra/compose.yml down
```

Persistent state lives in named volumes. Do not use `down -v` unless PostgreSQL,
Redis, media and Laravel storage may be deleted. Keep
`BAILEYS_AUTH_ENCRYPTION_KEY` stable and backed up; changing it makes existing
WhatsApp credentials unreadable.

## SaaS Rules

- New accounts receive one 7-day trial after e-mail verification.
- Trial defaults: 1 instance, 500 outbound messages, 2 webhooks, 1 API key and
  1 portal.
- Disposable e-mail domains are blocked. E-mail, organization domain, network,
  device fingerprint, portal domain and connected WhatsApp number are retained
  as hashed anti-abuse signals where applicable.
- API keys are stored only as SHA-256 hashes and shown in plaintext once.
- Outbound messages are queued and support an `Idempotency-Key` header.
- Customer webhooks are HMAC signed, retried and recorded.
- Panel logins use a single-use six-digit e-mail code that expires after five
  minutes.
- Team access uses expiring invitations and owner/admin/support/developer/member
  roles; API keys can be restricted to granular team abilities.
- Automation flows run on the Redis queue, persist every execution and dedupe
  repeated source events.
- Billing checkout, invoice, payment and renewal records are provider-neutral.
  Credit, debit and Pix are prepared; `BILLING_DRIVER=pending` remains active
  until the commercial gateway is selected.

Anti-abuse controls reduce repeated trials but cannot prove that two identities
belong to the same person. Production should combine them with e-mail reputation,
CAPTCHA/risk scoring and payment-method verification as usage grows.

## Repository

```text
infra/    deployment, Nginx, containers and environment initialization
laravel/  public SaaS API, panel, jobs and business rules
openwa/   private OpenWA/Baileys runtime
sample/   original interface reference assets
```

The public contract and examples are documented in [`docs/API.md`](docs/API.md).
The machine-readable OpenAPI 3.1 contract is available in
[`docs/openapi.yaml`](docs/openapi.yaml) and at `GET /api/openapi.yaml`.

## Live end-to-end test

The opt-in live test validates Laravel, PostgreSQL, Redis, the queue worker,
OpenWA and a connected WhatsApp number. It sends one real message while issuing
eight concurrent API calls with the same idempotency key.

```bash
WAFLOW_E2E_API_KEY='waflow_...' \
WAFLOW_E2E_INSTANCE_UUID='00000000-0000-0000-0000-000000000000' \
WAFLOW_E2E_TO='5562999999999' \
make e2e-live
```

The stack must already be running. The script deliberately does not create an
account or choose a recipient automatically.

## Important

Baileys communicates with WhatsApp through an unofficial protocol. It is not the
Meta WhatsApp Business Cloud API and carries operational risks including account
logout, protocol changes and number restrictions. Use dedicated numbers, rate
limits and gradual traffic ramp-up; never present the integration as officially
supported by Meta.
