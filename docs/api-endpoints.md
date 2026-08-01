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
- [CalendarIntegration — Calendarios externos](#calendarintegration--calendarios-externos)

---

## Health

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
  "photoUrl": "https://..."
}
```

> `photoUrl` es opcional. Si `since` se omite en el sync, devuelve todo desde epoch 0.

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
| `DELETE` | `/profiles/{id}/schedules/{sid}` | ✅ | — | `204` | Elimina un schedule |

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
> `daysOfWeek`: `1` = Lunes … `7` = Domingo.

---

## DoseEvent — Registro de tomas

**Controller:** `src/DoseEvent/UI/Http/DoseEventController.php`

| Método | Path | Auth | Body / Query | Status | Descripción |
|--------|------|------|------|--------|-------------|
| `POST` | `/profiles/{id}/dose-events` | ✅ | Ver abajo | `201` | Registra una toma |
| `GET`  | `/profiles/{id}/dose-events` | ✅ | `?from=<ISO>&to=<ISO>` | `200` | Lista eventos en un rango |

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
| `DELETE` | `/devices/{token}` | ✅ | — | `204` | Elimina un device token |
| `PATCH`  | `/notifications/preferences` | ✅ | Ver abajo | `200` | Actualiza preferencias push |

### Body — `POST /devices`

```json
{
  "fcmToken": "string",
  "platform": "android | ios",
  "locale": "es-MX"
}
```

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

---

## CalendarIntegration — Calendarios externos

**Controller:** `src/CalendarIntegration/UI/Http/CalendarController.php`

| Método | Path | Auth | Body / Query | Status | Descripción |
|--------|------|------|------|--------|-------------|
| `POST`   | `/calendars/google/connect` | ✅ | `{ profileId, refreshToken }` | `200` | Conecta Google Calendar |
| `POST`   | `/calendars/microsoft/connect` | ✅ | `{ profileId, refreshToken }` | `200` | Conecta Microsoft Calendar |
| `DELETE` | `/calendars/{provider}` | ✅ | `?profileId=<uuid>` *(query)* | `204` | Desconecta un calendario |
| `POST`   | `/calendars/sync` | ✅ | `?profileId=<uuid>` *(query, opcional)* | `200` | Sync manual de calendario |

> `{provider}` ∈ `{ google, microsoft }`
> Los tokens OAuth se almacenan **cifrados en reposo** (libsodium) y **nunca** se exponen en la API.

---

## Resumen de endpoints

| Contexto | Total |
|----------|-------|
| Identity | 4 |
| Profile | 5 |
| Medication | 4 |
| Schedule | 3 |
| DoseEvent | 2 |
| Notification | 3 |
| CalendarIntegration | 4 |
| Health | 1 |
| **Total** | **26** |
