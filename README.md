# FirmEasy Web — Deep Link Launcher

Página web (PHP + Nginx en Docker) que genera jobs de firma y lanza la app móvil FirmEasy mediante deep link cifrado `firmeasy://sign?data=BLOB`.

## Estructura

```
firma-web-movil/
├── index.php                  # Frontend principal (tabla responsive de documentos)
├── app-no-instalada.php       # Fallback si la app móvil no está instalada
├── docker-compose.yml         # Orquestación Docker (puerto 8081:80)
├── Dockerfile                 # Imagen PHP-FPM + Nginx + Supervisor (Alpine)
├── docker/
│   ├── nginx.conf             # Configuración Nginx (rutas API)
│   ├── supervisord.conf       # Supervisor para PHP-FPM + Nginx
│   └── php.ini                # Configuración PHP
├── api/
│   ├── generar-uri.php        # POST: crea job y devuelve URI firmeasy:// completa
│   ├── job.php                # GET /api/job/{job}: devuelve config del job
│   ├── token.php              # GET /api/token/{job}: legacy
│   ├── generar-job.php        # Legacy (no lo usa el frontend)
│   ├── list-pdfs.php          # GET: lista PDFs originales en document/
│   ├── download.php           # GET: descarga PDF original
│   ├── upload-signed.php      # POST: recibe PDF firmado en BINARIO
│   ├── list-signed.php        # GET: lista PDFs firmados en document/signed/
│   ├── download-signed.php    # GET: descarga PDF firmado
│   └── clear-signed.php       # POST: elimina todos los PDFs firmados
├── document/                 # PDFs originales a firmar
│   ├── doc_prueba1.pdf
│   ├── doc_prueba2.pdf
│   └── test.pdf               # PDF fake de prueba
├── document/signed/           # PDFs firmados subidos por la app móvil
├── storage/jobs/              # Jobs en JSON (uno por archivo {uuid}.json)
├── test_payload.json          # Payload de prueba para POST /api/generar-uri.php
├── INTEGRACION_API.md         # Documentación de integración para backends externos
├── INTEGRACION_COMPLETA.md    # Guía completa de integración
└── README_programador.md      # Documentación técnica para app móvil
```

## Producción con Docker

```bash
# Construir y levantar
docker-compose up -d --build

# Ver logs
docker-compose logs -f

# Parar
docker-compose down
```

La web queda en `http://localhost:8081` (accesible desde el móvil en la misma red usando la IP del host).

### Variables de entorno

| Variable | Descripción | Ejemplo |
|---|---|---|
| `BASE_URL_EXTERNO` | URL base que la app móvil usará para consultar el job | `http://localhost:8081` |
| `ENCRYPTION_KEY` | Clave base64 de 32 bytes para AES-256-GCM | Generar con `openssl rand -base64 32` |

## Flujo de Firma

```
1. Usuario abre http://localhost:8081/ en el navegador
2. Selecciona un PDF (se lista desde /api/list-pdfs.php)
3. Toca "Firmar" → modal pide Token y Tipo de certificado
4. Frontend hace POST /api/generar-uri.php
5. Backend genera job (UUID v4) + exp (10 min) + guarda JSON
6. Backend cifra URI completa con AES-256-GCM
7. Backend retorna uri_encrypted (blob)
8. Frontend construye deep link: firmeasy://sign?data={BLOB}
9. Navegador dispara deep link → abre app móvil
10. App móvil descifra blob, obtiene job/exp/token
11. App móvil hace GET /api/job/{job} para obtener from/to
12. App móvil descarga PDF, firma, sube a /api/upload-signed.php
13. Al volver a la web, lista se refresca (Pendiente → Firmado)
```

## API

### POST /api/generar-uri.php

Crea job y retorna URI cifrada.

**Request:**

```json
{
  "configuration": {
    "signature_type": "basic",
    "signature_reason": "Acepto el contenido del documento",
    "generate_request": "NOMBRE EMPRESA",
    "certificate_type": "all"
  },
  "token": "TOKEN_USUARIO",
  "documents": [
    {
      "file": "documento.pdf",
      "user_id": "USER123",
      "doc_sha256": "",
      "settings": {
        "vis_sig_x": 340,
        "vis_sig_y": 693,
        "vis_sig_width": 155,
        "vis_sig_height": 55,
        "vis_sig_page": 1,
        "vis_sig_text_size": 10,
        "vis_sig_text": "Firmado digitalmente por:\n<SIGNER>\nFecha: <DATE>\nOU: <OU>\nFirmado con FirmEasy\nMotivo: {{signature_reason}}",
        "vis_sig_graphic": "http://imagen-firma.com/logo.png"
      }
    }
  ]
}
```

**Response:**

```json
{
  "uri_encrypted": "BASE64URL_BLOB",
  "uri_plain": "firmeasy://sign?data=http%3A%2F%2Flocalhost%3A8081%2Fapi%2Fjob%2F{uuid}&exp={ts}&token={token}",
  "job": "uuid-v4",
  "exp": 1786978911,
  "data": "BASE64URL_BLOB"
}
```

**Deep link final:** `firmeasy://sign?data=BASE64URL_BLOB`

### GET /api/job/{job}

Devuelve la configuración completa del job (con `from`, `to`, `doc_sha256`, `settings`).

### GET /api/download.php?file={archivo.pdf}

Descarga segura del PDF (bloquea path traversal, solo `.pdf`, máximo 20 MB).

### POST /api/upload-signed.php?file={archivo.pdf}&user_id={id}

Recibe el PDF firmado en binario (`php://input`).

### GET /api/list-signed.php

Lista PDFs firmados. Filtro opcional: `?original={archivo.pdf}`.

### POST /api/clear-signed.php?confirm=1

Elimina todos los PDFs firmados.

## Encriptación (AES-256-GCM)

**Algoritmo:** AES-256-GCM
**Formato blob:** `base64url( IV(12 bytes) || CIPHERTEXT || TAG(16 bytes) )`
**Clave:** `ENCRYPTION_KEY` (base64, 32 bytes) - misma en backend y app móvil

**Contenido del blob descifrado:**

```
firmeasy://sign?data={JOB_URL_ENCODED}&exp={UNIX_TS}&token={USER_TOKEN}
```

## Prueba rápida

```bash
# Generar key de encriptación (una vez)
openssl rand -base64 32

# Levantar con la key en docker-compose.yml
docker-compose up -d --build

# Generar job + URI
$body = Get-Content test_payload.json -Raw
Invoke-RestMethod -Uri "http://localhost:8081/api/generar-uri.php" -Method Post `
  -ContentType "application/json" -Body $body

# Consultar un job
Invoke-RestMethod -Uri "http://localhost:8081/api/job/{JOB_ID}"
```

## Documentación

- **[INTEGRACION_COMPLETA.md](INTEGRACION_COMPLETA.md)** — Guía completa de integración para desarrolladores
- **[INTEGRACION_API.md](INTEGRACION_API.md)** — Documentación de la API para backends externos
- **[README_programador.md](README_programador.md)** — Documentación técnica para la app móvil

## Requisitos

- Docker 20.10+ / Docker Compose 2.0+
- Puerto 8081 disponible
- (Opcional) App FirmEasy instalada para pruebas reales
- Abrir puerto 8081 en firewall: `abrir_puerto_8081.bat` (Windows)
