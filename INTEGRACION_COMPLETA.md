# FirmEasy — Guía de Integración Completa

**Versión:** 1.0.0 | **Fecha:** 17/08/2026

---

## Tabla de Contenidos

1. [Qué es FirmEasy](#1-qué-es-firmeasy)
2. [Arquitectura del Sistema](#2-arquitectura-del-sistema)
3. [Flujo General de Firma](#3-flujo-general-de-firma)
4. [Instalación y Configuración](#4-instalación-y-configuración)
5. [API — Endpoints Disponibles](#5-api--endpoints-disponibles)
6. [Deep Link y Cifrado AES-256-GCM](#6-deep-link-y-cifrado-aes-256-gcm)
7. [Integración desde tu Backend](#7-integración-desde-tu-backend)
8. [Integración desde tu Frontend](#8-integración-desde-tu-frontend)
9. [Configuración de Red](#9-configuración-de-red)
10. [Ejemplos de Código](#10-ejemplos-de-código)
11. [Seguridad](#11-seguridad)
12. [Solución de Problemas](#12-solución-de-problemas)

---

## 1. Qué es FirmEasy

**FirmEasy** es un sistema de firma digital que permite firmar documentos PDF utilizando certificados digitales (DNI electrónico o certificado digital) desde una aplicación móvil.

### Componentes

| Componente | Tecnología | Descripción |
|---|---|---|
| **Backend Web** | PHP 8.3 + Nginx + Docker | Genera jobs de firma, sirve PDFs, recibe PDFs firmados |
| **App Móvil** | Android / iOS | Firma los PDFs con certificado digital |
| **Frontend Web** | HTML/CSS/JS (index.php) | Interfaz para seleccionar documentos y lanzar la firma |

### Stack Técnico

- **PHP 8.3** (sin framework)
- **Nginx** como reverse proxy
- **Docker** con Alpine Linux
- **Sin base de datos** — jobs almacenados como JSON en `storage/jobs/`
- **Cifrado AES-256-GCM** para deep links seguros

---

## 2. Arquitectura del Sistema

```
┌─────────────────────────────────────────────────────────────────┐
│                     USUARIO FINAL (Navegador)                   │
│                                                                 │
│  1. Abre http://<IP>:8081/                                      │
│  2. Selecciona documento a firmar                               │
│  3. Ingresa Token + Tipo de certificado                         │
│  4. Sistema genera deep link cifrado                            │
│  5. Deep link abre app móvil FirmEasy                           │
└──────────────────────────────┬──────────────────────────────────┘
                               │
                               ▼
┌─────────────────────────────────────────────────────────────────┐
│                    APP MÓVIL FIRMEASY                           │
│                                                                 │
│  1. Recibe deep link: firmeasy://sign?data=BLOB                 │
│  2. Descifra BLOB con AES-256-GCM                               │
│  3. Obtiene: job URL + exp + token                              │
│  4. Consulta GET /api/job/{job} para obtener config             │
│  5. Descarga PDF desde 'from'                                   │
│  6. Firma el PDF con certificado digital                        │
│  7. Sube PDF firmado a 'to'                                     │
└──────────────────────────────┬──────────────────────────────────┘
                               │
                               ▼
┌─────────────────────────────────────────────────────────────────┐
│                    BACKEND WEB (PHP + Docker)                   │
│                                                                 │
│  - Genera jobs de firma (UUID v4)                               │
│  - Cifra deep links con AES-256-GCM                             │
│  - Sirve PDFs originales (download.php)                         │
│  - Recibe PDFs firmados (upload-signed.php)                     │
│  - Almacena jobs en storage/jobs/{uuid}.json                    │
└─────────────────────────────────────────────────────────────────┘
```

---

## 3. Flujo General de Firma

### Paso a Paso

```
1. Usuario abre la web → ve lista de documentos PDF
2. Toca "Firmar" → modal pide Token y Tipo de certificado
3. Frontend hace POST /api/generar-uri.php con:
   - configuration (tipo firma, motivo, empresa, certificado)
   - token (del usuario)
   - documents (archivo, user_id, settings de firma)
4. Backend genera:
   - job (UUID v4)
   - exp (10 minutos)
   - Guarda job en storage/jobs/{job}.json
   - Cifra la URI completa con AES-256-GCM
5. Backend retorna:
   - uri_encrypted (blob cifrado)
   - uri_plain (para depuración)
6. Frontend construye deep link:
   firmeasy://sign?data={uri_encrypted}
7. Navegador dispara deep link → abre app móvil
8. App móvil:
   - Descifra el blob
   - Valida expiración (exp)
   - Consulta GET /api/job/{job}
   - Descarga PDF desde 'from'
   - Firma el PDF
   - Sube PDF firmado a 'to'
9. Al volver a la web, el documento pasa de Pendiente → Firmado
```

### Diagrama de Secuencia

```
┌──────┐    ┌──────────┐    ┌──────────┐    ┌──────────┐
│ User │    │ Frontend │    │ Backend  │    │ App Móvil│
└──┬───┘    └────┬─────┘    └────┬─────┘    └────┬─────┘
   │             │               │               │
   │  1. Selecciona doc         │               │
   │────────────>│               │               │
   │             │               │               │
   │  2. Ingresa token          │               │
   │────────────>│               │               │
   │             │               │               │
   │             │  3. POST /api/generar-uri.php  │
   │             │──────────────>│               │
   │             │               │               │
   │             │  4. Retorna uri_encrypted      │
   │             │<──────────────│               │
   │             │               │               │
   │  5. Muestra deep link       │               │
   │<────────────│               │               │
   │             │               │               │
   │  6. Toca Firmar             │               │
   │────────────>│               │               │
   │             │               │               │
   │             │  7. window.location.href = deep_link
   │             │──────────────────────────────>│
   │             │               │               │
   │             │               │  8. Descifra blob
   │             │               │<──────────────│
   │             │               │               │
   │             │               │  9. GET /api/job/{job}
   │             │               │<──────────────│
   │             │               │               │
   │             │               │  10. Retorna config
   │             │               │──────────────>│
   │             │               │               │
   │             │               │  11. GET /api/download.php
   │             │               │<──────────────│
   │             │               │               │
   │             │               │  12. Descarga PDF
   │             │               │──────────────>│
   │             │               │               │
   │             │               │  13. POST /api/upload-signed.php
   │             │               │<──────────────│
   │             │               │               │
   │  14. Refresca lista         │               │
   │<────────────│               │               │
   │             │               │               │
```

---

## 4. Instalación y Configuración

### Requisitos Previos

- Docker 20.10+ / Docker Compose 2.0+
- Puerto 8081 disponible
- (Opcional) App FirmEasy instalada para pruebas reales

### Paso 1: Clonar el Repositorio

```bash
git clone <URL_DEL_REPOSITORIO>
cd firma-web-movil
```

### Paso 2: Configurar Variables de Entorno

Editar `docker-compose.yml`:

```yaml
environment:
  - PHP_MEMORY_LIMIT=256M
  - PHP_MAX_EXECUTION_TIME=60
  - BASE_URL_EXTERNO=http://localhost:8081  # Cambiar según red
  - ENCRYPTION_KEY=l1L0gPaxGHkH/aei5Hs7awe8XhPrHHtzywfZYF+mTc0=  # Clave AES-256
```

**Generar nueva clave de encriptación:**

```bash
openssl rand -base64 32
```

### Paso 3: Levantar el Servicio

```bash
# Construir y levantar
docker-compose up -d --build

# Verificar que funciona
curl http://localhost:8081/
# Debe retornar HTTP 200

# Ver logs
docker-compose logs -f
```

### Paso 4: Agregar Documentos PDF

Colocar los PDFs a firmar en la carpeta `document/`:

```bash
# Copiar PDFs al directorio
cp /ruta/a/mis/documentos/*.pdf ./document/
```

**Estructura de carpetas:**

```
document/
├── documento1.pdf        # PDF original a firmar
├── documento2.pdf
└── signed/               # PDFs firmados (se crea automáticamente)
    └── documento1_USER123.pdf
```

### Paso 5: Verificar Funcionamiento

```bash
# Listar PDFs disponibles
curl http://localhost:8081/api/list-pdfs.php

# Generar un job de prueba
$body = Get-Content test_payload.json -Raw
Invoke-RestMethod -Uri "http://localhost:8081/api/generar-uri.php" `
  -Method Post -ContentType "application/json" -Body $body
```

---

## 5. API — Endpoints Disponibles

### 5.1 Generar Job de Firma

**POST** `/api/generar-uri.php`

Crea un job de firma y retorna la URI cifrada para lanzar la app móvil.

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
      "data": "https://mi-backend.com/api/firma/12345",
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

**Campos del documento:**

| Campo | Tipo | Requerido | Descripción |
|---|---|---|---|
| `file` | string | Condicional | Nombre del archivo PDF en `document/` (requerido si no se envía `data`) |
| `user_id` | string | Sí | Identificador del usuario/firmante |
| `doc_sha256` | string | No | Hash SHA-256 del PDF (se calcula automáticamente si se omite) |
| `data` | string | No | **URL personalizada** que la app móvil usará para obtener la config del job. Si se omite, se usa `/api/job/{uuid}` |
| `settings` | object | Sí | Configuración visual de la firma |
```

**Response (200):**

```json
{
  "uri_encrypted": "BASE64URL_BLOB",
  "uri_plain": "firmeasy://sign?data=http%3A%2F%2Fmi-backend.com%2Fapi%2Ffirma%2F12345&exp={ts}&token={token}",
  "job": "uuid-v4",
  "exp": 1786978911,
  "data": "BASE64URL_BLOB"
}
```

**Nota:** El campo `data` en la respuesta es el blob cifrado (alias de `uri_encrypted`).

**Si no se envía `data` personalizado:**

```json
{
  "uri_plain": "firmeasy://sign?data=http%3A%2F%2Flocalhost%3A8081%2Fapi%2Fjob%2F{uuid}&exp={ts}&token={token}"
}
```
```

**Campos de la respuesta:**

| Campo | Tipo | Descripción |
|---|---|---|
| `uri_encrypted` | string | Blob cifrado AES-256-GCM (usar en deep link) |
| `uri_plain` | string | URI en claro (solo para depuración) |
| `job` | string | UUID v4 del job |
| `exp` | integer | Timestamp de expiración (10 minutos) |
| `data` | string | Alias de `uri_encrypted` |

**Errores:**

| Código | Causa |
|---|---|
| 400 | JSON inválido, campos faltantes, token vacío |
| 404 | Archivo no existe en `document/` |
| 405 | Método no POST |
| 500 | Error interno, ENCRYPTION_KEY no configurada |

---

### 5.2 Consultar Job

**GET** `/api/job/{job_id}`

Retorna la configuración completa del job.

**Response (200):**

```json
{
  "job": "uuid-v4",
  "configuration": {
    "signature_type": "basic",
    "signature_reason": "Acepto el contenido del documento",
    "generate_request": "NOMBRE EMPRESA",
    "certificate_type": "all"
  },
  "documents": [
    {
      "from": "http://localhost:8081/api/download.php?file=documento.pdf",
      "to": "http://localhost:8081/api/upload-signed.php?file=documento.pdf&user_id=USER123",
      "doc_sha256": "abc123...",
      "settings": { ... }
    }
  ]
}
```

**Nota:** `token` y `exp` NO se retornan — la app móvil los obtiene del blob descifrado.

**Errores:**

| Código | Causa |
|---|---|
| 400 | Job ID inválido |
| 404 | Job no encontrado |
| 410 | Job expirado |

---

### 5.3 Listar PDFs Originales

**GET** `/api/list-pdfs.php`

**Response:**

```json
{
  "success": true,
  "count": 3,
  "files": [
    {
      "filename": "documento.pdf",
      "size": 25791,
      "modified": 1786135484
    }
  ]
}
```

---

### 5.4 Descargar PDF Original

**GET** `/api/download.php?file={nombre.pdf}`

Descarga el PDF original con `Content-Disposition: attachment`.

---

### 5.5 Subir PDF Firmado

**POST** `/api/upload-signed.php?file={nombre.pdf}&user_id={id}`

Recibe el PDF firmado en binario (`php://input`).

**Headers:**

```
Content-Type: application/pdf
```

**Body:** Bytes del PDF firmado

**Response:**

```json
{
  "success": true,
  "filename": "documento_USER123.pdf",
  "original": "documento.pdf",
  "user_id": "USER123",
  "size": 30541
}
```

---

### 5.6 Listar PDFs Firmados

**GET** `/api/list-signed.php?original={nombre.pdf}`

**Response:**

```json
{
  "success": true,
  "count": 1,
  "files": [
    {
      "filename": "documento_USER123.pdf",
      "size": 30541,
      "modified": 1786200000,
      "url": "/api/download-signed.php?file=documento_USER123.pdf"
    }
  ]
}
```

---

### 5.7 Descargar PDF Firmado

**GET** `/api/download-signed.php?file={nombre_firmado.pdf}`

---

### 5.8 Limpiar PDFs Firmados

**POST** `/api/clear-signed.php?confirm=1`

Elimina todos los PDFs de `document/signed/`.

**Response:**

```json
{
  "success": true,
  "deleted": 3
}
```

---

## 6. Deep Link y Cifrado AES-256-GCM

### Formato del Deep Link

```
firmeasy://sign?data={BLOB_CIFRADO}
```

El blob contiene la URI completa cifrada:

```
firmeasy://sign?data={DATA_URL}&exp={TIMESTAMP}&token={USER_TOKEN}
```

### Campo `data` — URL Flexible

El parámetro `data` dentro del deep link **puede ser cualquier URL válida** que el cliente defina. No hay restricción de estructura.

**Ejemplos de URLs válidas:**

| URL | Descripción |
|---|---|
| `http://localhost:8081/api/job/{uuid}` | Endpoint estándar (por defecto) |
| `http://localhost:8081/api/firma/{uuid}` | Endpoint personalizado |
| `http://mi-backend.com/api/v1/firma/12345` | API externa del cliente |
| `https://empresa.com/firma?id=abc123` | Cualquier URL accesible |

**Flujo:**
1. Cliente envía campo `data` en el request (opcional)
2. Si se omite, se usa `/api/job/{uuid}` por defecto
3. La app móvil hace `GET {data}` para obtener la config del job
4. La respuesta debe ser JSON con `configuration`, `documents`, `settings`

### Cifrado AES-256-GCM

**Parámetros:**

| Parámetro | Tamaño | Descripción |
|---|---|---|
| KEY | 256 bits (32 bytes) | Clave compartida backend ↔ app móvil |
| IV (nonce) | 96 bits (12 bytes) | Generado aleatoriamente en cada cifrado |
| AUTH_TAG | 128 bits (16 bytes) | Calculado automáticamente al cifrar |

**Formato del BLOB:**

```
BLOB_RAW = IV (12 bytes) || CIPHERTEXT (N bytes) || AUTH_TAG (16 bytes)
BLOB = base64url_encode(BLOB_RAW)
```

**Encoding:** base64url (sin padding, `-` en vez de `+`, `_` en vez de `/`)

### Ejemplo de Cifrado en PHP

```php
function encriptarUri(string $uriPlano, string $keyBase64): string {
    $key = base64_decode($keyBase64); // 32 bytes
    $iv = random_bytes(12); // nuevo en cada llamada

    $ciphertext = openssl_encrypt(
        $uriPlano,
        'aes-256-gcm',
        $key,
        OPENSSL_RAW_DATA,
        $iv,
        $tag,   // se llena por referencia, 16 bytes
        '',     // AAD opcional
        16      // longitud del tag
    );

    $blobRaw = $iv . $ciphertext . $tag;

    return rtrim(strtr(base64_encode($blobRaw), '+/', '-_'), '=');
}
```

### Ejemplo de Descifrado en Kotlin

```kotlin
fun descifrarBlob(blob: String, keyBase64: String): String {
    // 1. Decodificar base64url
    var base64 = blob.replace('-', '+').replace('_', '/')
    while (base64.length % 4 != 0) base64 += "="
    val raw = Base64.getDecoder().decode(base64)

    // 2. Extraer IV, ciphertext, tag
    val iv = raw.copyOfRange(0, 12)
    val tag = raw.copyOfRange(raw.size - 16, raw.size)
    val ct = raw.copyOfRange(12, raw.size - 16)

    // 3. Descifrar con AES-256-GCM
    val key = Base64.getDecoder().decode(keyBase64)
    val cipher = Cipher.getInstance("AES/GCM/NoPadding")
    val spec = GCMParameterSpec(128, iv)
    cipher.init(Cipher.DECRYPT_MODE, SecretKeySpec(key, "AES"), spec)
    val plaintext = cipher.doFinal(ct + tag)

    return String(plaintext, Charsets.UTF_8)
}
```

### Ejemplo de Descifrado en C#

```csharp
public string DescifrarBlob(string blob, string keyBase64)
{
    // 1. Decodificar base64url
    string base64 = blob.Replace('-', '+').Replace('_', '/');
    while (base64.Length % 4 != 0) base64 += "=";
    byte[] raw = Convert.FromBase64String(base64);

    // 2. Extraer IV, ciphertext, tag
    byte[] iv = raw[..12];
    byte[] tag = raw[^16..];
    byte[] ct = raw[12..^16];

    // 3. Descifrar con AES-256-GCM
    byte[] key = Convert.FromBase64String(keyBase64);
    byte[] plaintext = new byte[ct.Length];
    using var aes = new AesGcm(key);
    aes.Decrypt(iv, ct, tag, plaintext);

    return Encoding.UTF8.GetString(plaintext);
}
```

### Contenido del Blob Descifrado

```
firmeasy://sign?data={JOB_URL_ENCODED}&exp={UNIX_TIMESTAMP}&token={USER_TOKEN}
```

| Parámetro | Tipo | Descripción |
|---|---|---|
| `data` | string (URL encoded) | URL del endpoint `/api/job/{job_id}` |
| `exp` | integer | Timestamp Unix de expiración (10 minutos) |
| `token` | string | Token del usuario final |

---

## 7. Integración desde tu Backend

### Flujo de Integración

```
1. Tu sistema web muestra documentos al usuario
2. Usuario selecciona documento y proporciona token
3. Tu backend prepara el JSON con la configuración
4. Tu backend hace POST a /api/generar-uri.php
5. Recibe uri_encrypted (blob cifrado)
6. Tu frontend redirige al usuario:
   window.location.href = "firmeasy://sign?data=" + response.uri_encrypted
7. App móvil firma y sube el PDF
8. Tu sistema consulta /api/list-signed.php para verificar estado
```

### Requisitos Previos

1. **El PDF debe existir en `document/` del servidor FirmEasy**
   - Opción A: Subir PDF vía SFTP/SCP/rsync
   - Opción B: (Futuro) Endpoint de subida de PDFs

2. **Nombres de archivo únicos**
   - Usar prefijos: `cliente123_contrato_20240810.pdf`

3. **Red accesible**
   - El móvil debe poder acceder a `http://<IP>:8081`

### Ejemplo en PHP

```php
<?php
// 1. Preparar payload
$payload = [
    'configuration' => [
        'signature_type' => 'basic',
        'signature_reason' => 'Acepto términos y condiciones',
        'generate_request' => 'Mi Empresa S.A.C.',
        'certificate_type' => 'all'
    ],
    'token' => 'TOKEN_USUARIO_FINAL',
    'documents' => [
        [
            'file' => 'contrato_cliente_001.pdf',
            'user_id' => 'CLI-001',
            'doc_sha256' => '', // Se calcula automático
            'settings' => [
                'vis_sig_x' => 340,
                'vis_sig_y' => 693,
                'vis_sig_width' => 155,
                'vis_sig_height' => 55,
                'vis_sig_page' => 1,
                'vis_sig_text_size' => 10,
                'vis_sig_text' => "Firmado digitalmente por:\n<SIGNER>\nFecha: <DATE>\nOU: <OU>\nFirmado con FirmEasy\nMotivo: {{signature_reason}}",
                'vis_sig_graphic' => 'https://miempresa.com/firma.png'
            ]
        ]
    ]
];

// 2. Llamar al API
$ch = curl_init('http://localhost:8081/api/generar-uri.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = json_decode(curl_exec($ch), true);
curl_close($ch);

// 3. Construir deep link
$deepLink = 'firmeasy://sign?data=' . $response['uri_encrypted'];

// 4. Redirigir al usuario (en frontend JavaScript)
echo "window.location.href = '$deepLink';";
?>
```

### Ejemplo en Python

```python
import requests
import json

# 1. Preparar payload
payload = {
    "configuration": {
        "signature_type": "basic",
        "signature_reason": "Acepto términos y condiciones",
        "generate_request": "Mi Empresa S.A.C.",
        "certificate_type": "all"
    },
    "token": "TOKEN_USUARIO_FINAL",
    "documents": [{
        "file": "contrato_cliente_001.pdf",
        "user_id": "CLI-001",
        "doc_sha256": "",
        "settings": {
            "vis_sig_x": 340,
            "vis_sig_y": 693,
            "vis_sig_width": 155,
            "vis_sig_height": 55,
            "vis_sig_page": 1,
            "vis_sig_text_size": 10,
            "vis_sig_text": "Firmado digitalmente por:\n<SIGNER>\nFecha: <DATE>\nOU: <OU>\nFirmado con FirmEasy\nMotivo: {{signature_reason}}",
            "vis_sig_graphic": "https://miempresa.com/firma.png"
        }
    }]
}

# 2. Llamar al API
response = requests.post(
    "http://localhost:8081/api/generar-uri.php",
    json=payload
)
data = response.json()

# 3. Construir deep link
deep_link = f"firmeasy://sign?data={data['uri_encrypted']}"

print(f"Deep link: {deep_link}")
```

---

## 8. Integración desde tu Frontend

### Ejemplo en JavaScript

```javascript
// 1. Función para firmar documento
async function firmarDocumento(filename, userId, token, certificateType) {
    const response = await fetch('/api/generar-uri.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            configuration: {
                signature_type: 'basic',
                signature_reason: 'Acepto el contenido del documento',
                generate_request: 'NOMBRE EMPRESA',
                certificate_type: certificateType
            },
            token: token,
            documents: [{
                file: filename,
                user_id: userId,
                doc_sha256: '',
                settings: {
                    vis_sig_x: 340,
                    vis_sig_y: 693,
                    vis_sig_width: 155,
                    vis_sig_height: 55,
                    vis_sig_page: 1,
                    vis_sig_text_size: 10,
                    vis_sig_text: 'Firmado digitalmente por:\n<SIGNER>\nFecha: <DATE>\nOU: <OU>\nFirmado con FirmEasy\nMotivo: {{signature_reason}}',
                    vis_sig_graphic: 'http://imagen-firma.com/logo.png'
                }
            }]
        })
    });

    const data = await response.json();

    // 2. Construir deep link
    const deepLink = 'firmeasy://sign?data=' + encodeURIComponent(data.uri_encrypted);

    // 3. Lanzar app móvil
    window.location.href = deepLink;

    // 4. Fallback si la app no está instalada
    setTimeout(function() {
        window.location.href = '/app-no-instalada.php';
    }, 3500);
}

// 5. Al volver de la app, refrescar lista
window.addEventListener('focus', function() {
    setTimeout(loadPdfList, 2000);
});
```

### Manejo de Fallback (App No Instalada)

```javascript
// Opción 1: Página intermedia
window.location.href = deepLink;
setTimeout(function() {
    window.location.href = '/app-no-instalada.php';
}, 3500);

// Opción 2: Redirect directo a Play Store
window.location.href = deepLink;
var start = Date.now();
setTimeout(function() {
    if (Date.now() - start < 2500) {
        window.location.href = 'https://play.google.com/store/apps/details?id=com.firmeasy';
    }
}, 2000);

// Opción 3: Android Intent URI
const intentUri = `intent://sign?data=${blob}#Intent;scheme=firmeasy;package=com.firmeasy;S.browser_fallback_url=https%3A%2F%2Fplay.google.com%2Fstore%2Fapps%2Fdetails%3Fid%3Dcom.firmeasy;end`;
window.location.href = intentUri;
```

---

## 9. Configuración de Red

### localhost (Desarrollo)

```yaml
# docker-compose.yml
environment:
  - BASE_URL_EXTERNO=http://localhost:8081
```

### LAN (Producción interna)

```yaml
# docker-compose.yml
environment:
  - BASE_URL_EXTERNO=http://192.168.1.100:8081
```

### Verificar IP del Host

```powershell
# Windows
ipconfig | findstr /C:"IPv4"

# Linux/Mac
ip addr show | grep "inet "
```

### Abrir Puerto en Firewall (Windows)

```powershell
# Ejecutar como Administrador
netsh advfirewall firewall add rule name="FirmEasy Web (Puerto 8081)" ^
  dir=in action=allow protocol=TCP localport=8081 profile=any
```

### Verificar Puerto Abierto

```powershell
netstat -ano | findstr ":8081" | findstr "LISTENING"
```

---

## 10. Ejemplos de Código

### PowerShell — Generar Job

```powershell
$body = Get-Content "test_payload.json" -Raw
$resp = Invoke-RestMethod -Uri "http://localhost:8081/api/generar-uri.php" `
  -Method Post -ContentType "application/json" -Body $body

Write-Host "Deep link: firmeasy://sign?data=$($resp.uri_encrypted)"
Write-Host "Plain URI: $($resp.uri_plain)"
```

### PowerShell — Subir PDF Firmado

```powershell
$bytes = [System.IO.File]::ReadAllBytes("C:\document\documento.pdf")
Invoke-RestMethod -Uri "http://localhost:8081/api/upload-signed.php?file=documento.pdf&user_id=USER123" `
  -Method Post -ContentType "application/pdf" -Body $bytes
```

### PowerShell — Listar PDFs Firmados

```powershell
$resp = Invoke-RestMethod -Uri "http://localhost:8081/api/list-signed.php"
$resp.files | ForEach-Object { Write-Host $_.filename $_.size }
```

### cURL — Generar Job

```bash
curl -X POST http://localhost:8081/api/generar-uri.php \
  -H "Content-Type: application/json" \
  -d @test_payload.json
```

### cURL — Consultar Job

```bash
curl http://localhost:8081/api/job/{JOB_ID}
```

---

## 11. Seguridad

### Cifrado

- **Algoritmo:** AES-256-GCM (NIST SP 800-38D)
- **Clave:** 256 bits (32 bytes), compartida backend ↔ app móvil
- **IV:** 12 bytes, generado aleatoriamente en cada cifrado
- **Tag:** 16 bytes, verifica integridad y autenticidad

### Validaciones

1. **Tag GCM:** Si el tag no verifica → rechazar (blob alterado)
2. **Expiración:** Si `exp < time()` → rechazar (job expirado)
3. **Token:** Debe existir y no estar vacío
4. **Job URL:** Debe ser URL válida con `/api/job/`

### Buenas Prácticas

- **No exponer `ENCRYPTION_KEY`** en el frontend
- **Usar HTTPS** en producción (el deep link usa `firmeasy://`)
- **Validar `exp`** siempre antes de procesar
- **Invalidar tokens** después de su uso (pendiente)
- **Auditar accesos** a los endpoints API

### Limitaciones Actuales

- **CORS abierto:** `Access-Control-Allow-Origin: *` (pendiente restringir)
- **Sin autenticación API:** Cualquiera puede llamar a los endpoints (pendiente API Keys)
- **Tokens reutilizables:** Un token puede usarse múltiples veces (pendiente use-once)

---

## 12. Solución de Problemas

### Problema: Deep link no abre la app móvil

**Causas:**
1. App FirmEasy no instalada
2. Puerto 8081 bloqueado por firewall
3. IP incorrecta en `BASE_URL_EXTERNO`

**Solución:**
```bash
# Verificar que el servicio está corriendo
curl http://localhost:8081/

# Verificar puerto abierto
netstat -ano | findstr ":8081"

# Revisar logs
docker-compose logs -f
```

### Problema: Error "Job expirado"

**Causa:** El job tiene 10 minutos de vida.

**Solución:** Generar un nuevo job antes de abrir el deep link.

### Problema: PDF no se sube

**Causas:**
1. Nombre de archivo incorrecto
2. Tamaño mayor a 20 MB
3. Content-Type incorrecto

**Solución:**
```bash
# Verificar tamaño
ls -lh document/documento.pdf

# Verificar Content-Type
Content-Type: application/pdf
```

### Problema: CORS errors

**Causa:** El backend tiene CORS abierto, pero puede haber problemas de proxy.

**Solución:** Verificar que Nginx está configurado correctamente (ver `docker/nginx.conf`).

---

## Contacto / Soporte

Para dudas de integración, contactar al equipo de FirmEasy.

---

**Versión del documento:** 1.0.0  
**Última actualización:** 17/08/2026
