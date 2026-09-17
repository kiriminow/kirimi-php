# Kirimi SDK — Public API Alignment Spec

Source of truth: `kirimi-mono-v2` (`apps/web/src/pages/ApiDocsPage.tsx`, `apps/web/src/pages/ApiTestPage.tsx`,
`apps/api/src/routes/api.routes.ts`) and the controllers under `apps/api/src/controllers/api/`.
Verified against controller source, not just docs — where docs and server disagree, **server wins** and the
disagreement is noted.

- Base URL: `https://api.kirimi.id`
- All endpoints are `POST` with JSON body (exception: `/v1/send-message-file` = multipart).
- Auth travels **in the body**: `user_code` + `secret` on every request. Not a header.
- Envelope: `{ "success": boolean, "data": object|null, "message": string }`
- Phone format: country code, no `+`. `6281234567890`.

## Status

All ten SDKs implement this contract. This file is kept in sync across every SDK repo.

| SDK | How to test | Result |
|---|---|---|
| `kirimi-ts` | `node --test tests/` + `tsc --noEmit` | 39 pass |
| `kirimi-node` | `node --test test/` | 50 pass |
| `kirimi-php` | `vendor/bin/phpunit --no-coverage` | 56 pass |
| `kirimi-python` | `.venv/bin/python -m pytest -q` | 83 pass |
| `kirimi-go` | `go test ./...` | ok |
| `kirimi-laravel` | `composer test` | 29 pass |
| `kirimi-ruby` | `bundle exec rake` | 56 pass |
| `kirimi-flutter` | `dart test` | 44 pass |
| `kirimi-wordpress` | `vendor/bin/phpunit` | 44 pass |
| `n8n-nodes-kirimi` | `npm run build` + `node validate.js` | ok |

## Alias policy

The server accepts several historical aliases. SDKs must send the **canonical** field and may accept the
alias as an input convenience, resolving it before the request.

| Endpoint | Canonical | Accepted alias (server) |
|---|---|---|
| `/v1/send-message`, `/v1/send-message-fast`, `/v1/send-message-file` | `receiver` | `phone` |
| `/v1/waba/send-message`, `/v1/waba/messages/reply`, `/v1/waba/send-otp`, `/v1/waba/verify-otp` | `to` | `phone`, `receiver` |
| `/v1/generate-otp` | `otp_length` / `otp_type` | `otpLength` / `otpType` |

Fields with **no** alias (server rejects anything else):

| Endpoint | Field |
|---|---|
| `/v1/broadcast-message` | `numbers` (array). `phones` is **not** accepted. |
| `/v1/save-contact` | `nama` + `nomor` (required). `name`/`phone` are **not** accepted. |
| `/v1/save-contacts-bulk` | `contacts: [{ nama, nomor }]` |

> These three were the top real bugs across the SDKs: most of them sent `phones`, `name`, `phone`.

## Response envelope

```json
{ "success": true, "data": {}, "message": "OK" }
```

HTTP codes: `400` invalid params · `401` wrong secret · `402` insufficient balance (`/v2/otp/send` whatsapp) ·
`403` feature not in package / subscription inactive · `404` not found · `429` rate limited · `500` server ·
`502` number undeliverable · `503` provider outage.

WABA: success means Meta **accepted** (`delivery_status: "accepted"`), not delivered. Final state arrives via
webhook as `message.sent` / `message.ack` / `message.failed`.

## Endpoint catalogue (30 public)

### Auth & Device

| Method + Path | Params |
|---|---|
| `POST /v1/user-info` | `user_code`, `secret` |
| `POST /v1/create-device` | `user_code`, `secret`, `package_id` (req), `voucher_code` |
| `POST /v1/connect-device` | `user_code`, `secret`, `device_id` |
| `POST /v1/renew-device` | `user_code`, `secret`, `device_id`, `package_id`, `voucher_code` |
| `POST /v1/device-status` | `user_code`, `secret`, `device_id` |
| `POST /v1/device-status-enhanced` | `user_code`, `secret`, `device_id` |
| `POST /v1/list-devices` | `user_code`, `secret`, `page`=1, `limit`=10 |

### WhatsApp Unofficial (auto-falls back to WABA for single text messages)

| Method + Path | Params |
|---|---|
| `POST /v1/send-message` | `user_code`, `secret`, `device_id`, `receiver`, `message`, `media_url`, `fileName`, `enableTypingEffect`=true, `typingSpeedMs`=350 (100–800), `quotedMessageId` |
| `POST /v1/send-message-fast` | same minus typing-effect params |
| `POST /v1/send-message-file` (multipart, max 50 MB) | `user_code`, `secret`, `device_id`, `receiver`, `file`, `message`, `caption`, `fileName`, `quotedMessageId` |
| `POST /v1/broadcast-message` | `user_code`, `secret`, `device_id`, `label` (req, max 100), `numbers` (array, req, max 1000), `message` (req), `delay`=30 (clamped 30–3600), `delayMin`, `delayMax`, `media_url`, `fileName`, `started_at` (ISO), `enableTypingEffect`, `typingSpeedMs` |

### WABA (Cloud API) — uses `waba_id`, never `device_id`

| Method + Path | Params |
|---|---|
| `POST /v1/waba/send-message` | `user_code`, `secret`, `waba_id` (req), `to` (req), `template_name` (req), `variables` (string[]), `header` (object), `buttons` |
| `POST /v1/waba/messages/reply` | `user_code`, `secret`, `waba_id`, `to`, `message` (object, `type` ∈ text/image/document/audio/video/interactive) |
| `POST /v1/waba/conversations` | `user_code`, `secret`, `limit`=50 (1–200), `page`=1 → returns `{items[], page, limit, total}` |
| `POST /v1/waba/templates/sync` | `user_code`, `secret`, `waba_id` |
| `POST /v1/waba/send-otp` | `user_code`, `secret`, `waba_id`, `to`, `template_name` |
| `POST /v1/waba/verify-otp` | `user_code`, `secret`, `waba_id`, `to`, `otp_code` (4–8 digits) |

`message` shapes for reply:
- `{ "type": "text", "text": "..." }`
- `{ "type": "image", "media_url": "...", "caption": "..." }` (also document/audio/video; `filename` for document)
- `{ "type": "interactive", "interactive": { ...Meta interactive object... } }`

### OTP v2

`POST /v2/otp/send` — `method` ∈ `whatsapp` (alias `waba`) | `device` | `waba_user`

| method | Params | Cost |
|---|---|---|
| `whatsapp` | `user_code`, `secret`, `phone`, `app_name`="Kirimi.id" | Rp 595 / delivered |
| `device` | + `device_id` (req), `custom_message` (must contain `{{otp}}`, 10–500 chars) | 0 |
| `waba_user` | + `waba_id` (req), `template_name` (req, AUTHENTICATION + APPROVED) | 0 (Meta bills own WABA) |

`POST /v2/otp/verify` — `user_code`, `secret`, `phone`, `otp_code`

### OTP Reverse

| Method + Path | Params |
|---|---|
| `POST /v2/otp-reverse/create` | `user_code`, `secret`, `phone`, `device_id` (req), `app_name`="Kirimi.id", `callback_url` (http(s), max 500), `custom_message` (must contain `{{token}}` and `{{phone}}`, 20–500), `success_message`, `failure_message` |
| `POST /v2/otp-reverse/status` | `user_code`, `secret`, `token` |

Status: `pending` · `verified` · `phone_mismatch` · `expired`. Token valid 10 min, single use.
Callback: `POST` with header `x-kirimi-event: otp-reverse.verified`.

### OTP v1 (legacy)

| Method + Path | Params |
|---|---|
| `POST /v1/generate-otp` | `user_code`, `secret`, `device_id`, `phone`, `otp_length` (4–20, default 8), `otp_type` (`numeric` default), `customOtpText` (max 20), `customOtpMessage` (must contain `{otp}`, max 500), `enableTypingEffect`, `typingSpeedMs` |
| `POST /v1/validate-otp` | `user_code`, `secret`, `device_id`, `phone`, `otp` |

### Contacts

| Method + Path | Params |
|---|---|
| `POST /v1/save-contact` | `user_code`, `secret`, `nama` (req), `nomor` (req), `device_id` |
| `POST /v1/save-contacts-bulk` | `user_code`, `secret`, `contacts: [{nama, nomor}]` (max 1000), `device_id` |

Existing numbers are skipped, not overwritten.

### Packages & Deposits

| Method + Path | Params |
|---|---|
| `POST /v1/list-packages` | `user_code`, `secret` |
| `POST /v1/create-deposit` | `user_code`, `secret`, `nominal` (min 100) |
| `POST /v1/deposit-status` | `user_code`, `secret`, `ref` |
| `POST /v1/cancel-deposit` | `user_code`, `secret`, `ref` (must be `unpaid`) |
| `POST /v1/list-deposits` | `user_code`, `secret`, `page`=1, `limit`=10, `status` (`unpaid`/`paid`/`expired`/`cancelled`) |

Max 2 unpaid deposits at a time; payment link valid 24 hours.

### Not public

`POST /member/v1/waba/send-flow` — dashboard session auth (`/member` JWT), **not** `user_code`+`secret`.
Out of scope for SDKs.

## Per-SDK requirements

Every SDK must expose all 30 public endpoints, send canonical field names, and keep its current return-shape
contract (no breaking change). Guards below are hard requirements.

- [ ] `sendMessage`/`sendMessageFast`/`sendMessageFile` send `receiver`
- [ ] `broadcastMessage` sends `numbers` as an **array** (never a joined string, never `phones`)
- [ ] `saveContact` sends `nama` + `nomor`
- [ ] `saveContactsBulk` added, sends `contacts: [{nama, nomor}]`
- [ ] `generateOtp` sends `otp_length` / `otp_type` (snake)
- [ ] `sendOtpV2` supports `whatsapp` | `device` | `waba_user` incl. `waba_id` + `template_name` + `custom_message`
- [ ] OTP Reverse both endpoints added
- [ ] WABA: `sendWabaMessage`, `reply`, `conversations`, `templatesSync`, `sendOtp` (WABA), `verifyOtp` (WABA)
- [ ] Devices: `createDevice`, `connectDevice`, `renewDevice`, `listDevices`, `deviceStatus`, `deviceStatusEnhanced`
- [ ] Deposits: `createDeposit`, `depositStatus`, `cancelDeposit`, `listDeposits`
- [ ] `listPackages`, `userInfo`
- [ ] Errors expose HTTP status where the language allows
- [ ] Tests cover the new surface
