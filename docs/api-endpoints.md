# MyPills API — Endpoint Reference

> **Base URL:** `/api/v1`
> **Auth:** `Authorization: Bearer <JWT>` en todos los endpoints marcados con ✅

---

## Table of Contents

- [Health](#health)
- [Identity — Autenticación](#identity--autenticación)
- [Profile — Perfiles de paciente](#profile--perfiles-de-paciente)
- [Medication — Medicamentos](#medication--medicamentos)
- [Schedule — Horarios de toma](#schedule--horarios-de-toma)
- [DoseEvent — Registro de tomas](#doseevent--registro-de-tomas)
- [Notification — Dispositivos y preferencias](#notification--dispositivos-y-preferencias)
- [Cancel — push y Calendar](#cancel--push-y-calendar)
- [CalendarIntegration — Calendarios externos](#calendarintegration--calendarios-externos)

---

## Health

> Ruta raíz — **sin** prefijo `/api/v1`.

| Método | Path | Auth | Descripción |
|--------|------|------|-------------|
| `GET` | `/health` | ❌ | Healthcheck del servidor |

---

## Identity — Autenticación

**Controller:** `src/Identity/UI/Http/AuthController.php`

| Método | Path | Auth | Body | Descripción |
|--------|------|------|------|-------------|
| `POST` | `/auth/google` | ❌ | `{ idToken: string }` | Login / registro con Google |
| `POST` | `/auth/microsoft` | ❌ | `{ idToken: string }` | Login / registro con Microsoft |
| `POST` | `/auth/refresh` | ❌ | `{ refreshToken: string }` | Refresca el JWT |
| `GET`  | `/me` | ✅ | — | Datos del usuario autenticado |

---

## Profile — Perfiles de paciente

**Controller:** `src/Profile/UI/Http/ProfileController.php`

| Método | Path | Auth | Body / Query | Status | Descripción |
|--------|------|------|------|--------|-------------|
| `GET`    | `/profiles` | ✅ | — | `200` | Lista todos los perfiles del usuario |
| `POST`   | `/profiles` | ✅ | Ver abajo | `201` | Crea un perfil |
| `PATCH`  | `/profiles/{id}` | ✅ | Ver abajo | `200` | Actualiza un perfil |
| `DELETE` | `/profiles/{id}` | ✅ | — | `204` | Elimina un perfil |
| `GET`    | `/profiles/{id}/sync` | ✅ | `?since=<ISO8601>` | `200` | Sync delta desde una fecha |

### Body — `POST /profiles` y `PATCH /profiles/{id}`

```json
{
  "name": "string",
  "birthDate": "2000-01-15",
  "gender": "string",
  "photoUrl": "https://...",
  "timezone": "America/El_Salvador"
}
```

> `photoUrl` es opcional. `timezone` es un identificador IANA; si se omite, el backend guarda `UTC`. Si `since` se omite en el sync, devuelve todo desde epoch 0.

---

## Medication — Medicamentos

**Controller:** `src/Medication/UI/Http/MedicationController.php`

| Método | Path | Auth | Body | Status | Descripción |
|--------|------|------|------|--------|-------------|
| `GET`    | `/profiles/{id}/medications` | ✅ | — | `200` | Lista medicamentos del perfil |
| `POST`   | `/profiles/{id}/medications` | ✅ | Ver abajo | `201` | Crea un medicamento |
| `PATCH`  | `/profiles/{id}/medications/{mid}` | ✅ | Ver abajo | `200` | Actualiza un medicamento |
| `DELETE` | `/profiles/{id}/medications/{mid}` | ✅ | — | `204` | Elimina un medicamento |

### Body — `POST /medications`

```json
{
  "name": "string",
  "dosage": "500mg",
  "instructions": "Tomar con comida",
  "photoUrl": "https://...",
  "clientId": "uuid-device-minted"
}
```

### Body — `PATCH /medications/{mid}`

```json
{
  "name": "string",
  "dosage": "500mg",
  "instructions": "string",
  "photoUrl": "https://..."
}
```

> `instructions`, `photoUrl` y `clientId` son opcionales.
> `clientId` es un UUID generado en el dispositivo para crear idempotente (offline-first).

---

## Schedule — Horarios de toma

**Controller:** `src/Schedule/UI/Http/ScheduleController.php`

Usa Doctrine STI sobre la tabla `schedules`, discriminador `type`.

| Método | Path | Auth | Body | Status | Descripción |
|--------|------|------|------|--------|-------------|
| `GET`    | `/profiles/{id}/schedules` | ✅ | — | `200` | Lista schedules del perfil |
| `POST`   | `/profiles/{id}/schedules` | ✅ | Ver abajo | `201` | Crea un schedule |
| `DELETE` | `/profiles/{id}/schedules/{sid}` | ✅ | — | `204` | Elimina un schedule y dispara limpieza de eventos de Calendar de sus dosis |

### Body — `POST /schedules`

Los campos varían según `type`:

```json
// ── Campos comunes ────────────────────────────────────────
{
  "medicationId": "uuid",
  "type": "daily | daily_interval | specific_days",
  "startDate": "2025-01-01",
  "endDate": "2025-12-31",
  "clientId": "uuid"
}
```

```json
// ── type = "daily" ────────────────────────────────────────
{
  "timesOfDay": [
    { "hour": 8, "minute": 0 },
    { "hour": 20, "minute": 30 }
  ]
}
```

```json
// ── type = "daily_interval" ───────────────────────────────
{
  "everyHours": 6,
  "startAt": { "hour": 8, "minute": 0 },
  "endAt":   { "hour": 22, "minute": 0 }
}
```

```json
// ── type = "specific_days" ────────────────────────────────
{
  "daysOfWeek": [1, 3, 5],
  "timesOfDay": [{ "hour": 9, "minute": 30 }]
}
```

> `endDate` y `clientId` son opcionales.
> `daysOfWeek`: `1` = Lunes … `7` = Domingo (ISO-8601, convención; el dominio valida rango 1-7).

---

## DoseEvent — Registro de tomas

**Controller:** `src/DoseEvent/UI/Http/DoseEventController.php`

| Método | Path | Auth | Body / Query | Status | Descripción |
|--------|------|------|------|--------|-------------|
| `POST` | `/profiles/{id}/dose-events` | ✅ | Ver abajo | `201` | Registra una toma |
| `GET`  | `/profiles/{id}/dose-events` | ✅ | `?from=<ISO>&to=<ISO>` | `200` | Lista eventos en un rango |
| `POST` / `DELETE` | `/profiles/{id}/dose-events/{doseEventId}/cancel` | ✅ | Ver [Cancel](#cancel--push-y-calendar) | `200` | Alias de cancelar una toma (push + Calendar) |

### Body — `POST /dose-events`

```json
{
  "scheduleId": "uuid",
  "scheduledAt": "2025-06-01T08:00:00Z",
  "status": "taken | skipped | pending",
  "takenAt": "2025-06-01T08:05:00Z",
  "clientId": "uuid"
}
```

> `from` y `to` son **obligatorios** en el `GET`.
> `takenAt` y `clientId` son opcionales.

---

## Notification — Dispositivos y preferencias

**Controller:** `src/Notification/UI/Http/NotificationController.php`

| Método | Path | Auth | Body | Status | Descripción |
|--------|------|------|------|--------|-------------|
| `POST`   | `/devices` | ✅ | Ver abajo | `201` | Registra un device token (FCM) |
| `DELETE` | `/devices/{deviceId}` | ✅ | — | `204` | Elimina un device registration |
| `GET`    | `/notifications/preferences` | ✅ | — | `200` | Lee preferencias push |
| `PATCH`  | `/notifications/preferences` | ✅ | Ver abajo | `200` | Actualiza preferencias push |
| `POST`   | `/notifications/test-push` | ✅ | `{ title, body, data? }` | `200` / `500` | Envía un push de prueba; devuelve `500` si alguna entrega falla |
| `POST` / `DELETE` | `/profiles/{id}/notifications/{doseEventId}/cancel` | ✅ | Ver [Cancel](#cancel--push-y-calendar) | `200` | Cancela push y/o evento de Calendar de una toma |
| `POST`   | `/profiles/{id}/notifications/cancel-recurring` | ✅ | Ver [Cancel](#cancel--push-y-calendar) | `200` | Cancela pendientes de schedule, medicamento o perfil |
| `POST` / `DELETE` | `/profiles/{id}/schedules/{sid}/cancel-recurring` | ✅ | Ver [Cancel](#cancel--push-y-calendar) | `200` | Cancela pendientes de ese schedule |

### Body — `POST /devices`

```json
{
  "fcmToken": "string",
  "platform": "android | ios",
  "locale": "es-MX"
}
```

The `201` response contains only the registration `id`, `platform`, and `locale`. The FCM token is never returned. Use that `id` in `DELETE /devices/{deviceId}`.

### Body — `PATCH /notifications/preferences`

```json
{
  "doseRemindersEnabled": true,
  "missedDoseNudgesEnabled": true,
  "refillAlertsEnabled": true,
  "weeklyStreakSummariesEnabled": true
}
```

> Todos los campos de preferencias tienen `true` como valor por defecto.

### Cancel — push y Calendar

El cliente **no** guarda IDs de Google/Microsoft. El backend tiene `calendar_event_mappings` (`doseEventId` + `provider` + `externalEventId`) y borra el evento remoto al cancelar.

| Método | Path | Qué cancela |
|--------|------|-------------|
| `POST` / `DELETE` | `/profiles/{id}/notifications/{doseEventId}/cancel` | Una toma: `pending` → `skipped`, borra push y evento de Calendar |
| `POST` / `DELETE` | `/profiles/{id}/dose-events/{doseEventId}/cancel` | Igual (`CancelNotificationCommand`) |
| `POST` | `/profiles/{id}/notifications/cancel-recurring` | Pendientes de `scheduleId`, `medicationId` o todo el perfil |
| `POST` / `DELETE` | `/profiles/{id}/schedules/{sid}/cancel-recurring` | Pendientes de ese schedule |
| `DELETE` | `/profiles/{id}/schedules/{sid}` | Borra el schedule; `ScheduleDeletedEvent` limpia eventos de Calendar |

Body (JSON, opcional; omitir un flag lo deja en su default):

```json
{
  "cancelPush": true,
  "cancelCalendar": true,
  "deleteSchedule": false,
  "scheduleId": "uuid",
  "medicationId": "uuid"
}
```

- `cancelPush` y `cancelCalendar` default **true**.
- `deleteSchedule` default **false**; solo aplica a cancel-recurring con `scheduleId` / `{sid}`.
- `scheduleId` / `medicationId` solo en `POST …/notifications/cancel-recurring`. Si ambos se omiten, se cancelan los pendientes de todo el perfil.

El mapping local se borra solo si el proveedor confirma el delete o responde **404** (el evento ya no existe). Un **5xx** conserva el mapping para reintentar.

Respuesta `200` (cancel individual):

```json
{
  "doseEventId": "uuid",
  "status": "skipped",
  "pushCancelled": true,
  "calendarEventsDeleted": 1
}
```

Respuesta `200` (cancel recurring):

```json
{
  "profileId": "uuid",
  "scheduleId": "uuid",
  "medicationId": null,
  "schedulesTargeted": 1,
  "pendingDosesCancelled": 2,
  "calendarEventsDeleted": 2,
  "pushCancelled": true
}
```

`calendarEventsDeleted` cuenta mappings cuyo delete remoto se confirmó (o 404). Si el remoto falla, el cancel HTTP sigue `200` y ese contador no incluye los fallidos.

---

## CalendarIntegration — Calendarios externos

**Controller:** `src/CalendarIntegration/UI/Http/CalendarController.php`

| Método | Path | Auth | Body / Query | Status | Descripción |
|--------|------|------|------|--------|-------------|
| `POST`   | `/calendars/{provider}/authorize` | ✅ | `{ profileId, codeChallenge }` | `200` | Starts PKCE authorization |
| `POST`   | `/calendars/{provider}/connect` | ✅ | `{ profileId, code, state, codeVerifier }` | `200` | Completes PKCE authorization |
| `GET`    | `/calendars` | ✅ | `?profileId=<uuid>` | `200` | Returns connection status |
| `DELETE` | `/calendars/{provider}` | ✅ | `?profileId=<uuid>` *(query)* | `204` | Desconecta un calendario |
| `POST`   | `/calendars/sync` | ✅ | `?profileId=<uuid>` *(query, opcional)* | `200` | Sync manual de calendario |

> `{provider}` ∈ `{ google, microsoft }`. El `codeChallenge` y `codeVerifier` pertenecen al flujo Authorization Code + PKCE. La solicitud de autorización expira en cinco minutos y `state` solo puede consumirse una vez.
> El refresh token se cifra en el backend con libsodium y nunca se devuelve en la API. Se obtiene únicamente en el backend durante el flujo PKCE.

### Configuración de providers

Google Calendar y Microsoft Graph usan clientes OAuth públicos de Android con PKCE. Configura `GOOGLE_CLIENT_ID`, `GOOGLE_CALENDAR_REDIRECT_URI`, `MICROSOFT_CLIENT_ID`, `MICROSOFT_TENANT_ID` y `MICROSOFT_CALENDAR_REDIRECT_URI`. Los client secrets son opcionales y nunca deben incluirse en la aplicación Android. Firebase Cloud Messaging usa HTTP v1 con una cuenta de servicio, no el endpoint legacy ni `FCM_SERVER_KEY`:

- `FIREBASE_PROJECT_ID`
- `FIREBASE_CLIENT_EMAIL`
- `FIREBASE_PRIVATE_KEY`

Las credenciales deben existir únicamente en variables de entorno/secret manager, nunca en el repositorio ni en logs.

---

## Resumen de endpoints

| Contexto | Total |
|----------|-------|
| Identity | 4 |
| Profile | 5 |
| Medication | 4 |
| Schedule | 3 |
| DoseEvent | 3 |
| Notification | 8 |
| CalendarIntegration | 5 |
| Health | 1 |
| **Total** | **33** |
