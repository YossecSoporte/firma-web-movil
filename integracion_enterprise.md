# Componente de Firma – Manual de integración Enterprise

Software de firma digital (encriptación **X25519**)

**V 1.0.0**

## Historial de cambios

| Versión | Descripción | Fecha |
|---|---|---|
| 1.0.0 | Versión inicial | 05/08/2026 |

---

## 1. Introducción

El presente manual tiene como objetivo guiar la integración del Componente de Firma digital individual y firma masiva con cualquier aplicación web. **Toda la implementación se realiza en el sistema de la empresa (tu backend):** tu sistema crea el job, genera el deep link y lo lanza en el móvil del firmante.

Para completar la integración de la firma individual con el Componente de Firma en su aplicación web, debe realizar los siguientes pasos.

Vale la pena señalar que no está limitado a ningún lenguaje de programación o tecnología de servidor. Sin embargo, los ejemplos de código se muestran en PHP.

---

## 2. Manual de integración de FirmEasy – Aplicación móvil

### 2.1. Deep link para la comunicación a la aplicación móvil

```
firmeasy://integration?data={BLOB}&sid={TOKEN}
```

**Descripción de cada parámetro**

| Parámetro | Tipo | Descripción |
|---|---|---|
| `data` | string (base64url) | Blob cifrado con **X25519 (ECDH) + HKDF-SHA256 + AES-256-GCM**. Al descifrarlo se obtiene el payload `{"url": ..., "exp": ...}` |
| `sid` | string | Token de integración **en claro** (`tkn_bat_...` / `tkn_ind_...`). Se usa para identificar la sesión/proceso del firmante |

**Contenido del blob `data` (después de descifrar):**

```json
{
  "url": "https://mi-empresa.com/api/job/88e8313f-e578-4146-9f8d-0da3b9945b54",
  "exp": 1786393069
}
```

| Campo | Tipo | Descripción |
|---|---|---|
| `url` | string (URL) | Endpoint GET **de tu sistema** que la app consulta para obtener la configuración de firma (`from`, `to`, `doc_sha256`, `settings`) |
| `exp` | integer (Unix timestamp) | Expiración del job. 10 minutos desde la creación. Si la app abre la URI después de `exp`, debe rechazarla |

**Ejemplo de deep link** (construido por tu sistema):

```
firmeasy://integration?data=npMc2OYOsRn6RAxNxAXnpIcZkHsPy-wUH9kORDxxLUzRnn6FswwvaA_mhDkjB_oOPNj7gA8JS7Ih3eKqkAg9E1Rfw7JWB7-idso_BnQfoIzpPg9asoXyh1qZBYBybqf-TPw3yGniXyAALWKw2ijFKRjdsy67kNk0Iw&sid=tkn_bat_CTaIC6YKJR9o4v9vxSEcpJsPQYodbPvF
```

> El valor de `data` es **siempre** un blob cifrado (en base64url). Nunca se construye la URI con el `url` del job en claro.

### 2.1.1. De dónde se obtiene el token (`sid`)

El token `sid` lo emite la **API de autenticación de FirmEasy Enterprise**. La empresa lo solicita con su **API Key** (secreta, se usa solo del lado del servidor, nunca en el navegador):

```
POST https://enterprise.digital.firmeasy.legal/api/v1/auth/token
Headers:
  Content-Type: application/json
  X-API-KEY: {TU_API_KEY}
```

**Cuerpo según el tipo de proceso:**

| Body | Token resultante | Uso |
|---|---|---|
| `{"type": "batch"}` | `tkn_bat_...` | Firma en bloque / lote de documentos |
| `{"type": "individual"}` | `tkn_ind_...` | Firma individual de un documento |

**Respuesta:**

```json
{
  "token": "tkn_bat_CTaIC6YKJR9o4v9vxSEcpJsPQYodbPvF",
  "type": "batch",
  "expires_in": 300
}
```

El valor de `token` es exactamente el que se coloca en el parámetro `sid` del deep link.

> El token se obtiene en tu servidor (nunca expongas tu `X-API-KEY` en el navegador) justo antes de construir el deep link.

### 2.2. Cómo tu sistema genera el deep link

Todos estos pasos se implementan en el **backend de tu empresa**:

1. Generas el `job` (UUID v4) y su `exp` (10 minutos).
2. Creas el JSON del job (abajo) y lo **expones en `GET {url}`** — ese endpoint es el que la app consulta.
3. Obtienes el token de integración (sección 2.1.1) → es tu `sid`.
4. Cifras `{"url": ..., "exp": ...}` con X25519 + HKDF + AES-256-GCM (sección 3.5) → obtienes el `data`.
5. Construyes y abres en el móvil del firmante: `firmeasy://integration?data=BLOB&sid=TOKEN`.

**Job que tu sistema crea y expone en `GET {url}`** (la URL que viaja dentro del `data` cifrado):

```json
{
  "job": "88e8313f-e578-4146-9f8d-0da3b9945b54",
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
      "download": {
        "mode": "continue",
        "retry": 2
      },
      "upload": {
        "mode": "continue",
        "retry": 2
      }
    }
  },
  "documents": [
    {
      "document_code": "3041d9e3-7716-406e-aaa0-4607c921d3f1",
      "from": "https://mi-empresa.com/api/descargar?file=doc_prueba1.pdf",
      "to": "https://mi-empresa.com/api/subir-firmado?file=doc_prueba1.pdf&user_id=USER123&job=88e8313f-e578-4146-9f8d-0da3b9945b54&document_code=3041d9e3-7716-406e-aaa0-4607c921d3f1",
      "name_pdf": "doc_prueba1.pdf",
      "doc_sha256": "d5076e6e6ab7062b8fd7380e1e105214d482fa0876b2abc9b1a2badc0d4585d4",
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
  ],
  "callback": "https://mi-empresa.com/webhook/notificacion-firma"
}
```

> `from`, `to` y `callback` son **URLs de tu sistema**: la app descarga el PDF desde `from`, sube el PDF firmado a `to` y, al terminar, notifica el resultado a `callback`. Cada documento del lote lleva su `document_code`, `name_pdf`, `doc_sha256`, `status` (`pending`) y `settings`.

El deep link final queda así (ver el cifrado en la sección 3):

```
firmeasy://integration?data=BLOB&sid=TOKEN
```

### 2.3. Configuración que devuelve el job

La app móvil, tras descifrar `data`, consulta el endpoint GET `{url}` (el valor dentro del blob) para obtener la configuración de firma.

**Estructura que retorna:** es el **mismo job de la sección 2.2** (el que tu sistema crea y expone en `{url}`): `job`, `configuration`, `documents[]` (cada uno con `document_code`, `from`, `to`, `name_pdf`, `doc_sha256`, `status`, `settings`) y `callback`. La app lo usa así:

1. Descarga el PDF desde `from`.
2. Verifica su hash con `doc_sha256`.
3. Firma y sube el PDF firmado a `to`.
4. Al terminar el lote, notifica el resultado al webhook de `callback`.

> Si tienes más de un documento en el lote, `documents[]` contiene tantos elementos como PDFs se firmarán (ej. `doc_prueba1.pdf` … `doc_prueba10.pdf`).

### 2.4. Parámetros soportados por la aplicación

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

### 2.5. Callback (notificación de resultado)

Cuando la app móvil termina de procesar **todos** los documentos del lote, el sistema notifica el resultado al **webhook de la empresa** (el valor de `callback` que se envió al generar la URI). La empresa no necesita hacer polling: recibe la notificación cuando el proceso termina.

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

## 3. Encriptación del deep link (X25519 + AES-256-GCM)

La protección del parámetro `data` usa criptografía **asimétrica** con **X25519 (Curve25519)**. Ambos lados derivan un mismo secreto compartido con el intercambio de claves Diffie-Hellman (ECDH), sin que la clave privada de ninguno de los dos viaje.

### 3.1. Modelo de claves (quién genera qué)

| Paso | Quién | Qué |
|---|---|---|
| 1 | **Tu sistema** | Genera un par X25519 (privada + pública) una sola vez |
| 2 | **Tu sistema** | Envía a FirmEasy su **clave pública** y un `kid` (la privada nunca sale de tu lado) |
| 3 | **FirmEasy** | Guarda tu pública bajo el `kid` y te entrega su `FIRMEASY_PUBLIC` |
| 4 | **Tu sistema** | Deriva el secreto compartido (`tx`) y cifra `{"url", "exp"}` en el parámetro `data` |

> El descifrado del `data` lo hace la app móvil de FirmEasy con su par de claves (el `rx` que equivale a tu `tx`). Tu clave privada nunca sale de tu sistema.

**¿Qué es el `kid`?**

El `kid` (Key ID) es el **identificador** con el que FirmEasy registra y distingue tu clave pública:

- FirmEasy guarda las claves públicas de **muchos clientes a la vez** (`storage/keys/{kid}.json`); el `kid` permite saber de **qué cliente** es cada pública y asociarla a su cuenta (modelo multi-tenant y auditoría).
- Formato `[a-zA-Z0-9_-]`: letras, números, `-` y `_`. Ej.: `empresa-2026-09`, `acme-os`, `cliente-42`.
- No es un secreto: es solo una etiqueta. La seguridad real está en tu `SECRET` (que nunca viaja).
- Debe ser **único** en tu cuenta. Si registras dos veces el mismo `kid`, el segundo registro reemplaza al primero.
- Delimitación: registra un `kid` nuevo (o re-registra el mismo tras revocar) cada vez que roten tus claves.

### 3.2. Qué claves intervienen

| Clave | Quién la tiene | ¿Viaja? |
|---|---|---|
| `SECRET` (privada X25519) | Solo tu sistema | No |
| `PUBLIC` (pública X25519) | Tu sistema + FirmEasy | Sí (para registro) |
| `FIRMEASY_PUBLIC` (pública de FirmEasy) | FirmEasy + tu sistema | Sí (FirmEasy te la entrega) |
| Par de FirmEasy (`storage/firmeasy_keys.json`) | Solo FirmEasy | No |

Clave pública de FirmEasy de referencia:

| Clave | Valor (base64) |
|---|---|
| `FIRMEASY_PUBLIC` | `uc39D7873TaG9nps15ehTOHE+i7hhDt40zoV7Ov3k34=` |

### 3.3. Cómo generar el par de claves X25519 (una sola vez, lado cliente)

**PHP (libsodium — recomendado):**

```php
<?php
$keypair = sodium_crypto_kx_keypair();            // 64 bytes: secret(32) + public(32)
$secret  = sodium_crypto_kx_secretkey($keypair);   // 32 bytes — guardar, NO compartir
$public  = sodium_crypto_kx_publickey($keypair);   // 32 bytes — enviar a FirmEasy

echo 'SECRET: ' . sodium_bin2base64($secret, SODIUM_BASE64_VARIANT_ORIGINAL) . "\n";
echo 'PUBLIC: ' . sodium_bin2base64($public, SODIUM_BASE64_VARIANT_ORIGINAL) . "\n";
?>
```

**Node.js (tweetnacl):**

```js
const nacl = require('tweetnacl');
const kp   = nacl.box.keyPair();               // X25519 (Curve25519)
console.log('SECRET:', Buffer.from(kp.secretKey).toString('base64'));
console.log('PUBLIC:', Buffer.from(kp.publicKey).toString('base64'));
```

**Python (PyNaCl):**

```python
from nacl.public import PrivateKey
import base64

kp = PrivateKey.generate()
print('SECRET:', base64.b64encode(kp.encode()).decode())
print('PUBLIC:', base64.b64encode(kp.public_key.encode()).decode())
```

**Resultado (ejemplo):**

```
SECRET: <tu_clave_privada_base64>
PUBLIC: <tu_clave_publica_base64>
```

### 3.4. Registrar la clave pública (tu sistema)

Envía tu `PUBLIC` (base64) junto con un identificador `kid` (`[a-zA-Z0-9_-]`) al **endpoint de registro de FirmEasy** (la URL de registro te la indica FirmEasy al darte de alta):

```json
POST /api/register-key.php
{
  "kid": "empresa-2026-09",
  "public_key": "<TU_PUBLIC_base64>"
}
```

FirmEasy guarda tu pública bajo ese `kid` y la usa como referencia de que el deep link proviene de tu par de claves. Si pierdes el `SECRET`, revoca la clave en FirmEasy y genera un par nuevo.

**Qué recibes de FirmEasy:**

1. **Confirmación de registro** — la respuesta confirma que tu `PUBLIC` quedó guardada bajo tu `kid` (ej: `{"success": true, "kid": "empresa-2026-09", "public_key": "..."}`).
2. **`FIRMEASY_PUBLIC`** — la clave pública de FirmEasy (ver §3.2). Con tu `SECRET` + esta pública, tu sistema deriva la sesión de cifrado `tx` (§3.5) para proteger el `data` del deep link.

> La clave privada de FirmEasy nunca se entrega. Solo se comparte su pública.

### 3.5. Cadena criptográfica (cómo cifra tu sistema el `data`)

Tu sistema cifra de este modo:

```
1) Secreto compartido tx = sodium_crypto_kx_client_session_keys(tuKeypair, FIRMEASY_PUBLIC)[1]
2) aesKey = HKDF-SHA256(tx, salt="firmeasy-deeplink-v1", info="aes-encryption-key", 32)
3) BLOB_RAW = IV(12) || AES-256-GCM(aesKey, {"url": ..., "exp": ...}) || TAG(16)
4) BLOB = base64url(BLOB_RAW)

5) Deep link = firmeasy://integration?data=BLOB&sid=TOKEN
```

FirmEasy deriva el mismo secreto con su par y tu pública:

```
rx = sodium_crypto_kx_server_session_keys(parFirmEasy, PUBLIC_cliente)[0]
(rx == tx — mismo valor de 32 bytes)
```

La app móvil descifra `data` con la misma cadena (HKDF + AES-256-GCM inverso) para obtener `{"url", "exp"}`.

### 3.6. Requisitos de implementación (X25519)

1. Las claves se codifican en **base64 estándar** (con padding `=` si aplica).
2. El secreto compartido se deriva con **`crypto_kx`** (libsodium / NaCl), compatible entre lenguajes. No usar X25519/ECDH plano.
3. HKDF-SHA256 (RFC 5869): `extract` con el salt + `expand` con el info, salida binaria de 32 bytes.
4. AES-256-GCM: IV **12 bytes**, tag **16 bytes**, orden `IV + CIPHERTEXT + TAG`.
5. Encoding final del blob: **base64url** (sin padding), para que viaje seguro en la URI.
6. El deep link final siempre es `firmeasy://integration?data=BLOB&sid=TOKEN`; `sid` va en claro, el contenido sensible va dentro del blob cifrado.

### 3.7. Flujo completo

1. Tu sistema genera el par X25519 y registra tu `PUBLIC` + `kid` en FirmEasy (sección 3.4).
2. FirmEasy te entrega la `FIRMEASY_PUBLIC` (sección 3.4).
3. Tu sistema obtiene el token de integración `sid` (sección 2.1.1).
4. Tu sistema crea el job y lo expone en `GET {url}` (sección 2.2).
5. Tu sistema cifra `{"url", "exp"}` con X25519 + HKDF + AES-256-GCM (sección 3.5) y construye `firmeasy://integration?data=BLOB&sid=TOKEN`.
6. El navegador del firmante dispara el deep link → abre la app móvil FirmEasy.
7. La app descifra `data`, valida `exp` y consulta `GET {url}` (sección 2.3).
8. La app descarga el PDF desde `from`, lo firma y lo sube a `to`.
9. Cuando el lote termina, tu webhook recibe el callback (sección 2.5).

---

## 7. Códigos de error

Los siguientes códigos pertenecen a la app, servicios de red o servidor. Pueden mostrarse en pantalla; llegar al callback depende de la etapa y de que ya se conozca el manifiesto/destino. No todos los errores de pantalla producen callback. Los mensajes concretos pueden cambiar o incluir el nombre del PDF.

### Enlace y descifrado

| Código | Significado |
|---|---|
| `APP-100` | Esquema/acción/formato de enlace inválido o falta data. |
| `APP-101` | Blob no válido como Base64/Base64URL. |
| `APP-102` | Blob demasiado corto para IV y tag. |
| `APP-103` | Falló autenticación AES-GCM: tag inválido, contenido alterado o claves incompatibles. |
| `APP-104` | Contenido descifrado inválido. |
| `APP-105` | URL del job inválida. |
| `APP-106` | Expiración ausente o con formato inválido. |
| `APP-107` | Enlace expirado. |
| `APP-108` | Código reservado para token requerido; en esta entrada token es opcional. |
| `APP-109` | Cancelación explícita del usuario. |
| `APP-111` | Dominio del job o redirección no autorizado por allowed_domains de la sesión. |

### Sesión

| Código | Significado |
|---|---|
| `NET-200` | Sin conexión a Internet. |
| `APP-210` | Token de sesión vacío. |
| `APP-216` | Respuesta de sesión incompleta. |
| `SRV-211` | Error general al iniciar sesión. |
| `SRV-212` | No autorizado (HTTP 401/403). |
| `SRV-213` | HTTP 410: recurso expirado/no disponible; puede indicar token ya usado. |
| `SRV-214` | Timeout o conexión lenta en inicio de sesión. |
| `SRV-215` | Cuota agotada o HTTP 429 en sesión. |

### Consulta y validación del job

| Código | Significado |
|---|---|
| `NET-300` / `NET-301` | Conexión fallida / timeout al obtener el job. |
| `SRV-300` | Job: HTTP 400. |
| `SRV-301` | Job: HTTP 404. |
| `SRV-302` | Job: HTTP 410. |
| `SRV-303` | Job: HTTP 5xx. |
| `SRV-304` | Job: HTTP 401/403. |
| `SRV-305` | Job: HTTP 429. |
| `SRV-306` | Otro error HTTP del job. |
| `APP-304` | Job/configuración ausente o respuesta inválida; también política batch inválida. |
| `APP-305` | Falta identificador job. |
| `APP-306` | URL callback inválida. |
| `APP-310` | Lista de documentos vacía o ausente. |
| `APP-311` | document_code ausente/inválido. |
| `APP-312` | document_code duplicado. |
| `APP-313` | URL from inválida. |
| `APP-314` | URL to inválida. |
| `APP-315` | name_pdf inválido. |
| `APP-316` | doc_sha256 no tiene 64 caracteres hexadecimales. |

### Descarga, imágenes e integridad

| Código | Significado |
|---|---|
| `APP-320` | PDF sin URL de descarga. |
| `APP-406` | Documento descargado vacío. |
| `APP-407` | Error local/de conexión/timeout de descarga sin código HTTP específico. |
| `APP-408` | Fallo al descargar imagen de firma. |
| `APP-409` | Imagen de firma inválida. |
| `SRV-400` | Error HTTP de descarga genérico, incluido 400. |
| `SRV-401` | Descarga no autorizada: HTTP 401/403. |
| `SRV-404` | PDF no encontrado: HTTP 404. |
| `SRV-405` | PDF no disponible: HTTP 410. |
| `SRV-408` | Error de servidor al descargar: HTTP 5xx. |
| `SRV-409` | Demasiadas solicitudes de descarga: HTTP 429. |
| `NET-400` | Código definido para ausencia de red en descarga; no todos los fallos de red se mapean a él. |
| `APP-500` | Falta huella para verificar integridad. |
| `APP-501` | SHA-256 no coincide. |
| `APP-502` | No existe el archivo local a verificar. |
| `APP-503` | No se puede leer el PDF para calcular su hash. |

### Firma y subida

| Código | Significado |
|---|---|
| `APP-600` | Fallo de firma / operación de firma bloqueada. |
| `APP-601` | La firma no devolvió documentos/resultados. |
| `APP-602` | Fallo parcial de firma. |
| `APP-700` | Ningún documento firmado tiene subida confirmada, o fallo global de subida. |
| `APP-701` | Subida parcial: algunos resultados no tienen entrega confirmada. |
| `APP-900` | Error inesperado del flujo. |

### Consumo y cierre (posteriores al callback de subida)

| Código | Significado |
|---|---|
| `SRV-600` | Error general al registrar consumo. |
| `SRV-601` | Sesión cerrada al registrar consumo. |
| `SRV-602` | Timeout al registrar consumo. |
| `SRV-700` | Error general al cerrar sesión. |
| `SRV-701` | Timeout al cerrar sesión. |

Estos últimos códigos forman parte del catálogo interno. No debe esperarse un nuevo callback terminal por esas tareas posteriores.