# Waflow API v1

Base URL: `https://your-waflow-host/api/v1`

Responses are JSON. Except for registration, plans, health and the private
OpenWA callback, endpoints require a bearer API key:

```http
Authorization: Bearer waflow_...
Accept: application/json
```

The key is returned only when created. A key can have `*` or granular abilities
such as `instances:write`, `messages:write`, `groups:read` and `webhooks:write`.
Requests made before e-mail verification receive HTTP 403. Expired or inactive
subscriptions receive HTTP 402. Quota violations receive HTTP 429.

## Registration

```http
POST /api/v1/auth/register
Content-Type: application/json

{
  "name": "Portal Exemplo",
  "email": "integracao@portal.com.br",
  "password": "a-strong-password",
  "password_confirmation": "a-strong-password",
  "workspace_name": "Portal Exemplo",
  "site_url": "https://portal.com.br",
  "portal_name": "Portal principal",
  "device_fingerprint": "stable-random-installation-id"
}
```

HTTP 201 returns `user`, `workspace`, `api_key`, `trial_ends_at` and, when a
portal is supplied, its one-time `portal_verification_token`. The trial starts
at registration and lasts 7 days by default. Resend the verification message
with `POST /auth/email/verification-notification` using the API key.

## Instance Lifecycle

```http
POST /instances
{"name":"Notícias"}

POST /instances/{instance_uuid}/connect
GET  /instances/{instance_uuid}/qr
GET  /instances/{instance_uuid}/status
POST /instances/{instance_uuid}/disconnect
DELETE /instances/{instance_uuid}
```

Creating an instance provisions a private OpenWA session. `connect` starts
Baileys and returns a `qr_code` when authentication is pending. Poll `status` or
consume `session.*` webhooks until the state is `connected`.

Connected instances expose:

```http
GET /instances/{instance_uuid}/groups
GET /instances/{instance_uuid}/channels
GET /instances/{instance_uuid}/remote-contacts
```

## Messages

Messages are accepted with HTTP 202 and delivered asynchronously by the Laravel
queue. Always send a stable idempotency key for retryable requests.

```http
POST /messages
Authorization: Bearer waflow_...
Idempotency-Key: wordpress-post-987-group-120363...
Content-Type: application/json

{
  "instance_uuid": "d91f1c74-59ca-4fde-a13c-bf25a27bb863",
  "to": "120363000000000000@g.us",
  "type": "image",
  "url": "https://portal.com.br/uploads/noticia.jpg",
  "caption": "Título da notícia\n\nhttps://portal.com.br/noticia"
}
```

Supported `type` values are `text`, `image`, `video`, `audio`, `document`,
`sticker`, `location`, `contact` and `poll`. Media accepts a public `url` or
`base64`. Recipients may be phone numbers or explicit WhatsApp chat IDs such as
`@g.us` groups and channel IDs returned by the channel endpoint.

```http
GET  /messages?status=sent&direction=outbound&per_page=25
GET  /messages/{message_uuid}
POST /messages/bulk

{"messages":[{...},{...}]}
```

Bulk requests accept at most 100 messages. A repeated idempotency key returns
the original message with `idempotent_replay: true`.

## Portals

```http
GET    /portals
POST   /portals
GET    /portals/{portal_uuid}
PATCH  /portals/{portal_uuid}
POST   /portals/{portal_uuid}/verify
DELETE /portals/{portal_uuid}
```

Create with `{"name":"Portal","url":"https://portal.com.br"}`. The response
contains a one-time verification token; submit it to `/verify`. A portal domain
can belong to only one Waflow workspace.

## Customer Webhooks

```http
POST /webhooks

{
  "name": "WordPress",
  "url": "https://portal.com.br/wp-json/waflow/v1/events",
  "events": ["message.sent", "message.failed", "session.status"]
}
```

The response contains a one-time `signing_secret`. Other operations are:

```http
GET    /webhooks
GET    /webhooks/{webhook_uuid}
PATCH  /webhooks/{webhook_uuid}
POST   /webhooks/{webhook_uuid}/test
DELETE /webhooks/{webhook_uuid}
```

Supported events are `message.received`, `message.sent`, `message.ack`,
`message.failed`, `message.revoked`, `message.reaction`, `message.edited`,
`session.status`, `session.qr`, `session.authenticated` and
`session.disconnected`; `*` subscribes to all.

Deliveries include:

```http
X-Waflow-Event: message.sent
X-Waflow-Delivery: 7fd...
X-Waflow-Signature: sha256=<hex-hmac>
```

Verify the HMAC-SHA256 against the exact raw request body and reject replayed
delivery IDs. Redirects are disabled. Failed deliveries retry with backoff and
are recorded in `webhook_deliveries`.

## Other Resources

```text
GET/PATCH                  /workspace
GET                        /billing/usage
GET/POST/GET/PATCH/DELETE  /contacts
GET/POST/GET/PATCH/DELETE  /flows
GET/POST/DELETE            /api-keys
GET                        /plans
GET                        /health
```

All model lookups are workspace-scoped. API requests receive an `X-Request-Id`
and are written to the request audit table without request bodies or secrets.
