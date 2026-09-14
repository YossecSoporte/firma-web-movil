# Encriptación del deep link `firmeasy://sign`

## 1. Qué algoritmos usamos y para qué sirve cada uno

| Algoritmo | Rol | Qué hace |
|---|---|---|
| **X25519** | Intercambio de claves (ECDH) | Genera pares de claves y calcula un secreto compartido entre dos partes. **No cifra nada.** |
| **HKDF-SHA256** | Derivación de clave | Convierte el secreto compartido (crudo) en una clave AES-256 lista para usar. |
| **AES-256-GCM** | Cifrado simétrico | Cifra y descifra el payload real (`data=`). Es quien hace el trabajo de "encriptar" propiamente dicho. |

Ninguno de los tres reemplaza a otro — funcionan en cadena:

```
X25519 (par de claves + ECDH) → HKDF-SHA256 (deriva clave) → AES-256-GCM (cifra/descifra el payload)
```

## 2. Quién posee qué

Cada lado genera **su propio** par completo (privada + pública). Nadie usa la privada de otro, ni falta que la comparta.

- **Firmador (nosotros)**: generamos nuestro par **una sola vez**. La privada se queda local en el programa para siempre y nunca sale de ahí. La pública se la compartimos a cada empresa una vez, en el onboarding.
- **Empresa (cada cliente que integra)**: genera su propio par con su propia librería, en su backend. Su privada se queda local en su servidor para siempre. Su pública nos la comparte a nosotros.

| | Privada | Pública |
|---|---|---|
| **Firmador** | Solo nosotros la tenemos, nunca sale del programa | La tiene la empresa (se la dimos nosotros) |
| **Empresa** | Solo ellos la tienen, nunca sale de su servidor | La tenemos nosotros (nos la dieron ellos) |

**Regla sin excepciones: ninguna clave privada viaja jamás por la red, por email, ni se guarda en el backend de la otra parte.** Lo único que viaja entre las partes, en cualquier dirección, son las públicas — eso es seguro porque las públicas no son secretas por diseño.

Cuando la empresa cifra, combina **su privada + nuestra pública**. Cuando nosotros descifra­mos, combinamos **nuestra privada + su pública**. Por las matemáticas de Diffie-Hellman, ambas combinaciones dan el mismo secreto compartido, sin que ninguna privada se haya movido de su sitio.

## 3. Cómo viaja cada pieza

| Pieza | ¿Viaja en claro o cifrada? | ¿Cuándo? | ¿Por dónde? |
|---|---|---|---|
| Pública del firmador | En claro | Una vez, en el onboarding de cada empresa | Documentación de integración / canal que se acuerde (no requiere ser secreto) |
| Pública de la empresa | En claro | Una vez en el onboarding, y de nuevo si rotan su clave | La empresa nos la entrega; queda guardada en nuestro backend |
| `kid` + pública de la empresa (para uso del firmador) | En claro | En cada sesión | Dentro de la respuesta del session endpoint (ver sección 5) |
| Payload real (URL del job) | **Cifrado** (AES-256-GCM) | En cada firma | Dentro del parámetro `data=` del deep link |
| Token bearer (`sid`) | En claro | En cada firma | Como parámetro aparte del deep link, **fuera** del blob cifrado |

## 4. Formato del deep link

```
firmeasy://sign?data=<blob cifrado>&sid=<token bearer>
```

Ejemplo:

```
firmeasy://sign?data=-28pIVuuI-UEPLes5vxj3vctVo_HWPhX1mTJuAWk2pmcN4olNgUc7PM2XppQWMiPZ7DMLJFEyRBlts9GPR__OMorznW-obFqZhUe_qMCzhz_NUGMzezzuSNTQaOaMaFg8EpTOTtqLyZr7Tz4VU6GGZL_T-O24SyYGlcZXfAwdY5cxjT9L5VGV9BokFVACCH1pcX72zRRvzDzT1JkJizimJDW4wxI3zfx5e4kCrzWlApoC0cJEICqWSmBfn_zZHY&sid=tkn_bat_GmszTddCPMP8KUtIVyGgjg1wbkIgYsnF
```

**Punto clave de este diseño**: el `token` (`sid`) va **fuera** del blob cifrado, no dentro. Esto es intencional y corrige un problema de diseño anterior: si el token estuviera *dentro* del blob cifrado, se generaría una dependencia circular — necesitaríamos el token para pedirle al backend la pública de la empresa con la que descifrar, pero necesitaríamos descifrar primero para obtener el token. Al sacar el token del blob, el firmador puede leerlo directamente de la URL, sin descifrar nada todavía, y usarlo para pedir al backend lo que necesita para descifrar.

### Formato del blob (`data=`)

```
base64url( IV (12 bytes) || ciphertext || auth_tag (16 bytes) )
```

Al descifrar, el contenido plano es la URL del job (y opcionalmente un `exp` si se decide mantenerlo dentro del blob en vez de como parámetro aparte — pendiente de decidir, ver sección 6).

## 5. Flujo completo, paso a paso

1. La empresa arma el payload (URL del job).
2. La empresa calcula el secreto compartido: `ECDH(privada_empresa, pública_firmador)`.
3. Deriva la clave AES con HKDF-SHA256 sobre ese secreto.
4. Cifra el payload con AES-256-GCM → obtiene el blob.
5. Arma la URL: `firmeasy://sign?data=<blob>&sid=<token>` y la envía al firmador (vía deep link).
6. El firmador recibe la URL y separa `data` y `sid` **sin descifrar nada todavía**.
7. El firmador consulta el session endpoint usando `sid` como token bearer.
8. El backend responde con `session_id`, `expires_in`, `allowed_domains`, `tenant`, `quota`, y el bloque `encryption` con el `kid` y la `public_key` de la empresa dueña de ese token:

   ```json
   {
       "session_id": "3f221a6c-3032-4243-b1aa-9f7bed7a4c14",
       "expires_in": 86400,
       "security": {
           "allowed_domains": ["localhost", "notaries.whatsign.local", "staging.firmeasy.legal"]
       },
       "tenant": { "name": "Yoseec Test" },
       "quota": { "is_unlimited": true, "limit": null, "used": 698, "remaining": null },
       "encryption": {
           "kid": "yoseec-2026-09",
           "public_key": "base64url o hex de la pública X25519 de la empresa"
       }
   }
   ```

9. El firmador calcula el secreto compartido: `ECDH(privada_firmador, public_key recibida)`.
10. Deriva la misma clave AES con HKDF-SHA256.
11. Descifra `data` con AES-256-GCM usando esa clave → obtiene la URL del job.
12. Continúa el flujo normal (descarga del PDF, validaciones, firma, subida).

Con esto, tu ejemplo **está bien planteado** — la corrección de sacar el token fuera del blob es justo lo que resuelve el problema de dependencia circular que tenía la versión anterior.

## 6. Pendiente de decidir

- **`exp` (expiración del deep link)**: hoy vivía dentro del blob cifrado junto con `data` y `token`. Si el token ya sale del blob, conviene evaluar si `exp` también debería ir como parámetro en claro (permite rechazar links vencidos sin tener que hacer la llamada de red al session endpoint ni el cálculo criptográfico), o si se mantiene dentro del blob por preferir no exponer esa metadata. No afecta la seguridad de forma crítica en ningún caso — es una decisión de eficiencia/diseño, no de seguridad.
- **Revocación**: cuando una empresa se da de baja, el backend debe dejar de devolver su `encryption.public_key` (o rechazar la sesión completa) en el session endpoint.
- **Caché de la pública de la empresa en el firmador**: no debe guardarse indefinidamente — debe pedirse fresca en cada sesión, para que la revocación surta efecto de inmediato.
