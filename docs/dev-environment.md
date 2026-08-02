# Entorno de desarrollo — MyPills API

> Documentación consolidada; para referencia de endpoints ver [api-endpoints.md](api-endpoints.md).

---

## Arranque

El proyecto incluye un `Makefile` propio en la raíz. No uses la plantilla genérica del skeleton Symfony Docker.

| Comando            | Acción                                              |
|--------------------|-----------------------------------------------------|
| `make up`          | Levanta todos los servicios y espera healthchecks   |
| `make down`        | Detiene todos los servicios                         |
| `make sh`          | Abre shell en el contenedor PHP                     |
| `make logs`        | Logs en vivo de todos los servicios                 |
| `make db`          | Abre PostgreSQL shell (`psql -U app -d app`)         |
| `make reset-db`    | Dropea y recrea la base de datos + migrations       |
| `make test`        | Ejecuta PHPUnit (`vendor/bin/simple-phpunit`)       |
| `make migrate`     | Corre `doctrine:migrations:migrate`                 |
| `make cs`          | PHP CS Fixer en modo `--dry-run --diff`             |
| `make stan`        | PHPStan (`analyse --memory-limit=-1`)               |

Stack: FrankenPHP + Caddy + PostgreSQL 16. La base de datos se expone internamente; para acceder desde host usa `make db` dentro del contenedor.

---

## Puertos y opciones de Caddy/FrankenPHP

Variables de entorno opcionales al levantar el stack (`docker compose up`):

| Variable                       | Descripción                                                                     | Default     |
|--------------------------------|---------------------------------------------------------------------------------|-------------|
| `HTTP_PORT`                    | Puerto HTTP                                                                     | `80`        |
| `HTTPS_PORT`                   | Puerto HTTPS                                                                    | `443`       |
| `HTTP3_PORT`                   | Puerto HTTP/3 (UDP)                                                             | `443`       |
| `SERVER_NAME`                  | Nombre/dirección del server Caddy                                              | `localhost` |
| `CADDY_GLOBAL_OPTIONS`         | Bloque de [global options](https://caddyserver.com/docs/caddyfile/options#global-options), una por línea |             |
| `CADDY_EXTRA_CONFIG`           | Snippet o named-routes, una por línea                                           |             |
| `CADDY_SERVER_EXTRA_DIRECTIVES`| Directivas del server block                                                      |             |
| `CADDY_SERVER_LOG_OPTIONS`     | Bloque de server log options, una por línea                                     |             |
| `FRANKENPHP_CONFIG`            | Directivas globales FrankenPHP, una por línea                                   |             |
| `FRANKENPHP_WORKER_CONFIG`     | Directivas del worker FrankenPHP, una por línea                                 |             |

> Tip: persiste estas variables en `.env` para no pasarlas en cada arranque.
> Nota: Let's Encrypt solo soporta los puertos estándar 80/443. Usar otros puertos impide emitir certificados públicos.

Ejemplo:

```console
HTTP_PORT=8000 HTTPS_PORT=4443 HTTP3_PORT=4443 docker compose up --wait
```

---

## TLS local

### Confiar la CA auto-firmada

Caddy genera una CA local en el contenedor. Para trusted tu máquina host:

**Linux:**
```console
docker cp $(docker compose ps -q php):/data/caddy/pki/authorities/local/root.crt /usr/local/share/ca-certificates/root.crt && sudo update-ca-certificates
```

**Mac:**
```console
docker cp $(docker compose ps -q php):/data/caddy/pki/authorities/local/root.crt /tmp/root.crt && sudo security add-trusted-cert -d -r trustRoot -k /Library/Keychains/System.keychain /tmp/root.crt
```

**Windows:**
```console
docker compose cp php:/data/caddy/pki/authorities/local/root.crt %TEMP%/root.crt && certutil -addstore -f "ROOT" %TEMP%/root.crt
```

### Certificados custom con mkcert

1. Instala [`mkcert`](https://github.com/FiloSottile/mkcert) localmente.
2. Crea el directorio de certs:

   ```console
   mkdir -p frankenphp/certs
   ```

3. Genera los certs para tu host (ejemplo: `server-name.localhost`):

   ```console
   mkcert -cert-file frankenphp/certs/tls.pem -key-file frankenphp/certs/tls.key "server-name.localhost"
   ```

4. Añade a `compose.override.yaml`:

   ```diff
    php:
      environment:
   +    CADDY_EXTRA_CONFIG: |
   +      https:// {
   +          tls /etc/caddy/certs/tls.pem /etc/caddy/certs/tls.key
   +      }
        # ...
      volumes:
   +    - ./frankenphp/certs:/etc/caddy/certs:ro
        # ...
   ```

5. Reinicia el servicio `php`.

### Deshabilitar HTTPS para desarrollo

```console
SERVER_NAME=http://localhost \
docker compose up --wait
```

Verifica acceso en `http://localhost`.

---

## Xdebug

La imagen dev incluye [Xdebug](https://xdebug.org/). Por defecto el step debugger está deshabilitado (overhead de performance). Se habilita incluyendo `debug` en `XDEBUG_MODE`:

**Linux/Mac:**
```console
XDEBUG_MODE=develop,debug docker compose up --wait
```

**Windows:**
```console
set XDEBUG_MODE=develop,debug&& docker compose up --wait&set XDEBUG_MODE=
```

### PhpStorm

1. Settings → PHP | Servers → nuevo server:
   - Name: `symfony` (valor usado en `PHP_IDE_CONFIG`)
   - Host: `localhost` (o el definido en `SERVER_NAME`)
   - Port: `443`
   - Debugger: `Xdebug`
   - Marca `Use path mappings`
   - Absolute path on server: `/app`
2. Run → Start Listening for PHP Debug Connections.
3. Añade `XDEBUG_SESSION=PHPSTORM` query param a la URL, o usa la extensión de navegador de Xdebug.
4. Para CLI, setea `PHP_IDE_CONFIG`:

   ```console
   XDEBUG_SESSION=1 PHP_IDE_CONFIG="serverName=symfony" php bin/console ...
   ```

### VS Code

1. Instala [PHP Tools for VS Code](https://marketplace.visualstudio.com/items?itemName=DEVSENSE.phptools-vscode).
2. Configura `.vscode/launch.json`:

   ```json
   {
     "version": "0.2.0",
     "configurations": [
       {
         "name": "Debug PHP",
         "type": "php",
         "request": "launch",
         "pathMappings": {
           "/app": "${workspaceFolder}"
         }
       }
     ]
   }
   ```

3. Run and Debug → `Debug PHP`.

### Verificación

```console
docker compose exec php php --version
```

Debe mostrar `with Xdebug v3.x.x`.

> Nota: con Dev Containers, Xdebug está pre-configurado y funciona out-of-the-box usando la launch config `Debug PHP`.

---

## Dev Containers y agentes de IA

El proyecto incluye configuración [Dev Container](https://containers.dev/) en `.devcontainer/` para ejecutar agentes de coding con autonomía dentro de un sandbox con firewall de red.

Características:
- [Claude Code](https://claude.ai/claude-code) pre-instalado en modo YOLO (bypass permissions).
- Compatible con [OpenAI Codex CLI](https://github.com/openai/codex) y [opencode](https://opencode.ai).
- Network sandbox mediante `iptables` + `ipset` + `dnsmasq` (script `.devcontainer/init-firewall.sh`).
- `.devcontainer/AGENTS.md` provee contexto del proyecto a los agentes.

### Dominios permitidos por el firewall

| Destino                                             | Motivo                          |
|-----------------------------------------------------|---------------------------------|
| GitHub (`github.com`, `api.github.com`)             | Git y API                       |
| Anthropic (`anthropic.com`)                         | Backend de Claude Code          |
| npm registry (`registry.npmjs.org`)                 | Dependencias Node               |
| jsDelivr CDN (`cdn.jsdelivr.net`)                   | CDN de paquetes npm             |
| Packagist (`packagist.org`, `repo.packagist.org`)   | Dependencias PHP/Composer       |
| VS Code Marketplace (`marketplace.visualstudio.com`) | Descarga de extensiones       |
| VS Code blobs (`vscode.blob.core.windows.net`)      | Assets de VS Code              |
| VS Code updates (`update.code.visualstudio.com`)    | Actualizaciones de VS Code     |
| Sentry (`sentry.io`)                                | Telemetría de Claude Code       |
| Statsig (`statsig.com`)                             | Telemetría de Claude Code       |
| Host gateway IP                                     | Comunicación con Docker host    |

Todo el tráfico saliente a otros destinos es **rechazado**. Inbound desde el host gateway se permite en todos los puertos; 80, 443 TCP y 443 UDP (HTTP/3) quedan abiertos a cualquier source.

### Personalizar dominios permitidos

Edita `.devcontainer/init-firewall.sh` y agrega el dominio a la línea `ipset`:

```bash
# Dominios separados por '/', terminan con el nombre del ipset
ipset=/github.com/anthropic.com/your-domain.com/allowed-domains
```

Rebuild del Dev Container para aplicar.

### Otros agentes

- **Codex CLI:** permite `api.openai.com` en el firewall, luego:

  ```console
  npm install -g @openai/codex
  export OPENAI_API_KEY=your-key
  codex --full-auto
  ```

- **opencode:** permite el dominio del provider, luego:

  ```console
  curl -fsSL https://opencode.ai/install | bash
  opencode
  ```

### Uso sin VS Code

El Dev Container funciona con cualquier herramienta compatible con la especificación:
- [Dev Container CLI](https://github.com/devcontainers/cli) (`devcontainer up`)
- [GitHub Codespaces](https://github.com/features/codespaces)
- JetBrains (con plugin Dev Containers)

Claude Code desde terminal dentro del contenedor:
```console
claude
```

YOLO desde CLI:
```console
claude --dangerously-skip-permissions
```

### Troubleshooting del Dev Container

- **Firewall bloquea un dominio:** revisa logs del firewall y agrega el dominio al allowlist de dnsmasq.
- **Container no arranca:** Docker corriendo, mínimo 2 GB RAM. El firewall requiere capability `NET_ADMIN` (configurada automáticamente en el compose).

---

## Resolución de problemas

### Permisos de archivos en Linux

Si no puedes editar archivos creados por el contenedor en el primer setup:

```console
docker compose run --rm php chown -R $(id -u):$(id -g) .
```

### Build de imágenes producción

Por defecto `docker compose up` usa `compose.yaml` + `compose.override.yaml` (modo dev). Para producción:

```console
docker compose -f compose.yaml -f compose.prod.yaml build --pull --no-cache
docker compose -f compose.yaml -f compose.prod.yaml up --wait
```

> El orden de los argumentos `-f` importa.
> Las imágenes dev y prod usan nombres distintos (`app-php-dev`, `app-php-prod`), no hay conflicto entre ellas.