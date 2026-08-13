# FirmEasy Web - Deep Link Launcher

Página web (PHP + Nginx) que genera un job de firma y lanza la app móvil FirmEasy mediante deep link `firmeasy://`.

## Estructura

```
.
├── index.php              # Página principal con botón de firma
├── app-no-instalada.php   # Fallback si la app no está instalada
├── docker-compose.yml     # Orquestación Docker (puerto host 8081)
├── Dockerfile             # Imagen PHP-FPM + Nginx + Supervisor
├── docker/
│   ├── nginx.conf         # Configuración Nginx (rutas API + PHP-FPM)
│   ├── supervisord.conf   # Supervisor para PHP-FPM + Nginx
│   └── php.ini            # Configuración PHP
├── api/
│   ├── generar-uri.php    # POST: crea el job y devuelve la URI firmeasy:// completa
│   ├── job.php            # GET /api/job/{job}: devuelve la config del job
│   ├── token.php          # GET /api/token/{job}: legacy, token del job
│   ├── generar-job.php    # Legacy (ya no lo usa el frontend)
│   ├── list-pdfs.php      # GET: lista PDFs en document/
│   └── download.php       # GET: descarga un PDF de document/ con seguridad
├── storage/jobs/          # Jobs en JSON (sin base de datos)
├── document/              # PDFs a firmar
├── test_payload.json      # Payload de prueba para POST /api/generar-uri.php
└── ejemplo.json           # Ejemplo de payload
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

La web queda en `http://<IP_DEL_HOST>:8081` (accesible desde el móvil en la misma red).
El host actual usa `http://10.21.132.143:8081`.

### Variable de entorno

- `BASE_URL_EXTERNO`: base URL que la app móvil usará para consultar el job y descargar/ subir PDFs. Debe apuntar a este mismo servicio (ej. `http://10.21.132.143:8081`) o al backend externo si fuera el caso. Si no se define, `generar-uri.php` usa como fallback `http://10.21.132.143:8081`.

## Flujo

1. Usuario abre `http://<IP>:8081/` en el navegador móvil
2. Selecciona un PDF (se lista desde `/api/list-pdfs.php`)
3. Toca "Firmar en FirmEasy" → `index.php` hace `POST /api/generar-uri.php`
4. El API valida el archivo en `document/`, calcula `doc_sha256`, genera `job` (UUID v4) + `nonce` + `exp` (10 min), guarda `storage/jobs/{job}.json` y devuelve la URI:

```
firmeasy://sign?job=<BASE_URL_EXTERNO/api/job/{job} urlencoded>&nonce=...&exp=...&kid=default&token=tkn_ind_...
```

5. El navegador dispara el deep link
6. Si la app está instalada → abre y recibe los parámetros; consulta `job` para obtener `from` (descarga PDF) y `to` (subida de firma)
7. Si NO está instalada → tras 3.5s redirige a `app-no-instalada.php` (enlaces a Play/App Store)

## API

### POST /api/generar-uri.php

Body simplificado:

```json
{
  "configuration": {
    "signature_type": "basic",
    "signature_reason": "Acepto el contenido del documento",
    "generate_request": "NOMBRE EMPRESA"
  },
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

Respuesta: `{ "uri", "job", "nonce", "exp", "token" }`

### GET /api/job/{job}

Devuelve la configuración completa del job (con `from`, `to`, `doc_sha256`, `settings`).
Errores: `404` no existe, `410` expirado.

### GET /api/download.php?file={archivo.pdf}

Descarga segura del PDF (bloquea path traversal, solo `.pdf`, máximo 20 MB, soporta ranges).

### GET /api/list-pdfs.php

Lista los PDFs de `document/` ordenados por fecha de modificación.

## Prueba rápida

```bash
# Generar job + URI (desde Windows PowerShell)
Invoke-RestMethod -Uri "http://10.21.132.143:8081/api/generar-uri.php" -Method Post `
  -ContentType "application/json" -Body (Get-Content test_payload.json -Raw)

# Consultar un job
Invoke-RestMethod -Uri "http://10.21.132.143:8081/api/job/{JOB_ID}"
```

## Requisitos

- Docker 20.10+ / Docker Compose 2.0+
- Móvil en la misma red que el host (para probar el deep link)
- App FirmEasy instalada en el móvil para probar la firma real
- Abrir el puerto 8081 en el firewall de Windows: `abrir_puerto_8081.bat`
