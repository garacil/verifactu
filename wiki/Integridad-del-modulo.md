# Integridad del módulo

El módulo ofrece dos formas de resumir en un hash el código instalado: `calculateVerifactuIntegrityChecksums()`, que recorre todos los ficheros PHP del módulo y es lo que publican `integrity.php` y la API REST, y `calcularHashModuloVerifactu()`, que concatena una lista fija de ficheros críticos y es lo que aparece en la declaración responsable. Esta página explica qué calcula cada una, qué muestra `integrity.php`, cuándo cambian los valores, cómo cotejar una instalación con una copia limpia de la misma versión y, sobre todo, qué **no** garantizan.

## Dos hashes distintos

| | `calculateVerifactuIntegrityChecksums($dir)` | `calcularHashModuloVerifactu($basePath)` |
|---|---|---|
| Fichero | `lib/functions/functions.configuration.php` | `conf/declaracion_responsable.conf.php` |
| Ámbito | Todos los ficheros con extensión `.php` bajo la carpeta del módulo, recursivamente (incluye `lib/newfenix/`, `vendor/`, `tests/`, `views/`, `admin/`…) | Los 13 ficheros de `integridad.ficheros_verificados` |
| Algoritmo | `md5_file()` de cada fichero, indexado por su ruta relativa; `ksort()`; `sha256(json_encode(array))` | Concatenación del contenido de los ficheros en el orden de la lista; `sha256` |
| Salida | 64 caracteres hexadecimales en minúsculas, o `false` si la carpeta no existe | 64 caracteres hexadecimales en minúsculas |
| Dónde se muestra | `integrity.php` y `GET …/verifactuapi/integrity` | Página de la declaración responsable y su exportación JSON |
| Sensible a | Cualquier byte de cualquier `.php`, y a la lista de ficheros (añadir o quitar un `.php` lo cambia) | Cualquier byte de los 13 ficheros; un fichero ausente queda fuera y se registra en `ficheros_no_encontrados` |
| No sensible a | Ficheros que no sean `.php` (idiomas, CSS, SQL, README, imágenes, esquemas) | Todo lo que no esté en la lista |

Los dos valores son independientes: no se derivan uno de otro y ninguno se guarda como referencia en el repositorio (`integridad.hash_modulo` está vacío en el fichero y se calcula en cada visita).

## `integrity.php`

Es un script en la raíz del módulo que devuelve un JSON con el hash global y algunos indicadores de estado. Se llama por su URL (`/custom/verifactu/integrity.php`, o `/verifactu/integrity.php` según cómo esté publicado el módulo) y **no exige sesión**: define `NOLOGIN`, `NOREQUIREUSER` y `NOREQUIREMENU` antes de cargar `main.inc.php`. Responde con `Content-Type: text/json` (`top_httphead('text/json')`).

Qué hace:

1. Carga `lib/verifactu.lib.php` y el descriptor `modVerifactu` (para leer `$verifactuModule->version`).
2. Calcula `calculateVerifactuIntegrityChecksums(__DIR__)`. Si devuelve `false`, responde HTTP 500 con `{"status":"error","message":"Could not calculate integrity hash","hash":null}`.
3. Consulta la base de datos de la entidad activa: si existe alguna factura con `verifactu_csv_factura` y `verifactu_estado` no vacíos (`invoices_sent`) y si existe alguna factura validada con `verifactu_error` no vacío (`invoices_with_errors`). Ambas consultas llevan `COUNT(*)` con `LIMIT 1`, así que devuelven el número total de filas que cumplen la condición.
4. Comprueba si están definidas `VERIFACTU_CERTIFICATE_PRIVATE_KEY`, `VERIFACTU_HOLDER_NIF` y `VERIFACTU_HOLDER_COMPANY_NAME`.
5. Responde HTTP 200 con:

```json
{
    "status": "success",
    "message": "Integrity hash calculated successfully",
    "hash": "74e8bf5d6304e9ac107bc98ce4f77d72bc0b8ff0a3faed9846c210cbc7a72890",
    "module": "verifactu",
    "version": "2.2.2",
    "timestamp": 1789842504,
    "domain": "dolibarr.ejemplo.es",
    "directory": "verifactu",
    "license": null,
    "has_private_key": false,
    "invoices_sent": 0,
    "invoices_with_errors": 0,
    "local_certificate": true,
    "configuration": {
        "has_nif": true,
        "has_company_name": true,
        "environment": "test"
    }
}
```

| Campo | Origen |
|---|---|
| `hash` | `calculateVerifactuIntegrityChecksums()` |
| `version` | `$this->version` del descriptor (`2.2.2`) |
| `timestamp` | `time()` en el momento de la llamada |
| `domain` | `getVerifactuDomain()`: `HTTP_HOST`, `SERVER_NAME` o `localhost` |
| `directory` | `basename(__DIR__)` |
| `license`, `local_certificate` | Campos heredados de una versión anterior que comprobaba una licencia: siempre `null` y `true` («License is no longer required») |
| `has_private_key` | `VERIFACTU_CERTIFICATE_PRIVATE_KEY` no vacía (la escribe `admin/managecertificates.php` al extraer la clave) |
| `invoices_sent`, `invoices_with_errors` | Consultas descritas arriba, acotadas a `f.entity = $conf->entity` |
| `configuration.has_nif`, `.has_company_name` | `VERIFACTU_HOLDER_NIF`, `VERIFACTU_HOLDER_COMPANY_NAME` |
| `configuration.environment` | `getEnvironment()` |

Cualquier excepción durante el proceso produce HTTP 500 con `{"status":"error","message":"Error en el cálculo de integridad: …","hash":null,"trace":"…"}`.

> **Importante:** como el script no requiere autenticación, cualquiera que alcance la URL obtiene el hash, la versión, el entorno configurado, si hay NIF y razón social configurados y si hay facturas enviadas o con errores. No expone datos de facturas ni del certificado. Si no quieres publicar esa información, restringe la URL en el servidor web.

La API REST expone el mismo hash con menos campos (`status`, `message`, `hash`, `module`, `version`, `timestamp`, `directory`, `domain`); ver [[API REST|API-REST]].

## `calculateVerifactuIntegrityChecksums()`

```php
function calculateVerifactuIntegrityChecksums($moduleDirectory)
{
    if (!is_dir($moduleDirectory)) {
        return false;
    }
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($moduleDirectory, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $relativePath = str_replace($moduleDirectory . '/', '', $file->getPathname());
            $files[$relativePath] = md5_file($file->getPathname());
        }
    }
    ksort($files);
    return hash('sha256', json_encode($files));
}
```

Las rutas se guardan relativas a la carpeta del módulo, así que el valor no depende de dónde esté instalado (`custom/verifactu` o cualquier otra ruta) ni del nombre de la carpeta, solo de su contenido `.php`. `ksort()` hace el resultado independiente del orden de lectura del sistema de ficheros.

## `calcularHashModuloVerifactu()` y `ficheros_verificados`

La lista de la sección `integridad` del fichero de declaración, en el orden en que se concatenan:

| # | Fichero | Por qué está en la lista |
|---|---|---|
| 1 | `core/modules/modVerifactu.class.php` | Descriptor: versión, constantes, permisos, campos adicionales |
| 2 | `class/actions_verifactu.class.php` | Hooks: validación, botones, QR en PDF y TPV |
| 3 | `class/verifactu.utils.php` | Reintento de envíos pendientes |
| 4 | `lib/verifactu.lib.php` | Cargador de la biblioteca de funciones |
| 5 | `lib/verifactu-types.array.php` | Tablas de códigos de la AEAT |
| 6 | `core/triggers/interface_999_modVerifactu_VerifactuTriggers.class.php` | Trigger principal (alta al validar) |
| 7 | `core/triggers/interface_900_modVerifactu_BillRestrictions.class.php` | Bloqueos sobre facturas remitidas |
| 8 | `lib/functions/functions.hash.php` | Cadena: bloqueo y registro anterior |
| 9 | `lib/functions/functions.submission.php` | Construcción y envío del registro |
| 10 | `lib/functions/functions.cancellation.php` | Anulaciones |
| 11 | `lib/functions/functions.configuration.php` | Entorno e identidad del sistema |
| 12 | `lib/functions/functions.response.php` | Aceptación de respuestas y guardado |
| 13 | `lib/functions/functions.certificates.php` | Certificados |

La función concatena el contenido de cada fichero existente, calcula SHA-256 y escribe en `$declaracionResponsable['integridad']` el hash, la fecha de cálculo y la lista de ficheros no encontrados; si esta última no está vacía, lo registra con `dol_syslog()` a nivel `LOG_WARNING` (cuando `dol_syslog()` existe, es decir, dentro de Dolibarr). El comentario del fichero fija la regla: «Debe estar siempre vacío: un fichero ausente queda fuera del hash y por tanto sin verificar».

Esta lista se corrigió en la 2.0.0 (issue #30, punto 2): referenciaba `interface_99_…` en vez de `interface_999_…` y un `class/verifactu.class.php` inexistente, de modo que el trigger principal quedaba fuera del cálculo sin que nadie lo notara. `tests/IssueFixesTest.php` comprueba ahora que todos los ficheros existen, que el trigger principal está incluido, que la función devuelve 64 caracteres y que `ficheros_no_encontrados` queda vacío.

Quedan **fuera** de esta lista, entre otros: toda la biblioteca `lib/newfenix/src/` (incluida `Invoice.php`, donde se calcula la huella), `functions.qr.php`, `functions.query.php`, `functions.utilities.php`, `functions.compatibility.php`, `functions.admin.php`, `class/api_verifactu.class.php`, `integrity.php`, el propio `conf/declaracion_responsable.conf.php`, las vistas, las páginas de administración, el modelo PDF y `vendor/`. El hash global de `integrity.php` sí los cubre (los `.php`).

## Cuándo cambia cada hash

| Suceso | Hash global (`integrity.php`, API) | Hash de la declaración |
|---|---|---|
| Actualizar el módulo a otra versión | Cambia | Cambia |
| Editar cualquiera de los 13 ficheros verificados | Cambia | Cambia |
| Editar otro `.php` (una vista, la biblioteca, un test, la API) | Cambia | No cambia |
| Añadir o borrar un `.php` en cualquier subcarpeta (incluido `vendor/` o `tests/`) | Cambia | No cambia, salvo que sea uno de los 13 (entonces pasa a `ficheros_no_encontrados`) |
| Cambiar idiomas, CSS, JS, SQL, README, imágenes, esquemas cacheados, certificados | No cambia | No cambia |
| Cambiar la configuración en Dolibarr o los datos de facturas | No cambia | No cambia |
| Cambiar solo los finales de línea o la codificación de un fichero | Cambia | Cambia si es uno de los 13 |

## Cómo verificar la integridad de una instalación

Ningún hash de referencia se publica en el repositorio ni en las versiones etiquetadas: el flujo `release.yml` comprueba versiones y pasa la suite de pruebas, pero no genera sumas de comprobación. Verificar una instalación consiste, por tanto, en comparar sus hashes con los de una copia limpia de **la misma versión**.

1. **Identifica la versión instalada.** En `core/modules/modVerifactu.class.php` (`$this->version`), en la página «Acerca de» del módulo, o en el campo `version` de `integrity.php` o de la API. Comprueba que coincide con `sistema.version` de la declaración responsable.
2. **Obtén una copia limpia** de esa misma versión desde las [versiones publicadas del repositorio](https://github.com/garacil/verifactu/releases) y descomprímela en una carpeta cualquiera de un ordenador con PHP.
3. **Calcula el hash de la declaración** en las dos carpetas. Como es la concatenación de los 13 ficheros, se reproduce sin PHP:

```bash
cd /ruta/al/modulo/verifactu
cat core/modules/modVerifactu.class.php \
    class/actions_verifactu.class.php \
    class/verifactu.utils.php \
    lib/verifactu.lib.php \
    lib/verifactu-types.array.php \
    core/triggers/interface_999_modVerifactu_VerifactuTriggers.class.php \
    core/triggers/interface_900_modVerifactu_BillRestrictions.class.php \
    lib/functions/functions.hash.php \
    lib/functions/functions.submission.php \
    lib/functions/functions.cancellation.php \
    lib/functions/functions.configuration.php \
    lib/functions/functions.response.php \
    lib/functions/functions.certificates.php | sha256sum
```

   El resultado debe coincidir con el «Hash del módulo» de la página de la declaración responsable de la instalación.

4. **Calcula el hash global** en las dos carpetas con el mismo algoritmo que `integrity.php`:

```bash
php -r '$d = "/ruta/al/modulo/verifactu"; $f = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($d, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::LEAVES_ONLY);
foreach ($it as $x) { if ($x->isFile() && $x->getExtension() === "php") { $f[str_replace($d . "/", "", $x->getPathname())] = md5_file($x->getPathname()); } }
ksort($f); echo hash("sha256", json_encode($f)), " (", count($f), " ficheros)", PHP_EOL;'
```

   y compáralo con el campo `hash` de `integrity.php` o de `GET /api/index.php/verifactuapi/integrity`. Si el número de ficheros difiere, hay `.php` de más o de menos en la instalación.

5. **Si no coinciden**, localiza la diferencia con `diff -r --brief` entre las dos carpetas (excluyendo las carpetas de datos que no forman parte del módulo). Ediciones locales legítimas, ficheros de copia de seguridad (`*.php.bak`, `*.orig.php`) o restos de una versión anterior son las causas habituales.

> **Nota:** el hash de la declaración que muestra la página se recalcula en cada visita, así que refleja siempre el estado actual del disco. Si haces la comparación desde la línea de órdenes, ejecútala con el mismo árbol de ficheros que sirve el servidor web.

## Relación con la declaración responsable

La sección `integridad` de la declaración (`algoritmo`, `hash_modulo`, `fecha_calculo`, `ficheros_verificados`, `ficheros_no_encontrados`) es la que se muestra en la página y en el JSON exportado. Su finalidad es dejar constancia de qué versión exacta de los ficheros críticos corresponde a la declaración suscrita: quien modifique uno de esos ficheros obtiene un hash distinto del que acompañaba a la declaración original y, según el propio fichero, debería actualizar la declaración (ver [[Declaración responsable|Declaracion-responsable]]).

## Qué no cubre

- **No es una firma.** Ninguno de los dos hashes está firmado ni se coteja con un valor externo; quien modifica un fichero puede recalcularlos. Sirven para comparar con una copia de confianza, no para demostrar por sí solos que el código no ha sido alterado.
- **No cubre ficheros no PHP** (hash global) ni **nada fuera de los 13 ficheros** (hash de la declaración), incluida la biblioteca `lib/newfenix/src/` que calcula la huella y habla con la AEAT.
- **No cubre datos ni configuración**: ni los registros de las facturas, ni las constantes `VERIFACTU_*`, ni los certificados, ni los esquemas cacheados, ni los campos adicionales.
- **No cubre el núcleo de Dolibarr** ni las extensiones de PHP de las que depende el módulo.
- **No comprueba la cadena de registros**: la integridad de las facturas remitidas se verifica con la huella encadenada y las consultas a la AEAT, no con estos hashes. Ver [[Encadenamiento y huella|Encadenamiento-y-huella]] y [[Consulta a la AEAT|Consulta-a-la-AEAT]].
- **`integrity.php` y la API no autentican**: el hash es información pública de la instalación.

## Véase también

- [[Declaración responsable|Declaracion-responsable]] — la sección `integridad` y el resto del fichero de configuración.
- [[API REST|API-REST]] — el endpoint `integrity` y su ruta real.
- [[Actualización|Actualizacion]] — comprobar la instalación después de actualizar.
- [[Publicación de versiones|Publicacion-de-versiones]] — qué comprueba el flujo de publicación.
- [[Suite de pruebas|Suite-de-pruebas]] — `tests/IssueFixesTest.php` y la lista de ficheros verificados.
- [[Registro y depuración|Registro-y-depuracion]] — el aviso de ficheros no encontrados en el log.
- [[Arquitectura del módulo|Arquitectura-del-modulo]] — qué hace cada uno de los ficheros verificados.
