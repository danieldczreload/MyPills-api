# Flutter: mover la dosis del medicamento al recordatorio

Handover para el agente del frontend. Contrato ya desplegado en el API MyPills. Implementar esto; no reinventar el modelo.

Base path: `/api/v1`. JSON `camelCase`. Auth: `Authorization: Bearer <jwt>`.

---

## Objetivo

La dosis (cuánto tomar: `5 ml`, `400 mg`, `1 tablet`) **ya no pertenece al medicamento**. Pertenece al **schedule (recordatorio / toma)**.

El usuario debe:

1. Crear un medicamento **sin** campo de dosis.
2. Al crear un recordatorio, elegir **cantidad + unidad**.
3. Ver en hoy / push / calendario: “tomá {nombre} ({dosis})”.

Si el cliente viejo sigue mandando `dosage` en el medicamento y **no** manda `doseAmount`+`doseUnit` al crear el schedule, el create pega **422** y no hay recordatorio ni push. Ese camino no puede quedar a medias.

---

## 1. Borrar `dosage` del medicamento

Quitar `dosage` de:

- modelo de dominio / Drift / Hive / Isar / JSON DTO
- `POST` / `PATCH` / `GET` medications
- payload de `sync.medications[]`
- formularios de alta y edición de medicamento
- cualquier UI que mostrara “400mg” pegado al nombre del med

El backend **ignora** `dosage` si lo mandás. **Ya no lo devuelve.** Si el parser exige el campo, se rompe.

Medicamento vigente:

```json
{
  "id": "uuid",
  "profileId": "uuid",
  "name": "Ibuprofeno",
  "instructions": "Con comida",
  "photoUrl": null,
  "form": "pill",
  "colorToken": "sky",
  "clientId": "device-uuid",
  "createdAt": "2026-09-02T12:00:00+00:00",
  "updatedAt": "2026-09-02T12:00:00+00:00"
}
```

Create:

```http
POST /api/v1/profiles/{profileId}/medications
```

```json
{
  "name": "Ibuprofeno",
  "instructions": "Con comida",
  "photoUrl": null,
  "form": "pill",
  "colorToken": "sky",
  "clientId": "device-generated-uuid"
}
```

`name` obligatorio. `instructions`, `photoUrl`, `form` (default server `pill`), `colorToken` (default `sky`), `clientId` opcionales.

Migración local: drop column / campo `dosage`. No migrar esos strings al schedule; la dosis se pide de nuevo al crear el recordatorio.

---

## 2. Modelo `Dose`

Un solo objeto. No tres campos sueltos en HTTP (`dosage` + `doseAmount` + `doseUnit`).

```dart
class Dose {
  const Dose({
    required this.amount,
    required this.unit,
    required this.display,
  });

  final num amount;     // 400 o 2.5 (int o double en JSON)
  final String unit;    // código canónico: mg, ml, tablet, ...
  final String display; // listo para UI: "400 mg", "2 tablets", "5 ml"

  factory Dose.fromJson(Map<String, dynamic> json) => Dose(
        amount: json['amount'] as num,
        unit: json['unit'] as String,
        display: json['display'] as String,
      );
}

Dose? parseDose(Object? json) {
  if (json is! Map<String, dynamic>) return null;
  return Dose.fromJson(json);
}
```

Aparece en:

| Recurso | Campo | Null |
|---|---|---|
| `GET/POST` schedules | `dose` | sí, schedules viejos |
| `GET` dose-events | `dose` | sí, si el schedule no tiene dosis |
| `GET` sync → `schedules[]` y `doseEvents[]` | `dose` | sí |

UI: usar **`dose.display`**. No concatenar amount+unit en el cliente salvo en el form de alta.

---

## 3. Catálogo de unidades

```http
GET /api/v1/dose-units
```

Auth JWT. Sin `profileId`. Cacheable (cambia poco). No hardcodear la lista.

```json
[
  {
    "code": "mg",
    "symbol": "mg",
    "name": "milligram",
    "kind": "mass",
    "suggestedForForms": ["pill", "tablet", "capsule", "powder", "injection"]
  }
]
```

| Campo | Uso |
|---|---|
| `code` | valor de `doseUnit` en el POST |
| `symbol` / `name` | label del picker |
| `kind` | agrupar: `mass` \| `volume` \| `household` \| `special` \| `count` |
| `suggestedForForms` | filtrar sugeridas según `medication.form` |

Códigos actuales: `mcg`, `mg`, `g`, `ml`, `drop`, `tsp`, `tbsp`, `iu`, `meq`, `mmol`, `tablet`, `capsule`, `puff`, `spray`, `patch`, `application`, `unit`.

El picker **siempre envía `code`**. No enviar aliases (`comprimido`, `gotas`, `µg`, `UI`) aunque el server los acepte.

UX: si `medication.form == "liquid"`, prefiltrar unidades cuyo `suggestedForForms` incluya `liquid` / `syrup` / `drops`. El usuario puede elegir otra.

---

## 4. Crear recordatorio — dosis obligatoria

```http
POST /api/v1/profiles/{profileId}/schedules
```

Campos comunes (todos los `type`):

```json
{
  "medicationId": "uuid",
  "type": "daily",
  "startDate": "2026-09-02",
  "endDate": null,
  "doseAmount": 5,
  "doseUnit": "ml",
  "clientId": "device-generated-uuid"
}
```

- `doseAmount`: número **> 0**, hasta **4 decimales** (`5`, `2.5`, `0.125`). JSON number, no `"5 mg"`.
- `doseUnit`: `code` del catálogo.
- Sin ellos → **422** `"doseAmount and doseUnit are required."` con `details.allowedUnits`.
- Unidad inválida → **422** `"Invalid dose unit."` con `details.allowedUnits`.
- Amount 0 / negativo / más de 4 decimales → **422**.

Por `type`, igual que antes:

- `daily`: `timesOfDay: [{ "hour": 8, "minute": 0 }]`
- `daily_interval`: `everyHours`, `startAt`, `endAt?`
- `specific_days`: `daysOfWeek` (1=lunes … 7=domingo), `timesOfDay`

Respuesta:

```json
{
  "id": "uuid",
  "medicationId": "uuid",
  "type": "daily",
  "startDate": "...",
  "endDate": null,
  "dose": { "amount": 5, "unit": "ml", "display": "5 ml" },
  "timesOfDay": [{ "hour": 8, "minute": 0 }],
  "clientId": "...",
  "createdAt": "...",
  "updatedAt": "..."
}
```

No hay `PATCH` de schedule. Cambiar dosis = **DELETE + POST** (nuevo `clientId`).

`clientId` del outbox: no regenerar en retries.

Pantalla: el picker de dosis va en **nuevo recordatorio**, no en nuevo medicamento.

---

## 5. Sync y vista de hoy

```http
GET /api/v1/profiles/{profileId}/sync
GET /api/v1/profiles/{profileId}/sync?since=<ISO>
```

```json
{
  "medications": [ /* sin dosage */ ],
  "schedules": [ /* incluyen dose */ ],
  "doseEvents": [ /* incluyen dose copiado del schedule */ ],
  "taxonomyGroups": [],
  "tombstones": []
}
```

Apply: medications → schedules → doseEvents → tombstones. Cursor solo después de aplicar todo.

```http
GET /api/v1/profiles/{profileId}/dose-events?from=<ISO>&to=<ISO>
```

Cada evento trae `dose` (o `null`). La vista de hoy **no hace join** con el schedule: usa `event.dose?.display`.

```text
Ibuprofeno · 5 ml
```

Si `dose == null` (legacy): solo el nombre. No inventar dosis.

---

## 6. Push FCM

`message.data` — **todos los values son string**.

| Key | Ejemplo | Notas |
|---|---|---|
| `type` | `dose_reminder` | |
| `doseEventId` | uuid | |
| `medicationName` | `Amoxicilina` | |
| `doseDisplay` | `500 mg` | **reemplaza `dosage`** |
| `doseAmount` | `500` | string |
| `doseUnit` | `mg` | |
| `scheduledAt` | ISO-8601 | |
| `anticipationMinutes` | `0` | |
| `doseRemindersEnabled` | `1` \| `0` | |
| `inAppBannersEnabled` | `1` \| `0` | |

Título OS (lo arma el server): `Hora de tu medicación`  
Body OS: `Es hora de tomar Amoxicilina (500 mg)` — sin paréntesis si no hay dosis.

Banner in-app:

```dart
final display = data['doseDisplay'] ?? data['dosage'] ?? '';
final body = display.isEmpty
    ? 'Es hora de tomar $name'
    : 'Es hora de tomar $name ($display)';
```

El fallback a `dosage` es solo por si llega un push de un backend viejo. Código nuevo lee `doseDisplay`.

Foreground: `type == dose_reminder` → overlay si `inAppBannersEnabled`. No duplicar tray si el push nativo también está on.

Tap de notificación: `/today` o el dose event.

---

## 7. Calendario

No cambia el cliente. El backend escribe:

`Take Medication: {name} ({dose.display})`

o solo el nombre si el schedule no tiene dosis. El app no edita esos eventos.

---

## 8. Checklist de implementación

Hacer en este orden:

1. Drop `dosage` del modelo Medication + migración de DB local + DTOs.
2. Modelo `Dose` + `parseDose` nullable.
3. Schedule, DoseEvent y sync: campo `dose`.
4. Cliente HTTP `GET /dose-units` + cache.
5. Form de recordatorio: input numérico + picker de `code`. Validar amount > 0 antes del POST. Mandar `doseAmount` (num) y `doseUnit` (code).
6. Today / detalle de toma: `event.dose?.display`.
7. Push: leer `doseDisplay` (fallback `dosage`).
8. Quitar UI que pedía mg/ml en el medicamento.
9. Tests de parse:
   - medication JSON sin `dosage` no explota
   - schedule/doseEvent con `dose` objeto
   - `dose: null` no explota
   - POST schedule sin amount/unit no se encola como éxito
10. Outbox: mismo `clientId` en retry.

---

## 9. Qué no hacer

- No guardar la dosis en el medicamento “por si acaso”.
- No mandar `dosage: "400mg"` como string libre en el schedule.
- No hardcodear unidades; usar el catálogo.
- No formatear `display` en el cliente para listas; usar el del server.
- No asumir que `amount` es siempre `int` (`2.5` es válido).
- No crear un segundo set de ocurrencias en el dispositivo; las tomas las expande el server.

---

## 10. Errores

Forma:

```json
{
  "error": {
    "type": "VALIDATION",
    "message": "doseAmount and doseUnit are required.",
    "details": { "allowedUnits": ["mcg", "mg", "..."] }
  }
}
```

| Status | Acción |
|---|---|
| 422 al crear schedule | mostrar el mensaje; no marcar el outbox como synced |
| 401 | refresh + retry |
| 5xx / red | reintentar con el mismo `clientId` |
