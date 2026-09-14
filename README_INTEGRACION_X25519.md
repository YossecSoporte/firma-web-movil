# Integración `/integracion` — Cifrado X25519

Documento de planificación e integración para la página **`http://localhost:8081/integracion`**.
Aquí se describe qué vamos a implementar en FirmEasy Web y **qué debe hacer el otro programa (cliente)**
para generar su clave pública X25519 y entregárnosla.

---

## 1. Contexto actual

| Página | Ruta | Cifrado actual | Estado |
|---|---|---|---|
| `index.php` | `/` | AES-256-GCM simétrico con `ENCRYPTION_KEY` (env) | Se mantiene como está |
| `integracion.php` | `/integracion` | (pendiente) | Solo lista `doc_prueba1.pdf` … `doc_prueba10.pdf` |

Para **`/integracion`** se migrará la generación del deep link a **cifrado asimétrico X25519**
(ECDH + HKDF-SHA256 + AES-256-GCM), sin clave compartida secreta.

---

## 2. Modelo de roles (quién es quién)

- **FirmEasy Web (nosotros) = SERVER.**
  Tiene un par de claves X25519 estático y persistente en `storage/firmeasy_keys.json` (gitignored).
- **Otro programa (backend de la empresa / app móvil) = CLIENT.**
  Genera su propio par X25519 y **nos entrega solo su clave pública** (la privada nunca sale de su lado).

Ninguna clave secreta viaja entre ambos. Cada lado cifra/descifra con su privada + la pública del otro.

---

## 3. Qué vamos a implementar (plan en FirmEasy Web)

> **Estado: implementado.** A continuación el detalle de lo que ya está en marcha en esta rama.

1. **Nuevo endpoint generador X25519** — `api/generar-uri-x25519.php` (ya creado):
   - Acepta `kid` (identificador de la empresa/cliente) en el body.
   - Carga `storage/firmeasy_keys.json` (par del servidor).
   - Carga la pública del cliente desde `storage/keys/{kid}.json`.
   - Deriva el secreto compartido y cifra el payload (ver §4).
   - Conserva las features de `firma-integracion`: `document_code`, `callback`,
     `batch_error_handling`, `purpose`, `accepted_issuers`, firma múltiple (bloque).
2. **`integracion.php`** (ya actualizado): envía `kid` en el POST y construye el deep link con
   `buildDeepLink(data)` → `firmeasy://integration?data=<BLOB>&sid=<sid>`.
3. **Gestión de claves** (ya existe en esta rama y se reutiliza):
   - `POST /api/register-key.php` — registrar la pública del cliente.
   - `GET /api/list-keys.php` — listar clientes registrados.
   - `POST /api/delete-key.php` — revocar/eliminar una clave.
4. **Publicar nuestra clave pública** para que el cliente pueda descifrar:
   - Se entrega manualmente (valor en `storage/firmeasy_keys.json` → `public_key`) o
     vía un endpoint de solo lectura (pendiente, ej. `GET /api/firmeasy-pub.php`).
5. **`storage/firmeasy_keys.json`**: asegurar que exista (ver §7) y rotarlo solo de forma controlada
   (cambiar la pública implicaría que todos los clientes deben actualizarla).

---

## 4. Cadena criptográfica (detalle técnico)

### 4.1 Secreto compartido (ECDH X25519)

- **Servidor (nosotros) al cifrar:**
  `sodium_crypto_kx_server_session_keys($firmeasyKeypair, $empresaPublicKey)`
  → usa **`$kx[0]`** (`rx`).
- **Cliente al descifrar:**
  `sodium_crypto_kx_client_session_keys($clienteKeypair, $firmeasyPublicKey)`
  → usa **`$kx[1]`** (`tx`).

Ambos valores (`rx` del servidor y `tx` del cliente) son **el mismo secreto de 32 bytes**.
La app/backend NO debe hacer X25519/ECDH plano: debe usar `crypto_kx` (compatible con NaCl `crypto_box`).

### 4.2 Derivación de la clave AES (HKDF-SHA256)

```
aesKey = HKDF-SHA256(sharedSecret, salt="firmeasy-deeplink-v1", info="aes-encryption-key", length=32)
```

### 4.3 Payload plano (lo que se cifra)

```json
{
  "url": "http://localhost:8081/api/job/<job-uuid>",
  "exp": 1786140125
}
```

El `token` (sid) **NO va dentro del blob**.

### 4.4 Blob cifrado

```
blob = base64url( IV(12 bytes) || AES-256-GCM ciphertext || TAG(16 bytes) )
```

### 4.5 Deep link final

```
firmeasy://integration?data=<blob_base64url>&sid=<token_en_claro>
```

- `job` y `exp` viajan **cifrados** dentro del blob.
- `sid` va en claro (solo identificación/traceabilidad; es revocable).
- El cliente DEBE validar `exp` (`exp < now()` → rechazar), igual que hoy con AES.

### 4.6 Constantes

| Constante | Valor |
|---|---|
| `EXPIRACION_SEGUNDOS` | 600 (10 min) |
| `kid` por defecto | `default` |
| Encoding claves | base64 estándar (`SODIUM_BASE64_VARIANT_ORIGINAL`, con padding `=` si aplica) |
| Encoding blob | base64url (sin padding) |
| Longitud claves | 32 bytes (X25519) |
| Algoritmos | X25519 (`crypto_kx`) + HKDF-SHA256 + AES-256-GCM |

---

## 5. Qué necesita hacer el otro programa (CLIENT)

Estos son los pasos que debe realizar el programa externo (backend de la empresa o app)
para integrarse al cifrado X25519 de `/integracion`:

### Paso 1 — Generar un par de claves X25519

Debe generar un keypair de **Curve25519** (X25519). Cualquier librería compatible con
libsodium/NaCl sirve (las claves son intercambiables; todas son de 32 bytes).

**PHP (libsodium, lo que usa el servidor):**
```php
<?php
$keypair = sodium_crypto_kx_keypair();          // 64 bytes: secret(32) + public(32)
$secret  = sodium_crypto_kx_secretkey($keypair); // 32 bytes
$public  = sodium_crypto_kx_publickey($keypair); // 32 bytes
echo 'SECRET (guardar, NO compartir): '
   . sodium_bin2base64($secret, SODIUM_BASE64_VARIANT_ORIGINAL) . "\n";
echo 'PUBLIC (enviar a FirmEasy Web): '
   . sodium_bin2base64($public, SODIUM_BASE64_VARIANT_ORIGINAL) . "\n";
?>
```

**Node.js (tweetnacl):**
```js
const nacl = require('tweetnacl');         // o @noble/curves X25519
const kp   = nacl.box.keyPair();           // X25519 keypair (Curve25519)
console.log('SECRET:', Buffer.from(kp.secretKey).toString('base64'));
console.log('PUBLIC:', Buffer.from(kp.publicKey).toString('base64'));
```

**Python (PyNaCl):**
```python
from nacl.public import PrivateKey
kp = PrivateKey.generate()
print('SECRET:', __import__('base64').b64encode(kp.encode()).decode())
print('PUBLIC:', __import__('base64').b64encode(kp.public_key.encode()).decode())
```

### Paso 2 — Conservar el SECRET

El secreto queda **únicamente** en el lado del cliente. Si se pierde, se revoca la pública
registrada y se genera un par nuevo.

### Paso 3 — Definir un `kid`

`kid` es el identificador con el que registramos su clave (empresa, app, integrante…).
Solo caracteres `[a-zA-Z0-9_-]`. Ej: `empresa-2026-09`, `cliente-a`.

### Paso 4 — Entregarnos la clave pública

Opciones:
- **Manual**: envíanos el valor de `PUBLIC` (base64 estándar) + el `kid` elegido por el canal pactado.
- **API** (si tienes acceso): hacer `POST /api/register-key.php` con:

```json
{
  "kid": "empresa-2026-09",
  "public_key": "<PUBLIC base64 generado>"
}
```

El servidor la guarda en `storage/keys/{kid}.json`. Si necesitas un `kid` concreto para
pruebas locales, usa `default` (ya pre-cargado).

### Paso 5 — Obtener NUESTRA clave pública

Para descifrar los blobs que generemos, el cliente necesita la pública de FirmEasy:
te la entregamos (valor de `storage/firmeasy_keys.json` → `public_key`). En el entorno local actual:

```
uc39D7873TaG9nps15ehTOHE+i7hhDt40zoV7Ov3k34=
```

> Nota: esta clave pública es fija mientras no rote el par del servidor. En el plan está
> publicarla vía un endpoint de solo lectura (pendiente).

### Paso 6 — Descifrar el blob (lado cliente) **y** validar `exp`

```php
<?php
// Datos recibidos en el deep link
$data = 'firmeasy://integration?data=<BLOB>&sid=<TOKEN>';
parse_str(parse_url($data, PHP_URL_QUERY), $q);

// Nuestras claves (cliente)
$clientSecret = sodium_base642bin('<SECRET b64>', SODIUM_BASE64_VARIANT_ORIGINAL);
$clientPublic = sodium_base642bin('<PUBLIC b64>', SODIUM_BASE64_VARIANT_ORIGINAL);
$clientKeypair = $clientSecret . $clientPublic;

// Pública de FirmEasy Web
$serverPublic = sodium_base642bin('uc39D7873TaG9nps15ehTOHE+i7hhDt40zoV7Ov3k34=', SODIUM_BASE64_VARIANT_ORIGINAL);

// 1) Secreto compartido (tx == rx del servidor)
$kx = sodium_crypto_kx_client_session_keys($clientKeypair, $serverPublic);
$shared = $kx[1];

// 2) Derivar clave AES
$aesKey = hkdf_sha256($shared, 'firmeasy-deeplink-v1', 'aes-encryption-key', 32);
sodium_memzero($shared);

// 3) Decodificar blob: base64url -> IV(12)||CT||TAG(16)
$raw = base64_decode(strtr($q['data'], '-_', '+/'));   // OJO: restaurar padding si falta
$iv  = substr($raw, 0, 12);
$tag = substr($raw, -16);
$ct  = substr($raw, 12, -16);

// 4) Descifrar y validar exp
$plain = openssl_decrypt($ct, 'aes-256-gcm', $aesKey, OPENSSL_RAW_DATA, $iv, $tag);
$payload = json_decode($plain, true);
if (time() > $payload['exp']) {
    die('Enlace expirado');
}
// $payload['url'] -> GET /api/job/<job> -> descargar PDF (from) y subir firmado (to)

function hkdf_sha256(string $ikm, string $salt, string $info, int $length = 32): string
{
    $prk = hash_hmac('sha256', $ikm, $salt, true);
    $okm = ''; $t = '';
    for ($i = 1; strlen($okm) < $length; $i++) {
        $t = hash_hmac('sha256', $t . $info . chr($i), $prk, true);
        $okm .= $t;
    }
    return substr($okm, 0, $length);
}
```

**Importante en otros lenguajes:**
- La pública/servidor se codifica en base64 estándar (con padding); el blob en base64url (sin padding).
- HKDF: `hash_hmac('sha256',...,true)` (salida binaria), idéntico a RFC 5869.
- AES-256-GCM: IV de 12 bytes, tag de 16 bytes.

---

## 6. Endpoints involucrados

| Método | Ruta | Descripción |
|---|---|---|
| POST | `/api/register-key.php` | Registra la clave pública de un cliente (`{kid, public_key}`) |
| GET | `/api/list-keys.php` | Lista de claves registradas |
| POST | `/api/delete-key.php` | Elimina una clave registrada |
| GET | `/api/job/{job}` | Configuración del job (`from`/`to`) creado al generar la URI |
| POST | `/api/generar-uri-x25519.php` | **Implementado** — genera el blob cifrado con X25519 |
| GET | `/api/session.php?sid=...` | Devuelve `kid` y `public_key` de la sesión (soporte) |

---

## 7. Notas internas (FirmEasy Web)

- `storage/firmeasy_keys.json` debe existir con el par del servidor. Si faltara, generarlo una vez:
  ```bash
  docker exec firmeasy-web php -r '
    $kp = sodium_crypto_kx_keypair();
    $out = [
      "secret_key" => sodium_bin2base64(sodium_crypto_kx_secretkey($kp), SODIUM_BASE64_VARIANT_ORIGINAL),
      "public_key" => sodium_bin2base64(sodium_crypto_kx_publickey($kp), SODIUM_BASE64_VARIANT_ORIGINAL),
      "created_at" => date("c"),
    ];
    file_put_contents("/var/www/html/storage/firmeasy_keys.json", json_encode($out, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
    echo "firmeasy_keys.json generado\n";
  '
  ```
  > `storage/` está en `.gitignore` — los pares de claves nunca van al repositorio.
- El cliente puede pre-probar el flujo generando un keypair temporal y registrándolo con `kid` de prueba.
- **Pendiente de revisar**: el payload actual del job guarda `token` y `kid`; mantener compatibilidad
  con los jobs ya creados (10 min de vida) durante la transición.