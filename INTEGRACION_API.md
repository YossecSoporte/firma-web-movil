# Guía de Integración API - FirmEasy Web

Documentación para sistemas externos (backends de clientes) que deseen integrar la firma digital mediante la app móvil **FirmEasy** a través de este servicio web.

---

## ¿Qué es la **URI** que devuelve el API?

> **URI** = *Uniform Resource Identifier* (Identificador Uniforme de Recursos).
>
> En este contexto, la **URI `firmeasy://...` es un *deep link* (enlace profundo)** que:
>
> 1. **No es una URL web** (no se abre en el navegador como `https://...`)
> 2. **Usa un esquema personalizado** `firmeasy://` registrado por la app móvil FirmEasy
> 3. **Lanza directamente la app nativa** en el móvil del usuario final
> 4. **Transporta todos los parámetros cifrados** con **AES-256-GCM** (job, expiración, token)
>
> **Ejemplo:**
> ```
> firmeasy://sign?data=BASE64URL_BLOB
> ```
> donde el blob es `base64url( IV(12 bytes) || CIPHERTEXT || TAG(16 bytes) )` del contenido `firmeasy://sign?job=...&exp=...&token=...`.
>
> **En tu frontend web:** haces `window.location.href = "firmeasy://sign?data=" + response.uri_encrypted` → el SO del móvil intercepta el esquema `firmeasy://` y abre la app FirmEasy automáticamente.
>
> Si la app **no está instalada**, el navegador cae a `app-no-instalada.php` (página con enlaces a Play Store / App Store).

---

## Deep link cifrado con AES-256-GCM

El deep link final es `firmeasy://sign?data=<BASE64URL_BLOB>`. **Todo** (job, exp, token) viaja **cifrado** — no hay parámetros en claro.

- **Algoritmo:** AES-256-GCM
- **Formato blob:** `base64url( IV(12 bytes) || CIPHERTEXT || TAG(16 bytes) )`
- **Clave:** `ENCRYPTION_KEY` (base64 de 32 bytes), compartida entre el backend y la app móvil FirmEasy
- **Contenido descifrado:** `firmeasy://sign?data={URL_ENCODED_DE_GET_/api/job/{job}}&exp={unix_ts}&token={token_del_usuario}`

### Contenido del blob descifrado

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `job` | string (URL, **urlencodeado**) | URL completa del endpoint `/api/job/{job_id}` que la app móvil consulta para obtener la configuración de firma (`from`, `to`, `doc_sha256`, `settings`) |
| `exp` | integer (Unix timestamp) | Fecha/hora de expiración del job. **10 minutos** desde la creación (`EXPIRACION_SEGUNDOS = 600`). Si la app abre la URI después de `exp`, debe rechazarla |
| `token` | string | Token del usuario final (el que escribió en el modal de la web) |

### Descifrado (pseudocódigo Kotlin)

```kotlin
val decoded = Base64.getUrlDecoder().decode(blob)
val iv  = decoded.copyOfRange(0, 12)
val tag = decoded.copyOfRange(decoded.size - 16, decoded.size)
val ct  = decoded.copyOfRange(12, decoded.size - 16)
val cipher = Cipher.getInstance("AES/GCM/NoPadding")
cipher.init(Cipher.DECRYPT_MODE, SecretKeySpec(key, "AES"), GCMParameterSpec(128, iv))
val uri = cipher.doFinal(ct + tag)  // "firmeasy://sign?job=...&exp=...&token=..."
```

### Validaciones que debe hacer la app móvil al recibir la URI

1. **Descifrar `data`** con AES-256-GCM (clave `ENCRYPTION_KEY`); si el tag no verifica → rechazar
2. **Verificar `exp`** — Si `exp < time()`, rechazar con error "Job expirado"
3. **Consumir `job`** — Hacer `GET {job}` (URL decodificada) para obtener `from` (descarga PDF) y `to` (subida PDF firmado)
4. Descargar el PDF de `from`, firmarlo y subirlo a `to` (binario)

---

## Endpoint Principal

### POST `/api/generar-uri.php`

Crea un **job de firma** y devuelve la URI completa `firmeasy://` para lanzar la app móvil.

**URL base:** `http://<IP>:8081` (la IP/dominio donde se despliegue el servicio; ver AGENTS.md §3). Actualmente en local: `http://localhost:8081`.

---

## Autenticación (Pendiente)

> ⚠️ **Actualmente sin autenticación** — Cualquier origen puede llamar al endpoint.
> 
> **Próximamente:** API Keys por cliente (header `X-API-Key`) para multi-tenancy y auditoría.

---

## Request

**Headers:**
```
Content-Type: application/json
```

**Body (JSON):**
```json
{
  "configuration": {
    "signature_type": "basic",
    "signature_reason": "Acepto el contenido del documento",
    "generate_request": "NOMBRE EMPRESA",
    "certificate_type": "all"
  },
  "documents": [
    {
      "file": "doc_prueba1.pdf",
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

---

## Campos del JSON

### Nivel 1: `configuration` (objeto, requerido)

| Campo | Tipo | Requerido | Descripción |
|-------|------|-----------|-------------|
| `signature_type` | string | Sí | Tipo de firma. Valor actual: `"basic"` |
| `signature_reason` | string | Sí | Motivo/razón de la firma. Se usa en el placeholder `{{signature_reason}}` del texto visible |
| `generate_request` | string | Sí | Nombre de la empresa/solicitante que aparece en la app |
| `certificate_type` | string | No | `"all"` \| `"dni"` \| `"certificado"` (default `"all"`) |

### Nivel 1: `token` (string, requerido)

Token que escribió el usuario final en el modal de la web. **Es obligatorio en este POST** (si falta → `400`). Viaja cifrado dentro del blob `data`.

> **Ojo:** `token` **solo va en el request del POST**. No aparece en la respuesta de `GET /api/job/{id}` (se oculta a propósito por seguridad). El `token` y `exp` solo se obtienen del blob descifrado.

### Nivel 1: `documents` (array, requerido, mínimo 1)

Actualmente **solo se procesa el primer elemento** del array.

| Campo | Tipo | Requerido | Descripción |
|-------|------|-----------|-------------|
| `file` | string | Condicional | Nombre del archivo PDF en `document/` (requerido si no se envía `data`) |
| `user_id` | string | Sí | Identificador del usuario/firmante |
| `doc_sha256` | string | No | Hash SHA-256 del PDF (se calcula automáticamente si se omite) |
| `data` | string | No | **URL personalizada** que la app móvil usará para obtener la config del job. Si se omite, se usa `/api/job/{uuid}` |
| `settings` | object | Sí | Configuración visual de la firma (lo consume la app móvil) |

### Campo `data` — URL Flexible

El parámetro `data` permite al cliente definir **cualquier URL válida** como endpoint de configuración del job. No hay restricción de estructura.

**Ejemplos de URLs válidas:**

| URL | Descripción |
|---|---|
| `http://localhost:8081/api/job/{uuid}` | Endpoint estándar (por defecto) |
| `http://localhost:8081/api/firma/{uuid}` | Endpoint personalizado |
| `http://mi-backend.com/api/v1/firma/12345` | API externa del cliente |
| `https://empresa.com/firma?id=abc123` | Cualquier URL accesible |

**Importante:** El endpoint que el cliente defina debe retornar la misma estructura JSON que el endpoint estándar (`configuration`, `documents`, `settings`).

### Nivel 3: `settings` (objeto, requerido)

**Claves en INGLÉS (`vis_sig_*`) — NO CAMBIAR** (las consume la app móvil FirmEasy).

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `vis_sig_x` | number | Coordenada X de la firma visible (puntos PDF, esquina inferior izquierda) |
| `vis_sig_y` | number | Coordenada Y de la firma visible |
| `vis_sig_width` | number | Ancho del rectángulo de firma visible |
| `vis_sig_height` | number | Alto del rectángulo de firma visible |
| `vis_sig_page` | number | Número de página (1-indexed) donde colocar la firma |
| `vis_sig_text_size` | number | Tamaño de fuente del texto visible |
| `vis_sig_text` | string | Plantilla de texto con placeholders: `<SIGNER>`, `<DATE>`, `<OU>`, `{{signature_reason}}` |
| `vis_sig_graphic` | string | URL de imagen/logo para la firma visible (opcional) |

---

## Response

**Éxito (200):**
```json
{
  "uri_encrypted": "BASE64URL_BLOB",
  "uri_plain": "firmeasy://sign?job=http%3A%2F%2F<IP>%3A8081%2Fapi%2Fjob%2F{uuid}&exp={unix_ts}&token=TOKEN_DEL_USUARIO",
  "job": "uuid-v4-del-job",
  "exp": 1786726430,
  "data": "BASE64URL_BLOB"
}
```

| Campo | Descripción |
|-------|-------------|
| `uri_encrypted` | **Blob cifrado AES-256-GCM** (mismo valor que `data`). Es el que va como `firmeasy://sign?data=...` para lanzar la app |
| `uri_plain` | URI en claro **solo para depuración**. No debe mostrarse en producción |
| `job` | UUID v4 del job (para consultar estado vía `/api/job/{job}`) |
| `exp` | Timestamp Unix de expiración (10 minutos desde creación) |
| `data` | Alias de `uri_encrypted` (deep link final) |

**Deep link final:** `firmeasy://sign?data=BASE64URL_BLOB`

**Errores:**
| Código | Causa |
|--------|-------|
| 400 | JSON inválido, campos faltantes, `token` vacío, `certificate_type` inválido, `doc_sha256` mal formado |
| 404 | Archivo `file` no existe en `document/` |
| 405 | Método no POST |
| 500 | Error interno (guardado job, cálculo hash, `ENCRYPTION_KEY` no configurada) |

---

## Flujo de Integración (Backend del Cliente)

```
1. Cliente (usuario final) está en tu sistema web
2. Tu backend prepara el JSON con el PDF a firmar (debe estar en document/ del servidor FirmEasy)
3. Tu backend hace POST /api/generar-uri.php
4. Recibe `uri_encrypted` (blob AES-256-GCM)
5. Tu frontend redirige al usuario: window.location.href = "firmeasy://sign?data=" + response.uri_encrypted
   - En móvil: abre app FirmEasy directamente
   - En desktop: cae a app-no-instalada.php (enlaces a stores)
6. App móvil firma y sube PDF firmado a /api/upload-signed.php
7. Tu sistema puede consultar /api/list-signed.php?original={file} para ver si ya está firmado
```

---

## Comportamiento si la app NO está instalada

El esquema `firmeasy://` **NO redirige automáticamente a Play Store**. El SO del móvil simplemente no sabe qué hacer con ese esquema si la app no está registrada.

### Estrategias de fallback (a elegir según tu implementación)

#### Opción A — Página intermedia (implementación actual)

El frontend redirige a `firmeasy://sign?data=...` y si la app no abre en ~3.5s, redirige a `app-no-instalada.php` (página con botones manuales a Play Store / App Store).

```javascript
window.location.href = "firmeasy://sign?data=" + response.uri_encrypted;
setTimeout(function() {
  window.location.href = '/app-no-instalada.php';
}, 3500);
```

**Desventaja:** requiere un clic extra del usuario en la página intermedia.

#### Opción B — Timeout JS directo a Play Store (recomendada para web)

```javascript
window.location.href = "firmeasy://sign?data=" + response.uri_encrypted;
var start = Date.now();
setTimeout(function() {
  if (Date.now() - start < 2500) {
    window.location.href = "https://play.google.com/store/apps/details?id=com.firmeasy";
  }
}, 2000);
```

Si la app no abrió en 2 segundos, redirige directo a Play Store.

#### Opción C — Android Intent URI (comportamiento nativo Android)

En vez de usar `firmeasy://`, construir un `intent://` con fallback embebido:

```
intent://sign?data=BASE64URL_BLOB#Intent;scheme=firmeasy;package=com.firmeasy;S.browser_fallback_url=https%3A%2F%2Fplay.google.com%2Fstore%2Fapps%2Fdetails%3Fid%3Dcom.firmeasy;end
```

Android abre la app si está instalada, o va al Play Store automáticamente si no lo está.

#### Opción D — Android App Links (esquema `https://`)

Requiere registrar `assetlinks.json` en el dominio del backend FirmEasy y configurar el `intent-filter` en `AndroidManifest.xml` de la app móvil con `autoVerify="true"`. El SO valida la propiedad del dominio y abre la app directa o muestra selector.

---

## Requisitos Previos

1. **El PDF debe existir en `document/` del servidor FirmEasy** antes de llamar al API.
   - Opción A: Tu backend sube el PDF vía SFTP/SCP/rsync a la carpeta `document/`
   - Opción B: (Futuro) Endpoint de subida de PDFs previo a la firma

2. **Nombres de archivo únicos** — Evita colisiones. Usa prefijos: `cliente123_contrato_20240810.pdf`

3. **Red accesible** — El móvil del usuario final debe poder resolver y acceder a `http://<IP>:8081` (misma LAN o VPN / IP pública)

---

## Ejemplo Completo (PowerShell)

```powershell
$payload = @{
    configuration = @{
        signature_type = "basic"
        signature_reason = "Acepto términos y condiciones"
        generate_request = "Mi Empresa S.A.C."
        certificate_type = "all"
    }
    token = "TOKEN_USUARIO_FINAL"
    documents = @(
        @{
            file = "contrato_cliente_001.pdf"
            user_id = "CLI-001"
            doc_sha256 = ""  # Se calcula automático
            settings = @{
                vis_sig_x = 340
                vis_sig_y = 693
                vis_sig_width = 155
                vis_sig_height = 55
                vis_sig_page = 1
                vis_sig_text_size = 10
                vis_sig_text = "Firmado digitalmente por:\n<SIGNER>\nFecha: <DATE>\nOU: <OU>\nFirmado con FirmEasy\nMotivo: {{signature_reason}}"
                vis_sig_graphic = "https://miempresa.com/firma.png"
            }
        }
    )
} | ConvertTo-Json -Depth 5

$response = Invoke-RestMethod -Uri "http://<IP>:8081/api/generar-uri.php" -Method Post -ContentType "application/json" -Body $payload

# Redirigir al usuario en el frontend:
# window.location.href = "firmeasy://sign?data=" + $response.uri_encrypted
Write-Host "Deep link: firmeasy://sign?data=$($response.uri_encrypted)"
Write-Host "Plain (solo depuracion): $($response.uri_plain)"
```

---

## Consultar Estado del Job

### GET `/api/job/{job_id}`

Devuelve la configuración completa guardada (incluye `from` y `to` URLs).

> **Nota:** este endpoint **NO devuelve `token` ni `exp`** a propósito (seguridad, ver `api/job.php`). El `token` solo viaja dentro del blob cifrado `data` del deep link; la app móvil lo obtiene al descifrar.

### GET `/api/list-signed.php?original={filename}`

Lista PDFs firmados. Filtrar por `original` para saber si un documento específico ya fue firmado.

**Response:**
```json
{
  "success": true,
  "count": 1,
  "files": [
    {
      "filename": "contrato_cliente_001_CLI-001.pdf",
      "original": "contrato_cliente_001.pdf",
      "user_id": "CLI-001",
      "size": 26543,
      "modified": 1786393200
    }
  ]
}
```

---

## Descargar PDF Firmado

### GET `/api/download-signed.php?file={filename_firmado.pdf}`

Descarga directa con `Content-Disposition: attachment`.

---

## Notas Importantes

- **Expiración:** Jobs expiran a los 10 minutos (`EXPIRACION_SEGUNDOS = 600`). El blob cifrado no se aceptará después de `exp`.
- **Cifrado:** El blob usa AES-256-GCM; si el tag no verifica o la clave cambia, la app debe rechazarlo. `ENCRYPTION_KEY` debe ser la misma en backend y app móvil.
- **Use-once del job:** (Pendiente implementar validación estricta) — hoy un job puede consultarse varias veces con el mismo blob. Próximamente: marcar el job como `consumido` y devolver `410`.
- **Tamaño máximo PDF:** 20 MB (configurado en `download.php`).
- **CORS:** Habilitado (`Access-Control-Allow-Origin: *`) para integración desde cualquier origen web.

---

## Contacto / Soporte

Para dudas de integración, contactar al equipo de FirmEasy.