# Suite de pruebas

El módulo VeriFactu para Dolibarr (2.2.2) incluye en `tests/` una suite de regresión de quince ficheros PHP independientes que se ejecutan con el intérprete de PHP, sin PHPUnit, sin Dolibarr y sin base de datos. Cada fichero nació con una corrección concreta (una issue, un PR o una versión) y comprueba que el defecto no vuelva. La suite forma parte del flujo de publicación: si un fichero falla, no se crea la etiqueta ni la release.

## Cómo ejecutar los tests

Desde la carpeta del módulo:

```bash
cd htdocs/custom/verifactu
php tests/ChainIntegrityTest.php
```

Toda la suite, con el mismo bucle que usa el flujo de publicación:

```bash
fallos=0
for fichero in tests/*.php; do
  if php "$fichero" > /dev/null 2>&1; then echo "OK    $fichero"; else echo "FALLA $fichero"; fallos=$((fallos + 1)); fi
done
echo "Ficheros con fallos: $fallos"
```

Cada fichero devuelve código de salida `0` si todo pasa y `1` si algo falla, y escribe una línea `PASS:`/`FAIL:` por comprobación más un resumen final (`Total: N | Passed: N | Failed: N`). Tres ficheros (`InvoiceValidationTest.php`, `PartialResponseTest.php`, `PriorRejectionTest.php`) no llevan contador: salen con `1` en la primera comprobación fallida y, si todo va bien, imprimen una única línea de confirmación.

### Requisitos del entorno de ejecución

| Necesidad | Para qué | Si falta |
|---|---|---|
| PHP 7.4 o superior en línea de comandos | Todo | No se puede ejecutar |
| Extensión `openssl` de PHP | Certificados, clasificación de errores | Fallan las comprobaciones de certificados |
| Binario `openssl` en el `PATH` (idealmente OpenSSL 3) | `CertificateIdentityTest.php` genera certificados con `openssl req`/`openssl ca`; `IssueFixesTest.php` crea contenedores `.p12` legacy y modernos | Se imprime `SKIP:` y esas comprobaciones se omiten |
| `proc_open()` disponible | Parte final de `IssueFixesTest.php` (binario externo) | `SKIP: proc_open is disabled, external OpenSSL not exercised` |
| Extensión `gd` | Renderizado PNG/SVG del QR en `IssueFixesTest.php` | `SKIP: GD extension not available` |
| Variable `DOLIBARR_HTDOCS` (opcional) | `IssueFixesTest.php` coteja las constantes `TYPE_PROFORMA` y `TYPE_CREDIT_NOTE` contra el `facture.class.php` real | `SKIP: set DOLIBARR_HTDOCS to cross-check…` |
| Escritura en `sys_get_temp_dir()` | Certificados y esquemas temporales | Fallan las comprobaciones que crean ficheros |

Ejemplo con el cotejo contra un Dolibarr instalado:

```bash
DOLIBARR_HTDOCS=/var/www/dolibarr/htdocs php tests/IssueFixesTest.php
```

Los tests no escriben nada dentro del módulo ni en la base de datos, y limpian sus ficheros temporales al terminar. Pueden ejecutarse en una instalación en producción sin riesgo.

## Qué cubre cada fichero

| Fichero | Qué comprueba | Origen | Comprobaciones |
|---|---|---|---|
| `ApiAccessTest.php` | La clase `VerifactuApi` no declara acceso público por defecto; `toProduction()` y `toTest()` son `@access protected`, comprueban `DolibarrApiAccess::$user->admin`, responden 403 antes de tocar la constante del entorno y fuera del `try` (para que no se convierta en 500); `getIntegrity()` sigue siendo público y no escribe nada; ningún método público de la API escribe configuración ni datos | 2.2.2 | 20 |
| `AEATWaitTimeTest.php` | `registerAEATWaitTime()` guarda `TiempoEsperaEnvio` en `VERIFACTU_NEXT_SEND_AT`, `getAEATWaitTimeRemaining()` decrece con el tiempo y nunca es negativo, se ignoran respuestas sin el campo; el trigger llama `execVERIFACTUCall($object, 'Alta', false)` (la validación no aplica la espera) y la cola llama con `true` | PR #37, 2.0.0 | 10 |
| `CertificateIdentityTest.php` | `validateCertificateAndKey()` rechaza un certificado caducado y otro aún no válido, y acepta uno vigente (los tres se generan durante la ejecución); el caducado se reporta como `VERIFACTU_CERT_ERROR_EXPIRED`; `normalizeVerifactuTaxIdentifier()`; `hasVerifactuFiscalRecords()` bloquea con huellas y también cuando la consulta falla; `admin/setup.php` rechaza el cambio de NIF; los dos *bundles* PEM se escriben con `chmod 0600` | PR #41, 2.0.0 | 11 |
| `ChainIntegrityTest.php` | La consulta de la cadena no usa `DATE_FORMAT`, `MONTH()` ni `YEAR()`; la fecha sale como `dd-mm-yyyy`; un error de consulta lanza excepción y una cadena vacía devuelve `null`; el bloqueo se concede, no se toma dos veces, se libera con `pg_advisory_unlock`, usa `GET_LOCK` en MySQL, no bloquea en SQLite, tiene un nombre por entidad y una clave de 32 bits con signo; el trigger deja `PENDING_SUBMISSION` y la cola lo recoge y lo limpia; el envío toma y libera el bloqueo en un `finally`; la identidad declarada se recorta con `mb_substr()` y se anota en el registro; ningún fichero del módulo (salvo `vendor`, `tests` y `newfenix`) usa `DATE_FORMAT(`, `MONTH(`, `YEAR(`, `IFNULL(` ni `CURDATE(` en SQL | Issue #30 punto 4, 2.1.0 | 27 |
| `DashboardDependenciesTest.php` | `verifactuindex.php` sigue usando `dol_get_first_day()`, requiere `core/lib/date.lib.php` y lo hace antes de la primera llamada; ningún fichero del módulo usa un ayudante de `date.lib.php` (lista de 21 funciones) sin cargar la librería | 2.2.1 | 5 |
| `EntityIsolationTest.php` | Siete ficheros (`functions.hash.php`, `verifactu.utils.php`, el trigger principal, `list.facture.php`, `query.facture.php`, `verifactuindex.php`, `integrity.php`) no usan `getEntity('invoice')` y filtran con `((int) $conf->entity)`; `getLastInvoiceHash()` lee solo la entidad activa y no puede recibir una lista de entidades; `integrity.php` no cuenta facturas de todas las entidades; la activación inicializa el estado y el valor por defecto de país solo para la entidad activada | PR #34, 2.0.0 | 19 |
| `ExceptionKeepsRecordTest.php` | `buildVerifactuExceptionErrorData()` no incluye nunca huella ni CSV entre los datos a guardar, conserva el estado de una factura con registro aceptado y pone «Error» a la que no lo tiene; el `catch` de `execVERIFACTUCall()` usa esa función, relee los campos guardados antes de decidir y no asigna ni pasa a `saveVerifactuErrorData()` la huella ni el CSV | 2.2.2 | 16 |
| `InvoiceValidationTest.php` | `Invoice::validate()` acepta un registro correcto (F1 y F2) y rechaza: F2 con destinatario, descripción de más de 500 caracteres, tipo desconocido, emisor de más de 120 caracteres, ausencia de encadenamiento, huella anterior mal formada; y acepta un enlace de cadena válido | PR #40, 2.0.0 | sin contador |
| `IssueFixesTest.php` | Issue #32: `renderQrCode()` declara `$outputType` como `string` y `renderPng()`/`renderSvg()`/`generateBase64QR()` funcionan. Issue #31: `beforePDFCreation()` sobrevive a un modelo de informe de pagos y a `null`, mantiene el título de la factura simplificada y no emite avisos. Issue #30 (2): todos los ficheros de la lista de integridad existen, el trigger principal está incluido, el hash es SHA-256 y no se omite ninguno. Issue #30 (1/6): `getSystemConfig()` transmite la misma identidad que la declaración y respeta `TextMax2Type`, `TextMax30Type`, `TextMax50Type` y `TextMax100Type`. Issue #33: clasificación de errores de OpenSSL, mensajes de `describeCertificateExtractionFailure()`, expresión regular de detección de OpenSSL 3 para `-legacy`, contenedores `.p12` legacy (`RC2-40-CBC` + `PBE-SHA1-3DES`) y moderno generados en el momento, contraseña incorrecta reportada como tal e inyección de metacaracteres inerte. PR #42: existen `extractPkcs12WithPhp()` e `isPhpFunctionAvailable()`, el binario solo se usa si `proc_open` es invocable y no queda ninguna llamada a `exec`, `shell_exec`, `passthru`, `system` o `popen` en el código de certificados. Issue #30 (3): `isVerifactuApplicableInvoice()` excluye el tipo 4 y conserva 0, 1, 2, 3 y 5; trigger y envío la usan. Issue #30 (5): abonos y sustituciones se almacenan como R1 (`TYPE_CREDIT_NOTE_LEGAL`) y la proforma sin tipo | 2.0.0 | 65 |
| `LanguageKeysTest.php` | Recorre todo el código PHP del módulo (y las etiquetas y ayudas de los campos adicionales del descriptor) y comprueba que cada clave del módulo que se pide con `trans()`, `transnoentities()` o `transnoentitiesnoconv()` existe y no está vacía en los cinco ficheros de idioma; comprueba por nombre las claves corregidas en la 2.2.2 y que el estado «Anulada» en español sigue siendo la palabra que busca el código | 2.2.2 | 26 |
| `MassValidateInterceptionTest.php` | El hook `doActions` no intercepta otros contextos, otras acciones masivas ni validaciones sin confirmar; procesa cada factura en su propia transacción (`begin`/`commit` o `rollback`); un fallo no afecta a las demás; maneja fallos de `fetch()` y facturas ya validadas; muestra el mensaje informativo; detecta conflictos de fecha y pide confirmación; con confirmación ajusta las fechas; ordena por fecha ascendente; el mínimo avanza a medida que se procesa; sin cadena previa no hay conflicto; cuenta bien los conflictos; acepta `verifactu_toselect` como respaldo | 1.0.4 | 52 |
| `MultiEntityAndCancellationTest.php` | `getVerifactuEntityWithSameTaxId()` detecta el mismo NIF en otra entidad con cualquier formato, ignora NIF vacíos, considera solo entidades con `MAIN_MODULE_VERIFACTU`, lee solo `llx_const`, excluye la entidad actual, falla en abierto ante un error de consulta y no compara sin MultiCompany; `isVerifactuInvoiceNumberingShared()`; `admin/setup.php` usa ambas y `functions.submission.php` ninguna; una anulación sin registro anterior se marca como primer registro, la biblioteca lo admite y valida | PR #34 y PR #39, 2.2.0 | 19 |
| `PartialResponseTest.php` | `isAEATResponseAccepted()` con siete combinaciones de `EstadoEnvio` y `EstadoRegistro` (una sola línea llega como objeto, varias como array) y con respuestas nulas o vacías | PR #36, 2.0.0 | sin contador |
| `PriorRejectionTest.php` | `setAsPreviousRejection(true)` produce `RechazoPrevio = S`, `null` produce `X`, `false` lo elimina; `createSubsanacion()` con `null` y con `true` | PR #38, 2.0.0 | sin contador |
| `SchemaManagerTest.php` | `schemasExist()` con carpeta vacía, con los siete ficheros y con uno ausente; rutas del WSDL y de la carpeta; `clearSchemas()` borra también `schemas.meta.json`; `rewriteSchemaLocations()` sustituye la URL de W3C por el fichero local; `downloadSchemas(false)` no descarga si ya existen; metadatos; `Config::setLocalWsdlPath()`/`reset()` | 1.0.4 | 19 |

En total, doce ficheros suman 289 comprobaciones con contador, más las tres suites que salen al primer fallo. El README de la 2.1.0 hablaba de «10 ficheros, 100%»; desde entonces se añadieron `MultiEntityAndCancellationTest.php` (2.2.0), `DashboardDependenciesTest.php` (2.2.1) y `ApiAccessTest.php`, `ExceptionKeepsRecordTest.php` y `LanguageKeysTest.php` (2.2.2). Los tres de la 2.2.2 se comprobaron también contra el código de la 2.2.1, sobre el que fallan, antes de publicar la versión.

## Técnicas que usan

- **Análisis del código fuente.** Muchas comprobaciones leen un fichero con `file_get_contents()` y buscan una construcción con `strpos()` o `preg_match()`: que el trigger llame a `execVERIFACTUCall($object, 'Alta', false)`, que el bloqueo se libere dentro de un `finally`, que ningún fichero contenga `getEntity('invoice')`. Es barato, no necesita Dolibarr y detecta la regresión aunque cambie la lógica interna. `IssueFixesTest.php` va más lejos y tokeniza `functions.certificates.php` con `token_get_all()` para distinguir llamadas reales de menciones en comentarios.
- **Escaneos de todo el módulo.** `ChainIntegrityTest.php` recorre todos los `.php` en busca de funciones SQL exclusivas de MySQL; `DashboardDependenciesTest.php` busca ayudantes de `date.lib.php` sin su `require`. Cualquier fichero nuevo queda cubierto automáticamente.
- **Dobles mínimos de Dolibarr.** En lugar de cargar `main.inc.php`, cada test define lo justo: constantes (`MAIN_DB_PREFIX`, `LOG_ERR`…), funciones (`dol_syslog()`, `dol_now()`, `GETPOST()`, `getDolGlobalInt()`, `isModEnabled()`), y clases falsas de base de datos (`FakeChainDb`, `FakeEntityDb`, `MockDoliDB`) que registran las consultas recibidas y devuelven filas preparadas, incluida la simulación de `pg_try_advisory_lock`/`GET_LOCK`. `MassValidateInterceptionTest.php` sustituye `Facture`, `User`, `Conf`, `Translate` y `HookManager` por dobles que registran `begin`/`commit`/`rollback` y los ajustes de fecha.
- **Reflexión.** Para leer propiedades privadas de la biblioteca (`payload`, `registrationPayload`) y para invocar métodos privados (`rewriteSchemaLocations()`) o inspeccionar firmas (`renderQrCode()`).
- **Certificados reales generados en el momento.** `CertificateIdentityTest.php` monta una CA mínima con `openssl ca -selfsign` y fechas `-startdate`/`-enddate` explícitas; `IssueFixesTest.php` genera con `openssl pkcs12 -export -legacy -certpbe RC2-40-CBC -keypbe PBE-SHA1-3DES` un contenedor idéntico al de la FNMT antigua (el comando lo aportó @hippo16214 en la issue #33) y otro con los valores por defecto, y prueba también una contraseña con metacaracteres de shell comprobando que no se ejecuta nada (fichero canario).
- **Guardas de compatibilidad.** Todo se define dentro de `if (!defined(...))` o `if (!function_exists(...))`, de modo que los ficheros pueden ejecutarse tanto de forma aislada como en un entorno donde ya existan esas definiciones.

## Añadir un test nuevo

Respeta el estilo existente para que el fichero encaje en el bucle de publicación:

1. **Nombre y ubicación.** `tests/<Tema>Test.php`. El bucle de `release.yml` recoge `tests/*.php`, así que cualquier fichero PHP que dejes ahí se ejecutará como test.
2. **Cabecera.** Un bloque de comentario que diga qué regresión cubre y de qué issue, PR o versión procede, y cómo se ejecuta.
3. **Independencia.** Sin `main.inc.php`, sin base de datos, sin red. Define constantes y funciones con guardas y carga solo los ficheros del módulo que necesites con `require_once __DIR__ . '/../…'`.
4. **Comprobaciones.** Usa una de las dos ayudas ya empleadas:

```php
$passed = 0;
$failed = 0;
$total = 0;

function assert_test(bool $condition, string $message, &$passed, &$failed, &$total): void
{
    $total++;
    if ($condition) {
        $passed++;
        echo "  PASS: $message\n";
    } else {
        $failed++;
        echo "  FAIL: $message\n";
    }
}

// … comprobaciones …

echo "\n=== Results ===\n";
echo "Total:  $total\nPassed: $passed\nFailed: $failed\n";
exit($failed > 0 ? 1 : 0);
```

o la variante `check($condition, $label)` con globales `$total`/`$passed` y `exit($total === $passed ? 0 : 1)`. Lo importante es la línea `PASS:`/`FAIL:` por comprobación, el resumen final y el **código de salida**: es lo único que mira el flujo de publicación.
5. **Dependencias opcionales.** Si una comprobación necesita el binario `openssl`, GD o `proc_open`, detéctalo y escribe `  SKIP: motivo` en lugar de fallar; el test debe pasar en un alojamiento compartido.
6. **Ficheros temporales.** Solo en `sys_get_temp_dir()`, con un nombre que incluya `getmypid()` o `uniqid()`, y bórralos al final.
7. **Mensajes descriptivos.** Cada etiqueta debe decir qué se garantiza y, si falla, qué se encontró (por ejemplo, la lista de ficheros infractores).
8. **Ejecuta toda la suite** antes de proponer el cambio, y menciona el fichero en la entrada del registro de cambios del README, como se hizo con `tests/IssueFixesTest.php` en la 2.0.0.

> **Nota:** la carpeta `lib/newfenix` contiene `composer.json` y `phpunit.xml` que apuntan a `tests/Unit` y `tests/Integration` de la biblioteca; esas carpetas no se distribuyen con el módulo. La suite del módulo es la de `tests/` en la raíz y no depende de PHPUnit.

## Papel en la publicación de versiones

El flujo `.github/workflows/release.yml` ejecuta la suite completa antes de crear la etiqueta: recorre `tests/*.php`, imprime `OK` o `FALLA` por fichero y aborta con `::error::N ficheros de prueba fallan; no se publica.` si alguno devuelve un código distinto de cero. Como en los ejecutores de GitHub Actions hay OpenSSL 3, `proc_open` y GD, allí se ejercitan también las partes que en un alojamiento restringido se saltarían. El detalle del flujo está en [[Publicación de versiones|Publicacion-de-versiones]].

## Véase también

- [[Desarrollo y contribución|Desarrollo-y-contribucion]]
- [[Publicación de versiones|Publicacion-de-versiones]]
- [[Encadenamiento y huella|Encadenamiento-y-huella]]
- [[Validación masiva|Validacion-masiva]]
- [[Certificados digitales|Certificados-digitales]]
- [[Esquemas WSDL y XSD|Esquemas-WSDL-y-XSD]]
- [[Historial de versiones|Historial-de-versiones]]
