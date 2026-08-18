# README del Programador — App móvil FirmEasy

Documento técnico para el programador de la **app móvil FirmEasy**: cómo recibir, descifrar y validar el deep link que envía el backend web, y qué endpoint consultar para obtener los datos de firma.

> Contraparte de la documentación de integración: ver también `README.md` e `INTEGRACION_API.md`.

---

## 1. Visión general del flujo

El backend web genera un **job de firma** y devuelve un deep link que la app recibe al abrirse:

```
firmeasy://sign?data=BASE64URL_BLOB
```

La app debe hacer, en orden:

```
1. Recibir el deep link (firmeasy://sign?data=...)
2. Extraer el parámetro `data` (el blob)
3. Descifrar el blob con AES-256-GCM  →  obtiene: job URL + exp + token
4. Validar los datos (autenticidad, exp, token, job)
5. Consultar GET /api/job/{job_id}    →  obtiene: configuration + documents (from/to/settings)
6. Descargar el PDF de `from`, firmarlo y subirlo a `to`
```

Este documento se centra en los **pasos 3, 4 y 5** (descifrado, validación y consulta del endpoint). Los pasos 1, 2 y 6 se resumen al final.

---

## 2. Registro del deep link en la app

- **Android (AndroidManifest.xml):**
  ```xml
  <activity android:name=".DeepLinkActivity" android:exported="true">
    <intent-filter>
      <action android:name="android.intent.action.VIEW" />
      <category android:name="android.intent.category.DEFAULT" />
      <category android:name="android.intent.category.BROWSABLE" />
      <data android:scheme="firmeasy" android:host="sign" />
    </intent-filter>
  </activity>
  ```
- **iOS (Info.plist):**
  ```xml
  <key>CFBundleURLTypes</key>
  <array>
    <dict>
      <key>CFBundleURLSchemes</key>
      <array><string>firmeasy</string></array>
    </dict>
  </array>
  ```

**Importante — HTTP en claro:** el `job`, `from` y `to` apuntan a `http://IP:8081` (sin HTTPS). En Android hay que permitir tráfico en claro (`android:usesCleartextTraffic="true"` o Network Security Config para ese dominio); en iOS, ATS debe permitir el dominio (excepción `NSAllowsArbitraryLoads` o por dominio, solo para desarrollo/pruebas LAN).

---

## 3. Descifrado del blob `data` (lo más importante)

### 3.1 Datos de configuración

| Dato | Valor |
|---|---|
| Algoritmo | **AES-256-GCM** (tag autenticado de 128 bits, sin padding) |
| Clave | `ENCRYPTION_KEY` — base64 de **32 bytes** compartida backend ↔ app |
| Valor actual | `l1L0gPaxGHkH/aei5Hs7awe8XhPrHHtzywfZYF+mTc0=` |
| Formato del blob | `base64url( IV(12 bytes) ‖ CIPHERTEXT ‖ TAG(16 bytes) )` |
| Base64 | URL-safe sin padding (`-` en vez de `+`, `_` en vez de `/`) |

### 3.2 Pasos de descifrado

```
blob = parámetro "data" del deep link   (string base64url sin padding)

1) Agregar padding "=" hasta múltiplo de 4 si falta
2) Reemplazar: '-' → '+', '_' → '/'
3) Decodificar de Base64 → array de bytes "raw"

4) Verificar que raw.length >= 28  (12 + 16)
   iv  = raw[0 .. 11]                    // 12 bytes
   tag = raw[raw.length-16 .. len-1]     // 16 bytes (últimos)
   ct  = raw[12 .. raw.length-16-1]      // todo lo del medio

5) Descifrar con AES-256-GCM:
   cipher = AES/GCM/NoPadding
   cipher.init(DECRYPT_MODE, clave(32 bytes), GCMParameterSpec(128, iv))
   plaintext = cipher.doFinal( ct + tag )    // ct y tag concatenados

6) plaintext es la URI en claro → convertir a string UTF-8
```

**Nota:** si el descifrado lanza excepción de *Authentication Tag mismatch*, el blob fue alterado o la clave no coincide → **rechazar** (no seguir).

### 3.3 Resultado esperado

El texto descifrado tiene este formato (es la URI `firmeasy://sign` con sus parámetros):

```
firmeasy://sign?data={URL_ENCODED}&exp={unix_ts}&token={token}
```

Ejemplo real (descifrado):

```
firmeasy://sign?data=http%3A%2F%2Flocalhost%3A8081%2Fapi%2Fjob%2F9bccc656-a49e-480a-82da-d20a995c39f3&exp=1786726430&token=TOKEN_DE_PRUEBA_123
```

| Parámetro | Tipo | Descripción |
|---|---|---|
| `job` | string | **URL completa, codificada (urlencoded)** del endpoint a consultar. Hay que **URL-decodificarla** antes de usarla |
| `exp` | integer | Timestamp Unix de expiración (10 minutos desde la creación). **Validar SIEMPRE** |
| `token` | string | Token del usuario final (el que escribió en el modal web). Solo se obtiene del blob descifrado, NO del endpoint |

---

## 4. Validaciones que debe hacer la app (en este orden)

| # | Qué valida | Cómo | Si falla |
|---|---|---|---|
| 1 | Autenticidad del blob | Que el tag GCM verifique (no excepción de tag) | Rechazar el deep link, no continuar |
| 2 | `exp` | `exp < tiempo_actual_unix` → expirado | Mostrar "El enlace de firma ha expirado" y salir |
| 3 | `token` | Debe existir y no estar vacío en el texto descifrado | Rechazar (job malformado) |
| 4 | `job` | Debe ser una URL http/https válida y contener `/api/job/` | Rechazar |

**Regla de oro:** el `token` y el `exp` **solo se obtienen del blob descifrado**. No van en ninguna otra parte (ni en el deep link en claro, ni en la respuesta del endpoint).

---

## 5. Consulta al endpoint del job

Con el parámetro `job` **URL-decodificado** (pasó de `http%3A%2F%2F...` a `http://...`):

```
GET {job}
Ejemplo: GET http://localhost:8081/api/job/9bccc656-a49e-480a-82da-d20a995c39f3
```

No requiere headers de autenticación (CORS abierto `*`).

### 5.1 Respuesta exitosa (200) — estructura

```json
{
  "job": "9bccc656-a49e-480a-82da-d20a995c39f3",
  "configuration": {
    "signature_type": "basic",
    "signature_reason": "Acepto el contenido del documento",
    "generate_request": "NOMBRE EMPRESA",
    "certificate_type": "all"
  },
  "documents": [
    {
      "from": "http://localhost:8081/api/download.php?file=doc_prueba1.pdf",
      "to": "http://localhost:8081/api/upload-signed.php?file=doc_prueba1.pdf&user_id=USER123",
      "doc_sha256": "d5076e6e6ab7062b8fd7380e1e105214d482fa0876b2abc9b1a2badc0d4585d4",
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

### 5.2 Descripción de campos

| Campo | Tipo | Descripción |
|---|---|---|
| `job` | string | ID del job (coincide con el del deep link) |
| `configuration.signature_type` | string | Tipo de firma. Actual: `"basic"` |
| `configuration.signature_reason` | string | Motivo de la firma (se inyecta en `{{signature_reason}}`) |
| `configuration.generate_request` | string | Nombre de la empresa/solicitante |
| `configuration.certificate_type` | string | `"all"` \| `"dni"` \| `"certificado"` — define qué certificado usar en la firma |
| `documents[0].from` | string (URL) | **Descarga del PDF original** a firmar |
| `documents[0].to` | string (URL) | **Subida del PDF firmado** (binario) |
| `documents[0].doc_sha256` | string | SHA-256 del PDF original (verificación opcional) |
| `documents[0].settings` | object | Parámetros visuales de la firma (`vis_sig_*`) |

**Aviso importante:** la respuesta **NO incluye `token` ni `exp`**. El `token` y `exp` solo se obtienen del blob descifrado (paso 3). No buscarlos en esta respuesta.

### 5.3 Códigos de error del endpoint

| Código | Causa | Qué hacer en la app |
|---|---|---|
| 400 | Job ID malformado | Mostrar "Enlace inválido" |
| 404 | Job no existe | Mostrar "Enlace no válido o ya no existe" |
| 410 | Job **expirado** | Mostrar "El enlace de firma ha expirado" |

---

## 6. Resumen de los pasos siguientes (firma y subida)

Con `from`, `to`, `configuration` y `settings` ya en la app:

1. **Descargar el PDF** desde `from` (GET normal, sin auth).
2. *(Opcional pero recomendado)* Calcular SHA-256 del PDF descargado y comparar con `doc_sha256`.
3. **Firmar el PDF** según `configuration.certificate_type` y ubicar la firma visible según `settings`:
   - `vis_sig_x`, `vis_sig_y`, `vis_sig_width`, `vis_sig_height`, `vis_sig_page` (1-indexed), `vis_sig_text_size`.
   - `vis_sig_text`: plantilla con placeholders a reemplazar: `<SIGNER>` (firmante), `<DATE>` (fecha), `<OU>` (unidad/empresa), `{{signature_reason}}` (motivo).
   - `vis_sig_graphic`: URL opcional de un logo para la firma visible.
4. **Subir el PDF firmado** a `to` **en binario** (raw bytes, no multipart), `Content-Type: application/pdf`.
5. Al volver al navegador web, el documento pasa de *Pendiente* a *Firmado*.

Referencia de subida (simulación con PowerShell):

```powershell
$bytes = [System.IO.File]::ReadAllBytes("C:\...\document\doc_prueba1.pdf")
Invoke-RestMethod -Uri "http://localhost:8081/api/upload-signed.php?file=doc_prueba1.pdf&user_id=USER123" -Method Post -ContentType "application/pdf" -Body $bytes
```

---

## 7. Checklist de aceptación para el programador

- [ ] La app abre al recibir `firmeasy://sign?data=...` (Android e iOS).
- [ ] Extrae `data`, lo decodifica (base64url) y descifra con AES-256-GCM + `ENCRYPTION_KEY`.
- [ ] Descifrado con tag auténtico; si falla, rechaza el enlace.
- [ ] Valida `exp` (rechaza si expirado).
- [ ] Extrae `token` del blob (no lo busca en el endpoint).
- [ ] URL-decodifica `job` y hace `GET {job}`.
- [ ] Interpreta `configuration.certificate_type` (all/dni/certificado).
- [ ] Descarga el PDF de `from` y verifica `doc_sha256` (opcional).
- [ ] Coloca la firma con `settings.vis_sig_*` y reemplaza placeholders.
- [ ] Sube el PDF firmado en binario a `to` (POST, `application/pdf`).

---

## 8. Referencias y constantes

| Constante | Valor |
|---|---|
| Esquema deep link | `firmeasy://sign?data=...` |
| Cifrado | AES-256-GCM, tag 128 bits, IV 12 bytes, sin padding |
| Formato blob | `base64url( IV ‖ CIPHERTEXT ‖ TAG )` |
| `ENCRYPTION_KEY` | `l1L0gPaxGHkH/aei5Hs7awe8XhPrHHtzywfZYF+mTc0=` (base64, 32 bytes) |
| `EXPIRACION_SEGUNDOS` | `600` (10 minutos) |
| Endpoint job | `GET /api/job/{job_id}` |
| Endpoint descarga | `GET /api/download.php?file={nombre.pdf}` |
| Endpoint subida firmado | `POST /api/upload-signed.php?file={nombre.pdf}&user_id={id}` (binario) |

**Dónde va la clave en el backend:** `ENCRYPTION_KEY` está en `docker-compose.yml` (variable de entorno). La misma clave debe estar configurada en la app móvil.
