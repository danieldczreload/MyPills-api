# Plan: Worker de mensajes asíncronos (messenger:consume) en producción

> **Ejecutor:** otro agente/modelo. No se requiere decisión de diseño: seguir los pasos tal cual.
> **Contexto:** este plan es autocontenido. Lee solo las secciones necesarias.

---

## 1. Por qué

Los eventos de dominio (ej. `ScheduleCreatedEvent`, que genera los `dose_events` que luego se sincronizan a Google/Outlook Calendar) se rutean al transporte asíncrono `event.async` (`config/packages/messenger.yaml`). Ese transporte usa Doctrine (`messenger_messages` en PostgreSQL) y **requiere un proceso worker** (`messenger:consume event.async`) corriendo permanentemente.

En producción **no existe ese worker**: el compose solo levanta `php`, `database` y `mailpit`. Resultado: la cola acumuló mensajes y nunca se generan los `dose_events`, por lo que la sincronización de calendario responde `NO_UPCOMING_DOSE_EVENTS` aunque haya horarios activos.

**Verificación actual (al 2026-08-26):**
```bash
ssh vps 'docker exec mypills-api-database-1 psql -U app -d app -c "SELECT count(*) FROM messenger_messages;"'
# Esperado antes del fix: ~68 mensajes pendientes
```

---

## 2. Solución (elegida)

Agregar un servicio `worker` al compose que reutilice la **misma imagen** que `php` pero sobrescriba el comando para correr el consumidor de Messenger. Es la opción más simple (no requiere systemd units nuevos en el VPS ni modificar el Dockerfile).

- En **dev** (`compose.yaml`): también se agrega, para que el comportamiento local replique prod. En test (`when@test`) el transporte es `sync://`, así que la suite de PHPUnit no necesita worker.
- El entrypoint del contenedor ya contempla `bin/console` como `$1` (ver `frankenphp/docker-entrypoint.sh`), así que basta con `command:`.

### Archivos a tocar

1. `compose.yaml` — agregar servicio `worker` (base, usado en dev).
2. `compose.prod.yaml` — agregar servicio `worker` con la imagen de prod.
3. `config/packages/messenger.yaml` — (opcional pero recomendado) agregar `retry_strategy` para reintentos ante fallos transitorios (ej. token de Google expirado).
4. `.github/workflows/ci.yaml` — nada que cambiar; el worker no afecta los gates actuales.
5. `.env` — nada que cambiar; `MESSENGER_TRANSPORT_DSN` ya existe apuntando a Doctrine.

---

## 3. Implementación paso a paso

### 3.1 `compose.yaml` (dev)

Agregar después del servicio `php` (mismo nivel que `database:`):

```yaml
  worker:
    image: ${IMAGES_PREFIX:-}app-php-dev
    build:
      context: .
      target: frankenphp_dev
    restart: unless-stopped
    command: ["bin/console", "messenger:consume", "event.async", "--time-limit=3600", "--sleep=1"]
    environment:
      APP_ENV: "${APP_ENV:-dev}"
      DATABASE_URL: postgresql://${POSTGRES_USER:-app}:${POSTGRES_PASSWORD:-!ChangeMe!}@database:5432/${POSTGRES_DB:-app}?serverVersion=${POSTGRES_VERSION:-16}&charset=${POSTGRES_CHARSET:-utf8}
      MESSENGER_TRANSPORT_DSN: ${MESSENGER_TRANSPORT_DSN:-doctrine://default}
      OAUTH_TOKEN_ENCRYPTION_KEY: ${OAUTH_TOKEN_ENCRYPTION_KEY:-!ChangeThisOAuthTokenEncryptionKeyInEnvLocal!}
    depends_on:
      database:
        condition: service_healthy
    volumes:
      - ./:/app:z
      - /app/var
```

> Nota: en dev el código se monta con volumen (`./:/app`), igual que el servicio `php`, para hot-reload.

### 3.2 `compose.prod.yaml`

Agregar después del servicio `php`:

```yaml
  worker:
    image: ${IMAGES_PREFIX:-}app-php-prod
    build:
      context: .
      target: frankenphp_prod
    restart: unless-stopped
    command: ["bin/console", "messenger:consume", "event.async", "--time-limit=3600", "--sleep=1"]
    environment:
      APP_ENV: prod
      DATABASE_URL: postgresql://${POSTGRES_USER:-app}:${POSTGRES_PASSWORD}@database:5432/${POSTGRES_DB:-app}?serverVersion=16&charset=utf8
      MESSENGER_TRANSPORT_DSN: ${MESSENGER_TRANSPORT_DSN:-doctrine://default}
      OAUTH_TOKEN_ENCRYPTION_KEY: ${OAUTH_TOKEN_ENCRYPTION_KEY:?OAUTH_TOKEN_ENCRYPTION_KEY is required}
      # Mismas vars que pueda necesitar el handler de calendario al refrescar tokens:
      GOOGLE_CLIENT_ID: ${GOOGLE_CLIENT_ID:?GOOGLE_CLIENT_ID is required}
      GOOGLE_CLIENT_SECRET: ${GOOGLE_CLIENT_SECRET:-}
      GOOGLE_WEB_CLIENT_ID: ${GOOGLE_WEB_CLIENT_ID:-}
      GOOGLE_WEB_CLIENT_SECRET: ${GOOGLE_WEB_CLIENT_SECRET:-}
      MICROSOFT_CLIENT_ID: ${MICROSOFT_CLIENT_ID:?MICROSOFT_CLIENT_ID is required}
      MICROSOFT_CLIENT_SECRET: ${MICROSOFT_CLIENT_SECRET:-}
      MICROSOFT_TENANT_ID: ${MICROSOFT_TENANT_ID:?MICROSOFT_TENANT_ID is required}
      MICROSOFT_CALENDAR_REDIRECT_URI: ${MICROSOFT_CALENDAR_REDIRECT_URI:?MICROSOFT_CALENDAR_REDIRECT_URI is required}
    depends_on:
      database:
        condition: service_healthy
```

> **Clave:** el worker ejecuta handlers que pueden refrescar tokens OAuth y llamar a las APIs de Google/Microsoft; por eso hereda las mismas variables de integración que `php`. `APP_SECRET` no es estrictamente necesario (no sirve HTTP), pero no estorba si se incluye.

### 3.3 `config/packages/messenger.yaml` (recomendado)

Dentro de `transports.event.async`, agregar estrategia de reintentos para no perder mensajes por fallos transitorios (red, rate-limit de Google):

```yaml
            event.async:
                dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
                options:
                    queue_name: async
                    use_notify: true
                    check_delayed_interval: 60000
                retry_strategy:
                    max_retries: 3
                    delay: 1000
                    multiplier: 2
                    max_delay: 0
```

> Esto es seguro porque los handlers son idempotentes (el `ScheduleCreatedHandler` ya deduplica por timestamp; el sync de calendario usa `idempotencyKey`).

---

## 4. Despliegue

El deploy actual en el VPS corre `bws run -- docker compose -f compose.yaml -f compose.prod.yaml up -d --build --wait` vía systemd. Como el worker es un servicio más del compose, **se levanta solo** en el próximo `up` — no hace falta tocar systemd ni el Dockerfile.

1. Commit + push a `main` (dispara CI y el deploy automático).
2. Tras el deploy, confirmar en el VPS:
   ```bash
   ssh vps 'docker ps --format "{{.Names}}\t{{.Status}}" | grep worker'
   # Esperado: mypills-api-worker-1   Up X minutes
   ```

---

## 5. Procesar el backlog (una sola vez)

Los ~68 mensajes viejos se consumen solos cuando el worker arranque. No hay paso manual. Verificar que bajó a cero:

```bash
ssh vps 'docker exec mypills-api-database-1 psql -U app -d app -c "SELECT count(*) FROM messenger_messages;"'
# Esperado: 0 (o solo los generados en el último minuto)
```

> Si algún mensaje viejo falla por datos obsoletos (ej. un schedule borrado), quedará en `messenger_messages` con `delivered_at` NULL tras agotar reintentos; se puede purgar con `bin/console messenger:failed:delete` o dejarlo (no bloquea a los demás).

---

## 6. Verificación funcional (la que importa)

1. En la app: crear un medicamento con horario diario.
2. Confirmar en la DB que se generaron `dose_events` para ese schedule en < 5 s:
   ```bash
   ssh vps 'docker exec mypills-api-database-1 psql -U app -d app -c "SELECT count(*) FROM dose_events WHERE schedule_id = '\''<SCHEDULE_ID>'\'';"'
   # Esperado: > 0
   ```
3. Settings → "Sincronizar eventos ahora" → el mensaje ya NO debe decir "No hay dosis próximas en los siguientes 14 días"; debe reportar `creados: N`.
4. Revisar en Google Calendar que aparecieron los eventos.

---

## 7. Riesgos y notas

- **Duplicados:** el consumo de los 68 mensajes viejos puede crear dose_events de golpe. El handler ya deduplica por `scheduled_at`, así que no habrá duplicados por re-proceso.
- **Memoria:** `--time-limit=3600` reinicia el worker cada hora para evitar fugas de memoria (patrón estándar de Symfony Messenger). El `restart: unless-stopped` lo vuelve a levantar.
- **Orden de arranque:** `depends_on` con `service_healthy` garantiza que Postgres esté listo antes de que el worker intente consumir.
- **Rollback:** basta revertir el commit y `up -d` de nuevo; el servicio `worker` desaparece y el API sigue funcionando (vuelve el comportamiento actual de cola acumulada).

---

## 8. Resumen ejecutivo

| Acción | Archivo | Cambio |
|--------|---------|--------|
| Agregar worker dev | `compose.yaml` | servicio `worker` con `messenger:consume event.async` |
| Agregar worker prod | `compose.prod.yaml` | mismo comando, imagen `frankenphp_prod`, env de OAuth |
| Reintentos | `config/packages/messenger.yaml` | `retry_strategy` en `event.async` |
| Deploy | — | push a main, el compose lo levanta solo |
| Validar | VPS | `docker ps`, cola a 0, sync crea eventos en Calendar |
