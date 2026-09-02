# MyPills Flutter Client Requirements

This document is the integration contract for the Flutter client. The API endpoint reference remains in `docs/api-endpoints.md`.

## 1. Scope

The mobile application must use the application's own authentication system.

Google OAuth and Microsoft OAuth are only for connecting external calendars. They are not the application's user login mechanism.

The Flutter client must not use these current routes for normal login:

- `POST /api/v1/auth/google`
- `POST /api/v1/auth/microsoft`

The backend still exposes those legacy login routes. They should be disabled or removed when the own-authentication flow is released.

## 2. Required Authentication Contract

The final own-authentication API must provide:

- Account registration.
- Account login.
- Access-token refresh with refresh-token rotation.
- Logout and refresh-token revocation.
- Password reset and email verification if password authentication is used.

The client stores access and refresh tokens only with `flutter_secure_storage`. Access tokens should remain in memory whenever possible.

Every protected request sends:

```http
Authorization: Bearer <access-token>
Content-Type: application/json
```

The HTTP client must have one refresh flow guarded by a mutex. Multiple simultaneous `401` responses must not trigger multiple refresh requests. If refresh fails, clear credentials and return the user to login.

## 3. HTTP Contract

- Base path: `/api/v1`.
- Use HTTPS outside local development.
- JSON keys use `camelCase`.
- Timestamps use ISO-8601 with an explicit timezone, preferably UTC (`2026-08-03T14:00:00Z`).
- Dates without a time are used for profile birth dates and schedule boundaries.
- `204 No Content` responses must not be decoded as JSON.

Common status handling:

| Status | Client behavior |
|--------|-----------------|
| `200` | Decode response. |
| `201` | Decode created resource. |
| `204` | Mark local operation complete without decoding a body. |
| `401` | Refresh once, then retry the original request. |
| `403` | Show an ownership/permission error; do not retry. |
| `404` | Mark the local resource as missing or deleted. |
| `409` | Resolve the idempotency/conflict result. |
| `422` | Show field validation errors. |
| `5xx` or network failure | Keep the operation pending and retry with backoff. |

Error responses have this shape:

```json
{
  "error": {
    "type": "VALIDATION",
    "message": "Validation failed.",
    "details": {}
  }
}
```

The client must not display raw server exception messages in production.

## 4. Local Client Architecture

Use a feature-first structure with these layers:

- Presentation: screens, widgets, navigation, loading/error states.
- Application: use cases and state management.
- Domain: immutable models and business rules needed locally.
- Data: API DTOs, repositories, local database, and sync queue.

The local database must persist:

- Account session metadata.
- Patient profiles.
- Medications.
- Schedules.
- Dose events.
- Pending outbound operations.
- Per-profile sync cursor.
- Calendar connection status.

The local outbox must preserve the same `clientId` when retrying a create. Never generate a new `clientId` for a retry.

## 5. Offline and Synchronization Requirements

The client is offline-first for medication data and dose tracking.

Create operations for medications, schedules, and dose events use a device-generated UUID in `clientId`. The same UUID makes retries idempotent.

Initial synchronization:

```http
GET /api/v1/profiles/{profileId}/sync
```

Incremental synchronization:

```http
GET /api/v1/profiles/{profileId}/sync?since=<last-successful-sync-time>
```

The response contains:

```json
{
  "medications": [],
  "schedules": [],
  "doseEvents": [],
  "tombstones": []
}
```

Client synchronization rules:

1. Store the cursor only after the complete response is applied successfully.
2. Query with a small overlap before the stored cursor to tolerate clock and transaction boundaries.
3. Apply medications before schedules, and schedules before dose events.
4. Apply tombstones after upserts.
5. Delete local records using tombstones, whose `type` is currently `medication` or `schedule`.
6. Treat server `updatedAt` as authoritative. The backend uses last-write-wins behavior.
7. Do not use the device clock as the conflict authority.

The backend currently expands schedules into concrete dose events. The client should render dose events and must not independently create a second authoritative occurrence set.

## 6. Core Data Requirements

### Profiles

```http
GET    /api/v1/profiles
POST   /api/v1/profiles
PATCH  /api/v1/profiles/{profileId}
DELETE /api/v1/profiles/{profileId}
```

Fields:

```json
{
  "name": "string",
  "birthDate": "2000-01-15",
  "gender": "string",
  "photoUrl": "https://..."
}
```

### Medications

```http
GET    /api/v1/profiles/{profileId}/medications
POST   /api/v1/profiles/{profileId}/medications
PATCH  /api/v1/profiles/{profileId}/medications/{medicationId}
DELETE /api/v1/profiles/{profileId}/medications/{medicationId}
```

Create payload:

```json
{
  "name": "Ibuprofen",
  "instructions": "Take with food",
  "photoUrl": "https://...",
  "clientId": "device-generated-uuid"
}
```

### Schedules

```http
GET    /api/v1/profiles/{profileId}/schedules
POST   /api/v1/profiles/{profileId}/schedules
DELETE /api/v1/profiles/{profileId}/schedules/{scheduleId}
```

Common fields:

```json
{
  "medicationId": "uuid",
  "type": "daily | daily_interval | specific_days",
  "startDate": "2026-08-03",
  "endDate": "2026-12-31",
  "doseAmount": 5,
  "doseUnit": "ml",
  "clientId": "device-generated-uuid"
}
```

`doseAmount` and `doseUnit` are required. Fetch the picker catalog from `GET /api/v1/dose-units`. The reminder push and calendar event use this schedule dose. Medication has no dosage field.

Schedule variants:

- `daily`: `timesOfDay: [{"hour": 8, "minute": 0}]`.
- `daily_interval`: `everyHours`, `startAt`, and optional `endAt`.
- `specific_days`: ISO weekdays in `daysOfWeek` (`1` Monday through `7` Sunday) and `timesOfDay`.

### Dose Events

```http
POST /api/v1/profiles/{profileId}/dose-events
GET  /api/v1/profiles/{profileId}/dose-events?from=<ISO>&to=<ISO>
```

Create/update payload:

```json
{
  "scheduleId": "uuid",
  "scheduledAt": "2026-08-03T08:00:00Z",
  "status": "taken | skipped | pending",
  "takenAt": "2026-08-03T08:05:00Z",
  "clientId": "device-generated-uuid"
}
```

The UI must support at least `pending`, `taken`, and `skipped` states. `takenAt` is only meaningful for a taken event.

List responses include `dose: { amount, unit, display }` from the schedule so the today view can show “take 5 ml” without a local join. `dose` is `null` on legacy schedules.

## 7. Push Notifications

The client must:

1. Request notification permission using the platform APIs.
2. Obtain the Firebase registration token.
3. Register it after successful application login:

```http
POST /api/v1/devices
```

```json
{
  "fcmToken": "token",
  "platform": "android | ios",
  "locale": "es-MX"
}
```

4. Re-register when Firebase rotates the token.
5. Delete the registration on logout when the user is still authenticated:

```http
DELETE /api/v1/devices/{deviceId}
```

6. Handle foreground, background, and terminated-state notification taps.
7. Route a notification tap to the relevant profile, medication, or dose event.

Notification preferences:

```http
PATCH /api/v1/notifications/preferences
```

All preference fields default to `true` when not previously configured.

The backend still needs a read endpoint for preferences so a new device can hydrate settings without relying on local state.

Test push delivery (useful for diagnostics screens and QA):

```http
POST /api/v1/notifications/test-push
```

```json
{
  "title": "Test notification",
  "body": "Delivery check",
  "data": { "kind": "test" }
}
```

Response `200`: `{ "sent": <int>, "failed": 0 }` when all deliveries succeed. A delivery failure returns `500` with type `PUSH_PARTIAL_FAILURE`; invalid FCM registrations are removed server-side automatically.

The client must never log FCM tokens or notification payloads containing health information.

## 8. Calendar Connections

Calendar OAuth is separate from application authentication.

Target flow:

1. The authenticated user selects a profile.
2. Flutter generates a PKCE `codeVerifier`/`codeChallenge` pair and calls the backend `/authorize` endpoint.
3. The backend returns an authorization URL and a server-bound one-time `state`.
4. Flutter opens the URL in the system browser; the user grants calendar access.
5. The provider redirects to the registered app deep link with `code` and `state`.
6. Flutter completes the flow through `/connect`; the backend exchanges the code, encrypts, and stores the provider refresh token.
7. Flutter stores only non-sensitive connection status.

### 8.1 PKCE generation (client side)

Flutter must generate the PKCE pair per authorization attempt:

- `codeVerifier`: 43–128 characters from `[A-Za-z0-9._~-]`, generated from at least 32 random bytes.
- `codeChallenge`: `BASE64URL-ENCODE(SHA256(codeVerifier))` without padding.

Recommended Dart packages: `crypto` for SHA-256 and `dart:math`/`Random.secure()` for the verifier. The backend validates both formats and rejects invalid values with `422`.

Required provider scopes (already requested by the backend-built authorization URL):

- Google: `https://www.googleapis.com/auth/calendar` (event read/write).
- Microsoft Graph: `openid profile offline_access User.Read Calendars.ReadWrite`.

The redirect URI must be registered exactly in each provider console and must match the backend environment variables `GOOGLE_CALENDAR_REDIRECT_URI` and `MICROSOFT_CALENDAR_REDIRECT_URI`. Android must register the same URI as an application deep link (`AndroidManifest.xml` intent-filter with `android:scheme` matching the custom scheme, or an App Link). Use a Custom Tabs / ASWebAuthenticationSession-style system browser, not an embedded WebView: providers block OAuth in embedded WebViews.

Do not send an ID token to Calendar endpoints. An ID token identifies a login session; it is not a calendar authorization token.

### 8.2 Authorization endpoints

```http
POST /api/v1/calendars/{provider}/authorize
POST /api/v1/calendars/{provider}/connect
```

`{provider}` is `google` or `microsoft`.

Start authorization:

```json
{
  "profileId": "uuid",
  "codeChallenge": "base64url-sha256-code-challenge"
}
```

Response `200`:

```json
{
  "state": "one-time-state",
  "authorizationUrl": "https://accounts.google.com/o/oauth2/v2/auth?...",
  "expiresAt": "2026-08-03T14:05:00+00:00"
}
```

Flutter opens `authorizationUrl` in the system browser and receives the provider callback through the registered Android deep link. The authorization request expires 5 minutes after issue (`expiresAt`) and `state` can be consumed only once; on expiry or failure, restart from `/authorize`.

Complete authorization:

```json
{
  "profileId": "uuid",
  "code": "provider-authorization-code",
  "state": "one-time-state",
  "codeVerifier": "pkce-code-verifier"
}
```

The callback `state` from the provider must equal the `state` returned by `/authorize`; abort otherwise. Response `200`:

```json
{
  "profileId": "uuid",
  "provider": "google",
  "connected": true
}
```

The refresh token is obtained and encrypted only by the backend. Flutter must never persist, log, or display it.

### 8.3 Connection status and sync

```http
GET    /api/v1/calendars?profileId=<uuid>
DELETE /api/v1/calendars/{google|microsoft}?profileId=<uuid>
POST   /api/v1/calendars/sync?profileId=<uuid>
```

`GET /calendars` response `200` (array, one entry per connected provider):

```json
[
  {
    "provider": "google",
    "status": "active",
    "connected": true,
    "updatedAt": "2026-08-03T14:00:00+00:00"
  }
]
```

`status` is `active` or `reauth_required`. When `reauth_required`, the user's provider grant was revoked or is undecryptable; prompt the user to reconnect via the PKCE flow. Until reconnection, that provider no longer syncs.

`POST /calendars/sync` pushes the next 14 days of dose events to every connected provider calendar (idempotent: existing events are updated, not duplicated). Behavior on failure:

- All providers synced: `200`.
- One or more providers failed: `500` with error type `SYNC_PARTIAL_FAILURE` and `details.links` listing each failed provider and `reason` (`REAUTH_REQUIRED`, `REFRESH_FAILED`, `UPSERT_FAILED`, `UNSUPPORTED_PROVIDER`). Successfully synced providers stay synced; the client should surface per-provider errors and offer reconnect for `REAUTH_REQUIRED`.

The client should show these states:

- Not connected.
- Connected.
- Connected but reauthorization required (`status: reauth_required` or sync reason `REAUTH_REQUIRED`).
- Synchronizing.
- Provider temporarily unavailable (`REFRESH_FAILED` / `UPSERT_FAILED`, retry with backoff).

### 8.4 What the backend writes to calendars

Each dose event becomes one calendar event:

- Title: `Take Medication: {medication name} ({schedule dose})`. If the schedule has no dose, the title is just the medication name.
- Description: instructions, dose status, and scheduled time.
- Time: scheduled dose time, 30-minute duration.
- Google events use the event's own timezone offset; Microsoft events are sent in UTC.

The client must not mirror or edit these events locally; the backend is the only writer.

## 9. Timezone and Scheduling Requirement

The client must always know the device/profile IANA timezone, for example `America/Mexico_City`.

The backend currently lacks an explicit profile timezone contract. Before production scheduling, add a timezone field and send it from Flutter. Without it, daylight-saving changes and travel can produce incorrect dose times.

## 10. Acceptance Criteria

The Flutter client is ready for release when:

- Own authentication works without Google or Microsoft login.
- Access/refresh tokens are stored securely and refresh is single-flight.
- Medication, schedule, and dose data work offline.
- Creates retry with stable `clientId` values.
- Sync applies updates and tombstones atomically.
- Push registration handles token rotation and logout.
- Notification taps open the correct domain screen.
- Calendar authorization uses PKCE and never exposes provider refresh tokens.
- Calendar and notification errors have recoverable UI states.
- API traffic is covered by repository tests using mocked HTTP responses.
- Production and staging use separate credentials and Firebase projects.

## 11. Backend Gaps Before Flutter Production

- Add own-authentication endpoints and disable legacy social-login routes.
- Add `GET /notifications/preferences`.
- Add profile timezone support.
- Define notification payload and deep-link schema.
- Move calendar synchronization and push delivery to asynchronous jobs with retry/outbox handling.
- Rotate the OAuth and token-vault credentials independently for staging and production.
