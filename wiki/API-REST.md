# API REST

El módulo añade tres endpoints a la API REST de Dolibarr a través de la clase `VerifactuApi` (`class/api_verifactu.class.php`): uno para obtener el hash de integridad y dos para forzar el entorno de pruebas o de producción. Es una API deliberadamente limitada: no permite enviar, consultar ni anular facturas. Esta página explica cómo se activa, con qué ruta se accede realmente, cómo se autentica (el hash es público; el cambio de entorno exige la clave de API de un administrador desde la 2.2.2), qué devuelve cada endpoint, ejemplos con `curl`, los códigos de error y qué alternativas existen para lo que la API no cubre.

## Activación

- El descriptor del módulo declara `modApi` como dependencia (`$this->depends = array('1' => 'modFacture', '2' => 'modBlockedLog', '3' => 'modApi')`), de modo que Dolibarr activa el módulo **API/Servicios web (servidor REST)** al activar VeriFactu. Nada más del módulo depende de la API: el envío a la AEAT no la usa.
- `api/index.php` del núcleo rechaza cualquier llamada si `MAIN_MODULE_API` está vacío (responde con el texto «WarningModuleNotActive»).
- Si la instalación define `API_ENDPOINT_RULES`, solo se sirven los endpoints listados con `:1`; para este módulo la regla se casa con `verifactu` o `verifactuapi`. Un endpoint no permitido devuelve HTTP 501.
- El explorador de la API (`/api/index.php/explorer/`) lista la clase como `verifactuapi` cuando el módulo está activo; el explorador puede estar desactivado con `API_EXPLORER_DISABLED`.

## Ruta base: `verifactuapi`

Hasta la 2.2.1 el README indicaba `GET /api/index.php/verifactu/integrity`. Con el mecanismo de resolución de `api/index.php` del núcleo (comprobado sobre Dolibarr 19.0.2), la ruta que funciona es la que documenta el README desde la 2.2.2:

```text
/api/index.php/verifactuapi/<endpoint>
```

La razón está en cómo el núcleo localiza la clase a partir del primer segmento de la URL: `getModuleDirForApiClass()` y `api/index.php` quitan el sufijo `api` para encontrar la carpeta del módulo (`verifactu`) y el fichero `class/api_verifactu.class.php`, pero el nombre de clase que buscan es `ucwords()` del segmento completo. Con `verifactuapi` obtienen `Verifactuapi`, que PHP resuelve (los nombres de clase no distinguen mayúsculas) a la clase real `VerifactuApi`; con `verifactu` buscarían una clase `Verifactu`, que no existe, y Restler responde 404 «Not Found» en la fase de enrutado. Restler deriva además la ruta base del nombre corto de la clase en minúsculas, `verifactuapi`, que es también como aparece en el explorador.

En una instalación con Dolibarr 19.0.2 se comprobó que `GET /api/index.php/verifactuapi/integrity` devuelve HTTP 200 y `GET /api/index.php/verifactu/integrity` devuelve HTTP 404.

## Autenticación

En el framework Restler que usa Dolibarr, un método anotado con `@access public` tiene nivel de acceso 0 y, como `Defaults::$apiAccessLevel` también es 0, `Restler::authenticate()` no invoca a la clase de autenticación `DolibarrApiAccess`. Un método `@access protected` sí pasa por ella, que es como se protegen los métodos de la API del núcleo de Dolibarr.

| Método | Anotación | Clave de API | Permiso |
|---|---|---|---|
| `getIntegrity()` | `@access public` | No se exige; si se envía, no se evalúa | Ninguno: el hash es público, igual que `integrity.php` |
| `toProduction()` | `@access protected` (desde la 2.2.2) | Obligatoria | Administrador de Dolibarr |
| `toTest()` | `@access protected` (desde la 2.2.2) | Obligatoria | Administrador de Dolibarr |

Para los dos métodos protegidos:

- La clave se envía en la cabecera `DOLAPIKEY` (recomendado) o en el parámetro `DOLAPIKEY`/`api_key` de la URL. Es la clave de API del usuario de Dolibarr, que se genera en su ficha.
- `DolibarrApiAccess::__isAllowed()` rechaza con **401** una petición sin clave («Failed to login to API. No parameter 'HTTP_DOLAPIKEY' on HTTP header…») y también una clave que no corresponde a ningún usuario.
- Con una clave válida, el método comprueba `DolibarrApiAccess::$user->admin` **antes** de tocar la configuración y responde **403** «Only an administrator can change the VeriFactu environment» a cualquier otro usuario. La comprobación está fuera del `try` para que el rechazo llegue como 403 y no como 500.
- La entidad sobre la que actúa la petición se toma de la cabecera HTTP `DOLAPIENTITY` (que define `DOLENTITY` en `api/index.php`); sin ella, la entidad por defecto de la instalación.

La anotación `@class DolibarrApiAccess {@requires none}` que acompaña a `getIntegrity()` no cambia su carácter público: `DolibarrApiAccess::verifyAccess()` solo se ejecuta después de una autenticación, que en un método público no ocurre.

> **Importante:** hasta la 2.2.1 la clase y los tres métodos estaban anotados con `@access public`, de modo que `toProduction` y `toTest` podían invocarse **sin credenciales** y cambiar a qué entorno de la AEAT se remitían los registros de una entidad. Si una instalación sigue en una versión anterior y su API es accesible desde fuera de la red de confianza, actualiza a la 2.2.2 o, mientras tanto, restringe la ruta `/api/index.php/verifactuapi/` en el servidor web o usa `API_ENDPOINT_RULES` para no servirla. Los cambios de entorno también se pueden hacer desde la pantalla de configuración con una sesión de administrador; ver [[Conexión SOAP y entornos|Conexion-SOAP-y-entornos]].

`tests/ApiAccessTest.php` comprueba la anotación de cada método, que el rechazo 403 precede a cualquier cambio de configuración y que ningún método público escribe configuración ni datos (ver [[Suite de pruebas|Suite-de-pruebas]]).

### Dolibarr 17: la API del módulo no llega a ejecutarse

En Dolibarr 17 cualquier llamada a `/api/index.php/verifactuapi/…` termina con HTTP 500, y el registro del servidor web muestra:

```text
PHP Fatal error:  Uncaught Error: Access to undeclared static property Composer\Autoload\ClassLoader::$loader
in …/includes/restler/framework/Luracast/Restler/AutoLoader.php:293
```

Es un fallo del **núcleo de Dolibarr**, no del módulo: el Restler que incluye Dolibarr 17 contiene en `AutoLoader::loadThisLoader()` la expresión `$b::$loader[1]($className)`, escrita para PHP 5, que PHP 7 y posteriores interpretan como acceso a una propiedad estática. Falla en cuanto hay registrado un autoloader de Composer de tipo `[objeto, método]`, como el de `lib/newfenix/vendor/`, que el módulo carga desde su primera versión. Dolibarr 19 ya incluye la corrección (con un comentario que cita literalmente este error). En esas instalaciones el hash de integridad puede consultarse con `integrity.php`, y el entorno se cambia desde la pantalla de configuración.

## Endpoints

### `GET /api/index.php/verifactuapi/integrity`

Método `getIntegrity()`. Calcula el hash global del módulo con `calculateVerifactuIntegrityChecksums(dol_buildpath('verifactu'))`, es decir, el mismo valor que `integrity.php`, y devuelve una versión reducida de su respuesta.

- **Parámetros:** ninguno.
- **Permisos:** ninguno (ver arriba).
- **Respuesta 200:**

```json
{
    "status": "success",
    "message": "Integrity hash calculated successfully",
    "hash": "74e8bf5d6304e9ac107bc98ce4f77d72bc0b8ff0a3faed9846c210cbc7a72890",
    "module": "verifactu",
    "version": "2.2.4",
    "timestamp": 1789842504,
    "directory": "verifactu",
    "domain": "dolibarr.ejemplo.es"
}
```

| Campo | Origen |
|---|---|
| `hash` | `calculateVerifactuIntegrityChecksums()`: SHA-256 del mapa `ruta relativa → md5` de todos los `.php` del módulo |
| `version` | `$verifactuModule->version` del descriptor |
| `timestamp` | `time()` |
| `directory` | La cadena fija `verifactu` |
| `domain` | `getVerifactuDomain()`: `HTTP_HOST`, `SERVER_NAME` o `localhost` |

- **Errores:** si el cálculo devuelve `false` (carpeta inexistente) o se produce cualquier excepción, HTTP 500 con el mensaje `Error en el cálculo de integridad: <detalle>` (por ejemplo `Could not calculate the integrity hash for VERIFACTU module`).

No incluye los campos `has_private_key`, `invoices_sent`, `invoices_with_errors` ni `configuration` que sí devuelve `integrity.php`; ver [[Integridad del módulo|Integridad-del-modulo]].

### `POST /api/index.php/verifactuapi/toProduction`

Método `toProduction()`. Ejecuta `dolibarr_set_const($db, 'VERIFACTU_FORCE_PRODUCTION_ENVIRONMENT', 1, 'chaine', 0, '', $conf->entity)`: a partir de ese momento `getEnvironment()` devuelve `production` para la entidad, con independencia de la fecha y del tipo de empresa.

- **Parámetros:** ninguno (no admite cuerpo).
- **Autenticación:** clave de API (`DOLAPIKEY`) de un usuario administrador (desde la 2.2.2).
- **Respuesta 200:**

```json
{
    "status": "success",
    "message": "Entorno de verifactu actualizado a producción correctamente"
}
```

- **Errores:** HTTP 401 sin clave o con una clave que no corresponde a ningún usuario; HTTP 403 `Only an administrator can change the VeriFactu environment` si el usuario no es administrador (la constante no se toca); HTTP 500 `Error en la actualización del entorno: <detalle>` si `dolibarr_set_const()` devuelve un valor negativo o salta una excepción.
- **Efectos:** los mismos que la acción `setproduction` de `admin/setup.php`. Los envíos siguientes irán a `www1`/`www10.agenciatributaria.gob.es`, la cadena de producción empezará con un `PrimerRegistro` si aún no tiene registros y los QR nuevos apuntarán a `www2.agenciatributaria.gob.es`.

### `POST /api/index.php/verifactuapi/toTest`

Método `toTest()`. Ejecuta `dolibarr_del_const($db, 'VERIFACTU_FORCE_PRODUCTION_ENVIRONMENT', $conf->entity)`: elimina el forzado, y `getEnvironment()` vuelve a decidir por fecha y tipo de empresa (antes de las fechas de transición de 2027 eso significa `test`).

- **Parámetros:** ninguno.
- **Autenticación:** clave de API (`DOLAPIKEY`) de un usuario administrador (desde la 2.2.2).
- **Respuesta 200:**

```json
{
    "status": "success",
    "message": "Entorno de verifactu actualizado a test correctamente"
}
```

- **Errores:** HTTP 401 sin clave válida; HTTP 403 si el usuario no es administrador; HTTP 500 `Error en la actualización del entorno: <detalle>`.

> **Aviso:** ni `toProduction` ni `toTest` comprueban si la entidad ya tiene registros encadenados. Alternar entornos con facturas emitidas produce dos cadenas distintas para la misma serie; ver [[Encadenamiento y huella|Encadenamiento-y-huella]].

## Ejemplos con `curl`

Hash de integridad (no hace falta clave; la cabecera `DOLAPIKEY`, si se añade, no se evalúa):

```bash
curl -s "https://dolibarr.ejemplo.es/api/index.php/verifactuapi/integrity"
```

Forzar producción y volver a pruebas, con la clave de API de un administrador:

```bash
curl -s -X POST -H "DOLAPIKEY: <clave-de-un-administrador>" "https://dolibarr.ejemplo.es/api/index.php/verifactuapi/toProduction"
curl -s -X POST -H "DOLAPIKEY: <clave-de-un-administrador>" "https://dolibarr.ejemplo.es/api/index.php/verifactuapi/toTest"
```

Actuar sobre otra entidad de Multiempresa:

```bash
curl -s -X POST -H "DOLAPIKEY: <clave-de-un-administrador>" -H "DOLAPIENTITY: 2" "https://dolibarr.ejemplo.es/api/index.php/verifactuapi/toTest"
```

Sin la cabecera `DOLAPIKEY` las dos llamadas anteriores responden 401 y no cambian nada.

Comprobar el hash de la instalación frente al calculado localmente:

```bash
curl -s "https://dolibarr.ejemplo.es/api/index.php/verifactuapi/integrity" | php -r 'echo json_decode(stream_get_contents(STDIN))->hash, PHP_EOL;'
```

## Códigos de respuesta

| Código | Situación |
|---|---|
| 200 | Operación realizada; cuerpo JSON con `status: success` |
| 401 | `toProduction` o `toTest` sin cabecera `DOLAPIKEY` o con una clave que no corresponde a ningún usuario (desde la 2.2.2) |
| 403 | `toProduction` o `toTest` con la clave de un usuario que no es administrador; la configuración no se modifica (desde la 2.2.2) |
| 404 | Ruta no encontrada: segmento `verifactu` en lugar de `verifactuapi`, endpoint mal escrito o método HTTP incorrecto (por ejemplo `GET` sobre `toProduction`) |
| 500 | Error interno: no se pudo calcular el hash, error de base de datos al escribir la constante, u otra excepción; el mensaje empieza por `Error en el cálculo de integridad:` o `Error en la actualización del entorno:`. En Dolibarr 17, todas las llamadas por el fallo de Restler descrito arriba |
| 501 | El endpoint está bloqueado por `API_ENDPOINT_RULES`, o el núcleo no pudo incluir el fichero de la API |
| 503 | La clave pertenece a un usuario bloqueado o desactivado, o hay un error al leerla de la base de datos |
| Página HTML con «WarningModuleNotActive» | El módulo API de Dolibarr está desactivado |

`getIntegrity()` nunca responde 401 ni 403: es público.

## Qué no ofrece la API y alternativas

- **No hay endpoints para enviar, subsanar, anular ni consultar facturas** en la AEAT, ni para leer el estado VeriFactu de una factura. Los campos `verifactu_*` son campos adicionales de la factura, así que la API estándar de facturas de Dolibarr los devuelve dentro de `array_options` al consultar una factura (`GET /api/index.php/invoices/{id}`); ver [[Campos adicionales|Campos-adicionales]].
- **El envío se dispara desde el trigger `BILL_VALIDATE`**, no desde la interfaz: una validación realizada a través de la API estándar de facturas (`POST /api/index.php/invoices/{id}/validate`) recorre el mismo trigger que la validación desde la ficha. Esta wiki no documenta ese uso; si lo necesitas, pruébalo primero contra el entorno de pruebas y revisa el registro. Ver [[Flujo de validación y envío|Flujo-de-validacion-y-envio]].
- **La declaración responsable** se exporta en JSON desde `views/declaration_json.php`, que sí exige sesión y permiso `verifactu->manage`; no forma parte de la API REST. Ver [[Declaración responsable|Declaracion-responsable]].
- **`integrity.php`** ofrece más indicadores que el endpoint `integrity`, también sin autenticación.

## Referencia del fichero

`class/api_verifactu.class.php` incluye `lib/verifactu.lib.php`, declara `class VerifactuApi extends DolibarrApi` y define:

| Método | Anotaciones | Ruta resultante |
|---|---|---|
| `__construct()` | `@url GET /` | Guarda `$db`; no define un endpoint utilizable |
| `getIntegrity()` | `@url GET integrity`, `@access public`, `@class DolibarrApiAccess {@requires none}` | `GET /verifactuapi/integrity` |
| `toProduction()` | `@url POST toProduction`, `@access protected`; comprueba `DolibarrApiAccess::$user->admin` | `POST /verifactuapi/toProduction` |
| `toTest()` | `@url POST toTest`, `@access protected`; comprueba `DolibarrApiAccess::$user->admin` | `POST /verifactuapi/toTest` |

La clase declara `@access protected` como valor por defecto: cualquier método nuevo exige la clave salvo que se declare público expresamente.

## Véase también

- [[Integridad del módulo|Integridad-del-modulo]] — qué mide el hash y cómo cotejarlo.
- [[Conexión SOAP y entornos|Conexion-SOAP-y-entornos]] — qué implica forzar el entorno de producción.
- [[Configuración|Configuracion]] — `VERIFACTU_FORCE_PRODUCTION_ENVIRONMENT` y las acciones equivalentes de la pantalla de ajustes.
- [[Permisos y menús|Permisos-y-menus]] — permisos del módulo; la API exige ser administrador para cambiar el entorno, no el permiso `manage`.
- [[Multiempresa|Multiempresa]] — la cabecera `DOLAPIENTITY` y la configuración por entidad.
- [[Instalación|Instalacion]] — dependencias que Dolibarr activa con el módulo.
- [[Resolución de problemas|Resolucion-de-problemas]] — 404 en la API y otros síntomas.
