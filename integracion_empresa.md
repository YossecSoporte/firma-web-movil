# Componente de Firma – Manual de integración

Software de firma digital

**V 1.0.0**

## Historial de cambios

| Versión | Descripción | Fecha |
|---|---|---|
| 1.0.0 | Versión inicial | 05/08/2026 |

---

## 1. Introducción

El presente manual tiene como objetivo guiar la integración del Componente de Firma digital individual y firma masiva con cualquier aplicación web. Para completar la integración de la firma individual con el Componente de Firma en su aplicación web, debe realizar los siguientes pasos.

Vale la pena señalar que no está limitado a ningún lenguaje de programación o tecnología de servidor. Sin embargo, los ejemplos de código se muestran en PHP.

---

## 2. Manual de integración de FirmEasy – Aplicación móvil

### 2.1. Generar URI para la comunicación a la aplicación móvil

```
firmeasy://sign?data={JOB_URL}&exp={EXP}
```

**Descripción de cada parámetro**

| Parámetro | Tipo | Descripción |
|---|---|---|
| `JOB_URL` | string (URL, urlencoded) | URL completa del endpoint que la app móvil consulta para obtener la configuración de firma (`from`, `to`, `doc_sha256`, `settings`) |
| `exp` | integer (Unix timestamp) | Fecha/hora de expiración del job. 10 minutos desde la creación (`EXPIRACION_SEGUNDOS = 600`). Si la app abre la URI después de `exp`, debe rechazarla |

**Ejemplo de URI (sin encriptar):**

Esta es la URI que se debe generar en formato URL-encoded (también conocido como Percent-Encoding). Aún falta aplicar el paso de encriptación.

```
firmeasy://sign?data=http%3A%2F%2F10.21.132.143%3A8081%2Fapi%2Fjob%2Fef1d54ae-e5d9-44f2-b3fa-f4e6dabdbedc&exp=1786393069
```

### 2.2. Cómo generar el job

Debe ser un endpoint de tipo **GET** donde la aplicación consultará la configuración de la firma.

**Estructura del endpoint que debe retornar** (ejemplo real)

```json
{
  "job": "859428a1-184c-4a25-b856-314046dbf0b8",
  "configuration": {
    "signature_type": "basic",
    "signature_reason": "Acepto el contenido del documento",
    "generate_request": "NOMBRE EMPRESA",
    "certificate_type": "all",
    "purpose": "signing",
    "accepted_issuers": [
      "CN=AC RAIZ001, O=RENIEC, C=PE",
      "CN=FirmEasy SubCA, O=GIRASOL PE SOCIEDAD COMERCIAL DE RESPONSABILIDAD LIMITADA, C=PE"
    ],
    "batch_error_handling": {
      "download": { "mode": "continue", "retry": 2 },
      "upload":   { "mode": "continue", "retry": 2 }
    }
  },
  "documents": [
    {
      "document_code": "c83a66b3-47ff-4f8d-93fe-97afd35cdca2",
      "from": "http://localhost:8081/api/download-fail.php?file=doc_prueba99.pdf",
      "to": "http://localhost:8081/api/upload-signed.php?file=doc_prueba1.pdf&user_id=USER123&job=859428a1-184c-4a25-b856-314046dbf0b8&document_code=c83a66b3-47ff-4f8d-93fe-97afd35cdca2",
      "name_pdf": "doc_prueba1.pdf",
      "doc_sha256": "0000000000000000000000000000000000000000000000000000000000000000",
      "status": "pending",
      "settings": {
        "vis_sig_x": 340,
        "vis_sig_y": 693,
        "vis_sig_width": 155,
        "vis_sig_height": 55,
        "vis_sig_page": 1,
        "vis_sig_text_size": 10,
        "vis_sig_text": "Firmado digitalmente por:\n<SIGNER>\nFecha: <DATE>\nOU: <OU>\nFirmado con FirmEasy\nMotivo: {{signature_reason}}",
        "vis_sig_graphic": "https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTlcZ50Ci9uRJBet3r17ORbbDGEq-adGoaPS5Hm8L07qD_okGo9F6URTWE&s=10"
      }
    }
  ]
}
```

### 2.3. Parámetros soportados por la aplicación

#### Nivel Job (objeto, requerido)

| Campo | Tipo | Requerido | Descripción |
|---|---|---|---|
| `job` | String | Sí | Identificador único (UUID) de la solicitud de firma. Se usa para identificar qué solicitud presentó fallas |

#### Nivel Configuration (objeto, requerido)

| Campo | Tipo | Requerido | Valores | Descripción |
|---|---|---|---|---|
| `signature_type` | string | Sí | `basic`, `with_timestamp`, `long_term` | Nivel de firma. `basic` = firma simple; `with_timestamp` = firma con sello de tiempo; `long_term` = firma de larga duración (añade otra capa de sellado) |
| `signature_reason` | string | Sí | texto libre | Motivo/razón de la firma. Se usa en el placeholder `{{signature_reason}}` del texto visible |
| `generate_request` | string | Sí | texto libre | Nombre de la empresa/solicitante que aparece en la app |
| `certificate_type` | String | Sí | `all`, `dni`, `certificado` | **Solo móvil.** Qué credencial usa el firmante. Ver tabla abajo |
| `purpose` | string | No | `signing` (u otro texto) | **Solo móvil.** Indica el propósito declarado del proceso de firma. Es informativo para la app |
| `accepted_issuers` | array | No | lista de strings (DN) | **Solo móvil.** Lista de emisores de certificado aceptados. Si se envía, la app **solo** acepta certificados emitidos por esas autoridades (ver detalle abajo) |
| `batch_error_handling` | object | No | ver estructura | Manejo de errores cuando el lote tiene fallas (ver tabla abajo) |

**`purpose` — valores:**

| Valor | Descripción |
|---|---|
| `signing` | El proceso es una firma de documentos (valor estándar) |

> Si no se envía, la app asume su comportamiento por defecto. No es obligatorio.

**`accepted_issuers` — para qué sirve y qué valores recibe:**

Es un arreglo con los **DN (Distinguished Name)** de las autoridades certificadoras (AC) cuyo certificado la app acepta para firmar. Si lo envías, el firmante **solo podrá firmar con un certificado emitido por una de esas AC**; si su certificado no coincide, la app lo rechaza.

Valores de ejemplo (DN de las AC de RENIEC y la SubCA de FirmEasy):

```json
"accepted_issuers": [
  "CN=AC RAIZ001, O=RENIEC, C=PE",
  "CN=FirmEasy SubCA, O=GIRASOL PE SOCIEDAD COMERCIAL DE RESPONSABILIDAD LIMITADA, C=PE"
]
```

> Si lo omites, la app acepta cualquier certificado válido. Es un filtro de emisores.

**`certificate_type` — valores soportados (lo consume la app móvil):**

Este campo es **solo para la app móvil**: le indica qué credencial digital debe usar el firmante. Se envía dentro de `configuration`.

| Valor | Descripción |
|---|---|
| `all` | Permite firmar con **DNI** o **certificado digital** (el firmante elige en la app) |
| `dni` | **Solo DNI** electrónico: la app exige el DNI |
| `certificado` | **Solo certificado digital**: la app exige un certificado válido |

> Ejemplo típico para entornos con firma estándar: `"certificate_type": "all"`.

**`batch_error_handling` (opcional):**

```json
{
  "download": { "mode": "continue", "retry": 2 },
  "upload":   { "mode": "continue", "retry": 2 }
}
```

| Campo | Valores | Default | Descripción |
|---|---|---|---|
| `download.mode` | `abort`, `continue` | `abort` | `abort` rechaza todo el lote si falla 1 descarga; `continue` sigue con los demás |
| `download.retry` | 0–10 | `0` | Reintentos de descarga por documento |
| `upload.mode` | `block`, `continue` | `block` | `block` se detiene (evita rate-limit); `continue` sube el resto |
| `upload.retry` | 0–10 | `0` | Reintentos de subida por documento |

#### Nivel documents (array, requerido, mínimo 1)

| Campo | Tipo | Requerido | Descripción |
|---|---|---|---|
| `document_code` | string (UUID) | Sí | Identificador único del documento en tu sistema. Se usa para correlacionar el callback y el PDF firmado |
| `from` | string | Sí | URL desde donde el Componente de Firma descargará el documento por firmar |
| `to` | string | Sí | URL donde el Componente de Firma enviará el documento firmado; en formato binario |
| `name_pdf` | string | Sí | Nombre del PDF que se firmará |
| `doc_sha256` | string | Sí | Hash SHA-256 del PDF (64 chars hex) |
| `status` | string | Sí | Estado del documento dentro del flujo (ver tabla abajo) |
| `settings` | object | Sí* | Configuración visual de la firma (lo consume la app móvil). *Opcional: si se omite, la app usa defaults |

**`documents[].status` — valores posibles:**

| Valor | Descripción |
|---|---|
| `pending` | Valor inicial: la app aún no ha procesado el documento |
| `signed` | La app firmó y subió el PDF correctamente |
| `error` | Hubo un error al firmar (aparece en el callback como `status: "error"` + `error_code`) |
| `failed` | El documento no se pudo procesar (p. ej. la descarga desde `from` falló) |

#### Nivel settings (objeto)

| Campo | Tipo | Descripción |
|---|---|---|
| `vis_sig_x` | number | Coordenada X dentro del documento, siendo `X,0` la esquina inferior izquierda |
| `vis_sig_y` | number | Coordenada Y dentro del documento, siendo `0,Y` la esquina inferior izquierda |
| `vis_sig_width` | number | Ancho del rectángulo de firma visible |
| `vis_sig_height` | number | Alto del rectángulo de firma visible |
| `vis_sig_page` | number | Número de página donde colocar la firma |
| `vis_sig_text_size` | number | Tamaño de fuente del texto visible |
| `vis_sig_text` | string | Plantilla de texto con placeholders (ver abajo) |
| `vis_sig_graphic` | string | URL de imagen/logo para la firma visible (opcional) |

**`vis_sig_text`**: Texto que va en la marca visual donde se pueden utilizar las siguientes etiquetas:

- `<SIGNER>`: Información del firmante, obtenido del certificado digital.
- `<DATE>`: Información de la fecha y hora de firma.
- `<SN>`: Número de serie del certificado digital.
- `<TITLE>`: Cargo de la persona.
- `<OU>`: Área o unidad organizacional del firmante.
- `<EMAIL>`: Correo electrónico del firmante.
- `<O>`: Organización del firmante.
- `{{signature_reason}}`: Se reemplaza con el valor de `configuration.signature_reason`.

A continuación el JSON real que retorna el endpoint `GET /api/job/{job_id}` (el mismo que verifica la app móvil al descifrar la URI):

```json
{
  "job": "859428a1-184c-4a25-b856-314046dbf0b8",
  "configuration": {
    "signature_type": "basic",
    "signature_reason": "Acepto el contenido del documento",
    "generate_request": "NOMBRE EMPRESA",
    "certificate_type": "all",
    "purpose": "signing",
    "accepted_issuers": [
      "CN=AC RAIZ001, O=RENIEC, C=PE",
      "CN=FirmEasy SubCA, O=GIRASOL PE SOCIEDAD COMERCIAL DE RESPONSABILIDAD LIMITADA, C=PE"
    ],
    "batch_error_handling": {
      "download": { "mode": "continue", "retry": 2 },
      "upload":   { "mode": "continue", "retry": 2 }
    }
  },
  "documents": [
    {
      "document_code": "c83a66b3-47ff-4f8d-93fe-97afd35cdca2",
      "from": "http://localhost:8081/api/download-fail.php?file=doc_prueba99.pdf",
      "to": "http://localhost:8081/api/upload-signed.php?file=doc_prueba1.pdf&user_id=USER123&job=859428a1-184c-4a25-b856-314046dbf0b8&document_code=c83a66b3-47ff-4f8d-93fe-97afd35cdca2",
      "name_pdf": "doc_prueba1.pdf",
      "doc_sha256": "0000000000000000000000000000000000000000000000000000000000000000",
      "status": "pending",
      "settings": {
        "vis_sig_x": 340,
        "vis_sig_y": 693,
        "vis_sig_width": 155,
        "vis_sig_height": 55,
        "vis_sig_page": 1,
        "vis_sig_text_size": 10,
        "vis_sig_text": "Firmado digitalmente por:\n<SIGNER>\nFecha: <DATE>\nOU: <OU>\nFirmado con FirmEasy\nMotivo: {{signature_reason}}",
        "vis_sig_graphic": "https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTlcZ50Ci9uRJBet3r17ORbbDGEq-adGoaPS5Hm8L07qD_okGo9F6URTWE&s=10"
      }
    }
  ]
}
```

### 2.4. Callback (notificación de resultado)

Cuando la app móvil termina de procesar **todos** los documentos del lote, el sistema notifica el resultado al **webhook de la empresa** (el valor de `callback` que se envió al crear el job). La empresa no necesita hacer polling: recibe la notificación cuando el proceso termina.

**Estructura del callback (POST al webhook de la empresa)**

Headers:
```
Content-Type: application/json
X-Job-Id: {job_id}
```

Body:

```json
{
  "success": true,
  "code": 200,
  "message": "PDF firmado exitosamente",
  "job": "bbb56528-83d3-4ba6-94f9-50b271cceb48",
  "data": [
    {
      "document_code": "550e8400-e29b-41d4-a716-446655440000",
      "name_pdf": "doc_prueba1.pdf",
      "status": "signed",
      "message": "Firmado exitosamente"
    }
  ]
}
```

**Campos del callback**

| Campo | Tipo | Descripción |
|---|---|---|
| `success` | boolean | `true` = todos firmados; `false` = hubo errores |
| `code` | number | `200` (OK) o `422` (hubo documentos con error) |
| `message` | string | Resumen del resultado |
| `job` | string | UUID del job |
| `data[]` | array | Resultado por documento |
| `data[].document_code` | string | El `document_code` que enviaste al crear el job |
| `data[].name_pdf` | string | Nombre del PDF |
| `data[].status` | string | `signed` u `error` |
| `data[].message` | string | Detalle del resultado |
| `data[].error_code` | string | (solo si falló) Código del error, ej: `SIGN_ERROR` |

**Ejemplo de callback con error (una firma falló):**

```json
{
  "success": false,
  "code": 422,
  "message": "Uno o más documentos no pudieron firmarse",
  "job": "bbb56528-83d3-4ba6-94f9-50b271cceb48",
  "data": [
    {
      "document_code": "550e8400-e29b-41d4-a716-446655440000",
      "name_pdf": "doc_prueba1.pdf",
      "status": "signed",
      "message": "Firmado exitosamente"
    },
    {
      "document_code": "550e8400-e29b-41d4-a716-446655440001",
      "name_pdf": "doc_prueba2.pdf",
      "status": "error",
      "error_code": "SIGN_ERROR",
      "message": "Error al firmar"
    }
  ]
}
```

Tu webhook debe responder con HTTP `200` para confirmar la recepción. El sistema intenta una única vez.

---

## 3. Encriptar la URI (AES-256-GCM)

**Responsable de esta etapa:** el cliente (empresa que integra el Componente de Firma). FirmEasy entrega la `KEY`, y es el cliente quien debe cifrar la URI antes de invocarla.

La `KEY` es única y compartida entre todos los clientes que integran el componente.

| Parámetro | Tamaño | Quién lo genera | ¿Es secreto? |
|---|---|---|---|
| `KEY` | 256 bits (32 bytes) | FirmEasy la entrega al cliente, una sola vez | Sí — nunca viaja en la URI |
| `IV` (nonce) | 96 bits (12 bytes) | El cliente, uno nuevo en cada encriptación | No — viaja dentro del BLOB |
| `AUTH_TAG` | 128 bits (16 bytes) | Se calcula automáticamente al cifrar (no es aleatorio, depende de KEY + IV + ciphertext) | No — viaja dentro del BLOB, se usa para verificar integridad |

**Formato del BLOB**

```
BLOB_RAW = IV (12 bytes) || CIPHERTEXT (N bytes) || AUTH_TAG (16 bytes)
BLOB = base64url_encode(BLOB_RAW)
```

**URI final:**

```
firmeasy://sign?data=BLOB
```

**Requisitos que debe cumplir cualquier implementación cliente**

1. **Tamaño del IV:** exactamente 12 bytes (96 bits).
2. **Tamaño del tag:** exactamente 16 bytes (128 bits), sin truncar.
3. **Orden de concatenación:** siempre `IV + CIPHERTEXT + AUTH_TAG`, en ese orden.
4. **Encoding final:** base64url (no base64 estándar), para que el BLOB viaje seguro dentro de la URI sin necesidad de urlencode adicional.

AES-256-GCM es un estándar (NIST SP 800-38D) soportado de forma nativa en la mayoría de lenguajes — el cliente puede implementarlo en el que use, siempre que respete los 4 puntos anteriores.

**Ejemplo de referencia — cifrado en PHP (lado cliente)**

```php
<?php

// 1) Construir la URI en claro (JOB_URL urlencoded + exp)
$jobUrl = 'http://10.21.132.143:8081/api/job/' . $jobId;
$uriPlano = 'firmeasy://sign?data=' . rawurlencode($jobUrl) . '&exp=' . $exp;

// 2) Encriptar con AES-256-GCM (KEY entregada por FirmEasy)
function encriptarUri(string $uriPlano, string $keyBase64): string {
    $key = base64_decode($keyBase64); // 32 bytes, entregada por FirmEasy
    $iv  = random_bytes(12);          // nuevo en cada llamada

    $ciphertext = openssl_encrypt(
        $uriPlano,
        'aes-256-gcm',
        $key,
        OPENSSL_RAW_DATA,
        $iv,
        $tag,      // se llena por referencia, 16 bytes
        '',        // AAD opcional
        16         // longitud del tag
    );

    $blobRaw = $iv . $ciphertext . $tag;
    return rtrim(strtr(base64_encode($blobRaw), '+/', '-_'), '='); // base64url
}

$uriFinal = 'firmeasy://sign?data=' . encriptarUri($uriPlano, 'TU_KEY_BASE64_ENTREGADA_POR_FIRMEASY');

// 3) Redirigir/abrir $uriFinal en el dispositivo móvil del firmante
header('Location: ' . $uriFinal);
```

**Flujo completo (lado de la empresa):**

1. Crear el job en tu backend (endpoint GET `/api/job/{job}` que devuelve el JSON de la sección 2.2).
2. Construir la URI en claro: `firmeasy://sign?data={JOB_URL}&exp={EXP}` con `{JOB_URL}` urlencoded.
3. Encriptar la URI con AES-256-GCM (sección 3) y obtener `firmeasy://sign?data=BLOB`.
4. Abrir/redirigir la URI final en el móvil del firmante.
5. La app descifra, valida `exp`, consulta `{JOB_URL}`, descarga el PDF desde `from`, firma y sube el PDF firmado a `to`.
6. Cuando el lote termina, tu webhook recibe el callback (sección 2.4).