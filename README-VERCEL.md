# Guía de integración con Vercel

Esta guía explica cómo desplegar **FirmEasy Web** en Vercel y cómo está configurado el proyecto para funcionar tanto en Docker local como en Vercel.

---

## 1. Requisitos

- Cuenta en [Vercel](https://vercel.com) (plan Hobby funciona)
- Repo de GitHub conectado a Vercel
- PHP runtime: `vercel-php@0.7.4` (PHP 8.3)

---

## 2. Estructura del proyecto para Vercel

```
firma-web-movil/
├── index.html              ← Frontend estático que sirve Vercel
├── index.php               ← Frontend para Docker local (mismo contenido)
├── vercel.json             ← Configuración de funciones y rewrites
├── api/                    ← Endpoints PHP (uno por función Vercel)
│   ├── _lib/store.php      ← Cliente REST de Vercel Blob (auto-detecta Blob/disco)
│   ├── generar-uri.php
│   ├── job.php
│   ├── upload-signed.php
│   ├── list-pdfs.php
│   ├── download.php
│   ├── list-signed.php
│   ├── download-signed.php
│   └── clear-signed.php
└── document/               ← PDFs originales (incluidos vía includeFiles)
```

**Puntos clave:**
- El frontend se sirve como **estático** desde `index.html`. NO usar `index.php` como función — Vercel lo descarga como archivo en vez de mostrar HTML.
- Todas las funciones deben vivir dentro de `api/`.
- `api/_lib/store.php` auto-detecta el entorno: si existen credenciales de Vercel Blob usa Blob, si no, disco local (`storage/jobs/`, `document/signed/`).

---

## 3. Variables de entorno (Settings → Environment Variables)

| Variable | Valor | Entornos |
|---|---|---|
| `ENCRYPTION_KEY` | Clave AES-256-GCM compartida con la app móvil | Production, Preview |
| `BASE_URL_EXTERNO` | URL pública del deploy, **sin `/` final** (ej: `https://firma-web-movil.vercel.app`) | Production, Preview |
| `FIRMEASY_API_KEY` | API Key de FirmEasy Enterprise (para token batch) | Production, Preview |

⚠️ `BASE_URL_EXTERNO` debe ser la URL completa y correcta — el deep link se construye con ella. Si está mal escrita la app móvil rechaza el enlace.

---

## 4. Storage: Vercel Blob (obligatorio)

Vercel tiene filesystem de solo lectura — los jobs y PDFs firmados se guardan en **Vercel Blob**.

### Crear y conectar el store

1. Proyecto → pestaña **Storage** → **Create Database** → **Blob**
2. Nombre (ej: `firmeasy-blob`) → acceso **Private**
3. Región cercana a tus usuarios
4. Conectar al proyecto:
   - Store → pestaña **Projects** → **Connect to Project**
   - Environments: ✅ Production ✅ Preview
   - ✅ Marcar **"Add a read-write token env var to this connection"**

> ⚠️ Por defecto Vercel conecta con **OIDC** (`BLOB_STORE_ID` + `VERCEL_OIDC_TOKEN`). Nuestro código soporta ambos modos (RW token u OIDC). Si la conexión no crea el token, desconecta y reconecta marcando la casilla del token.

5. **Deployments → ⋮ → Redeploy** para que las variables surtan efecto

### Verificar conexión

Abrir `/api/debug-env.php` (endpoint de diagnóstico), debe responder:

```json
{ "rw_token_set": true, "oidc_token_set": "...", "store_id": "store_xxx" }
```

Eliminar este endpoint cuando no se necesite.

---

## 5. Protocolo Vercel Blob usado (store.php)

El cliente REST replica exactamente lo que hace el SDK oficial `@vercel/blob`:

| Operación | Endpoint | Headers |
|---|---|---|
| PUT (escribir) | `PUT https://vercel.com/api/blob/?pathname={path}` | `Authorization: Bearer {token}` + `x-vercel-blob-store-id` + `x-vercel-blob-access: private` + `x-content-type` + `x-add-random-suffix` |
| LIST | `GET https://vercel.com/api/blob/?prefix=...&limit=1000` | Auth + store-id |
| DELETE | `POST https://vercel.com/api/blob/delete` con body `{"urls": [...]}` | Auth + store-id |
| GET (leer) | `GET https://{storeId}.private.blob.vercel-storage.com/{path}` | Auth |

Notas importantes aprendidas:
- Las escrituras/listados/borrados van al **control-plane**, no directo al CDN del store.
- Stores privados leen desde host `.private.blob.vercel-storage.com`.
- La URL directa del Blob da **403 Forbidden** en el navegador (store privado) — los PDFs se sirven vía `download-signed.php` que hace proxy con autenticación.
- Header `x-api-version: 12` requerido.
- El pathname en el query va URL-encoded (`jobs%2Fxxx.json`) pero las barras del path de lectura NO se codifican.

---

## 6. Deploy

### Desde GitHub (recomendado)

1. Push a la rama conectada → Vercel hace deploy automático
2. Para producción: **Deployments → ⋮ → Promote to Production**

### Dominios

- Producción: `https://{proyecto}.vercel.app`
- Previews: `https://{proyecto}-{hash}-...vercel.app`

> Los previews pueden tener **Deployment Protection** (login de Vercel). Desactívalo en Settings → Deployment Protection, o valida siempre en producción.

---

## 7. Límites del plan Hobby

| Límite | Valor |
|---|---|
| Body de requests | 4.5 MB (MAX_FILE_SIZE reducido a 4 MB en `upload-signed.php`) |
| Blob storage | 1 GB |
| Operaciones simples | 10k/mes |
| Operaciones avanzadas | 2k/mes |
| Duración función | configurada por función en `vercel.json` |

---

## 8. Desarrollo local (Docker)

```powershell
docker-compose up -d --build      # puerto 8081
```

En local no hay credenciales de Blob → `store.php` cae automáticamente a disco:
- Jobs → `storage/jobs/*.json`
- Firmados → `document/signed/`

Variables en `docker-compose.yml`: `ENCRYPTION_KEY`, `BASE_URL_EXTERNO=http://localhost:8081`, `FIRMEASY_API_KEY`.

---

## 9. Checklist de troubleshooting

| Síntoma | Causa probable |
|---|---|
| La web descarga un archivo en vez de mostrarse | Se está sirviendo `index.php`; usar `index.html` estático |
| `<br /><b>` en respuesta JSON | Warning/fatal de PHP antes del JSON — revisar logs de la función |
| `Error guardando job en almacenamiento` / `storage/jobs` no existe | Credenciales de Blob ausentes → conectar store + Redeploy |
| `Blob not found` (404) en PUT | URL/host incorrecto — escribir vía control-plane |
| `Cannot use public access on a private store` | Falta header `x-vercel-blob-access: private` |
| `Invalid pathname` (400) | Falta `x-api-version` o pathname mal formado |
| Deep link rechazado por la app `[APP-104]` | `BASE_URL_EXTERNO` mal escrita o vacía |
| PDF firmado no aparece en la web | `upload-signed.php` crasheó — revisar que no use constantes de disco en modo Blob |
| Botón Actualizar no borra nada | Doble slash en endpoint delete (`blob//delete`) |
| 403 Forbidden al abrir URL del Blob directamente | Normal con store privado — descargar vía `download-signed.php` |
