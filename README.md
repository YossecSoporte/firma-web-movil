# FirmEasy Web - Deep Link Launcher

Página web (PHP + Nginx) que genera un job de firma y lanza la app móvil FirmEasy mediante deep link `firmeasy://`.

## Estructura

```
.
├── index.php              # Página principal con botón de firma + modal token/certificado
├── app-no-instalada.php   # Fallback si la app no está instalada
├── docker-compose.yml     # Orquestación Docker (puerto host 8081)
├── Dockerfile             # Imagen PHP-FPM + Nginx + Supervisor
├── docker/
│   ├── nginx.conf         # Configuración Nginx (rutas API + PHP-FPM)
│   ├── supervisord.conf   # Supervisor para PHP-FPM + Nginx
│   └── php.ini            # Configuración PHP
├── api/
│   ├── generar-uri.php    # POST: crea el job y devuelve URI encriptada
│   ├── job.php            # GET /api/job/{job}: devuelve la config del job
│   ├── token.php          # GET /api/token/{job}: legacy, token del job
│   ├── generar-job.php    # Legacy (ya no lo usa el frontend)
│   ├── list-pdfs.php      # GET: lista PDFs en document/
│   └── download.php       # GET: descarga un PDF de document/ con seguridad
├── storage/jobs/          # Jobs en JSON (sin base de datos)
├── document/              # PDFs a firmar
├── test_payload.json      # Payload de prueba para POST /api/generar-uri.php
��── ejemplo.json           # Ejemplo de payload
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

- `BASE_URL_EXTERNO`: base URL que la app móvil usará para consultar el job y descargar/ subir PDFs. Debe apuntar a este mismo servicio (ej. `http://localhost:8081`) o al backend externo si fuera el caso. Si no se define, `generar-uri.php` usa como fallback `http://localhost:8081`.
- `ENCRYPTION_KEY`: clave base64 de 32 bytes para encriptar la URI completa con AES-256-GCM. **Requerida**. Generar con: `openssl rand -base64 32`

## Flujo

1. Usuario abre `http://localhost:8081/` en el navegador móvil
2. Selecciona un PDF (se lista desde `/api/list-pdfs.php`)
3. Toca "Firmar" → se abre modal pidiendo **Token** y **Tipo de certificado** (all/dni/certificado)
4. Al confirmar, `index.php` hace `POST /api/generar-uri.php` con `token` y `certificate_type`
5. El API valida el archivo en `document/`, calcula `doc_sha256`, genera `job` (UUID v4) + `exp` (10 min), guarda `storage/jobs/{job}.json`
6. Construye URI plano: `firmeasy://sign?job={jobUrl}&exp={exp}&token={userToken}`
7. Encripta la URI completa con AES-256-GCM → blob base64url(IV||CT||TAG)
8. Devuelve `{ "uri_encrypted": "blob", "uri_plain": "...", "job": "...", "exp": ..., "data": "blob" }`
9. Frontend construye deep link final: `firmeasy://sign?data=BLOB`
10. El navegador dispara el deep link
11. La app móvil descifra el blob, extrae `job`, `exp`, `token`, consulta `GET /api/job/{job}` para obtener `from` y `to`, y procede con la firma

## API

### POST /api/generar-uri.php

Body:

```json
{
  "configuration": {
    "signature_type": "basic",
    "signature_reason": "Acepto el contenido del documento",
    "generate_request": "NOMBRE EMPRESA",
    "certificate_type": "all"
  },
  "token": "TOKEN_USUARIO_REQUERIDO",
  "documents": [
    {
      "file": "doc_prueba1.pdf",
      "user_id": "USER123",
      "doc_sha256": "",
      "settings": { "vis_sig_x": 340 }
    }
  ]
}
```

- `configuration.certificate_type`: `"all"` | `"dni"` | `"certificado"` (default: `"all"`)
- `token`: string, requerido, lo provee el usuario en la UI

Respuesta:

```json
{
  "uri_encrypted": "BASE64URL_BLOB",
  "uri_plain": "firmeasy://sign?job=...&exp=...&token=...",
  "job": "uuid-v4",
  "exp": 1786664133,
  "data": "BASE64URL_BLOB"
}
```

**Deep link final:** `firmeasy://sign?data=BASE64URL_BLOB`

### GET /api/job/{job}

Devuelve la configuración completa del job (con `from`, `to`, `doc_sha256`, `settings`, `configuration.certificate_type`).
Errores: `404` no existe, `410` expirado.

### GET /api/download.php?file={archivo.pdf}

Descarga segura del PDF (bloquea path traversal, solo `.pdf`, máximo 20 MB, soporta ranges).

### GET /api/list-pdfs.php

Lista los PDFs de `document/` ordenados por fecha de modificación.

## Encriptación (para app móvil)

**Algoritmo:** AES-256-GCM
**Formato blob:** `base64url( IV(12 bytes) || CIPHERTEXT || TAG(16 bytes) )`
**Clave:** `ENCRYPTION_KEY` (base64, 32 bytes) - misma en backend y app móvil

**Descifrado (pseudocódigo):**
```kotlin
// Kotlin
val decoded = Base64.getUrlDecoder().decode(blob)
val iv = decoded.copyOfRange(0, 12)
val tag = decoded.copyOfRange(decoded.size - 16, decoded.size)
val ct = decoded.copyOfRange(12, decoded.size - 16)
val cipher = Cipher.getInstance("AES/GCM/NoPadding")
cipher.init(Cipher.DECRYPT_MODE, SecretKeySpec(key, "AES"), GCMParameterSpec(128, iv))
val plaintext = cipher.doFinal(ct + tag)
// plaintext = "firmeasy://sign?job=...&exp=...&token=..."
```

```csharp
// C#
var decoded = Base64UrlDecode(blob);
var iv = decoded[..12];
var tag = decoded[^16..];
var ct = decoded[12..^16];
var aes = new AesGcm(key);
var plaintext = new byte[ct.Length];
aes.Decrypt(iv, ct, tag, plaintext);
```

## Prueba rápida

```bash
# Generar key de encriptación (una vez)
openssl rand -base64 32

# Levantar con la key en docker-compose.yml
docker-compose up -d --build

# Generar job + URI (desde Windows PowerShell)
$body = Get-Content test_payload.json -Raw
Invoke-RestMethod -Uri "http://localhost:8081/api/generar-uri.php" -Method Post `
  -ContentType "application/json" -Body $body

# Consultar un job
Invoke-RestMethod -Uri "http://localhost:8081/api/job/{JOB_ID}"
```

## Requisitos

- Docker 20.10+ / Docker Compose 2.0+
- Móvil en la misma red que el host (para probar el deep link)
- App FirmEasy instalada en el móvil para probar la firma real
- Abrir el puerto 8081 en el firewall de Windows: `abrir_puerto_8081.bat`