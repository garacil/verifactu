# Desarrollo y contribución

Guía para quien quiera modificar el módulo VeriFactu para Dolibarr (2.2.2), proponer correcciones o redistribuirlo: cómo montar un entorno de desarrollo, dónde vive cada cosa y dónde añadir funciones, hooks, triggers o páginas, las convenciones que sigue el proyecto en ramas, commits, pruebas y registro de cambios, cómo abrir issues y PR útiles, y qué implica la licencia GPL-3.0-or-later al redistribuir.

## Entorno de desarrollo

### Dolibarr y el módulo

1. Instala un Dolibarr de desarrollo. Las correcciones de la 2.0.0 se verificaron sobre 17.0.2, 19.0.3 y 22.0.5 con PHP 7.4, 8.2 y 8.4 (README, «Compatibilidad verificada»); ten a mano al menos la versión mínima y la más reciente que uses. Los requisitos de extensiones están en [[Requisitos|Requisitos]].
2. Clona el repositorio dentro de `htdocs/custom/` con el nombre exacto `verifactu` (las rutas `dol_buildpath('/verifactu/...')` y `dol_include_once('/verifactu/...')` dependen de él):

```bash
cd /ruta/a/dolibarr/htdocs/custom
git clone https://github.com/garacil/verifactu.git verifactu
```

3. Activa el módulo en Configuración > Módulos. La activación crea los campos adicionales `verifactu_*` en factura y tercero, las constantes por defecto, la tarea programada de reintentos y las entradas de menú ([[Instalación|Instalacion]]).
4. Prueba con **MySQL/MariaDB y PostgreSQL**. La cadena, el bloqueo y el panel tienen ramas distintas por motor, y la 2.1.0 corrigió un fallo que solo aparecía en PostgreSQL. `tests/ChainIntegrityTest.php` rechaza cualquier función SQL exclusiva de MySQL.

### Entorno de pruebas de la AEAT y certificado

- `getEnvironment()` devuelve `test` hasta la fecha de transición de 2027 salvo que `VERIFACTU_FORCE_PRODUCTION_ENVIRONMENT` esté activa, así que un Dolibarr de desarrollo trabaja contra preproducción sin tocar nada. Los servidores de pruebas son `prewww1.aeat.es` (certificado «normal») y `prewww10.aeat.es` («sello»), y los esquemas y la validación del QR salen de `prewww2.aeat.es`. Véase [[Conexión SOAP y entornos|Conexion-SOAP-y-entornos]].
- Necesitas un certificado electrónico real (FNMT o equivalente) que la AEAT admita en su portal de preproducción; el módulo no incluye ninguno de pruebas. Súbelo desde la pestaña Certificados ([[Certificados digitales|Certificados-digitales]]).
- La cadena de pruebas es independiente de la de producción (`verifactu_entorno`) y tiene su propio bloqueo, de modo que puedes encadenar facturas de prueba sin afectar a nada real.
- Activa el módulo Syslog en `LOG_DEBUG` ([[Registro y depuración|Registro-y-depuracion]]).

### Suite de pruebas

La suite de `tests/` corre sin Dolibarr ni base de datos: `php tests/NombreTest.php`. Ejecútala completa antes de cada cambio y añade un fichero nuevo por cada regresión que corrijas ([[Suite de pruebas|Suite-de-pruebas]]).

## Estructura del código y dónde añadir cosas

| Carpeta / fichero | Qué contiene | Cuándo tocarlo |
|---|---|---|
| `core/modules/modVerifactu.class.php` | Descriptor del módulo: versión (`$this->version`, `verifactu_version`, `verifactu_version_date`), `module_parts` (triggers, sustituciones, modelos, CSS, JS, contextos de hooks), constantes por defecto, pestañas, tarea programada `sendInvoicesWithErrors`, permiso `manage`, menús, campos adicionales en `init()` | Nueva constante, nuevo contexto de hook, nueva entrada de menú, nuevo campo adicional, cambio de versión |
| `core/triggers/interface_900_modVerifactu_BillRestrictions.class.php` | Bloqueos sobre facturas sin `PROV`: `billDelete`, `lineBillInsert/Update/Delete`, `billUnvalidate` (prioridad 900) | Nuevas restricciones |
| `core/triggers/interface_999_modVerifactu_VerifactuTriggers.class.php` | `billCreate` (valores por defecto y tipo), `billValidate` (envío o marca de pendiente, reversión a borrador), `companyModify` (NIF del tercero) | Lógica que deba ocurrir en un evento de negocio |
| `class/actions_verifactu.class.php` | Hooks: `doActions` (validación masiva y fecha), `dolGetButtonAction`, `afterPDFCreation` (QR en PDF con TCPDI), `beforePDFCreation`, `moreHtmlStatus`, `formDolBanner`, `TakeposReceipt`, `ODTSubstitution`, `pdf_build_address`, `updateSession`, `printCommonFooter` | Cambios en la interfaz de Dolibarr o en documentos |
| `class/verifactu.utils.php` | `VerifactuUtils::sendInvoicesWithErrors()` (cola de reintentos, invocada por el cron y antes de cada validación) | Cambios en el reintento |
| `class/api_verifactu.class.php` | API REST: `GET integrity`, `POST toProduction`, `POST toTest` | Nuevos endpoints |
| `lib/verifactu.lib.php` | Punto de entrada: carga la biblioteca, el `vendor` y los once ficheros de `lib/functions` en orden de dependencias | Al crear un fichero de funciones nuevo |
| `lib/functions/functions.*.php` | Funciones por tema: `compatibility`, `utilities`, `configuration`, `admin`, `certificates`, `hash`, `qr`, `response`, `submission`, `cancellation`, `query` | Casi toda la lógica del módulo |
| `lib/newfenix/src/*.php` | Biblioteca `OpenAEAT\Billing` (`Invoice`, `Cancellation`, `Query`, `Manager`, `SoapClient`, `Config`, `SchemaManager`, `QRGenerator`, `BillingException`), con alias `Sietekas\Verifactu\*` en `autoload.php` | Estructura del registro, validación contra el XSD, transporte |
| `lib/newfenix/vendor/` | `chillerlan/php-qrcode` | No se edita |
| `lib/verifactu-types.array.php` | Listas L1, L2, L7, L8, L9, L10 e incidencia | Nuevos códigos de la AEAT |
| `lib/dumper.php` | Volcado legible de arrays y objetos | Depuración en páginas |
| `views/*.php` | Páginas: `list.facture.php`, `query.facture.php`, `tabVERIFACTU.facture.php`, `declaration.php`, `declaration_json.php`, `faq.php`, `documentation.php`, `pos.facture.php` | Nuevas pantallas |
| `verifactuindex.php` | Panel principal | Estadísticas |
| `admin/*.php` | `setup.php`, `managecertificates.php`, `uploadcertificates.php`, `about.php`, `copying.php` | Configuración |
| `conf/declaracion_responsable.conf.php` | Declaración responsable: productor, sistema (`nombre_sistema_informatico`, `id_sistema_informatico`, `version`, `fecha_version`), lista `ficheros_verificados` del hash de integridad | Cambio de versión, ficheros críticos nuevos, redistribución |
| `core/modules/facture/doc/pdf_verifactu.modules.php` | Plantilla PDF propia | Cambios en el PDF |
| `core/substitutions/functions_verifactu.lib.php` | Sustitución `FACTUREVERIFACTUTAB` | Nuevas sustituciones |
| `langs/{es_ES,en_US,ca_ES,eu_ES,gl_ES}/verifactu.lang` | Traducciones | Cualquier texto nuevo, en los cinco idiomas |
| `sql/dolibarr_allversions.sql` | SQL de instalación | Cambios de esquema |
| `tests/*.php` | Suite de regresión | Con cada corrección |
| `.github/workflows/` | `release.yml` y `limpiar-ramas.yml` | Flujo de publicación |

### Añadir una función

Colócala en el fichero de `lib/functions/` del tema que corresponda (o crea `functions.<tema>.php` y añádelo a `lib/verifactu.lib.php` respetando el orden: base, configuración, certificados, proceso, operaciones con la AEAT). Sigue el patrón del resto: bloque de documentación, `global $conf, $db, $langs` solo cuando se usan, `dol_syslog()` con prefijo `VERIFACTU:` y nivel adecuado, entidad siempre acotada con `((int) $conf->entity)` y sin funciones SQL exclusivas de un motor. Si la función decide si un registro se envía, recuerda que en la validación un `false` revierte la factura a borrador: los fallos transitorios de base de datos no deben caer por esa vía (así se hizo con las comprobaciones de Multiempresa en la 2.2.0).

### Añadir un hook

1. Comprueba que el contexto está en `module_parts['hooks']` de `modVerifactu.class.php` (hoy: `main`, `invoicecard`, `invoicelist`, `invoicelistverifactu`, `pdfgeneration`, `takeposfrontend`, `odtgeneration`); si no, añádelo y reactiva el módulo.
2. Crea el método público en `ActionsVerifactu` con la firma del hook de Dolibarr. Devuelve `0` para dejar continuar al código estándar, `1` para sustituirlo, `< 0` en error; usa `$this->resprints` para imprimir y `$this->results` para devolver datos (así comunica `doActions` el conflicto de fechas a la vista).
3. Guarda las entradas contra objetos inesperados: `beforePDFCreation` recibe modelos de informe que no son facturas (issue #31). Cubre el caso con un test.

### Añadir un trigger

Los ficheros de `core/triggers/` se cargan solos. Añade un método con el nombre del evento en camelCase (`BILL_VALIDATE` → `billValidate`, `COMPANY_MODIFY` → `companyModify`) en el trigger existente que corresponda: restricciones en el 900, lógica VeriFactu en el 999. Devuelve `-1` con `setEventMessage(..., 'errors')` para bloquear la operación, `1` si actuaste y `0` si no. Excluye las proformas con `isVerifactuApplicableInvoice()` cuando proceda.

### Añadir una página

Copia el bloque de carga de `main.inc.php` de cualquier fichero de `views/`, carga `verifactu@verifactu` con `$langs->loadLangs()`, comprueba `$user->rights->verifactu->manage` (o `$user->admin`), y **requiere explícitamente** las librerías del núcleo que uses (`core/lib/date.lib.php`, `core/lib/admin.lib.php`…): `main.inc.php` no las carga todas, y olvidar `date.lib.php` produjo el error 500 de la 2.1.0/2.2.0. Registra la entrada en `$this->menu` de `modVerifactu.class.php` con `mainmenu => 'verifactu'` y el permiso `manage`.

### Añadir un texto

Toda cadena visible pasa por `$langs->trans('CLAVE')` y la clave se añade a los **cinco** ficheros de `langs/`. Antes de crear una clave nueva comprueba si ya existe una equivalente: el código conserva algunos nombres heredados en español (`VERIFACTU_STATUS_ANULADA`, `SOCIETE_HAVE_INVOICES_NOT_*`…) que desde la 2.2.2 están definidos como alias de su clave en inglés, al final de cada fichero de idioma. `tests/LanguageKeysTest.php` falla si el código pide una clave del módulo que no existe, o que está vacía, en alguno de los cinco idiomas, así que un texto nuevo sin traducir no pasa la suite.

### Tocar la biblioteca

`lib/newfenix/src` construye y valida los registros. Cualquier límite nuevo debe salir del esquema oficial (`SuministroInformacion.xsd`, `RespuestaSuministro.xsd`) y citarlo en el comentario, como hacen `Invoice::validate()` o `setAsPreviousRejection()`. Mantén los alias `Sietekas\Verifactu\*` del `autoload.php`: el código del módulo los usa. Si añades un fichero crítico para la huella, el envío o la anulación, inclúyelo en `ficheros_verificados` de la declaración responsable ([[Integridad del módulo|Integridad-del-modulo]]).

## Convenciones del proyecto

Observadas en el repositorio y en los flujos de GitHub Actions:

- **Ramas.** El trabajo se hace en ramas cortas con prefijo `work/` (por ejemplo `work/error-500-pagina-principal`) que se integran en `main` mediante *merge* o *pull request*. Tras la integración, `limpiar-ramas.yml` borra automáticamente las ramas remotas cuyo contenido ya está completo en `main`; solo conserva `main`, `master`, `develop` y `stable` y cualquier rama con commits pendientes. No cuentes con que una rama integrada siga existiendo.
- **Mensajes de commit.** En español, asunto en minúsculas, con prefijo `fix:`, `feat:`, `docs:`, `ci:`, `test:` o `chore:` y sin tildes ni eñes en el asunto (`fix: cargar date.lib.php en la pagina principal del modulo`, `ci: publicar la version al integrar en main`). El cuerpo sí lleva tildes y explica qué fallaba, por qué, cómo se ha corregido, cómo se ha verificado y, si procede, que la versión se declara en los tres ficheros y en el README. Los commits anteriores a agosto de 2026 estaban en inglés y sin prefijo; la convención actual es la descrita.
- **Cada corrección lleva su test** en `tests/` y la suite completa debe pasar. Las aportaciones de la 2.0.0 «cada una venía con su test» (README, Agradecimientos), y eso es lo que se espera.
- **El README lleva el registro de cambios.** Cada versión tiene una entrada `### vX.Y.Z (AAAA-MM-DD)` que explica el problema y la solución con referencia a issues y PR, acredita a quien lo reportó y enumera ficheros modificados y tests. El flujo de publicación toma de ahí las notas de la release.
- **La versión se declara en tres sitios** que el flujo compara antes de publicar ([[Publicación de versiones|Publicacion-de-versiones]]).
- **Esta wiki se edita en el repositorio**, en la carpeta `wiki/`. Al integrar en `main`, `publicar-wiki.yml` la copia a la wiki; editar una página desde la interfaz de GitHub funciona, pero el siguiente cambio en `wiki/` la sobrescribirá.
- **Estilo de código.** Tabuladores y llaves al estilo de Dolibarr en el módulo; PSR-12 con `declare(strict_types=1)` y espacios en la biblioteca `newfenix`. Comentarios y nombres de funciones en inglés, con alias en español donde había código previo (`consultarFacturaEnAEAT()` junto a `queryInvoiceInAEAT()`). Compatibilidad con PHP 7.4: nada de `match`, tipos de unión ni `readonly`.
- **Referencias normativas en el código.** Cuando una decisión sale de un artículo o del XSD, se cita en el comentario (`Art. 13 Orden HAC/1177/2024`, `SuministroInformacion.xsd declares…`). Los tests lo comprueban a veces literalmente.
- **Seguridad.** Contraseñas por variable de entorno (`-password env:`), `proc_open` con argumentos en array, PEM con permisos `0600`, entidad siempre acotada, sin `die()` en las funciones de biblioteca.

## Cómo abrir una issue útil

Incluye lo que pide [[Registro y depuración|Registro-y-depuracion]]: versiones (módulo, Dolibarr, PHP, base de datos, OpenSSL), tipo de alojamiento y `disable_functions`, entorno de pruebas o producción, el mensaje exacto y los campos Estado/Error/Última respuesta de la factura, el fragmento del registro en `LOG_DEBUG` y, para errores 500, la línea `PHP Fatal error` del servidor web. Si el problema es normativo, cita el artículo o el elemento del XSD: la issue #30 llegó «con referencia al articulado punto por punto» y por eso se pudo resolver en varias entregas.

## Cómo proponer un cambio (PR)

1. Parte de `main` en una rama `work/<tema>`.
2. Un tema por PR. Si el cambio viene de una issue, enlázala.
3. Explica el **fondo** del hallazgo, no solo la implementación: qué exige el esquema o la norma y qué hacía mal el módulo. En la 2.2.0 los PR #34 y #39 se resolvieron aplicando el fondo con otra implementación, y el motivo quedó explicado en cada PR; un PR bien argumentado sirve aunque su código no se integre tal cual.
4. Añade el test que reproduce el fallo y que pasa con tu cambio, y ejecuta toda la suite.
5. Si el cambio altera lo que se transmite a la AEAT, dilo explícitamente: condiciona el número de versión.
6. Actualiza los cinco ficheros de idioma si hay textos nuevos.
7. No modifiques la versión ni el registro de cambios del README salvo que el mantenedor te lo pida; la entrada la redacta quien publica, acreditando la aportación.

Los cambios que inspeccionan la estructura interna de módulos ajenos a Dolibarr (por ejemplo, tablas de MultiCompany) o que bloquean la activación o la validación por comprobaciones de configuración se han rechazado hasta ahora: se prefiere leer solo tablas del núcleo, fallar en abierto en la pantalla de configuración y nunca en el camino del envío.

## Licencia y redistribución

El módulo se distribuye bajo **GPL-3.0-or-later**; el texto completo está en `COPYING` y se muestra en la pestaña «Acuerdo de licencia» de la configuración (`admin/copying.php`). Al activar el módulo se aceptan sus términos (`VERIFACTU_INIT_LICENSE_ACCEPTED`). Deriva de un módulo previo también bajo GPL v3, cuya atribución se conserva en las cabeceras de los ficheros y en la sección «Historia del Proyecto» del README.

Si redistribuyes el módulo, modificado o no:

- Conserva la licencia y los avisos de copyright de las cabeceras, entrega el código fuente (o el acceso a él) y, si has modificado ficheros, indícalo.
- Pasas a ser el **productor del sistema informático** a efectos del Art. 15 de la Orden HAC/1177/2024. Edita `conf/declaracion_responsable.conf.php` con los datos de tu empresa (`productor`, `suscripcion`, `firmante`) y mantén coherentes `nombre_sistema_informatico` (máximo 30 caracteres), `id_sistema_informatico` (máximo 2) y `version` (máximo 50) con lo que se transmite: `getSystemConfig()` los lee de ese fichero, y un recorte silencioso quedaría anotado como error en el registro. El README de la 2.1.0 explica por qué estos datos no se parametrizan desde la pantalla de administración: el fichero existe precisamente para esto. Véase [[Declaración responsable|Declaracion-responsable]].
- Si cambias la versión, hazlo en los tres ficheros y en el README, como describe [[Publicación de versiones|Publicacion-de-versiones]]; el valor se transmite a la AEAT en el campo `Version`.
- El hash de integridad de la declaración se calcula sobre los ficheros de `ficheros_verificados`; cualquier modificación de esos ficheros cambia el hash, que es lo esperado.

## Véase también

- [[Arquitectura del módulo|Arquitectura-del-modulo]]
- [[Biblioteca newfenix|Biblioteca-newfenix]]
- [[Suite de pruebas|Suite-de-pruebas]]
- [[Publicación de versiones|Publicacion-de-versiones]]
- [[Registro y depuración|Registro-y-depuracion]]
- [[Declaración responsable|Declaracion-responsable]]
- [[Integridad del módulo|Integridad-del-modulo]]
- [[Historial de versiones|Historial-de-versiones]]
