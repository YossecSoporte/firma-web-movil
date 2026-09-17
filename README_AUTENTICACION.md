# Guía de la web para invocar FirmEasy Signer: autenticación

Esta guía explica cómo construir la URI de autenticación en el backend de la empresa, con cualquier lenguaje, y qué endpoints exponer para completar el flujo. Se basa en la implementación PHP de referencia. La clave AES se entrega por separado.

El flujo es de **autenticación con certificado** mediante `firmeasy://auth`, no de firma de PDFs.

## 1. Flujo completo que implementa la web

1. El backend prepara `display_name`, `accepted_issuers` y las URLs de la integración.
2. Genera tres UUID v4 (`job`, `jti`, `state`) y una expiración de 600 segundos.
3. Guarda la operación y construye los claims con las URLs de su propio backend.
4. Firma el JSON de los claims con **Ed25519** usando la clave privada de la web.
5. Construye el sobre `{"claims": {...}, "sig": "..."}`.
6. Cifra ese sobre con **AES-256-GCM** y devuelve `firmeasy://auth?data=<blob>`.
7. El frontend abre ese enlace para invocar FirmEasy Signer.
8. La web atiende la consulta a `challenge_url`: entrega un nonce aleatorio asociado a `state`.
9. La web recibe en `submit_url` la firma de los **bytes del nonce**, la verifica y guarda la respuesta cuando la acepta.
10. El propio backend de `submit` envía un callback de éxito a `callback_url`.
11. El frontend consulta `check-verify` para mostrar el resultado.

**Separación de responsabilidades:** el navegador solo solicita y abre el enlace. El backend genera claves/datos, firma, cifra, entrega el reto y recibe el resultado. El callback lo emite este backend, no la pantalla.

## 2. Claves y configuración que necesita el equipo web

| Elemento | Formato y uso | Qué se comparte |
|---|---|---|
| `ENCRYPTION_KEY_AUTH` | Variable de entorno con base64 estándar de una clave AES de **32 bytes**. Se decodifica antes de cifrar. | El valor se entrega por otro medio y debe coincidir con el configurado para descifrar en FirmEasy. |
| Ed25519 `secret_key` | Clave secreta de **64 bytes** en formato libsodium, almacenada en base64 estándar en `storage/auth_keys.json`. Firma los claims. | La conserva el backend que genera solicitudes; no se envía en el deep link. |
| Ed25519 `public_key` | Clave pública de **32 bytes**, base64 estándar. | Se comparte con FirmEasy para verificar la firma de los claims. |
| Certificado del usuario | Para este flujo P7S, el contenedor CMS/PKCS#7 debe incluir el certificado que necesita el verificador. | No se envía como campo separado de `submit` ni se pide la clave privada del usuario. |

Clave pública Ed25519 actual de este entorno de PRUEBA:

```text
IdOvuH2044hfmWMQTKqrXQwoSbZcvhJLAhtc9cp0rn4=
```

Esta clave pública corresponde al par actual de `storage/auth_keys.json`. Si el nuevo backend reutiliza ese par, su clave privada se configura en servidor por el canal interno correspondiente. Si genera un par nuevo, debe compartir la nueva pública con FirmEasy; no puede firmar con una privada nueva y esperar verificación con la pública anterior.

`generate_auth_keys.php` crea el par mediante libsodium únicamente si no existe el archivo. En el proyecto PHP se puede ejecutar una vez:

```sh
php generate_auth_keys.php
```

No se ejecutó ni se regeneró ningún par durante esta revisión.

### Configuración de URLs

El código actual fija:

```text
Challenge:    http://localhost:8081/api/auth/challenge.php?state=<STATE>
Submit:       http://localhost:8081/api/auth/submit.php
Callback por defecto:
              http://localhost:8081/api/auth/callback.php
```

En el backend de la empresa, construir `challenge_url` y `submit_url` con el dominio/IP accesible desde el móvil, y configurar `callback_url` para que sea alcanzable desde el servidor que ejecuta `submit`.

`localhost` en el móvil apunta al móvil. 

## 3. Datos que prepara el backend para construir la URI

El backend de cada empresa prepara directamente este objeto `claims`. No necesita llamar a un endpoint de generación de este proyecto: puede implementar la firma y el cifrado en su propio lenguaje con los pasos de las secciones 4 y 5.

```json
{
  "iss": "firmeasy-web",
  "aud": "firmeasy-signer",
  "purpose": "authentication",
  "iat": 1789597025,
  "nbf": 1789597025,
  "exp": 1789597625,
  "jti": "2f8db78d-bce5-4d05-af98-b433e8faa940",
  "job": "7cf15305-db0d-4f5e-9157-6e1bf055521d",
  "state": "271f103f-7a57-48a2-bf0c-bc2ce71acddc",
  "display_name": "Ingresar como Organización Acme",
  "accepted_issuers": [
    "CN=AC RAIZ001, O=RENIEC, C=PE",
    "CN=FirmEasy SubCA, O=GIRASOL PE SOCIEDAD COMERCIAL DE RESPONSABILIDAD LIMITADA, C=PE",
    "CN=ECEP-RENIEC CAClass 2,O=Registro Nacional de Identificación y Estado Civil,C=PE"
  ],
  "challenge_url": "http://localhost:8081/api/auth/challenge.php?state=271f103f-7a57-48a2-bf0c-bc2ce71acddc",
  "submit_url": "http://localhost:8081/api/auth/submit.php",
  "callback_url": "https://web.empresa.com/api/auth/callback"
}
```

Los UUID y las fechas son ilustrativos: generar valores nuevos para cada operación. Configurar `display_name`, `accepted_issuers` y las URLs según la integración de la empresa. Este objeto se firma y se incluye dentro del sobre `{claims, sig}` que se cifra; la URI se construye después del cifrado.

### Significado de los claims que construye la web

| Campo | Valor/origen y finalidad |
|---|---|
| `iss` | Constante `firmeasy-web`: identifica al emisor de la solicitud. |
| `aud` | Constante `firmeasy-signer`: identifica al destinatario previsto. |
| `purpose` | Constante `authentication`: diferencia esta operación de firma de documentos. |
| `iat` | Hora de generación, Unix en **segundos**. |
| `nbf` | Igual a `iat`: instante desde el que es válida la solicitud. |
| `exp` | `iat + 600`: caducidad a los diez minutos. |
| `jti` | UUID v4 de la solicitud emitida. Se guarda pero estos endpoints no implementan consumo único por `jti`. |
| `job` | UUID v4 de la operación; nombre del archivo persistido del job. |
| `state` | UUID v4 usado para encontrar el job, obtener el reto, recibir la firma y consultar resultado. |
| `display_name` | Texto descriptivo enviado por el frontend o su valor por defecto. |
| `accepted_issuers` | Lista de DN enviada al firmador. El `submit` actual no comprueba esta lista. |
| `challenge_url` | Endpoint de la web que devuelve el nonce para este `state`. |
| `submit_url` | Endpoint de la web que recibe la firma del nonce. |
| `callback_url` | Destino al que el backend de `submit` notifica éxito. Se guarda también en el job. |

## 4. Firma Ed25519: bytes exactos que genera la web

El backend construye los claims en el orden mostrado en el objeto de la sección 3: `iss`, `aud`, `purpose`, `iat`, `nbf`, `exp`, `jti`, `job`, `state`, `display_name`, `accepted_issuers`, `challenge_url`, `submit_url`, `callback_url`.

Serializa con:

```php
json_encode($claims, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
```

Para reproducir los ejemplos habituales en otro lenguaje:

- JSON compacto: sin indentación ni espacios añadidos entre elementos.
- Texto UTF-8, sin BOM.
- No escapar `/` como `\/`.
- No transformar los caracteres Unicode comunes, como letras acentuadas, en secuencias `\uXXXX`.
- Conservar el orden de los campos y del array de emisores.
- Fechas como números enteros, no strings.

No hay un algoritmo de canonicalización JSON adicional. La firma se calcula sobre **los bytes concretos** del JSON, no sobre un objeto abstracto. Si otra biblioteca vuelve a serializar con distinto orden o escapes, cambia el mensaje firmado. Para caracteres especiales y casos fuera de los ejemplos, comparar los bytes con la salida de PHP.

Después:

```text
claims_bytes = UTF8(JSON_compacto_compatible(claims))
secret = base64_estandar_decode(secret_key)
sig_bytes = Ed25519_sign_detached(claims_bytes, secret)
sig = base64url_sin_padding(sig_bytes)
```

La firma Ed25519 binaria tiene **64 bytes**. No es HMAC, no es un JWT y no se firma el blob cifrado.

Algunas bibliotecas reciben una semilla Ed25519 de 32 bytes o un contenedor PKCS#8, mientras libsodium guarda un secreto de 64 bytes. Importar/convertir el formato correctamente; no pasar los 64 bytes sin más a una API que espera una semilla. Confirmar que la pública derivada coincide con la que usa FirmEasy.

## 5. Cifrado AES-256-GCM y construcción del enlace

### Plaintext real

Lo que se cifra, y lo que debe recuperarse al descifrar `data`, es este sobre:

```json
{
  "claims": {
    "iss": "firmeasy-web",
    "aud": "firmeasy-signer",
    "purpose": "authentication"
  },
  "sig": "<FIRMA_ED25519_BASE64URL>"
}
```

Aquí se abrevió `claims` para mostrar la estructura: el contenido real incluye **todos** los campos de la sección 3. El sobre también se serializa como JSON compacto con las mismas opciones de UTF-8/Unicode/slashes. No contiene `deep_link` ni `data` dentro de sí mismo.

### Parámetros exactos

| Parámetro | Valor actual |
|---|---|
| Algoritmo | AES-256-GCM |
| Clave | 32 bytes obtenidos de base64 estándar de `ENCRYPTION_KEY_AUTH` |
| IV/nonce de cifrado | **12 bytes aleatorios nuevos**, `random_bytes(12)` |
| Plaintext | Bytes UTF-8 del JSON del sobre `{claims, sig}` |
| AAD | Cadena vacía: **0 bytes** |
| Ciphertext | Binario, equivalente a `OPENSSL_RAW_DATA` |
| Tag | **16 bytes** |
| Concatenación | `IV || CIPHERTEXT || TAG` |
| Codificación del blob | Base64url sin `=` al final |
| Enlace | `firmeasy://auth?data=` seguido del blob codificado |

No confundir el **IV de cifrado de 12 bytes** con el **nonce de autenticación de 32 bytes** que entrega `challenge` más adelante.

### Base64url usado

```text
base64url(bytes) = base64_estandar(bytes)
                  reemplazar '+' por '-'
                  reemplazar '/' por '_'
                  quitar '=' finales
```

No se codifica IV, ciphertext y tag por separado. Primero se concatenan sus bytes y luego se codifica el conjunto.

### Pseudocódigo del backend para cualquier lenguaje

```text
key = base64_standard_decode(ENCRYPTION_KEY_AUTH)
assert length(key) == 32

claims_json = serialize_like_PHP(claims)
signature = Ed25519.sign_detached(UTF8(claims_json), web_private_key)
envelope = { "claims": claims, "sig": base64url(signature) }
plaintext = UTF8(serialize_like_PHP(envelope))

iv = random_bytes(12)
ciphertext, tag = AES_256_GCM_encrypt(
    key=key, nonce=iv, plaintext=plaintext, aad=empty_bytes, tag_length=16
)
blob = concatenate(iv, ciphertext, tag)
data = base64url(blob)
deep_link = "firmeasy://auth?data=" + data
```

Si la biblioteca devuelve `ciphertext || tag` juntos, separar o concatenar correctamente: no añadir el tag dos veces.

### Cómo comprobar el enlace generado por la web

Para una prueba local de interoperabilidad:

```text
blob = base64url_decode(data)   # restaurar padding si la biblioteca lo necesita
iv = blob[0:12]
tag = blob[length(blob)-16:length(blob)]
ciphertext = blob[12:length(blob)-16]
plaintext = AES_256_GCM_decrypt(key, iv, ciphertext, tag, aad=empty_bytes)
envelope = JSON_parse(UTF8_decode(plaintext))
assert envelope contiene claims y sig
```

Después verificar `sig` con la pública Ed25519 y los bytes de los claims serializados de forma compatible. Dos enlaces generados para los mismos claims pueden ser distintos por el IV aleatorio; comparar el contenido y la verificación, no exigir blobs idénticos.

## 6. Abrir la URI construida

Una vez que el backend ha firmado los claims y cifrado el sobre, entrega la URI final a la página mediante el mecanismo propio de su aplicación. El navegador la abre:

```javascript
// uriAutenticacion contiene la URI completa construida por el backend.
window.location.href = uriAutenticacion;
```

Formato: `firmeasy://auth?data=<BLOB_BASE64URL>`. No hay una llamada HTTP adicional a FirmEasy para construir ese enlace. Abrirlo inicia el flujo; la web espera después el resultado de la firma.

## 7. Endpoint de reto que expone la web

### `GET /api/auth/challenge.php?state=<STATE>`

Busca un job por `state`. Si no tiene nonce, genera **32 bytes aleatorios**, los codifica en base64url sin padding y los guarda en el job como `nonce`, junto con `nonce_created_at`.

**El nonce se genera una sola vez por job:** consultas posteriores devuelven el mismo, mientras no haya expirado. El comentario del archivo dice “nonce fresco”, pero la implementación reutiliza el guardado.

Respuesta HTTP 200:

```json
{
  "state": "271f103f-7a57-48a2-bf0c-bc2ce71acddc",
  "nonce": "<BASE64URL_DE_32_BYTES_ALEATORIOS>",
  "purpose": "authentication",
  "display_name": "Ingresar como Organización Acme",
  "accepted_issuers": ["CN=AC RAIZ001, O=RENIEC, C=PE"],
  "expires_at": "2026-09-16T13:00:00-05:00"
}
```

`expires_at` es la fecha del `exp` del job en formato ISO 8601, usando la zona horaria de PHP. El valor de fecha de este ejemplo es ilustrativo.

**Qué bytes verificará la web:** decodifica `nonce` de base64url a los **32 bytes originales**. La firma que recibe debe corresponder a esos bytes, no al texto base64url ni al JSON completo del reto.

| HTTP | Respuesta |
|---|---|
| 400 | `{"error":"state requerido"}` |
| 404 | `{"error":"state no encontrado"}` |
| 410 | `{"error":"state expirado"}` |

El vencimiento se comprueba con `time() > exp`. El archivo no impone el método HTTP, aunque su uso previsto es GET.

## 8. Endpoint que recibe la firma

### `POST /api/auth/submit.php`

Cuerpo JSON:

```json
{
  "state": "271f103f-7a57-48a2-bf0c-bc2ce71acddc",
  "signature": "CONTENIDO_DEL_P7S_EN_BASE64",
  "algorithm": "SHA256withRSA"
}
```

| Campo | Requisito/comportamiento |
|---|---|
| `state` | Obligatorio y no vacío. Debe identificar un job existente. |
| `signature` | Contenido binario completo del archivo P7S (CMS/PKCS#7 DER), codificado en base64 estándar. No enviar la ruta, el nombre del archivo ni base64url. |
| `algorithm` | Algoritmo con el que se firmó el P7S; por ejemplo, `SHA256withRSA` indica SHA-256 con RSA. El PHP actual lo registra y copia al callback; no lo usa para seleccionar el verificador. |

El cuerpo de `submit` contiene únicamente estos tres campos: `state`, `signature` y `algorithm`. No se envía `certificate` por separado. El cliente debe enviar `Content-Type: application/json`; el servidor procesa `php://input` como JSON.

`CMS/PKCS#7` describe el formato del contenedor P7S; `SHA256withRSA` describe el algoritmo de firma. Por eso el ejemplo usa `SHA256withRSA` en `algorithm`, no `CMS`. La firma criptográfica se verifica a partir del P7S, no confiando únicamente en esa etiqueta. El PHP permite omitir `algorithm` y entonces guarda una cadena vacía, pero esta integración debe enviarlo con el algoritmo utilizado.

### Orden real de verificación

1. Buscar el job por `state`.
2. Exigir que exista `nonce`: se debe haber consultado `challenge` antes.
3. Decodificar el nonce a sus bytes originales y `signature` desde base64.
4. Intentar verificación **CMS/PKCS#7 DER** mediante:

```sh
openssl smime -verify -inform DER -noverify -content <archivo_nonce_binario> -in <archivo_firma_DER>
```

5. Si el comando termina con código 0, aceptar.
El flujo de integración descrito utiliza únicamente P7S. Si la verificación CMS falla, el nuevo backend debe rechazar la firma. El PHP de referencia contiene un fallback permisivo que puede aceptar una firma no vacía sin verificación criptográfica; no reproducir ese comportamiento como validación del P7S.

`-noverify` omite la validación de confianza de la cadena de certificados en CMS. El `submit` tampoco compara `accepted_issuers`, comprueba `exp` ni marca el job como consumido. Son límites concretos de esta implementación.

### Respuesta de éxito HTTP 200

```json
{
  "received": true,
  "state": "271f103f-7a57-48a2-bf0c-bc2ce71acddc",
  "job": "7cf15305-db0d-4f5e-9157-6e1bf055521d",
  "verification_ok": true,
  "verification_error": null
}
```

Se guarda en `storage/auth_responses/<state>.json` **antes** de intentar el callback. No se extrae ni devuelve identidad, DNI, nombre del titular o `issuer_dn`.

### Errores

| HTTP | Respuesta |
|---|---|
| 400 | `{"error":"JSON inválido"}` |
| 400 | `{"error":"state y signature requeridos"}` |
| 404 | `{"error":"state no encontrado"}` |
| 400 | `{"error":"nonce no generado"}` |
| 401 | `{"error":"Verificación fallida","detail":"Firma inválida","state":"<STATE>"}` |
| 401 | Igual, con `detail: "Certificado inválido"` si no puede extraer la pública. |
| 405 | `{"error":"Método no permitido"}` |
| 204 | OPTIONS, sin cuerpo. |

**Los errores retornan antes de guardar la respuesta y antes de enviar callback.** Si no había éxito previo, `check-verify` seguirá devolviendo `received: false`. Si había un éxito anterior para el mismo state, el error no borra ese archivo anterior.

## 9. Callback que debe recibir la web

### Quién lo envía y cuándo

Lo envía **`api/auth/submit.php`**, después de aceptar la firma y guardar el resultado, siempre que el job tenga un `callback_url` no vacío. No se envía directamente desde el navegador.

Petición real:

```http
POST <callback_url_del_job>
Content-Type: application/json
X-Job-Id: 7cf15305-db0d-4f5e-9157-6e1bf055521d
```

```json
{
  "success": true,
  "code": 200,
  "message": "Autenticación verificada",
  "job": "7cf15305-db0d-4f5e-9157-6e1bf055521d",
  "data": [
    {
      "state": "271f103f-7a57-48a2-bf0c-bc2ce71acddc",
      "verified_at": 1789597100,
      "algorithm": "SHA256withRSA"
    }
  ]
}
```

| Campo | Significado |
|---|---|
| `success` | Siempre `true` en los callbacks que genera este código. |
| `code` | Siempre `200` dentro del cuerpo; no es un código de error enviado por FirmEasy. |
| `message` | Texto fijo `Autenticación verificada`. |
| `job` / `X-Job-Id` | Identificador de la operación. |
| `data` | Array con un objeto de resultado. |
| `data[0].state` | Correlación con reto y firma. |
| `data[0].verified_at` | Timestamp Unix en segundos al crear el callback. |
| `data[0].algorithm` | Algoritmo de firma declarado en `submit`, por ejemplo `SHA256withRSA`. Se copia tal como llegó; el servidor no recalcula esta etiqueta. |

### Qué responde el receptor de ejemplo

`api/auth/callback.php` acepta POST, obtiene el job de `X-Job-Id` y, si no existe ese header, del cuerpo. Guarda:

```json
{
  "received_at": "<FECHA_ISO8601>",
  "job": "<JOB>",
  "payload": { "success": true, "code": 200 },
  "headers": { "X-Job-Id": "<JOB>" }
}
```

`payload` y `headers` contienen los datos completos recibidos; arriba están abreviados. Archivo de destino: `storage/auth_callbacks/<job>_<Y-m-d_H-i-s>.json`.

Devuelve HTTP 200:

```json
{ "received": true }
```

Responde 204 a OPTIONS y 405 sin cuerpo a otros métodos. No valida un esquema de callback ni comprueba una firma de autenticidad; el header solo transporta el identificador. Tampoco crea una sesión de usuario: únicamente registra la notificación.

### Si falla la entrega del callback

- Timeout de cURL: **5 segundos**.
- Un solo intento; no hay cola ni reintentos.
- Si hay error de transporte de cURL, escribe `Callback error for job ...` en el log.
- No comprueba el código HTTP del receptor ni interpreta su respuesta.
- Aunque el receptor responda 4xx/5xx o no sea alcanzable, `submit` conserva el resultado y devuelve éxito.
- El frontend puede recuperar el éxito mediante `check-verify` aunque no se haya registrado el callback.

### Si falla la autenticación

**No existe callback de error en esta implementación.** Firma inválida y certificado inválido producen el HTTP 401 de `submit`; cancelación o app no abierta no generan eventos en estos archivos.

Para que una nueva web reciba también fallos, se necesita una ampliación coordinada: persistir resultado fallido y definir/enviar un callback de error. Eso todavía no es parte del contrato actual, por lo que no debe documentarse un JSON inventado como si ya lo emitiera FirmEasy.

## 10. Consulta del resultado desde el frontend

### `GET /api/auth/check-verify.php?state=<STATE>`

Sin `state`, HTTP 400:

```json
{ "error": "state requerido" }
```

Si no hay archivo de respuesta, HTTP 200:

```json
{ "received": false }
```

Si existe, devuelve el JSON guardado por `submit`:

```json
{
  "received": true,
  "state": "<STATE>",
  "job": "<JOB>",
  "verification_ok": true,
  "verification_error": null
}
```

No comprueba si existe el job ni si expiró. Un `state` desconocido, una operación pendiente y una verificación fallida sin resultado previo pueden responder igual: `received: false`.

Para la nueva web, consultar el resultado cada **3 segundos** desde que se genera la operación y detener el seguimiento tras éxito o vencimiento. Comprobar el estado HTTP y manejar errores de red/JSON. Esta es la pauta de integración; la pantalla de referencia no implementa todo ese control.

## 11. Persistencia que debe reproducir el nuevo backend

Se puede usar una base de datos en lugar de archivos manteniendo las relaciones:

| Archivo actual | Contenido/relación |
|---|---|
| `storage/auth_keys.json` | Par Ed25519 (`secret_key`, `public_key`) y fecha de creación. |
| `storage/auth_jobs/<job>.json` | `job`, `jti`, `state`, `exp`, `purpose`, `display_name`, `accepted_issuers`, `callback_url`, `created_at`; luego `nonce` y `nonce_created_at`. |
| `storage/auth_responses/<state>.json` | Resultado aceptado por `submit`. |
| `storage/auth_callbacks/<job>_<fecha>.json` | Registro del callback recibido. |

La firma Ed25519 y el sobre cifrado no se guardan en el job actual. La búsqueda por `state` recorre los archivos de jobs; en una base de datos puede resolverse mediante índice.

Estos endpoints no implementan creación de sesión ni asociación de identidad a una cuenta. Si el objetivo final es iniciar sesión en la web empresarial, esa lógica se añade después de obtener una identidad verificada; el resultado actual no aporta datos de identidad del certificado.

## 12. Qué pasa en éxito y en cada fallo

| Caso | Comportamiento actual observable |
|---|---|
| Todo funciona | Generación → reto → submit 200 → resultado guardado → callback de éxito → polling muestra verificación. |
| Clave AES ausente o longitud incorrecta | El backend debe detener la construcción de la URI y reportar el error en su aplicación. |
| Job desconocido al pedir reto | Challenge devuelve 404. |
| Job vencido al pedir reto | Challenge devuelve 410. |
| Submit antes de pedir reto | Devuelve 400 `nonce no generado`. |
| Firma inválida con certificado utilizable, tras fallar CMS | Submit devuelve 401; no persiste fallo ni envía callback. |
| Certificado no interpretable, tras fallar CMS | Submit devuelve 401 `Certificado inválido`. |
| CMS falla y no hay certificado | Fallback actual acepta signature no vacía: no es verificación real. |
| Receptor del callback falla | Resultado sigue guardado; submit responde éxito; no reintenta. |
| Usuario cancela o no abre app | No se genera resultado ni callback en estos archivos. |
| Consulta de estado tras error sin éxito previo | Devuelve `received: false`; no distingue fallo de espera. |
| Reenvío de submit aceptado | Puede sobrescribir resultado y enviar otro callback; no hay consumo único. |

## 13. Comprobación al implementar en otro lenguaje

1. Configurar la clave AES entregada por separado y el par Ed25519 correcto.
2. Usar URLs accesibles por móvil y servidor; no copiar `localhost` al despliegue final.
3. Generar los claims, firmarlos y cifrar el sobre con los tamaños y formatos indicados.
4. Descifrar un enlace generado y comprobar que devuelve `{claims, sig}`; verificar la firma con la pública compartida.
5. Solicitar el reto dos veces y comprobar que se conserva el mismo nonce para el job.
6. Firmar los 32 bytes decodificados del nonce, obtener el P7S y enviarlo en base64 a `submit` junto con `state` y `algorithm: "SHA256withRSA"` si ese fue el algoritmo utilizado.
7. Comprobar respuesta de submit, contenido del callback y resultado de polling.
8. Probar P7S inválido, job desconocido, reto vencido y callback no alcanzable.

Esta guía se verificó contra el código fuente. No se generaron jobs ni se ejecutó una autenticación móvil durante la revisión. Solo se modificó este README.




