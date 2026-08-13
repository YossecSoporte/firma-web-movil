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
> 4. **Transporta todos los parámetros** necesarios para la firma (job, nonce, expiración, token)
>
> **Ejemplo:**
> ```
> firmeasy://sign?job=http%3A%2F%2F10.21.132.143%3A8081%2Fapi%2Fjob%2Fabc123&nonce=xyz789&exp=1786393069&kid=default&token=tkn_ind_...
> ```
>
> **En tu frontend web:** haces `window.location.href = response.uri` → el SO del móvil intercepta el esquema `firmeasy://` y abre la app FirmEasy automáticamente.
>
> Si la app **no está instalada**, el navegador cae a `app-no-instalada.php` (página con enlaces a Play Store / App Store).

---

## Parámetros de la URI `firmeasy://`

La URI devuelta por el API tiene esta forma genérica:

```
firmeasy://sign?job={JOB_URL}&nonce={NONCE}&exp={EXP}&kid={KID}&token={TOKEN}
```

### Descripción de cada parámetro

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| `job` | string (URL, **urlencodeado**) | URL completa del endpoint `/api/job/{job_id}` que la app móvil consulta para obtener la configuración de firma (`from`, `to`, `doc_sha256`, `settings`) |
| `nonce` | string hex (32 chars) | Número de uso único (single-use) que protege contra re intentos. Generado aleatoriamente con `random_bytes(16)` |
| `exp` | integer (Unix timestamp) | Fecha/hora de expiración del job. **10 minutos** desde la creación (`EXPIRACION_SEGUNDOS = 600`). Si la app abre la URI después de `exp`, debe rechazarla |
| `kid` | string | Identificador de la clave (Key ID). Valor fijo: `"default"` |
| `token` | string | Token de autenticación fijo hardcoded: `tkn_ind_yiaLpkwq42LIfgTp1GHhjzifHcjusTzT` |

### Ejemplo real de URI

```
firmeasy://sign?job=http%3A%2F%2F10.21.132.143%3A8081%2Fapi%2Fjob%2Fef1d54ae-e5d9-44f2-b3fa-f4e6dabdbedc&nonce=07e6d2b48f546c8af63835f93892fddb&exp=1786393069&kid=default&token=tkn_ind_yiaLpkwq42LIfgTp1GHhjzifHcjusTzT
```

### Validaciones que debe hacer la app móvil al recibir la URI

1. **Verificar `exp`** — Si `exp < time()`, rechazar con error "Job expirado"
2. **Verificar `kid`** — Debe ser `"default"` (único soportado por ahora)
3. **Verificar `token`** — Debe coincidir con el token fijo esperado
4. **Consumir `job`** — Hacer `GET {job}` (URL decodificada) para obtener `from` (descarga PDF) y `to` (subida PDF firmado)
5. **Usar `nonce` single-use** — (Pendiente validar estrictamente en servidor) Idealmente invalidar el tras primer uso

---

## Endpoint Principal

### POST `/api/generar-uri.php`

Crea un **job de firma** y devuelve la URI completa `firmeasy://` para lanzar la app móvil.

**URL base:** `http://10.21.132.143:8081` (o el dominio/IP donde se despliegue el servicio)

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
    "generate_request": "NOMBRE EMPRESA"
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

### Nivel 1: `documents` (array, requerido, mínimo 1)

Actualmente **solo se procesa el primer elemento** del array.

| Campo | Tipo | Requerido | Descripción |
|-------|------|-----------|-------------|
| `file` | string | Sí | Nombre del archivo PDF en la carpeta `document/` del servidor (ej: `doc_prueba1.pdf`) |
| `user_id` | string | Sí | Identificador único del usuario/firmante. Se usa para nombrar el PDF firmado: `{base}_{user_id}.pdf` |
| `doc_sha256` | string | No | Hash SHA-256 del PDF (64 chars hex). Si vacío, el servidor lo calcula automáticamente |
| `settings` | object | Sí | Configuración visual de la firma (lo consume la app móvil) |

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
  "uri": "firmeasy://sign?job=http%3A%2F%2F10.21.132.143%3A8081%2Fapi%2Fjob%2F{uuid}&nonce={hex32}&exp={unix_ts}&kid=default&token=tkn_ind_...",
  "job": "uuid-v4-del-job",
  "nonce": "hex32-aleatorio",
  "exp": 1786393069,
  "token": "tkn_ind_yiaLpkwq42LIfgTp1GHhjzifHcjusTzT"
}
```

| Campo | Descripción |
|-------|-------------|
| `uri` | **Deep link completo** listo para abrir en navegador móvil → lanza app FirmEasy |
| `job` | UUID v4 del job (para consultar estado vía `/api/job/{job}`) |
| `nonce` | Nonce hex de 32 chars (single-use, expira en 10 min) |
| `exp` | Timestamp Unix de expiración (10 minutos desde creación) |
| `token` | Token fijo hardcoded (kid=default) |

**Errores:**
| Código | Causa |
|--------|-------|
| 400 | JSON inválido, campos faltantes, `doc_sha256` mal formado |
| 404 | Archivo `file` no existe en `document/` |
| 405 | Método no POST |
| 500 | Error interno (guardado job, cálculo hash) |

---

## Flujo de Integración (Backend del Cliente)

```
1. Cliente (usuario final) está en tu sistema web
2. Tu backend prepara el JSON con el PDF a firmar (debe estar en document/ del servidor FirmEasy)
3. Tu backend hace POST /api/generar-uri.php
4. Recibe la URI firmeasy://
5. Tu frontend redirige al usuario: window.location.href = response.uri
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

El frontend redirige a `response.uri` y si la app no abre en ~3.5s, redirige a `app-no-instalada.php` (página con botones manuales a Play Store / App Store).

```javascript
window.location.href = response.uri;
setTimeout(function() {
  window.location.href = '/app-no-instalada.php';
}, 3500);
```

**Desventaja:** requiere un clic extra del usuario en la página intermedia.

#### Opción B — Timeout JS directo a Play Store (recomendada para web)

```javascript
window.location.href = response.uri;
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
intent://sign?job=...&nonce=...&exp=...&kid=default&token=...#Intent;scheme=firmeasy;package=com.firmeasy;S.browser_fallback_url=https%3A%2F%2Fplay.google.com%2Fstore%2Fapps%2Fdetails%3Fid%3Dcom.firmeasy;end
```

Android abre la app si está instalada, o va al Play Store automática mente si no lo está.

#### Opción D — Android App Links (esquema `https://`)

Requiere registrar `assetlinks.json` en el dominio del backend FirmEasy y configurar el `intent-filter` en `AndroidManifest.xml` de la app móvil con `autoVerify="true"`. El SO valida la propiedad del dominio y abre la app directa o muestra selector.

---

## Requisitos Previos

1. **El PDF debe existir en `document/` del servidor FirmEasy** antes de llamar al API.
   - Opción A: Tu backend sube el PDF vía SFTP/SCP/rsync a la carpeta `document/`
   - Opción B: (Futuro) Endpoint de subida de PDFs previo a la firma

2. **Nombres de archivo únicos** — Evita colisiones. Usa prefijos: `cliente123_contrato_20240810.pdf`

3. **Red accesible** — El móvil del usuario final debe poder resolver y acceder a `http://10.21.132.143:8081` (misma LAN o VPN / IP pública)

---

## Ejemplo Completo (PowerShell)

```powershell
$payload = @{
    configuration = @{
        signature_type = "basic"
        signature_reason = "Acepto términos y condiciones"
        generate_request = "Mi Empresa S.A.C."
    }
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

$response = Invoke-RestMethod -Uri "http://10.21.132.143:8081/api/generar-uri.php" -Method Post -ContentType "application/json" -Body $payload

# Redirigir al usuario en el frontend:
# window.location.href = $response.uri
Write-Host "URI para deep link: $($response.uri)"
```

---

## Consultar Estado del Job

### GET `/api/job/{job_id}`

Devuelve la configuración completa guardada (incluye `from` y `to` URLs).

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

- **Expiración:** Jobs expiran a los 10 minutos (`EXPIRACION_SEGUNDOS = 600`). La URI `firmeasy://` no funcionará después.
- **Nonce single-use:** (Pendiente implementar validación estricta) — Actualmente el nonce no invalida el job tras uso.
- **Tamaño máximo PDF:** 20 MB (configurado en `download.php`).
- **CORS:** Habilitado (`Access-Control-Allow-Origin: *`) para integración desde cualquier origen web.

---

## Contacto / Soporte

Para dudas de integración, contactar al equipo de FirmEasy.