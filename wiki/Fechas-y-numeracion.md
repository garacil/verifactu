# Fechas y numeración

La cadena VeriFactu de una entidad es única y secuencial, y la AEAT identifica cada registro por NIF del emisor, número de serie y fecha de expedición. De ahí que el módulo imponga dos reglas sobre las facturas de Dolibarr: la fecha de una factura nueva no puede ser anterior a la de la última factura ya encadenada, y la referencia definitiva debe cumplir el formato que admite el esquema. Esta página explica cómo se aplican ambas reglas, qué papel juega la opción `FAC_FORCE_DATE_VALIDATION` del núcleo y qué significan las referencias provisionales `PROV`.

## La regla cronológica

Todas las series de numeración de una entidad y un entorno comparten la misma cadena, y `getLastInvoiceHash()` devuelve el último registro por orden de inserción (`rowid`), sin distinguir series (véase [[Encadenamiento y huella|Encadenamiento-y-huella]]). Por eso la comprobación de fechas se hace contra **la última factura encadenada de la entidad**, sea de la serie que sea:

| Vía de validación | Fecha mínima que se exige | Dónde se aplica |
|---|---|---|
| Ficha de la factura (individual) | `max(hoy, fecha de la última encadenada)` | `doActions()`, contexto `invoicecard`, acción `valid` |
| Validación masiva | Fecha de la última encadenada, que avanza con cada factura del lote | `doActions()`, contextos `invoicelist` e `invoicelistverifactu` |

La comparación se hace **por día**, sin hora: la fecha de la factura se normaliza con `dol_mktime(0, 0, 0, mes, día, año)` antes de compararla. Una factura con la misma fecha que la última encadenada no necesita ajuste.

El núcleo añade su propia comprobación: el módulo activa al instalarse `INVOICE_CHECK_POSTERIOR_DATE = 1`, con lo que `Facture::validate()` y la ficha de factura llaman a `willBeLastOfSameType()` y rechazan con `ErrorInvoiceIsNotLastOfSameType` una factura cuya fecha sea anterior a la última validada **del mismo tipo**. Con esa misma constante, la acción masiva estándar de Dolibarr ordena las facturas seleccionadas por `datef` antes de validarlas. El ajuste que hace el módulo antes de validar evita que esa comprobación falle.

## Ajuste de la fecha al validar individualmente

Cuando la ficha recibe `action=valid` (el paso que abre el diálogo de confirmación) y la referencia contiene `PROV`, `doActions()` ejecuta:

1. Calcula «hoy» con `dol_now()` normalizado a las 00:00.
2. Obtiene la última factura encadenada con `getLastInvoiceHash()`; si su fecha es posterior a hoy, esa fecha pasa a ser el mínimo.
3. Si la fecha de la factura, normalizada a día, **difiere** del mínimo, asigna el mínimo a `$object->date` y lo escribe en `llx_facture.datef` con `setValueFrom('datef', $this->db->idate($minAllowedDate))`.
4. Añade al texto de confirmación el aviso `VERIFACTU_SINGLE_VALIDATE_DATE_ADJUSTED`, con la fecha original, la nueva y la referencia de la última factura encadenada.

Consecuencias prácticas:

- La fecha se ajusta tanto si era anterior como si era **posterior** al mínimo: una factura en borrador fechada en el futuro se traerá a hoy (o a la última fecha encadenada, si es mayor). La condición es `!=`, no `<`.
- El ajuste se escribe en la base de datos **al abrir el diálogo**. Si se cancela la validación, la fecha ya ha cambiado; el borrador sigue siendo editable y se puede volver a fechar, pero al validar se repetirá el ajuste.
- El resultado coincide con la política que describe el README desde la 1.0.4: «Ajusta automáticamente la fecha a `max(hoy, última_factura_validada)`».

## `FAC_FORCE_DATE_VALIDATION`

Es una opción del núcleo de Dolibarr («Forzar la fecha de la factura a la fecha de validación»). Dentro de `Facture::validate()`, cuando está activa y la referencia es provisional, el núcleo asigna `$this->date = dol_now()`, recalcula la fecha límite de pago y escribe `datef` con ese valor. El módulo interviene sobre ella en tres sitios:

| Dónde | Qué hace | Efecto |
|---|---|---|
| `core/modules/modVerifactu.class.php` (`$this->const`) | Al activar el módulo crea `FAC_FORCE_DATE_VALIDATION = '1'` | Valor guardado en `llx_const` para la entidad |
| `doActions()` en la ficha de factura | Pone la constante a `false` en memoria en cada petición de la ficha, y a `true` solo cuando `action=valid` y la referencia contiene `PROV` | En la petición que confirma la validación (`confirm_valid`) la constante está a `false`, de modo que el núcleo respeta la fecha ya ajustada por el módulo |
| `doActions()` en la validación masiva | Pone la constante a `true` en memoria antes de cada `validate()` | El núcleo fija la fecha de la factura en el momento de la validación |
| `printCommonFooter()` | En la página de configuración de facturas (`/admin/facture.php`) deshabilita con JavaScript el desplegable `forcedate` | El ajuste no se puede cambiar desde esa pantalla (la condición compara `$_SERVER['PHP_SELF']` con `/admin/facture.php` de forma exacta) |

Con esto, la fecha efectiva de una factura validada desde su ficha es el mínimo calculado por el módulo, y la de una factura validada en lote es la del momento de la validación, que en condiciones normales es igual o posterior a ese mínimo. En cualquier otra vía que llame a `validate()` sin pasar por la ficha (por ejemplo TakePOS o la API REST de Dolibarr), rige el valor guardado (`1`) y el núcleo fecha la factura en el momento de validarla.

> **Importante:** la fecha de expedición que se transmite es la que tiene la factura cuando el trigger `BILL_VALIDATE` construye el registro (`date('d-m-Y', $facture->date)`), y es la misma que la cola de reintentos usa después: `processInvoiceWithConnectionError()` conserva expresamente la fecha original y solo cambia la fecha de envío.

## Referencias provisionales y `getNextNumRef()`

Un borrador de factura tiene en Dolibarr una referencia provisional con la forma `(PROV{id})`. El módulo detecta esa condición de dos maneras, según el punto del código: `substr($object->ref, 1, 4) == 'PROV'` (trigger y ficha) o `strpos($object->ref, 'PROV') !== false` (restricciones y listados).

La referencia definitiva la calcula el módulo de numeración configurado en Dolibarr (`FACTURE_ADDON`, con sus máscaras) a través de `Facture::getNextNumRef($thirdparty)`. El módulo VeriFactu la consulta en dos momentos:

1. En la ficha, al abrir el diálogo de confirmación, para mostrarla en el texto `ConfirmValidateBillVerifactu` («¿Validar factura **%s**?»).
2. En el trigger `BILL_VALIDATE`, antes de cualquier otra operación, para validarla:

| Comprobación | Regla | Mensaje |
|---|---|---|
| Espacios | No puede contener espacios | `VERIFACTU_INVOICE_REF_CONTAINS_SPACES`: «La máscara de numeración contiene espacios. Modifíquela para continuar.» |
| Caracteres | Debe cumplir `^[a-zA-Z0-9\-_\/]+$` (letras, cifras, guion, guion bajo y barra) | `VERIFACTU_INVOICE_REF_NOT_ALPHANUMERIC`: «Referencia "%s" no válida. Solo caracteres alfanuméricos, guiones (-), guiones bajos (_) y barras (/).» |
| Longitud | Máximo 60 caracteres | `VERIFACTU_INVOICE_REF_TOO_LONG`: «Referencia "%s" demasiado larga (%s caracteres). Máximo 60 caracteres.» |

Si alguna falla, el trigger devuelve `-1` y la validación se aborta sin enviar nada. Como la comprobación se hace sobre la referencia **que se va a asignar**, el problema suele estar en la máscara de numeración, no en la factura: revisa la máscara en la configuración del módulo de facturas de Dolibarr.

Cuando la AEAT rechaza el registro, el trigger devuelve la factura a borrador escribiendo `ref = '(PROV{id})'`; la referencia definitiva que el núcleo había asignado se descarta. Con los módulos de numeración estándar de Dolibarr, que calculan el siguiente número a partir de las referencias existentes, ese número se reutiliza en la siguiente validación correcta.

## Qué implica para las series de numeración

- **Una cadena, varias series.** Puedes mantener series distintas (por ejemplo, un prefijo para facturas y otro para abonos, o máscaras por año); todas se encadenan en la misma secuencia de huellas y todas se comparan con la misma «última fecha encadenada». Una factura de una serie con fecha anterior a la última factura de otra serie se ajustará.
- **Formato de la máscara.** Evita espacios y caracteres fuera de `A-Z a-z 0-9 - _ /` en las máscaras de `FACTURE_ADDON`; el trigger rechazará la validación en caso contrario.
- **Series y tipos de factura.** El tipo VeriFactu (`F1`, `F2`, `R1`, `R5`) no depende de la serie sino del tipo de factura de Dolibarr y del origen (TakePOS o tercero con factura simplificada); véase [[Tipos de factura|Tipos-de-factura]].
- **Rectificativas.** El bloque `FacturasRectificadas` de una factura de sustitución toma número y fecha de la factura origen (`fk_facture_source`), formateada con `dol_print_date($staticInvoice->date, '%d-%m-%Y')`; véase [[Facturas rectificativas|Facturas-rectificativas]].
- **Numeración compartida en Multiempresa.** Si Multiempresa comparte la numeración de facturas entre entidades, dos obligados tributarios tomarían su serie del mismo contador. La pantalla de configuración lo advierte desde la 2.2.0 con `VERIFACTU_SHARED_INVOICE_NUMBERING_WARNING`; véase [[Multiempresa|Multiempresa]].

## Fechas que viajan en el registro y en la respuesta

| Dato | Formato | Dónde se guarda |
|---|---|---|
| `FechaExpedicionFactura` (fecha de la factura) | `dd-mm-aaaa` (`date('d-m-Y', $facture->date)`) | Forma parte de la huella; la AEAT la devuelve en `RespuestaLinea.IDFactura` |
| `FechaHoraHusoGenRegistro` (generación del registro) | ISO 8601 con desplazamiento (`Y-m-d\TH:i:sP`) | `verifactu_fecha_hora_generacion` |
| Fecha de factura confirmada por la AEAT | Se convierte de `dd-mm-aaaa` a `aaaa-mm-dd` | `verifactu_fecha_factura` («Fecha de emisión») |
| Última modificación VeriFactu | `dol_now()` | `verifactu_ultimafecha_modificacion` |
| Fecha de validación de Dolibarr | `date_valid` | Tabla `llx_facture`; se anula al revertir a borrador |

La fecha del registro anterior que se incluye en `Encadenamiento.RegistroAnterior` se formatea en PHP a partir de `llx_facture.datef` con el mismo `d-m-Y`, para que coincida con la que se transmitió en su día (véase [[Encadenamiento y huella|Encadenamiento-y-huella]]).

## Casos frecuentes

| Situación | Qué ocurre |
|---|---|
| Borrador con fecha de la semana pasada, última encadenada de ayer | Al abrir el diálogo la fecha pasa a hoy y se muestra el aviso de ajuste |
| Borrador fechado mañana | Se ajusta a hoy (o a la última encadenada si es posterior) |
| Lote de facturas con fechas de varios días atrás | Diálogo «Ajuste de fechas necesario»; tras confirmar, se validan por orden de fecha (véase [[Validación masiva|Validacion-masiva]]) |
| Factura de TakePOS | No pasa por la ficha; el núcleo aplica `FAC_FORCE_DATE_VALIDATION = 1` y la fecha es la del momento de validar; véase [[TakePOS|TakePOS]] |
| Máscara de numeración con espacio | La validación se aborta con `VERIFACTU_INVOICE_REF_CONTAINS_SPACES` hasta corregir la máscara |
| Envío pendiente que se reintenta días después | Se transmite con la fecha de expedición original; solo la fecha de envío es posterior, con `Incidencia = 'S'` |

## Véase también

- [[Flujo de validación y envío|Flujo-de-validacion-y-envio]]
- [[Validación masiva|Validacion-masiva]]
- [[Encadenamiento y huella|Encadenamiento-y-huella]]
- [[Multiempresa|Multiempresa]]
- [[Tipos de factura|Tipos-de-factura]]
- [[Facturas rectificativas|Facturas-rectificativas]]
- [[Reintentos y envíos pendientes|Reintentos-y-envios-pendientes]]
- [[TakePOS|TakePOS]]
- [[Configuración|Configuracion]]
