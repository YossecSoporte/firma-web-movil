# AGENTS.md — Guía rápida para el agente (firma-web-movil)

> **Lee esto primero.** Contiene todo lo que necesitas saber del proyecto sin tener que revisar todo el código.

---

## 1. Qué es este proyecto

**FirmEasy Web** — backend web (PHP + Nginx en Docker) que:
1. Genera **jobs de firma** y lanza la **app móvil FirmEasy** mediante deep link `firmeasy://sign?data=<encrypted_blob>`.
2. Sirve los PDFs a firmar (originales) y recibe los PDFs firmados que sube la app móvil.
3. Muestra una interfaz web (tabla responsive) para que el usuario elija qué PDF firmar.

**Stack técnico:**
- PHP 8.3 (sin framework) + Nginx + Supervisor, en Docker.
- Sin base de datos — los jobs se guardan como archivos JSON en `storage/jobs/`.
- Puerto host: **8081** (mapeado al puerto 80 del contenedor).
- **Encriptación AES-256-GCM** de la URI completa (clave en `ENCRYPTION_KEY`).

---

## 2. Estructura del proyecto

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
├── api/                       # Endpoints PHP (backend)
│   ├── generar-uri.php        # POST: crea job y devuelve URI firmeasy:// completa
│   ├── job.php                # GET /api/job/{job}: devuelve config del job
│   ├── token.php              # GET /api/token/{job}: legacy
│   ├── generar-job.php        # Legacy (no lo usa el frontend)
│   ├── list-pdfs.php          # GET: lista PDFs originales en document/
│   ├── download.php           # GET: descarga PDF original (Content-Disposition: attachment)
│   ├── upload-signed.php      # POST: recibe PDF firmado en BINARIO y guarda en document/signed/
│   ├── list-signed.php        # GET: lista PDFs firmados en document/signed/
│   ├── download-signed.php    # GET: descarga PDF firmado
│   └── clear-signed.php       # POST: elimina todos los PDFs firmados (limpieza)
├── document/                 # PDFs originales a firmar
│   ├── doc_prueba1.pdf        # PDF real (25791 bytes)
│   ├── doc_prueba2.pdf        # PDF real (25791 bytes)
│   └── test.pdf               # PDF fake de prueba (25 bytes, "%PDF-1.4 fake pdf content")
├── document/signed/           # PDFs firmados subidos por la app móvil (se crea sola)
├── storage/jobs/              # Jobs en JSON (uno por archivo {uuid}.json)
├── test_payload.json          # Payload de prueba para POST /api/generar-uri.php
├── ejemplo.json               # Ejemplo de payload
├── abrir_puerto_8081.bat      # Abre puerto 8081 en firewall de Windows (profile=any)
└── cerrar_puerto_8081.bat     # Cierra la regla del firewall
```

---

## 3. Configuración clave (cambiar cuando cambias de red)

**Estado actual:** `BASE_URL_EXTERNO=http://localhost:8081` — configurado para pruebas en la **misma máquina**. Si necesitas probar el deep link desde un **móvil en la misma LAN**, cambia `localhost` por la IP del host en los 3 archivos de abajo.

**Comandos para verificar IP y puerto:**
```powershell
ipconfig | findstr /C:"IPv4"
netstat -ano | findstr ":8081" | findstr "LISTENING"
```

---

## 4. Cómo ejecutar el proyecto

```powershell
# Levantar (reconstruye la imagen)
docker-compose up -d --build

# Reiniciar (sin rebuild, conservando imagen)
docker-compose restart

# Parar
docker-compose down

# Ver logs en vivo
docker-compose logs -f

# Verificar que responde
(Invoke-WebRequest -Uri "http://localhost:8081/" -UseBasicParsing).StatusCode  # 200
```

**Volumen bind:** `./:/var/www/html` — los cambios en archivos PHP se reflejan **al instante** sin reiniciar (excepto cambios en `docker/nginx.conf` que requieren `docker-compose restart`).

---

## 5. Endpoints de la API

### Generación de firma
| Método | Ruta | Descripción |
|---|---|---|
| POST | `/api/generar-uri.php` | Crea job, devuelve `{ "uri_encrypted", "uri_plain", "job", "exp", "data" }` |
| GET | `/api/job/{job_id}` | Devuelve config completa del job |
| GET | `/api/token/{job}` | Legacy — token del job |

### PDFs originales
| Método | Ruta | Descripción |
|---|---|---|
| GET | `/api/list-pdfs.php` | Lista PDFs en `document/` (más recientes primero) |
| GET | `/api/download.php?file={nombre.pdf}` | Descarga PDF original (forzar descarga con `attachment`) |

### PDFs firmados
| Método | Ruta | Descripción |
|---|---|---|
| POST | `/api/upload-signed.php?file={nombre.pdf}&user_id={id}` | **Recibe PDF firmado en BINARIO** (`php://input`), guarda en `document/signed/{base}_{user_id}.pdf` |
| GET | `/api/list-signed.php` | Lista PDFs firmados (filtro opcional `?original={nombre.pdf}`) |
| GET | `/api/download-signed.php?file={nombre}.pdf` | Descarga un PDF firmado |
| POST | `/api/clear-signed.php?confirm=1` | **Elimina todos los PDFs de `document/signed/`** (botón Actualizar) |

### Esquema del body POST `/api/generar-uri.php` (claves en INGLÉS)

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
      "file": "doc_prueba1.pdf",
      "user_id": "USER123",
      "doc_sha256": "",
      "settings": {
        "vis_sig_x": 340, "vis_sig_y": 693, "vis_sig_width": 155, "vis_sig_height": 55,
        "vis_sig_page": 1, "vis_sig_text_size": 10,
        "vis_sig_text": "Firmado digitalmente por:\n<SIGNER>\nFecha: <DATE>\nOU: <OU>\nFirmado con FirmEasy\nMotivo: {{signature_reason}}",
        "vis_sig_graphic": "http://imagen-firma.com/logo.png"
      }
    }
  ]
}
```

**Convención de claves (importante):**
- Claves de primer/segundo nivel en **inglés**: `configuration`, `documents`, `signature_type`, `signature_reason`, `generate_request`, `certificate_type`, `file`, `user_id`, `doc_sha256`, `settings`, `from`, `to`.
- `token` (string, requerido) — lo provee el usuario final en el modal de la web.
- `configuration.certificate_type`: `"all"` | `"dni"` | `"certificado"` (default `"all"`).
- Claves de `settings` (3er nivel): **`vis_sig_*` en inglés** — NO cambiar (los consume la app móvil FirmEasy; cambiarlos rompería la integración).
- Los **valores** (textos, placeholders `<SIGNER>`/`<DATE>`/`<OU>`) pueden estar en español.
- Los `storage/jobs/*.json` viejos usaban claves en español (`configuracion`, `documentos`, `tipo_firma`) — son históricos, no se modifican.

### Jobs antiguos vs nuevos
- **Jobs viejos** (con `configuracion`/`documentos`/`tipo_firma`) están en `storage/jobs/` con claves en español.
- **Jobs nuevos** (después del renombrado) usan claves en inglés.
- La app móvil debe poder leer ambos formatos durante la transición, o solo leer el nuevo (los viejos expiran en 10 min).

---

## 6. Flujo completo de firma

1. Usuario abre `http://<IP>:8081/` en el navegador → ve la tabla de documentos (o cards en móvil)
2. Pulsa **Firmar** en un documento → modal pide **Token** y **Tipo de certificado** → `index.php` hace `POST /api/generar-uri.php`
3. El backend genera `job` (UUID v4) + `exp` (10 min) + `token`, guarda `storage/jobs/{job}.json`, y **cifra la URI completa con AES-256-GCM** → devuelve `firmeasy://sign?data=<BASE64URL_BLOB>`
4. El navegador dispara el deep link → abre la app móvil FirmEasy
5. La app móvil **descifra el blob** (clave `ENCRYPTION_KEY`) → obtiene `job` + `exp` + `token`; consulta `GET /api/job/{job}` para `from` (descarga PDF) y `to` (subida PDF firmado)
6. La app móvil firma el PDF y lo **sube en BINARIO** a `/api/upload-signed.php?file={nombre}&user_id={id}`
7. El backend lo guarda en `document/signed/{base}_{user_id}.pdf`
8. Al volver a la web, la lista se recarga y el documento pasa de **Pendiente → Firmado**, aparece el botón **Ver PDF firmado**
9. Si quiere empezar de cero, pulsa **Actualizar** → `clear-signed.php` elimina todos los firmados → recarga la lista

### Deep link y cifrado (AES-256-GCM)

El deep link final es `firmeasy://sign?data=<BASE64URL_BLOB>` — **todo** (data/job, exp, token) viaja cifrado, no hay parámetros en claro.

- **Algoritmo:** AES-256-GCM
- **Formato blob:** `base64url( IV(12 bytes) || CIPHERTEXT || TAG(16 bytes) )`
- **Clave:** `ENCRYPTION_KEY` (base64 de 32 bytes), compartida backend ↔ app móvil
- **Contenido descifrado:** `firmeasy://sign?data={URL_ENCODED_DE_GET_/api/job/{job}}&exp={unix_ts}&token={token_del_usuario}`

**Validaciones de la app móvil al descifrar:** verificar `exp` (`exp < time()` → rechazar), hacer `GET {data}` (URL decodificada) para obtener `from`/`to`, descargar PDF de `from`, firmar y subir a `to`.

**Constantes fijas:**
- `EXPIRACION_SEGUNDOS = 600` (10 min)

---

## 7. Diseño del frontend (`index.php`)

**Responsive con dos vistas:**
- **Escritorio** (≥640px): tabla con columnas Documento | Tamaño | Estado | Acciones
- **Móvil** (<640px): cards apiladas con botones a ancho completo (breakpoint `@media max-width: 640px`)

**Estados visuales:**
- **Pendiente** (badge amarillo `#fff3cd/#856404`)
- **Firmado** (badge verde `#d4edda/#155724`)

**Botones por documento:**
- **Ver PDF** (gris) → abre `/api/download.php?file=...` en nueva pestaña (descarga directa con `Content-Disposition: attachment`)
- **Firmar** (azul `#0066cc`) → abre modal pidiendo **Token** + **Tipo de certificado** (`all`/`dni`/`certificado`), llama a `/api/generar-uri.php` y dispara deep link
- **Ver PDF firmado** (verde `#28a745`, solo habilitado si ya existe) → abre `/api/download-signed.php?file=...`

**Botón Actualizar:**
- Llama a `/api/clear-signed.php?confirm=1` (POST) → elimina todos los PDFs de `document/signed/`
- Luego recarga la lista (`loadPdfList`)
- El icono gira (clase `.spin`, animation `@keyframes spin`) durante la operación
- Muestra toast "Documentos limpiados. Estado restaurado." por 3s

**Colores principales:**
- Azul primario: `#0066cc`
- Gris fondo: `#f5f5f5`
- Blanco tarjeta: `#fff`
- Sombra: `0 2px 12px rgba(0,0,0,.08)`

---

## 8. Firewall (Windows)

La regla del firewall se llama **"FirmEasy Web (Puerto 8081)"**.

**Estado actual:**
- Regla habilitada, TCP, localport 8081, action=allow
- Pero solo cubre perfiles **Dominio,Privada**
- La red actual ("Red 146") está clasificada como **Pública** → hay que ampliar la regla

**Para arreglar (ejecutar como Administrador):**

Opción A (recomendada, rápida) — cambiar la red a Privada:
```powershell
Set-NetConnectionProfile -InterfaceAlias "Ethernet 2" -NetworkCategory Private
```

Opción B — recrear la regla con `profile=any`:
```powershell
# Como admin, ejecutar:
abrir_puerto_8081.bat
# (ya actualizado para hacer delete + add con profile=any)
```

**Comando para ver estado actual del firewall:**
```powershell
netsh advfirewall firewall show rule name="FirmEasy Web (Puerto 8081)"
```

---

## 9. Convenciones de código

- **Sin framework** — PHP puro, cada endpoint es un archivo `.php` en `api/`.
- **Headers CORS** en todos los endpoints: `Access-Control-Allow-Origin: *` + `Access-Control-Allow-Methods` + `Access-Control-Allow-Headers`. OPTIONS → `204`.
- **Seguridad en download.php / download-signed.php:**
  - Validación con regex `^[a-zA-Z0-9._-]+$` para el nombre de archivo
  - Bloquea path traversal (`..`)
  - Valida extensión `.pdf`
  - Verifica `realpath()` dentro del directorio permitido (defensa en profundidad)
  - Máximo 20 MB
  - Soporta range requests ( continuation de descargas)
- **upload-signed.php (subida binaria):**
  - Recibe el PDF en `php://input` (raw bytes), NO multipart
  - Valida magic bytes `%PDF-` al inicio
  - `Content-Type` esperado: `application/pdf` o `application/octet-stream`
  - Guarda como `{base}_{user_id}.pdf` en `document/signed/`
- **Jobs:** almacenados como JSON en `storage/jobs/{uuid}.json`, sin base de datos.

---

## 10. Modelo de negocio (SaaS multi-cliente)

**Modelo objetivo:** la app móvil FirmEasy la provee el dueño del proyecto (ventas). Los clientes (empresas) integran el servicio desde sus propios backends.

**Flujo SaaS ideal (aún no implementado completamente):**
1. El backend del cliente hace `POST /api/generar-uri.php` con su **API Key** (pendiente de implementar).
2. Tu backend genera job + URI completa y la devuelve al cliente.
3. El cliente solo redirecciona/lanza esa URI desde su sistema hacia el móvil del usuario final.

**Pendiente para el modelo SaaS:**
- **API Keys por cliente** — hoy cualquiera puede llamar a `/api/generar-uri.php` sin autenticación. Necesario para facturación/auditoría.
- **Validación estricta del `data` cifrado (invalidación use-once)** — hoy un job puede consultarse varias veces con el mismo blob. Implementación futura: marcar job como `consumido` tras el primer uso, devolver `410` si se reintenta.

---

## 11. Tareas pendientes / TODOs conocidos

- [ ] Implementar **API Keys por cliente** para multi-tenancy (SaaS)
- [ ] Implementar **invalidación use-once del job** en `job.php` / `download.php` (devolver `410` tras el primer consumo)
- [ ] Probar la **firma real** con la app móvil FirmEasy (deep link `firmeasy://sign?data=...`) — requiere que el móvil alcance el backend por LAN (cambiar `localhost` por la IP en `BASE_URL_EXTERNO`)
- [ ] Considerar renombrar placeholders `<SIGNER>`/`<DATE>`/`<OU>` → `<FIRMANTE>`/`<FECHA>`/`<OU>` en `vis_sig_text` (requiere coordinar con la app móvil)
- [ ] Eliminar endpoints legacy (`generar-job.php`, `token.php`) si ya no se usan

---

## 12. Comandos rápidos de prueba

```powershell
# Verificar servicio
(Invoke-WebRequest -Uri "http://localhost:8081/" -UseBasicParsing).StatusCode  # → 200

# Listar PDFs originales
(Invoke-WebRequest -Uri "http://localhost:8081/api/list-pdfs.php" -UseBasicParsing).Content

# Listar PDFs firmados
(Invoke-WebRequest -Uri "http://localhost:8081/api/list-signed.php" -UseBasicParsing).Content

# Generar job de firma
$body = Get-Content "C:\laragon\www\firma-web-movil\test_payload.json" -Raw
$resp = Invoke-RestMethod -Uri "http://localhost:8081/api/generar-uri.php" -Method Post -ContentType "application/json" -Body $body
$resp | ConvertTo-Json -Depth 5

# Subir PDF firmado (binario, simula la app móvil)
$bytes = [System.IO.File]::ReadAllBytes("C:\laragon\www\firma-web-movil\document\doc_prueba1.pdf")
Invoke-RestMethod -Uri "http://localhost:8081/api/upload-signed.php?file=doc_prueba1.pdf&user_id=USER123" -Method Post -ContentType "application/pdf" -Body $bytes

# Limpiar todos los PDFs firmados
(Invoke-WebRequest -Uri "http://localhost:8081/api/clear-signed.php?confirm=1" -Method POST -UseBasicParsing).Content
```

---

## 13. Cabos sueltos / advertencias

- **`test.pdf`** es un PDF fake de 25 bytes (`%PDF-1.4 fake pdf content`). No es un PDF válido real — solo está para pruebas. Déjalo a menos que se pida lo contrario.
- **Docker Desktop** en Windows expone correctamente los puertos publicados en `0.0.0.0`, así que el contenedor es accesible desde la LAN.
- **Volumen bind** (`./:/var/www/html`) — los cambios en archivos se reflejan al instante; solo los cambios en `docker/nginx.conf` requieren `docker-compose restart`.
- **Repositorio git:** sí hay `.git`, rama activa `firma-integracion`.
- El puerto 8081 está en uso por `com.docker.backend.exe` (Docker) — es este contenedor.
