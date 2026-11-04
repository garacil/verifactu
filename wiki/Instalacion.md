# Instalación

Esta página describe cómo instalar el módulo VeriFactu para Dolibarr 2.2.2: de dónde descargarlo, dónde colocarlo, qué permisos de ficheros necesita, cómo activarlo y, sobre todo, qué crea exactamente la activación en la base de datos y en el disco. Termina con las comprobaciones que conviene hacer antes de emitir la primera factura. La configuración posterior se trata en [[Configuración|Configuracion]] y [[Certificados digitales|Certificados-digitales]].

## Antes de empezar

Comprueba la página de [[Requisitos|Requisitos]]. En resumen:

- Dolibarr 17 a 22 y PHP 7.4 o superior.
- Extensión PHP `soap` cargada: sin ella la activación se detiene con el mensaje «La extensión SOAP no está instalada. Instálela y reinicie el servidor web.»
- Extensiones `openssl`, `gd`, `mbstring` y `allow_url_fopen` activado.
- Un certificado digital en formato PFX/P12 (o PEM) con su contraseña.
- El NIF y la razón social del obligado tributario tal y como deben transmitirse a la AEAT.
- La carpeta `htdocs/custom` habilitada en `conf/conf.php` (véase más abajo).

> **Importante:** el módulo activa opciones globales de Dolibarr que cambian el comportamiento de las facturas (no se pueden reabrir, no se pueden eliminar, la fecha se fuerza al validar). Léelas en el apartado «Constantes» antes de activarlo en una instalación en producción.

## Obtener el módulo

### Desde una versión publicada

Las versiones se publican en la página de *releases* del repositorio `github.com/garacil/verifactu`. El flujo `.github/workflows/release.yml` crea la etiqueta con el número de versión sin prefijo (por ejemplo `2.2.2`), pasa la suite de regresión de `tests/` y publica la versión con el título «VeriFactu para Dolibarr 2.2.2» y las notas tomadas del registro de cambios del README. Antes de publicar comprueba que el número declarado coincide en `core/modules/modVerifactu.class.php`, `conf/declaracion_responsable.conf.php` y `lib/functions/functions.configuration.php`, porque ese valor se transmite a la AEAT en el campo `Version`.

Descarga el archivo de código fuente de la versión, descomprímelo y renombra la carpeta resultante a `verifactu` si no se llama así. Todas las rutas del módulo están escritas como `/verifactu/...` (por ejemplo `dol_buildpath('/verifactu/lib/verifactu.lib.php')`), así que el nombre de la carpeta no es opcional.

### Con git

```bash
cd /ruta/a/dolibarr/htdocs/custom
git clone https://github.com/garacil/verifactu.git verifactu
cd verifactu
git checkout 2.2.2
```

Trabajar sobre la rama `main` sin etiqueta instala el estado de desarrollo; para producción usa siempre una etiqueta.

### Comprobar la versión descargada

```bash
grep "verifactu_version = " core/modules/modVerifactu.class.php
grep "'version' => " conf/declaracion_responsable.conf.php
```

Ambos deben mostrar `2.2.2`.

## Ubicación en `htdocs/custom`

La ruta final debe ser `htdocs/custom/verifactu`, con `core/modules/modVerifactu.class.php` directamente debajo. Para que Dolibarr busque módulos en `custom`, `conf/conf.php` debe tener las dos líneas habituales (ajusta la ruta absoluta):

```php
$dolibarr_main_url_root_alt = '/custom';
$dolibarr_main_document_root_alt = '/ruta/a/dolibarr/htdocs/custom';
```

La estructura mínima que debe quedar es:

```
htdocs/custom/verifactu/
├── admin/                (setup.php, managecertificates.php, uploadcertificates.php, about.php, copying.php)
├── class/                (actions_verifactu.class.php, api_verifactu.class.php, verifactu.utils.php)
├── conf/                 (declaracion_responsable.conf.php)
├── core/modules/         (modVerifactu.class.php, facture/doc/pdf_verifactu.modules.php)
├── core/triggers/        (interface_900_..., interface_999_...)
├── core/substitutions/   (functions_verifactu.lib.php)
├── lib/                  (verifactu.lib.php, functions/, newfenix/)
├── langs/                (es_ES, ca_ES, eu_ES, gl_ES, en_US)
├── views/                (tabVERIFACTU.facture.php, list.facture.php, query.facture.php, ...)
├── css/, js/, img/, tests/, docs/
├── verifactuindex.php, integrity.php, README.md, COPYING
```

El README no contempla el instalador de módulos externos de Dolibarr («Desplegar/instalar un módulo externo»), que espera un paquete con nombre `module_*.zip`; el flujo de publicación no genera ese paquete. La vía documentada es copiar la carpeta.

## Permisos de ficheros

- El usuario con el que se ejecuta el servidor web (`www-data`, `apache`, etc.) necesita **lectura** sobre toda la carpeta del módulo. No necesita escritura dentro de `htdocs/custom/verifactu`: el módulo no escribe ahí.
- Necesita **escritura** sobre la carpeta de documentos de Dolibarr, porque la activación crea `documents/verifactu/temp` y `documents/verifactu/certificates` (`$this->dirs` en el descriptor) y más tarde el módulo crea `documents/verifactu/schemas` y los PEM convertidos.
- Los PEM que genera `prepareLocalCertificate()` se escriben con `@chmod($bundleFile, 0600)`, es decir, solo legibles por el usuario del servidor web.

Ejemplo orientativo en Debian/Ubuntu con Apache:

```bash
chown -R www-data:www-data /ruta/a/dolibarr/htdocs/custom/verifactu
find /ruta/a/dolibarr/htdocs/custom/verifactu -type d -exec chmod 755 {} \;
find /ruta/a/dolibarr/htdocs/custom/verifactu -type f -exec chmod 644 {} \;
```

Si desplegaste con `git` y quieres que el usuario del sistema con el que haces `git pull` siga siendo el propietario, basta con que el servidor web tenga lectura.

## Activar el módulo

1. Entra en Dolibarr como administrador.
2. Ve a **Inicio > Configuración > Módulos/Aplicaciones**.
3. Busca «VeriFactu». El módulo se declara en la familia `financial` con posición `90`, así que aparece en la pestaña de módulos financieros con el icono de factura (`$this->picto = 'bill'`).
4. Activa el interruptor. Dolibarr activa antes las dependencias declaradas en `$this->depends`: **Facturas y abonos** (`modFacture`), **Registro de eventos inalterable** (`modBlockedLog`) y **API REST** (`modApi`).
5. Al terminar, aparece el icono de configuración junto al módulo (enlaza con `admin/setup.php`) y el menú superior «Verifactu».

Si la activación falla, el mensaje aparece en la propia lista de módulos. La única comprobación previa que hace `init()` es la de la extensión SOAP; el resto de errores posibles provienen del núcleo (creación de campos adicionales, permisos de carpeta).

## Qué crea la activación

`modVerifactu::init()` ejecuta, en este orden: la comprobación de SOAP, la carga de `lib/verifactu-types.array.php`, `_load_tables('/verifactu/sql/')`, la creación de campos adicionales, el valor por defecto de terceros, `remove()` (para limpiar registros de una activación anterior) y por último `_init()` del núcleo, que registra carpetas, pestañas, partes del módulo, constantes, tarea programada, permisos y menús, y ejecuta una sentencia SQL de inicialización.

### Constantes

Las 21 constantes de `$this->const` se insertan en `llx_const` para la entidad activa, **solo si no existen ya** (`insert_const()` cuenta antes las filas). Ninguna lleva la marca `deleteonunactive`, así que desactivar el módulo no las borra.

| Nombre | Valor inicial | Significado |
|---|---|---|
| `INVOICE_DISALLOW_REOPEN` | `1` | Opción del núcleo: impide reabrir facturas validadas o cerradas |
| `INVOICE_CAN_NEVER_BE_REMOVED` | `1` | Opción oculta del núcleo (`CommonInvoice::is_erasable()`): ninguna factura puede eliminarse |
| `BLOCKEDLOG_DISABLE_NOT_ALLOWED_FOR_COUNTRY` | `ES` | Impide desactivar el módulo Registro de eventos inalterable cuando el país de la empresa es España |
| `VERIFACTU_DIRECT_CALL_ON_VALIDATE` | `1` | Envío a la AEAT al validar la factura |
| `VERIFACTU_QR_SIZE` | `30` | Tamaño del QR en el PDF, en mm |
| `VERIFACTU_QR_POSITION_X` | `custom` | Posición horizontal del QR (`left`, `center`, `right`, `custom`) |
| `VERIFACTU_QR_POSITION_X_CUSTOM` | `70` | Posición X en mm cuando la anterior es `custom` |
| `VERIFACTU_QR_POSITION_Y` | `2` | Posición vertical del QR en mm |
| `VERIFACTU_QR_SHOW_TEXT` | `1` | Muestra «QR tributario:» y «VeriFactu» junto al QR del PDF |
| `VERIFACTU_QR_TEXT_SIZE` | `8` | Tamaño de fuente de ese texto |
| `VERIFACTU_MODE` | `verifactu` | Modo de operación (solo `verifactu` disponible) |
| `SOCIETE_IDPROF1_INVOICE_MANDATORY` | `0` | Desactiva la exigencia del núcleo de NIF para facturar; la exige el trigger del módulo según el caso |
| `INVOICE_CHECK_POSTERIOR_DATE` | `1` | Opción del núcleo: `Facture::validate()` rechaza validar si ya existe una factura del mismo tipo con fecha posterior (`ErrorInvoiceIsNotLastOfSameType`) |
| `VERIFACTU_DEFAULT_TAX_TYPE` | vacío | Impuesto por defecto para facturas nuevas |
| `VERIFACTU_DEFAULT_TAX_REGIME` | vacío | Clave de régimen por defecto |
| `VERIFACTU_DEFAULT_OPERATION_QUALIFICATION` | vacío | Calificación de operación por defecto |
| `VERIFACTU_DEFAULT_EXEMPT_OPERATION` | vacío | Operación exenta por defecto |
| `VERIFACTU_CERT_TYPE` | `normal` | Tipo de certificado (`normal` o `sello`) |
| `FAC_FORCE_DATE_VALIDATION` | `1` | Opción del núcleo: la fecha de la factura se fuerza a la fecha de validación |
| `VERIFACTU_COMPANY_TYPE` | `sociedad` | `sociedad` o `autonomo`; decide la fecha de paso automático a producción |
| `VERIFACTU_QR_SHOW_TEXT_TPV` | `1` | Muestra el texto explicativo también en el ticket de TPV |

Otras constantes las crea el módulo más adelante, no la activación: `VERIFACTU_HOLDER_COMPANY_NAME`, `VERIFACTU_HOLDER_NIF`, `VERIFACTU_CERTIFICATE`, `VERIFACTU_CERTIFICATE_KEY`, `VERIFACTU_CERTIFICATE_PRIVATE_KEY`, `VERIFACTU_FORCE_PRODUCTION_ENVIRONMENT`, `VERIFACTU_NEXT_SEND_AT` o `VERIFACTU_CHAIN_LOCK_TIMEOUT`. Todas se describen en [[Configuración|Configuracion]].

### Campos adicionales

`init()` llama a `ExtraFields::addExtraField()` para cada campo, con posición a partir de 1000, condición de permiso `($user->rights->verifactu->manage || $user->admin)` y condición de activación `$conf->verifactu->enabled`. Si la columna ya existe (reactivación), el núcleo lo tolera (`DB_ERROR_COLUMN_ALREADY_EXISTS`).

En **facturas de cliente** (`llx_facture_extrafields`): `verifactu_inicio_separador1`, `verifactu_estado`, `verifactu_error`, `verifactu_csv_factura`, `verifactu_id_factura`, `verifactu_fecha_factura`, `verifactu_ultima_salida`, `verifactu_ultimafecha_modificacion`, `verifactu_huella`, `verifactu_fecha_hora_generacion`, `verifactu_entorno`, `verifactu_modo`, `verifactu_payload`, `verifactu_inicio_separador2`, `verifactu_factura_tipo`, `verifactu_impuesto`, `verifactu_clave_regimen`, `verifactu_calificacion_operacion`, `verifactu_operacion_exenta` y `verifactu_incidencia`.

En **terceros** (`llx_societe_extrafields`): `verifactu_inicio_separador1`, `verifactu_tipo_identificacion`, `verifactu_impuesto`, `verifactu_clave_regimen`, `verifactu_calificacion_operacion`, `verifactu_operacion_exenta` y `verifactu_factura_simplificada`.

El significado de cada uno está en [[Campos adicionales|Campos-adicionales]]. El hook `updateSession()` impide borrarlos desde las pantallas de atributos adicionales de Dolibarr (mensaje «No se puede eliminar este campo. Los campos 'verifactu_' son esenciales para el módulo.»).

### Permisos, menús y pestañas

- Un permiso, `Gestionar módulo VeriFactu` (`$user->rights->verifactu->manage`, id `35010001`), que el núcleo concede automáticamente a los usuarios administradores (`insert_permissions(1, null, 1)`).
- Un menú superior «Verifactu» y seis entradas de menú izquierdo: Declaración responsable, Facturas emitidas, Consulta AEAT, Documentación, Ayuda y Configuración.
- Una pestaña «VeriFactu» en la ficha de la factura de cliente.

El detalle, con URL y condiciones, está en [[Permisos y menús|Permisos-y-menus]].

### Tarea programada

Se registra en `llx_cronjob`, para la entidad activa, la tarea **VeriFactu - Reenvío de facturas con errores de conexión** (`sendInvoicesWithErrorsCronName`): método `VerifactuUtils::sendInvoicesWithErrors()` de `class/verifactu.utils.php`, cada **2 horas** (`frequency = 2`, `unitfrequency = 3600`), activada (`status = 1`), prioridad 50 y condición `$conf->verifactu->enabled`. Para que se ejecute, el módulo Tareas programadas debe tener configurado el lanzador del sistema (crontab o programador de tareas). Véase [[Reintentos y envíos pendientes|Reintentos-y-envios-pendientes]].

### Plantilla PDF

El descriptor declara `'models' => 1`, así que Dolibarr añade `htdocs/custom/verifactu/core/modules/facture/doc/` a las carpetas donde busca plantillas de factura y la plantilla `verifactu` (`pdf_verifactu.modules.php`, descripción «Plantilla PDF con código QR VeriFactu y datos fiscales AEAT.») aparece en **Configuración > Módulos > Facturas y abonos > Modelos de documentos**. La activación **no la activa ni la marca por defecto**: hay que hacerlo desde esa pantalla si se quiere usar. Independientemente de la plantilla elegida, el hook `afterPDFCreation()` añade el QR a cualquier PDF de una factura ya enviada (véase [[Plantilla PDF verifactu|Plantilla-PDF-verifactu]] y [[Código QR|Codigo-QR]]).

### Carpetas

`insert_dirs()` crea `documents/verifactu/temp` y `documents/verifactu/certificates`. Con Multicompany y una entidad distinta de la 1, la raíz es `documents/<id_entidad>/verifactu`.

### Valor por defecto para terceros

En Dolibarr 14 o superior, `init()` crea un registro `DefaultValues` de tipo `mandatory` para la página `societe/card.php` y el parámetro `country_id`, en la entidad activa: el país pasa a ser obligatorio al crear o editar un tercero, porque el módulo lo necesita para decidir si exige NIF, NIF intracomunitario o dirección.

### Inicialización de facturas existentes

La última sentencia SQL de `init()` pone la insignia «No Enviada» en `verifactu_estado` de todas las facturas de la entidad activa que aún tengan ese campo a `NULL`:

```sql
UPDATE llx_facture_extrafields SET verifactu_estado = '<insignia No Enviada>'
WHERE verifactu_estado IS NULL
  AND fk_object IN (SELECT rowid FROM llx_facture WHERE entity = <entidad>);
```

Las facturas de otras entidades no se tocan (véase [[Multiempresa|Multiempresa]]).

### Lo que la activación no hace

- No crea tablas: `sql/dolibarr_allversions.sql` solo contiene un comentario.
- No descarga los esquemas WSDL/XSD; se descargan en el primer envío o desde el botón de la configuración.
- No configura el lanzador del cron del sistema.
- No activa la plantilla PDF `verifactu`.
- No pide aceptar la licencia de forma interactiva; el texto `VERIFACTU_INIT_LICENSE_ACCEPTED` del fichero de idioma recuerda que al activar se acepta la GPL v3 y que los campos fiscales son responsabilidad de la empresa. El texto completo de la licencia está en la pestaña «Acuerdo de licencia» de la administración (`admin/copying.php`).

## Primer arranque

Tras activar, el menú superior muestra «Verifactu». Su página de inicio, `verifactuindex.php` («Área VeriFactu»), es el panel descrito en [[Panel principal|Panel-principal]]: facturas por mes del año en curso, distribución por estado, últimas enviadas, facturas con errores y pendientes de envío. Recién instalado estará vacío.

El orden recomendado para dejar el módulo operativo es:

1. **Subir el certificado** en la pestaña «Gestionar Certificados» de la configuración. Si la validación es correcta, el módulo rellena por sí mismo `VERIFACTU_CERTIFICATE` y `VERIFACTU_CERTIFICATE_KEY`.
2. **Rellenar la configuración básica** en la pestaña «Ajustes»: razón social, NIF y tipo de empresa. La razón social y el NIF se proponen a partir de la ficha de la empresa (`$mysoc->name`, `$mysoc->idprof1`).
3. Guardar. Cuando razón social, NIF y certificado están informados, la pantalla muestra «Estado de la configuración: Completa» y despliega las secciones adicionales (QR, tipo de certificado, valores por defecto).
4. Comprobar que el **entorno activo** es «Pruebas» y pulsar **Descargar/Actualizar esquemas**.
5. Emitir una factura de prueba y revisar su pestaña VeriFactu.
6. Cuando toque, cambiar a producción con el botón «Cambiar a producción».

Cada paso se explica en [[Configuración|Configuracion]] y [[Certificados digitales|Certificados-digitales]].

> **Nota:** el NIF del obligado tributario queda **bloqueado** en cuanto la entidad tiene su primera huella (`hasVerifactuFiscalRecords()`), incluso si se generó en el entorno de pruebas. Revísalo antes de la primera factura.

## Comprobaciones posteriores

| Comprobación | Dónde | Resultado esperado |
|---|---|---|
| Módulo activo y versión | Inicio > Configuración > Módulos | «VeriFactu» activado, versión 2.2.2 |
| Dependencias | Misma pantalla | Facturas, Registro de eventos inalterable y API REST activos |
| Estado de la configuración | Configuración VeriFactu > Ajustes | «Completa» |
| Entorno activo | Misma pantalla | «Pruebas» hasta que decidas pasar a producción |
| Esquemas WSDL/XSD | Misma pantalla | «Descargados» con fecha de última descarga |
| Caducidad del certificado | Misma pantalla, campo «Caducidad del certificado» | Insignia «Válido» con los días restantes |
| Tarea programada | Inicio > Configuración > Módulos > Tareas programadas | «VeriFactu - Reenvío de facturas con errores de conexión», activa, cada 2 horas |
| Campos adicionales | Configuración > Módulos > Facturas > Atributos adicionales | Los 20 campos `verifactu_*` |
| Plantilla PDF | Configuración > Módulos > Facturas > Modelos de documentos | `verifactu` disponible (actívala si la quieres usar) |
| Pestaña en la factura | Cualquier factura de cliente | Pestaña «VeriFactu» visible para administradores y usuarios con el permiso |
| Carpetas | `documents/verifactu/` | `certificates/`, `temp/` y, tras la descarga, `schemas/` |
| Registro | `documents/dolibarr.log` con nivel DEBUG | Líneas `VERIFACTU ...` sin errores de certificado |

Para diagnosticar cualquier desviación, consulta [[Registro y depuración|Registro-y-depuracion]] y [[Resolución de problemas|Resolucion-de-problemas]].

## Desactivar o desinstalar

Al desactivar, `remove()` llama a `_remove()` del núcleo, que elimina pestañas, partes del módulo (hooks, triggers, CSS, JS), la tarea programada, los permisos, los menús y el registro de carpetas. **No** elimina:

- Las constantes de `$this->const`, porque ninguna tiene `deleteonunactive`. En particular `INVOICE_DISALLOW_REOPEN`, `INVOICE_CAN_NEVER_BE_REMOVED`, `FAC_FORCE_DATE_VALIDATION` e `INVOICE_CHECK_POSTERIOR_DATE` siguen activas en Dolibarr hasta que las borres a mano en Inicio > Configuración > Otros.
- Las constantes de configuración (`VERIFACTU_HOLDER_NIF`, certificado, etc.).
- Los campos adicionales ni su contenido: la cadena de huellas y los CSV de las facturas se conservan.
- Los ficheros de `documents/verifactu`.

Eliminar la carpeta `htdocs/custom/verifactu` sin desactivar antes el módulo deja registros huérfanos (menús, tarea programada, hooks); desactiva siempre primero.

## Véase también

- [[Requisitos|Requisitos]]
- [[Configuración|Configuracion]]
- [[Certificados digitales|Certificados-digitales]]
- [[Permisos y menús|Permisos-y-menus]]
- [[Actualización|Actualizacion]]
- [[Campos adicionales|Campos-adicionales]]
- [[Multiempresa|Multiempresa]]
