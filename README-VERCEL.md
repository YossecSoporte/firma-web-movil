# Guía de integración con Vercel

Guía para desplegar **FirmEasy Web** en Vercel manteniendo compatibilidad con Docker local.

---

## 1. Requisitos

- Cuenta en [Vercel](https://vercel.com) (plan Hobby funciona)
- Repo de GitHub conectado a Vercel (rama `firma-integracion2` u otra)
- PHP runtime: `vercel-php@0.7.4` (PHP 8.3)
- Composer: se usará `php>=8.1` con ext-openssl/curl/json (ver `composer.json`)

---

## 2. Estructura clave para Vercel

```
firma-web-movil/
├── index.html              ← Frontend estático que sirve Vercel (ruta /)
├── latam.html              ← Frontend LATAM
├── integracion.html        ← Frontend Enterprise X25519
├── autentificacion.html    ← Frontend auth con DNIe
├── callbacks.html          ← Dashboard de callbacks
├── keys.html               ← Gestión de claves X25519
├── pesados.html            ← Batch de PDFs pesados
├── actualizacion.html      ← Gestión de releases (instaladores)
├── app-no-instalada.html   ← Fallback deep link
├── vercel.json             ← Funciones, rewrites y headers
├── api/                    ← Endpoints PHP (una función por archivo)
│   ├── _lib/store.php      ← Cliente REST Vercel Blob (auto-detect)
│   ├── _lib/storage.php    ← Capa alta storage (auto-detect Blob/disco)
│   └── *.php               ← Todos los endpoints (ver vercel.json)
├── token/api/v1/           ← Proxies hacia FirmEasy Enterprise
└── document/               ← PDFs originales (vía includeFiles)
```

**Frontends:** se sirven como **estáticos** con extensión `.html` (los `.php` equivalentes son para Docker local). No usar `index.php` como función — Vercel lo descargaría en vez de mostrar HTML. Los rewrites mapean `/latam` → `/latam.html`, etc.

---

## 3. Variables de entorno (Settings → Environment Variables)

| Variable | Valor | Entornos |
|---|---|---|
| `ENCRYPTION_KEY` | Clave AES-256-GCM compartida con la app móvil | Production, Preview |
| `BASE_URL_EXTERNO` | URL pública del deploy, **sin `/` final** | Production, Preview |
| `FIRMEASY_API_KEY` | API Key FirmEasy Enterprise (token batch/proxies) | Production, Preview |
| `UPLOAD_RELEASE_TOKEN` | Token para subir instaladores vía `/api/upload-release.php` | Production, Preview |

⚠️ `BASE_URL_EXTERNO` se usa para construir los deep links — si está mal la app móvil rechaza el enlace.

---

## 4. Storage: Vercel Blob (obligatorio)

Vercel tiene filesystem de solo lectura — jobs, claves, callbacks, PDFs firmados, auth y releases se guardan en **Vercel Blob**.

1. Proyecto → **Storage** → **Create Database** → **Blob** (nombre `firmeasy-blob`, acceso **Private**)
2. **Connect to Project** → Production + Preview → marcar **"Add a read-write token env var"**
3. **Deployments → ⋮ → Redeploy** para que surtan efecto las variables

> Nuestro código acepta token RW (`BLOB_READ_WRITE_TOKEN`) u OIDC (`VERCEL_OIDC_TOKEN`). `api/_lib/storage.php` auto-detecta.

### Qué se guarda en cada prefijo del Blob

| Prefijo | Contenido | Endpoints |
|---|---|---|
| `jobs/` | Jobs de firma `{job}.json` | generar-uri, generar-uri-x25519, job, token, download, upload-signed, resend-callback, callback, session, batch-token |
| `keys/` | Claves públicas X25519 `{kid}.json` | register-key, list-keys, delete-key, generar-uri-x25519, session |
| `firmeasy_keys.json` | Par ECDH de FirmEasy | generar-uri-x25519 |
| `sha256_cache.json` | Caché SHA-256 de PDFs | generar-uri, generar-uri-x25519, populate-sha256-cache |
| `callbacks/` | Logs `{job}_{ts}.json` + `{job}_summary.json` | callback, list-callbacks |
| `auth_jobs/` | Jobs de auth `{job}.json` | generar-auth, auth/challenge, auth/submit |
| `auth_keys.json` | Par Ed25519 de auth | generar-auth |
| `auth_responses/` | Respuestas verificadas `{state}.json` | auth/submit, auth/check-verify |
| `auth_callbacks/` | Callbacks de auth `{job}_{ts}.json` | auth/callback |
| `auth_certs/` | Certificados `{state}.pem` | auth/upload-cert, auth/submit |
| `signed/` | PDFs firmados `{base}_{user_id}.pdf` | upload-signed, list-signed, download-signed, clear-signed |
| `releases/manifests/` | Manifests de release `{code}.json` | check-update, download-release, upload-release |
| `releases/files/` | Instaladores `{code}_{name}` | download-release, upload-release |

---

## 5. Funciones definidas en `vercel.json`

Todas usan `vercel-php@0.7.4`. Las que leen `document/` declaran `includeFiles: "document/**"`.

- generar-uri.php, generar-uri-x25519.php, job.php, token.php, generar-job.php
- download.php, download-fail.php, list-pdfs.php, export-csv.php, populate-sha256-cache.php
- upload-signed.php, upload-signed-fail.php, list-signed.php, download-signed.php, clear-signed.php
- register-key.php, list-keys.php, delete-key.php, session.php
- generar-auth.php, auth/callback.php, auth/challenge.php, auth/submit.php, auth/check-verify.php, auth/upload-cert.php
- callback.php, list-callbacks.php, resend-callback.php, batch-token.php
- check-update.php, download-release.php, upload-release.php
- token/api/v1/auth/token.php, token/api/v1/meter/tick.php, token/api/v1/session/start.php, token/api/v1/session/close.php

**Rewrites:**
- `/api/job/{id}` → `/api/job.php?job={id}`
- `/api/token/{id}` → `/api/token.php?job={id}`
- Rutas limpias de frontends: `/`, `/latam`, `/integracion`, `/autentificacion`, `/callbacks`, `/keys`, `/pesados`, `/actualizacion`, `/app-no-instalada`

**Headers CORS** en `/api/(.*)`.

---

## 6. Protocolo Vercel Blob

| Operación | Endpoint | Headers |
|---|---|---|
| PUT | `https://vercel.com/api/blob/?pathname={path}` | Bearer + `x-vercel-blob-store-id` + `x-vercel-blob-access: private` + `x-content-type` + `x-add-random-suffix` + `x-api-version: 12` |
| LIST | `GET https://vercel.com/api/blob/?prefix=...&limit=1000` | Auth + store-id |
| DELETE | `POST https://vercel.com/api/blob/delete` `{"urls":[...]}` | Auth + store-id |
| GET | `https://{storeId}.private.blob.vercel-storage.com/{path}` | Auth |

Las URLs directas de un store privado dan **403 en navegador** — los PDFs firmados se sirven vía `download-signed.php` (proxy con autenticación).

---

## 7. Deploy

1. Push a la rama conectada → deploy automático
2. Producción: **Deployments → ⋮ → Promote to Production**

---

## 8. Límites del plan Hobby

| Límite | Valor |
|---|---|
| Body de requests | 4.5 MB |
| Blob storage | 1 GB |
| Operaciones simples | 10k/mes |
| Operaciones avanzadas | 2k/mes |
| Duración función | por función en `vercel.json` |

---

## 9. Desarrollo local (Docker)

Sin credenciales de Blob → `storage.php` cae a disco:
- `storage/jobs/`, `storage/keys/`, `storage/callbacks/`, `storage/auth_*`, `storage/releases/`
- `document/signed/`

Verificar: `http://localhost:8081/` → 200.

---

## 10. Checklist de troubleshooting

| Síntoma | Causa probable |
|---|---|
| La web descarga un archivo en vez de mostrarse | Se está sirviendo `index.php`; usar `index.html` estático |
| `<br /><b>` en respuesta JSON | Warning/fatal de PHP antes del JSON |
| `Error guardando job en almacenamiento` | Credenciales de Blob ausentes → conectar store + Redeploy |
| `Blob not found` (404) en PUT | URL/host incorrecto |
| Deep link rechazado `[APP-104]` | `BASE_URL_EXTERNO` mal escrita |
| PDF firmado no aparece | `upload-signed.php` crasheó en modo Blob |
| 403 al abrir URL del Blob directamente | Normal con store privado — usar `download-signed.php` |
| Auth no verifica | `auth_keys.json` y pares `auth_jobs/` deben estar en Blob |
| Instalador no aparece | `UPLOAD_RELEASE_TOKEN` inválido o `releases/` vacío en Blob |