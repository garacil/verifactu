# Campos adicionales

El módulo no crea tablas propias: toda la información VeriFactu de una factura (estado, huella, CSV, respuesta de la AEAT, tipo de factura, parámetros fiscales) se guarda en campos adicionales de Dolibarr con el prefijo `verifactu_`, en `llx_facture_extrafields`, y la configuración fiscal por cliente en `llx_societe_extrafields`. Los campos se crean al activar el módulo (`modVerifactu::init()` en `core/modules/modVerifactu.class.php`) con `ExtraFields::addExtraField()`, están protegidos frente al borrado desde la administración de campos adicionales y los escriben el trigger de facturas, las funciones de envío, anulación y consulta, y la cola de reintentos. Esta página los enumera todos, indica quién escribe cada uno y en qué momento, y termina con consultas SQL de solo lectura para revisarlos.

## Atributos comunes

Todos los campos comparten, en su definición:

| Atributo | Valor |
|---|---|
| Posición | A partir de 1000, en el orden en que se enumeran más abajo |
| Permiso | `($user->rights->verifactu->manage \|\| $user->admin)` |
| Siempre editable | Sí (`alwayseditable = 1`) |
| Fichero de idioma | `verifactu@verifactu` |
| Condición de activación | `$conf->verifactu->enabled` |
| Único / obligatorio | No |

La visibilidad (`list`) es la que decide dónde aparece cada campo. Los valores usados por el módulo significan, según los trata la plantilla `core/tpl/extrafields_view.tpl.php` de Dolibarr:

| Visibilidad | Dónde se muestra | Edición desde la ficha |
|---|---|---|
| 0 | Nunca | No |
| 1 | Listados y todos los formularios (crear, editar, ver) | Sí |
| 4 (3 en Dolibarr anterior a la 12) | Listados y ficha (no en el formulario de creación) | Sí (icono de lápiz) |
| 5 | Listados y ficha | No |

Al activarse el módulo también se ejecuta una actualización sobre las facturas ya existentes de la entidad: `verifactu_estado` pasa a «No Enviada» en todas las que lo tengan a `NULL` (`UPDATE llx_facture_extrafields SET verifactu_estado = '<badge>' WHERE verifactu_estado IS NULL AND fk_object IN (SELECT rowid FROM llx_facture WHERE entity = <entidad>)`). Las facturas de otras entidades no se tocan.

## Campos de la factura (`llx_facture_extrafields`)

### Bloque «Datos VeriFactu» (solo lectura)

| Clave | Tipo (tamaño) | Etiqueta (es_ES) | Visibilidad | Contenido |
|---|---|---|---|---|
| `verifactu_inicio_separador1` | `separate` | Separador «Datos VeriFactu» (`verifactu_SECTION_SEPARATOR1`) | 5 | Cabecera de sección |
| `verifactu_estado` | `varchar` (255) | Estado | 5 | HTML de un badge de estado (véase la tabla de estados) o, en algunos errores de anulación, un texto plano |
| `verifactu_error` | `text` (500) | Error | 5 | Último error o marcador interno; vacío o `NULL` cuando el último envío fue correcto |
| `verifactu_csv_factura` | `text` (255) | Código CSV | 5 | `CSV` devuelto por la AEAT en el último envío aceptado (alta, modificación o baja) |
| `verifactu_id_factura` | `text` (255) | ID en VeriFactu | 5 | JSON `{"IDEmisorFactura":"…","NumSerieFactura":"…","FechaExpedicionFactura":"dd-mm-aaaa"}` de `RespuestaLinea.IDFactura` |
| `verifactu_fecha_factura` | `date` | Fecha de emisión | 5 | `FechaExpedicionFactura` de la respuesta, convertida a `aaaa-mm-dd` |
| `verifactu_ultima_salida` | `text` (255) | Última respuesta | 5 | Resumen `Status: … \| CSV: … \| Sent: … \| Record: … \| Error Code: … \| Error: …` (`buildVerifactuOutputSummary()`); JSON completo de la respuesta en las anulaciones; textos de la comprobación previa y de la verificación de estado |
| `verifactu_ultimafecha_modificacion` | `datetime` | Última modificación | 5 | Fecha y hora de la última escritura del módulo |
| `verifactu_huella` | `text` (255) | Huella digital | 5 | SHA-256 en mayúsculas (64 caracteres hexadecimales) del registro de alta, o la huella que devuelve la AEAT al cotejar |
| `verifactu_fecha_hora_generacion` | `varchar` (50) | Fecha/hora de huella | 5 | `FechaHoraHusoGenRegistro` (ISO 8601, por ejemplo `2026-03-12T10:15:30+01:00`); en anulaciones, `TimestampPresentacion` de la AEAT |
| `verifactu_entorno` | `select` | Entorno (pruebas/producción) | 5 | `test` («Test environment») o `production` («Production environment») |
| `verifactu_modo` | `varchar` (255) | Modo de operación | 5 | `<span class="badge  badge-primary badge-status" attr-status="Mode">verifactu</span>` (valor de `VERIFACTU_MODE`) |
| `verifactu_payload` | `text` (50000) | Datos XML | 0 | `json_encode()` del array `RegFactuSistemaFacturacion` enviado en el último alta o modificación aceptada |

### Bloque «Campos fiscales» (editables)

| Clave | Tipo | Etiqueta (es_ES) | Valor por defecto | Opciones | Visibilidad |
|---|---|---|---|---|---|
| `verifactu_inicio_separador2` | `separate` | Separador «CAMPOS FISCALES ⚠️» (`verifactu_SECTION_SEPARATOR2`) | — | — | 4 |
| `verifactu_factura_tipo` | `select` | Tipo de factura | `F1` | `$invoiceTypes`: F1, F2, F3, R1, R2, R3, R4, R5 | 4 |
| `verifactu_impuesto` | `select` | Impuesto | `01` | `$taxTypes`: 01, 02, 03, 05 | 4 |
| `verifactu_clave_regimen` | `select` | Clave Régimen | `01` | `$regimeKeys`: 01 a 20 | 4 |
| `verifactu_calificacion_operacion` | `select` | Calificación Operación | vacío | `$operationQualifications`: vacío (Automático), S1, S2, N1, N2 | 4 |
| `verifactu_operacion_exenta` | `select` | Operación Exenta | vacío | `$exemptOperations`: vacío (Automático), E1 a E6 | 4 |
| `verifactu_incidencia` | `select` | Incidencia | `N` | `$verifactuIncidence`: N, S | 4 |

Las listas de opciones proceden de `lib/verifactu-types.array.php`; sus textos completos están en [[Tipos de factura|Tipos-de-factura]] y [[Desglose de impuestos|Desglose-de-impuestos]]. El separador de esta sección lleva un tooltip que recuerda que impuesto, clave de régimen, calificación y exención son responsabilidad de la empresa.

## Valores de `verifactu_estado`

Salvo excepciones, el estado es un fragmento HTML con esta forma, donde el texto se traduce en el idioma del usuario que provoca la escritura:

```html
<div class="center"><span class="badge badge-status4 classfortooltip badge-status" attr-status="Enviada">Enviada</span></div>
```

| Texto (es_ES) | Clave de idioma | Clase | Quién lo escribe | Cuándo |
|---|---|---|---|---|
| No Enviada | `VERIFACTU_STATUS_NOT_SEND` | `badge-status8` | `billCreate()`; `init()` para facturas previas | Al crear la factura |
| Enviada | `VERIFACTU_STATUS_SEND` | `badge-status4` | `processInvoiceSendResponse()`, `execVERIFACTUCall()` (comprobación previa), `verificarEstadoFacturaAEAT()` | Alta o modificación aceptada; factura ya existente en la AEAT con los mismos importes |
| Parcial | `VERIFACTU_STATUS_SEND_PARTIAL` | `badge-status1` | `processInvoiceSendResponse()` | `EstadoEnvio` = `ParcialmenteCorrecto` con todas las líneas aceptadas |
| Error | `VERIFACTU_STATUS_ERROR` | `badge-status8` | `processInvoiceSendResponse()`, `execVERIFACTUCall()` (excepciones) | Rechazo de la AEAT, sin respuesta o excepción. Desde la 2.2.2 una excepción no pone esta insignia sobre una factura que ya tiene un registro aceptado (con huella): conserva su estado y solo se anota el error |
| Pendiente | `VERIFACTU_STATUS_PENDING_SERVICE` | `badge-status1` | `handleInvoiceCreationOrSubsanation()`, `billValidate()` | Servicio no disponible (503); validación con envío directo desactivado |
| Sin Conexión | `VERIFACTU_STATUS_NO_INTERNET` | `badge-status1` | `billValidate()` | Sin conexión a Internet al validar |
| Enviada (Duplicada) | `VERIFACTU_STATUS_SENT_DUPLICATE` | `badge-status4` | `processInvoiceSendResponse()` | Error 3000 con importes coincidentes |
| Duplicada (Importes diferentes) | `VERIFACTU_STATUS_DUPLICATE_DIFF` | `badge-status1` | `processInvoiceSendResponse()` | Error 3000 con importes distintos |
| Anulada | `VERIFACTU_STATUS_ANULADA` | `badge-status6` | `processCancellationSuccess()`, `processCancellationError()` | Anulación aceptada o registro ya anulado en la AEAT |
| `Error` / `Incorrecto` / `Anulada` (texto plano, sin badge) | — | — | `processCancellationError()`, `processCancellationResponse()` | Anulación rechazada o sin respuesta |
| Vacío | — | — | `verificarEstadoFacturaAEAT()` | Verificación: la factura no existe en la AEAT |

> **Aviso:** varias comprobaciones del módulo se hacen buscando cadenas dentro de este HTML: `badge-status4`, `VERIFACTU_STATUS_SEND` o `Correcto` para «enviada» (pestaña), `Anulada` o `VERIFACTU_STATUS_ANULADA` para «anulada» (pestaña y listado), `Enviada`/`No Enviada` para filtrar el envío masivo, y `attr-status="Enviada"` (texto traducido) en `isInvoiceSentToVerifactu()`, que decide si se muestra el código QR. Como el texto se guarda traducido en el idioma del usuario que envió, una instalación con usuarios en distintos idiomas puede ver comportamientos distintos sobre la misma factura.

## Valores de `verifactu_error`

| Valor | Quién lo escribe | Significado |
|---|---|---|
| `NO_INTERNET_CONNECTION` | `billValidate()` | Validada sin conexión; pendiente de reintento |
| `SERVICE_UNAVAILABLE` | `handleInvoiceCreationOrSubsanation()` | La AEAT devolvió 503; pendiente de reintento |
| `PENDING_SUBMISSION` | `billValidate()` | Validada con el envío directo desactivado; pendiente de reintento |
| `Exception: <mensaje>` | `execVERIFACTUCall()` | Excepción local (certificado, validación del registro, bloqueo de cadena, error SOAP) |
| `Error in VeriFactu submission - Status: <EstadoEnvio> - Code: <código> - <descripción>` | `processInvoiceSendResponse()` | Rechazo de la AEAT |
| `No response received from VeriFactu server` | `processInvoiceSendResponse()` | Sin respuesta en un alta |
| Texto de `VERIFACTU_DUPLICATE_AMOUNTS_DIFFER_DETAIL` | `processInvoiceSendResponse()` | Duplicado con importes distintos |
| `Error in VERIFACTU cancellation - Status: … - Record Status: … - Code: … - Description: …` | `processCancellationError()` | Rechazo de una anulación |
| `No response received from server` | `processCancellationResponse()` | Sin respuesta en una anulación |
| Vacío o `NULL` | `saveVerifactuSuccessData()`, `clearConnectionError()`, duplicado coincidente, verificación | Último envío correcto |

La cola de reintentos (`VerifactuUtils::getInvoicesWithConnectionErrors()`) selecciona las facturas validadas, sin `PROV`, sin huella y cuyo `verifactu_error` contenga alguno de los tres marcadores; al reenviar con éxito, `clearConnectionError()` elimina el marcador con un `UPDATE` directo y deja `verifactu_incidencia` en `S`. Véase [[Reintentos y envíos pendientes|Reintentos-y-envios-pendientes]].

## Quién escribe cada campo y cuándo

| Momento | Función | Campos escritos |
|---|---|---|
| Creación de la factura (`BILL_CREATE`) | `billCreate()` | Vacía en memoria **todos** los campos adicionales de la factura (también los ajenos al módulo, para no arrastrar valores al clonar) y escribe `verifactu_estado` (No Enviada), `verifactu_impuesto`, `verifactu_clave_regimen`, `verifactu_calificacion_operacion`, `verifactu_operacion_exenta` y `verifactu_factura_tipo` |
| Validación sin conexión | `billValidate()` | `verifactu_estado`, `verifactu_error`, `verifactu_incidencia` = S |
| Validación con envío directo desactivado | `billValidate()` | `verifactu_estado`, `verifactu_error`, `verifactu_incidencia`, `verifactu_ultimafecha_modificacion` |
| Alta o modificación aceptada | `processInvoiceSendResponse()` → `saveVerifactuSuccessData()` | `verifactu_estado`, `verifactu_csv_factura`, `verifactu_payload`, `verifactu_entorno`, `verifactu_modo`, `verifactu_ultima_salida`, `verifactu_ultimafecha_modificacion`, `verifactu_id_factura`, `verifactu_fecha_factura`, `verifactu_huella`, `verifactu_fecha_hora_generacion`; `verifactu_error` vacío |
| Alta rechazada o sin respuesta | `processInvoiceSendResponse()` → `saveVerifactuErrorData()` | `verifactu_estado`, `verifactu_error`, `verifactu_ultima_salida`, `verifactu_ultimafecha_modificacion` |
| Excepción durante el envío | `execVERIFACTUCall()` → `buildVerifactuExceptionErrorData()` → `saveVerifactuErrorData()` | `verifactu_error`, `verifactu_ultima_salida`, `verifactu_ultimafecha_modificacion` y, solo si la factura no tiene huella, `verifactu_estado` (Error). `verifactu_csv_factura` y `verifactu_huella` no se tocan (hasta la 2.2.1 se vaciaban) |
| Servicio no disponible | `handleInvoiceCreationOrSubsanation()` | `verifactu_estado`, `verifactu_error`, `verifactu_ultimafecha_modificacion`, `verifactu_incidencia` = S |
| Error 3000 (duplicado) | `processInvoiceSendResponse()` | `verifactu_estado`, `verifactu_error`, `verifactu_ultimafecha_modificacion` y, si coincide, `verifactu_huella` |
| Comprobación previa: ya existe | `execVERIFACTUCall()` → `saveVerifactuSuccessData()` | `verifactu_estado`, `verifactu_ultima_salida`, `verifactu_ultimafecha_modificacion`, `verifactu_huella` |
| Anulación aceptada | `processCancellationSuccess()` | `verifactu_estado`, `verifactu_csv_factura`, `verifactu_error` vacío, `verifactu_id_factura`, `verifactu_fecha_hora_generacion`, `verifactu_ultimafecha_modificacion`, `verifactu_ultima_salida` |
| Anulación rechazada | `processCancellationError()` → `saveVerifactuErrorData()` | `verifactu_estado`, `verifactu_error`, `verifactu_ultima_salida`, `verifactu_ultimafecha_modificacion`, `verifactu_fecha_hora_generacion` |
| Verificar estado: no existe | `verificarEstadoFacturaAEAT()` | Vacía `verifactu_estado`, `verifactu_error`, `verifactu_ultima_salida`, `verifactu_id_factura`, `verifactu_huella`, `verifactu_ultimafecha_modificacion` |
| Verificar estado: coincide | `verificarEstadoFacturaAEAT()` → `saveVerifactuSuccessData()` | `verifactu_estado`, `verifactu_ultima_salida`, `verifactu_ultimafecha_modificacion`, `verifactu_huella` |
| Reintento correcto | `VerifactuUtils::clearConnectionError()` | `verifactu_error` (marcador eliminado); `verifactu_incidencia` se mantiene en S |

`saveVerifactuSuccessData()` y `saveVerifactuErrorData()` (`lib/functions/functions.response.php`) escriben con una conexión de base de datos independiente para que el resultado sobreviva a un `rollback` de la transacción de validación; si esa conexión falla, se recurre a `insertExtraFields()` sobre la conexión normal.

### Claves que el módulo escribe pero no define

Algunas funciones asignan claves de `array_options` que no corresponden a ningún campo adicional declarado; `insertExtraFields()` solo persiste los campos definidos, así que se ignoran:

- `options_verifactu_tipo_operacion` (`processCancellationSuccess()` y `processCancellationError()`).
- `options_verifactu_csv` y `options_verifactu_fecha_huella` (`verificarEstadoFacturaAEAT()` al no encontrar la factura). Por eso, en ese caso, `verifactu_csv_factura` y `verifactu_fecha_hora_generacion` conservan su valor.

## Campos del tercero (`llx_societe_extrafields`)

| Clave | Tipo | Etiqueta (es_ES) | Valor por defecto | Visibilidad | Uso |
|---|---|---|---|---|---|
| `verifactu_inicio_separador1` | `separate` | Separador «Datos VeriFactu» | — | 5 | Cabecera de sección |
| `verifactu_tipo_identificacion` | `select` | Tipo de Identificación | `02` | 1 | `IDType` del bloque `IDOtro` para clientes no españoles (`$identificationTypes`: 02 a 07) |
| `verifactu_impuesto` | `select` | Impuesto | vacío | 1 | Si tiene valor, sustituye al impuesto por defecto al crear facturas de este cliente |
| `verifactu_clave_regimen` | `select` | Clave Régimen | vacío | 1 | Ídem para la clave de régimen |
| `verifactu_calificacion_operacion` | `select` | Calificación Operación | vacío | 1 | Ídem para la calificación |
| `verifactu_operacion_exenta` | `select` | Operación Exenta | vacío | 1 | Ídem para la exención |
| `verifactu_factura_simplificada` | `boolean` | Cliente de factura simplificada (clave `verifactu_SIMPLIFIED_INVOICE_CUSTOMER`; hasta la 2.2.1 sin entrada en los ficheros de idioma, por lo que se veía la clave) | vacío | 1 | Si está marcado, las facturas del cliente se guardan como F2 (o R5), no llevan destinatario y no se exige NIF, NIF-IVA ni dirección al validar |

Los cuatro campos fiscales del tercero tienen prioridad sobre las constantes globales `VERIFACTU_DEFAULT_*` en `billCreate()`; cuando se aplican, el módulo avisa con un mensaje. El NIF (`idprof1`) y el NIF-IVA (`tva_intra`) del tercero no son campos adicionales, pero el módulo impide cambiarlos cuando el cliente tiene facturas con CSV y huella (`companyModify()`); véase [[Restricciones sobre facturas enviadas|Restricciones-sobre-facturas-enviadas]].

## Protección frente al borrado

El hook `updateSession()` de `class/actions_verifactu.class.php` intercepta las páginas de administración de campos adicionales `societe/admin/societe_extrafields.php`, `compta/facture/admin/facture_cust_extrafields.php` y `compta/facture/admin/invoice_cust_extrafields.php`: si la acción es `delete` y `attrname` empieza por `verifactu_`, muestra el error «No se puede eliminar este campo. Los campos 'verifactu_' son esenciales para el módulo.» (`VERIFACTU_CANNOT_DELETE_EXTRA_FIELD`) y redirige sin borrar.

La protección cubre solo el borrado desde esas páginas. Editar la definición (etiqueta, opciones, visibilidad) sigue siendo posible, y desactivar el módulo no elimina los campos ni sus datos (`modVerifactu::remove()` no ejecuta ninguna sentencia). El fichero de idioma incluye además alias de etiquetas antiguas en español (`verifactu_ESTADO`, `verifactu_CSV_Factura`, `verifactu_HUELLA`, etc.) para instalaciones cuyas definiciones se crearon con versiones anteriores del módulo original.

## En los listados

El listado del módulo (`views/list.facture.php`) incluye `core/tpl/extrafields_list_array_fields.tpl.php`, una variante de la plantilla de Dolibarr que solo añade a las columnas seleccionables los campos `verifactu_` con visibilidad distinta de 0 y marca por defecto únicamente `verifactu_estado` y `verifactu_csv_factura`; el resto se activa desde el selector de columnas. En el listado estándar de facturas de Dolibarr los campos se comportan según su visibilidad, sin esa preselección.

## Consultas SQL de solo lectura

Las consultas siguientes funcionan igual en MySQL/MariaDB y en PostgreSQL. Sustituye `llx_` por el prefijo de tablas de tu instalación y `1` por la entidad que quieras revisar. Como el estado se guarda en HTML, se identifica por la clase del badge.

Recuento de facturas por estado VeriFactu:

```sql
SELECT
  CASE
    WHEN fe.verifactu_estado LIKE '%badge-status4%' THEN 'Enviada'
    WHEN fe.verifactu_estado LIKE '%badge-status6%' THEN 'Anulada'
    WHEN fe.verifactu_estado LIKE '%badge-status1%' THEN 'Pendiente, parcial o duplicada'
    WHEN fe.verifactu_estado LIKE '%badge-status8%' AND COALESCE(fe.verifactu_error, '') <> '' THEN 'Error'
    WHEN fe.verifactu_estado LIKE '%badge-status8%' THEN 'No enviada'
    WHEN COALESCE(fe.verifactu_estado, '') = '' THEN 'Sin estado'
    ELSE 'Otro (texto plano)'
  END AS estado_verifactu,
  COUNT(*) AS facturas
FROM llx_facture f
JOIN llx_facture_extrafields fe ON fe.fk_object = f.rowid
WHERE f.entity = 1
GROUP BY 1
ORDER BY 2 DESC;
```

Facturas validadas con error y su detalle:

```sql
SELECT f.ref, f.datef, fe.verifactu_error, fe.verifactu_ultimafecha_modificacion
FROM llx_facture f
JOIN llx_facture_extrafields fe ON fe.fk_object = f.rowid
WHERE f.entity = 1
  AND f.fk_statut > 0
  AND fe.verifactu_estado LIKE '%badge-status8%'
  AND COALESCE(fe.verifactu_error, '') <> ''
ORDER BY fe.verifactu_ultimafecha_modificacion DESC;
```

Facturas en la cola de reintentos (mismo criterio que `getInvoicesWithConnectionErrors()`):

```sql
SELECT f.ref, f.datef, fe.verifactu_error, fe.verifactu_incidencia
FROM llx_facture f
JOIN llx_facture_extrafields fe ON fe.fk_object = f.rowid
WHERE f.entity = 1
  AND f.fk_statut > 0
  AND f.ref NOT LIKE '%PROV%'
  AND (fe.verifactu_error LIKE '%NO_INTERNET_CONNECTION%'
       OR fe.verifactu_error LIKE '%SERVICE_UNAVAILABLE%'
       OR fe.verifactu_error LIKE '%PENDING_SUBMISSION%')
  AND COALESCE(fe.verifactu_huella, '') = ''
ORDER BY f.datef, f.rowid;
```

Última huella de la cadena de producción (el registro con el que se encadenará el siguiente envío, mismo criterio que `getLastInvoiceHash()`):

```sql
SELECT f.ref, f.datef, fe.verifactu_huella, fe.verifactu_fecha_hora_generacion
FROM llx_facture f
JOIN llx_facture_extrafields fe ON fe.fk_object = f.rowid
WHERE f.entity = 1
  AND f.fk_statut > 0
  AND COALESCE(fe.verifactu_huella, '') <> ''
  AND fe.verifactu_entorno = 'production'
ORDER BY f.rowid DESC
LIMIT 1;
```

Facturas enviadas al entorno de pruebas (útil antes de pasar a producción):

```sql
SELECT f.ref, f.datef, fe.verifactu_csv_factura, fe.verifactu_fecha_hora_generacion
FROM llx_facture f
JOIN llx_facture_extrafields fe ON fe.fk_object = f.rowid
WHERE f.entity = 1
  AND fe.verifactu_entorno = 'test'
  AND COALESCE(fe.verifactu_huella, '') <> ''
ORDER BY f.datef, f.rowid;
```

Terceros marcados como clientes de factura simplificada y su configuración fiscal:

```sql
SELECT s.rowid, s.nom, s.country_id,
       se.verifactu_tipo_identificacion, se.verifactu_impuesto,
       se.verifactu_clave_regimen, se.verifactu_calificacion_operacion, se.verifactu_operacion_exenta
FROM llx_societe s
JOIN llx_societe_extrafields se ON se.fk_object = s.rowid
WHERE s.entity = 1
  AND se.verifactu_factura_simplificada = 1
ORDER BY s.nom;
```

> **Importante:** estas consultas son de lectura. No modifiques a mano `verifactu_huella`, `verifactu_estado` ni `verifactu_entorno`: la huella guardada es la que se usa para encadenar el siguiente registro (véase [[Encadenamiento y huella|Encadenamiento-y-huella]]) y un valor alterado rompe la cadena frente a la AEAT. Para reconciliar una factura usa «Verificar Estado en AEAT» en la pestaña VeriFactu.

## Véase también

- [[Pestaña VeriFactu de la factura|Pestana-VeriFactu-de-la-factura]]
- [[Tipos de factura|Tipos-de-factura]]
- [[Desglose de impuestos|Desglose-de-impuestos]]
- [[Encadenamiento y huella|Encadenamiento-y-huella]]
- [[Reintentos y envíos pendientes|Reintentos-y-envios-pendientes]]
- [[Respuestas de la AEAT|Respuestas-de-la-AEAT]]
- [[Restricciones sobre facturas enviadas|Restricciones-sobre-facturas-enviadas]]
- [[Multiempresa|Multiempresa]]
- [[Instalación|Instalacion]]
- [[Actualización|Actualizacion]]
