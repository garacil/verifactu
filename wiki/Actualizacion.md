# Actualización

Esta página explica cómo actualizar el módulo VeriFactu para Dolibarr a la versión 2.2.4 desde cualquier versión anterior: precauciones, procedimiento (sustituir la carpeta o `git pull` y reactivar), qué hace exactamente la reactivación, qué se conserva, cómo comprobar la versión instalada y las notas de migración que el registro de cambios del README asocia a cada versión.

## Antes de actualizar

1. **Copia de seguridad de la base de datos.** El módulo guarda su estado en tablas del núcleo: `llx_const` (configuración por entidad), `llx_extrafields` (definición de los campos), `llx_facture_extrafields` y `llx_societe_extrafields` (huellas, CSV, estados, campos fiscales), `llx_cronjob`, `llx_menu`, `llx_rights_def` y `llx_default_values`.
2. **Copia de seguridad de `documents/verifactu`** (y de `documents/<id_entidad>/verifactu` en instalaciones Multicompany). Contiene los certificados subidos, los PEM convertidos y la caché de esquemas. Los PEM se escriben con permisos `0600`; conserva esos permisos al restaurar.
3. **Prueba en un entorno de pruebas** con una copia de la instalación y el entorno VeriFactu en «Pruebas». Las comprobaciones de la 2.0.0 se hicieron sobre Dolibarr 17.0.2, 19.0.3 y 22.0.5; si tu versión es otra, la prueba previa es aún más recomendable.
4. **Lee las notas de migración** de más abajo, especialmente si vienes de una versión anterior a la 2.0.0 o usas PostgreSQL.
5. **Elige un momento sin validaciones en curso.** El envío toma un bloqueo de sesión de base de datos por entidad y entorno (`acquireVerifactuChainLock()`); sustituir ficheros en mitad de un envío puede dejar una petición ejecutando código de dos versiones.
6. **Anota la versión actual** tal como la muestra Dolibarr en la lista de módulos, para poder confirmar el cambio después.

> **Importante:** el número de versión del módulo se transmite a la AEAT en el campo `Version` del bloque `SistemaInformatico` de cada registro (Art. 15.2.c de la Orden HAC/1177/2024). Actualizar cambia ese valor a partir del siguiente envío. Es el comportamiento esperado, pero conviene saberlo.

## Procedimiento

### Opción A: sustituir la carpeta

1. Descarga la versión desde la página de *releases* del repositorio (etiqueta `2.2.4`).
2. Renombra la carpeta actual como copia de respaldo, por ejemplo `htdocs/custom/verifactu.old`, o muévela fuera de `htdocs`.
3. Descomprime la nueva versión en `htdocs/custom/verifactu` (el nombre de la carpeta debe ser exactamente `verifactu`).
4. Ajusta propietario y permisos como en la instalación (lectura para el usuario del servidor web).
5. Reactiva el módulo (paso común más abajo).

No copies encima de la carpeta antigua sin vaciarla: ficheros eliminados en la nueva versión quedarían presentes y podrían cargarse.

### Opción B: git

```bash
cd /ruta/a/dolibarr/htdocs/custom/verifactu
git fetch --tags
git status            # comprueba que no hay cambios locales
git checkout 2.2.4
```

Si tienes cambios locales (por ejemplo en `conf/declaracion_responsable.conf.php` porque redistribuyes el módulo bajo tu nombre), guárdalos antes con `git stash` y vuelve a aplicarlos después.

### Paso común: reactivar el módulo

Dolibarr no ejecuta `init()` de un módulo externo cuando cambian sus ficheros. Para que la nueva versión registre lo que le falta:

1. Ve a **Inicio > Configuración > Módulos/Aplicaciones**.
2. Desactiva **VeriFactu** y vuelve a activarlo.
3. Comprueba que aparece la versión `2.2.4` en la lista.

En instalaciones Multicompany la activación es por entidad: repite el paso en cada entidad que tenga el módulo activo (véase [[Multiempresa|Multiempresa]]).

### Después de reactivar

- Abre **Configuración VeriFactu > Ajustes** y comprueba «Estado de la configuración: Completa» y el entorno activo.
- Pulsa **Descargar/Actualizar esquemas** si la versión anterior no tenía caché local (anteriores a la 1.0.4) o si la AEAT ha publicado esquemas nuevos.
- Revisa en **Tareas programadas** que la tarea de reenvío sigue activa con la frecuencia que quieres.
- Valida una factura en el entorno de pruebas o, si estás en producción, revisa la pestaña VeriFactu de la siguiente factura real.

## Qué hace la reactivación

`modVerifactu::init()` vuelve a ejecutar la misma secuencia que en la instalación. Lo relevante para una actualización es cómo trata lo que ya existe:

| Elemento | Comportamiento en la reactivación | Dónde |
|---|---|---|
| Constantes de `$this->const` | Solo se insertan las que **no existen** en la entidad; los valores actuales se respetan. Al desactivar no se borra ninguna, porque no llevan `deleteonunactive` | `DolibarrModules::insert_const()` / `delete_const()` |
| Constantes de configuración (`VERIFACTU_HOLDER_NIF`, certificado, entorno forzado...) | No se tocan | No forman parte de `$this->const` |
| Campos adicionales | Se vuelven a declarar; si la columna ya existe el núcleo lo acepta (`DB_ERROR_COLUMN_ALREADY_EXISTS`). Los campos nuevos de una versión se crean; los datos de los existentes se conservan | `ExtraFields::addExtraField()` |
| Permisos | Se eliminan de `llx_rights_def` y se vuelven a insertar con el mismo identificador (`35010001`), por lo que las asignaciones existentes siguen apuntando a él; los administradores lo reciben de nuevo | `delete_permissions()` / `insert_permissions()` |
| Menús | Se eliminan y se vuelven a crear | `delete_menus()` / `insert_menus()` |
| Tarea programada | Se elimina (por `module_name = 'verifactu'` y entidad) y se crea de nuevo **con los valores por defecto**: cada 2 horas, activa. Si habías cambiado la frecuencia, vuelve a ajustarla | `delete_cronjobs()` / `insert_cronjobs()` |
| Pestañas, hooks, triggers, CSS, JS, sustituciones, modelos | Se eliminan y se vuelven a registrar en `MAIN_MODULE_VERIFACTU_*` | `delete_module_parts()` / `insert_module_parts()` |
| Carpetas `documents/verifactu/temp` y `certificates` | Se registran de nuevo; los ficheros no se tocan | `insert_dirs()` |
| Valor por defecto `country_id` obligatorio en `societe/card.php` | Se crea de nuevo para la entidad activa | `DefaultValues::create()` |
| Insignia «No Enviada» | Solo se aplica a facturas de la entidad cuyo `verifactu_estado` sea `NULL`; las ya marcadas no cambian | SQL final de `init()` |

## Qué se conserva

- **La cadena de facturación.** Las huellas (`verifactu_huella`), fechas de generación, CSV, tipos de factura, incidencias y cargas XML (`verifactu_payload`) viven en `llx_facture_extrafields` y no se modifican. `getLastInvoiceHash()` seguirá encontrando el último registro de la entidad y entorno.
- **La configuración.** Todas las constantes `VERIFACTU_*` de la entidad, incluido el entorno forzado (`VERIFACTU_FORCE_PRODUCTION_ENVIRONMENT`), el NIF bloqueado y las opciones del QR.
- **Los certificados.** Los ficheros de `documents/verifactu/certificates` y las constantes `VERIFACTU_CERTIFICATE`, `VERIFACTU_CERTIFICATE_KEY` y `VERIFACTU_CERTIFICATE_PRIVATE_KEY`. Si la versión anterior ya convirtió el P12 a `*_bundle_protegido.pem`, la nueva lo reutiliza.
- **La caché de esquemas** de `documents/verifactu/schemas` y su fichero `schemas.meta.json`.
- **El tiempo de espera pendiente** que la AEAT pidiera (`VERIFACTU_NEXT_SEND_AT`).
- **Los campos adicionales de terceros** (tipo de identificación, impuesto, clave de régimen, cliente de factura simplificada).

Lo único que se reinicia es la definición de la tarea programada (frecuencia y estado) y, en Dolibarr 14 o superior, se añade otra vez el valor por defecto del país obligatorio.

## Comprobar la versión instalada

La versión aparece en varios sitios y todos deben coincidir:

| Dónde | Qué muestra | Origen |
|---|---|---|
| Inicio > Configuración > Módulos | `2.2.4` junto al nombre del módulo | `$this->version` en `core/modules/modVerifactu.class.php` |
| Configuración VeriFactu > pestaña **Acerca de** (`admin/about.php`) | El README completo, con la sección «Información del Módulo» y el registro de cambios | `getDescLong()` renderiza `README.md` |
| Menú Verifactu > Declaración responsable | Versión del sistema informático | `conf/declaracion_responsable.conf.php`, clave `sistema.version` |
| `GET /api/index.php/verifactuapi/integrity` | Campo `version` | `class/api_verifactu.class.php` |
| Registro de facturación enviado a la AEAT | Campo `Version` del bloque `SistemaInformatico` | `getDeclaredSystemIdentity()` en `lib/functions/functions.configuration.php` |

El flujo de publicación (`.github/workflows/release.yml`) se niega a publicar si `verifactu_version` del descriptor, `sistema.version` de la declaración responsable y el valor de respaldo de `getDeclaredSystemIdentity()` no coinciden. Desde la línea de órdenes:

```bash
grep -n "verifactu_version = " core/modules/modVerifactu.class.php
grep -n "'version' => '" conf/declaracion_responsable.conf.php lib/functions/functions.configuration.php
```

Tras la actualización, la pestaña «Acerca de» debe mostrar «Versión: 2.2.4» y la entrada «v2.2.4 (2026-11-03)» en el registro de cambios.

## Notas de migración por versión

Las notas siguientes resumen lo que el README indica que cambia de comportamiento en cada versión. Aplican todas las que haya entre tu versión de origen y la 2.2.4.

### Desde 1.0.x a 2.0.0

- **Renumeración.** El descriptor llevaba desde diciembre de 2025 congelado en `1.0.3`: los paquetes 1.0.4 y 1.1 se instalaban y mostraban como 1.0.3, y ese era el valor transmitido a la AEAT. La 2.0.0 unifica el número en el descriptor, la declaración responsable y el README. Si tenías instalada la etiqueta 1.1, la lista de módulos pasará de mostrar 1.0.3 a 2.0.0 sin retroceso aparente.
- **Rechazos locales nuevos.** Antes de llamar al servicio se comprueban tipo de factura, longitudes de `NombreRazonEmisor` y `DescripcionOperacion`, número de destinatarios y de líneas de desglose, encadenamiento y formato de las huellas contra el XSD. Registros que antes se transmitían pueden rechazarse ahora en local con un mensaje explícito.
- **Consultas acotadas a la entidad.** Las consultas dejan de usar `getEntity('invoice')`; en Multicompany con facturas compartidas, la cadena, el panel y los reintentos ya no ven facturas de otras entidades.
- **Respuestas parciales.** Un envío `ParcialmenteCorrecto` con líneas `Incorrecto` deja de darse por enviado (`isAEATResponseAccepted()`).
- **NIF bloqueado.** `VERIFACTU_HOLDER_NIF` no puede cambiarse en cuanto la entidad tiene su primera huella. Si tenías que corregirlo, hazlo antes de actualizar o valora las consecuencias con quien lleve tu fiscalidad; el módulo no ofrece un desbloqueo.
- **Certificados.** La extracción usa primero las funciones nativas de PHP, reintenta con `-legacy` bajo OpenSSL 3, pasa la contraseña por variable de entorno y ya no hace `die()` en caso de fallo. Los certificados fuera de su periodo de validez se rechazan con mensaje propio.
- **Identidad del sistema.** `IdSistemaInformatico` y `NombreSistemaInformatico` se leen de la declaración responsable (`DV` y `Dolibarr Verifactu Module`), y `Version` transmite la versión del módulo en lugar de `DOL_VERSION`.
- **Proformas.** Quedan excluidas de todas las vías de envío (`isVerifactuApplicableInvoice()`).
- **Tipo rectificativo.** Abonos y facturas de sustitución se almacenan como `R1`, que es lo que ya se transmitía; la pestaña VeriFactu deja de mostrar `R2`.
- **Informe de pagos y QR en PHP 8.** Se corrigen el error fatal de `beforePDFCreation()` con `pdf_paiement`/`pdf_paiement_fourn` y el `TypeError` de `QRGenerator::renderQrCode()`.

### A 2.1.0

- **PostgreSQL: la cadena no se construía.** `getLastInvoiceHash()` fallaba en silencio y cada factura se encadenaba como primer registro. Tras actualizar, el README recomienda **revisar los registros ya enviados** desde una instalación PostgreSQL, porque pueden haberse remitido como primer registro de cadena. Un error de lectura de la cadena lanza ahora la excepción `VERIFACTU_CHAIN_READ_FAILED` y la factura no se envía.
- **Bloqueo de la cadena.** Nueva constante opcional `VERIFACTU_CHAIN_LOCK_TIMEOUT` (30 segundos por defecto). Si tienes muchas validaciones simultáneas y ves el mensaje `VERIFACTU_CHAIN_LOCK_BUSY`, puedes subirla desde Inicio > Configuración > Otros.
- **Envío directo desactivado.** Con `VERIFACTU_DIRECT_CALL_ON_VALIDATE` a 0, la validación ya no termina sin rastro: la factura queda «Pendiente» con `verifactu_error = PENDING_SUBMISSION` e `Incidencia = S`, y la tarea programada la envía. Si tenías ese indicador desactivado, asegúrate de que el lanzador del cron funciona.
- **Identidad recortada.** Si `conf/declaracion_responsable.conf.php` tiene valores más largos que el esquema, el recorte se registra como error en el log en lugar de hacerse en silencio.
- **Panel principal** sin `MONTH()`/`YEAR()`.

### A 2.2.0

- **Un NIF por entidad.** Al guardar la configuración se rechaza un NIF que ya use otra entidad con VeriFactu activo (`getVerifactuEntityWithSameTaxId()`). Si tu instalación Multicompany ya tenía el mismo NIF en dos entidades, la pantalla de configuración de ambas mostrará el error al intentar guardar; no bloquea envíos.
- **Numeración compartida.** La pantalla avisa si Multicompany comparte la numeración de facturas.
- **Anulación como primer registro.** Una anulación sin registro anterior se marca explícitamente como primer registro (`setAsFirstInChain()`) en vez de fallar con un error genérico.
- No se incorpora la unicidad de la huella del certificado entre entidades: un certificado de representante puede firmar por varios obligados.

### A 2.2.1

- Corrige el error 500 de la página principal (`verifactuindex.php`) en instalaciones que venían de la 2.1.0 o 2.2.0: faltaba incluir `core/lib/date.lib.php` para `dol_get_first_day()` y `dol_get_last_day()`. La validación, el envío y la pestaña VeriFactu no estaban afectados. La suite de regresión incorpora `tests/DashboardDependenciesTest.php` para que no vuelva a ocurrir.

### A 2.2.2

- **API REST.** `POST …/verifactuapi/toProduction` y `…/toTest` exigen ahora la clave de API (`DOLAPIKEY`) de un administrador. Si algún script o integración los llamaba sin clave, recibirá 401 y dejará de cambiar el entorno: añade la cabecera con la clave de un usuario administrador. `GET …/verifactuapi/integrity` no cambia. Véase [[API REST|API-REST]].
- **Excepciones.** Una baja o una subsanación que termina en excepción ya no borra la huella ni el CSV del alta, y la factura conserva su estado «Enviada»; el error queda en `verifactu_error` y basta con repetir la operación. Si alguna factura perdió su huella con una versión anterior por este motivo, «Verificar Estado en AEAT» recupera la que tiene registrada la AEAT (véase [[Consulta a la AEAT|Consulta-a-la-AEAT]]).
- **Idiomas.** Desaparecen los mensajes que se mostraban como claves en mayúsculas. No hace falta reactivar el módulo para esto: Dolibarr lee los ficheros de idioma en cada petición.

### A 2.2.3

- **Fecha de operación.** Hay una opción nueva, **Transmitir la fecha de operación**, desactivada por defecto. Mientras no se active, el registro sale igual que antes. Al activarla se habilita también `INVOICE_POINTOFTAX_DATE`, de modo que la factura muestra el campo de fecha «Impuestos».
- **Presentación.** Los bloques fiscales de la ficha pasan a estar plegados y las columnas de detalle fiscal dejan de aparecer marcadas en los listados: siguen disponibles en el selector de columnas. Una instalación que actualiza recibe estos ajustes sobre los campos ya creados (`alignExtraFieldDisplay()`), respetando los que el administrador haya cambiado por su cuenta. El distintivo de estado junto al número de factura se gobierna con `VERIFACTU_STATUS_BADGE_ON_REF`.

### A 2.2.4

- **PDF.** Si la plantilla produce un documento que el parser de TCPDF no consigue reimportar, el módulo reintenta sobre una copia normalizada con Ghostscript o `qpdf` y, si no lo consigue, avisa en pantalla en lugar de entregar la factura sin QR. La opción `VERIFACTU_PDF_NORMALIZER` gobierna ese reintento: vacía busca el binario, una ruta fija cuál usar y `off` lo desactiva. No añade dependencias al módulo.
- **Facturas anteriores a la instalación del módulo.** Los cuatro campos fiscales se resuelven de la factura, del cliente y de los valores por defecto del módulo, por ese orden. Antes solo se leía la factura, así que las facturas creadas antes de instalar el módulo se rechazaban con el código 4102. Conviene dejar puestos los **valores por defecto** en la configuración antes de enviar facturas antiguas: si no hay nada configurado en ninguno de los tres sitios se transmite `S1` y se avisa en pantalla.
- **Listado de facturas.** La columna «Estado» de Dolibarr vuelve a mostrar el estado real en Dolibarr 20 y posteriores. Es presentación: no cambia ningún dato almacenado.

### Desde versiones muy antiguas (1.0.1 a 1.0.3)

- **1.0.3** ajustó las fechas de paso automático a producción a 2027 (sociedades 1 de enero, autónomos 1 de julio) y configuró los datos del productor de la declaración responsable.
- **1.0.4** añadió la caché local de esquemas WSDL/XSD (pulsa «Descargar/Actualizar esquemas» tras actualizar), la limpieza de PEM antiguos al subir un certificado nuevo (`deleteExistingPemFiles()`), la visualización de la caducidad del certificado, la validación masiva con transacciones independientes y el orden cronológico de fechas.
- Las etiquetas de los campos adicionales de instalaciones originales (en castellano, por ejemplo `verifactu_ESTADO`) siguen traducidas gracias a los alias que conserva `langs/es_ES/verifactu.lang` bajo «Legacy key aliases».

## Volver a una versión anterior

Volver atrás es restaurar la carpeta y la copia de la base de datos tomadas antes de actualizar. Ten en cuenta:

- Los registros enviados a la AEAT con la versión nueva ya llevan su número de `Version`; no se pueden «desenviar».
- Si la versión nueva creó campos adicionales o constantes que la antigua no conoce, no molestan, pero tampoco se usan.
- Si actualizaste desde una versión anterior a la 2.1.0 en PostgreSQL y validaste facturas con la 2.1.0 o posterior, la cadena de esas facturas es correcta; volver a la versión antigua volvería a romperla en el siguiente envío.

## Véase también

- [[Instalación|Instalacion]]
- [[Historial de versiones|Historial-de-versiones]]
- [[Publicación de versiones|Publicacion-de-versiones]]
- [[Configuración|Configuracion]]
- [[Encadenamiento y huella|Encadenamiento-y-huella]]
- [[Multiempresa|Multiempresa]]
- [[Suite de pruebas|Suite-de-pruebas]]
