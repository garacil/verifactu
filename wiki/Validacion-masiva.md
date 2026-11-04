# Validación masiva

Cuando se seleccionan varias facturas en borrador en un listado y se aplica la acción masiva **Validar**, el módulo intercepta la acción estándar de Dolibarr y la sustituye por su propio procesamiento: una transacción independiente por factura, orden cronológico ascendente, detección de fechas anteriores a la última factura encadenada y mensajes de resumen propios. La interceptación vive en `ActionsVerifactu::doActions()` (`class/actions_verifactu.class.php`) y el diálogo de confirmación de fechas en `views/list.facture.php`. Se introdujo en la 1.0.4 y se mantiene en la 2.2.2.

## Por qué se intercepta

En la validación masiva del núcleo, un fallo en una factura (por ejemplo un rechazo de la AEAT) puede arrastrar a las demás del lote, incluidas las que ya se transmitieron: el comentario del código lo resume así: *«Dolibarr core wraps all invoices in a single transaction during mass validation. If any invoice fails, ALL invoices are rolled back, including those already sent to AEAT»*. El módulo garantiza, con independencia de la versión del núcleo, que cada factura se valida en su propia transacción, de modo que un fallo aislado no afecta al resto.

Además, la interceptación aporta lo que el núcleo no hace: ordenar el lote por fecha, comprobar cada fecha frente a la última factura encadenada en VeriFactu y ajustarla con confirmación previa. Esto es necesario porque todas las series de una entidad comparten una sola cadena (véase [[Encadenamiento y huella|Encadenamiento-y-huella]]).

## Dónde y cuándo actúa

El hook `doActions()` se ejecuta en los contextos `invoicelist` (listado estándar de facturas de cliente) e `invoicelistverifactu` (listado **Facturas emitidas** del módulo). Intercepta solo cuando se cumplen todas estas condiciones:

| Condición | Origen |
|---|---|
| `massaction == 'validate'` | Desplegable de acciones masivas |
| `confirmmassaction` no vacío | Botón de confirmación de la barra de acciones masivas |
| `toselect` no vacío | Casillas marcadas; si llega vacío se reconstruye desde el campo oculto `verifactu_toselect` (IDs separados por comas) |
| `$user->hasRight('facture', 'creer')` | Permiso de creación de facturas |

Si no se cumplen, devuelve `0` y Dolibarr sigue su curso normal. Si se cumplen, la función termina siempre con `return 1`, lo que anula el código estándar de `core/actions_massactions.inc.php`.

> **Nota:** en el listado del módulo (`views/list.facture.php`) la llamada a `selectMassAction()` está comentada, por lo que el desplegable estándar de acciones masivas no se muestra ahí; ese listado ofrece sus propios botones «Enviar Alta», «Enviar Modificación» y «Enviar Baja» (véase más abajo). El diálogo de ajuste de fechas se pinta únicamente en ese fichero; el listado estándar del núcleo no conoce la acción `verifactu_confirm_date_adjust`.

## Bloqueo con `STOCK_CALCULATE_ON_BILL`

Antes de nada, el hook replica la comprobación del núcleo: si el módulo de stock está activo y `STOCK_CALCULATE_ON_BILL` está configurado, la validación masiva no está permitida. Se muestra el mensaje estándar `ErrorMassValidationNotAllowedWhenStockIncreaseOnAction` y no se procesa nada. Cada factura debe validarse entonces desde su ficha, donde Dolibarr pide el almacén.

## Fase 1: carga y ordenación

1. Cada ID de `toselect` se carga con `Facture::fetch()`. Los que no se pueden cargar se acumulan como `VERIFACTU_MASS_VALIDATE_FETCH_ERROR` («No se pudo cargar la factura con ID %s») y se contarán como errores al final.
2. Las facturas cargadas se ordenan por fecha ascendente (`$a->date - $b->date`) y, a igual fecha, por `id` ascendente.
3. Se obtiene la última factura encadenada con `getLastInvoiceHash()`; su fecha (`dd-mm-aaaa`) se convierte a marca de tiempo con `dol_mktime()`.
4. Se cuentan las facturas cuya fecha (solo día, sin hora) es **anterior** a la de la última encadenada. Si no hay registro anterior, no puede haber conflicto.

## Fase 2: diálogo de confirmación cuando hay fechas anteriores

Si hay al menos una factura con fecha anterior y la petición no trae `verifactu_date_confirm`, el hook no valida nada: guarda los datos del conflicto en `$this->results` (que Dolibarr propaga a `$hookmanager->resArray`), cambia `$action` a `verifactu_confirm_date_adjust` y devuelve `1`.

| Clave en `resArray` | Contenido |
|---|---|
| `verifactu_date_conflict` | `true` |
| `verifactu_date_conflict_count` | Número de facturas que necesitan ajuste |
| `verifactu_date_conflict_min_date` | Fecha de la última factura encadenada |
| `verifactu_date_conflict_last_ref` | Referencia de esa factura |
| `verifactu_date_conflict_toselect` | IDs seleccionados, separados por comas |

`views/list.facture.php` detecta esa acción y muestra un `formconfirm` con el título `VERIFACTU_MASS_VALIDATE_DATE_CONFIRM_TITLE` («Ajuste de fechas necesario») y el mensaje `VERIFACTU_MASS_VALIDATE_DATE_CONFIRM_MSG`:

> Se han detectado **%s factura(s)** con fecha anterior al **%s** (última factura validada: %s).
> Las fechas se ajustarán automáticamente al mínimo necesario para cumplir el orden cronológico requerido por VeriFactu.
> ¿Desea continuar con la validación?

El formulario del diálogo reenvía cuatro campos ocultos: `massaction=validate`, `confirmmassaction=yes`, `verifactu_date_confirm=yes` y `verifactu_toselect` con los IDs. El campo `verifactu_toselect` existe porque `formconfirm` no admite arrays: sin él, las casillas marcadas se perderían al confirmar (corregido en la 1.0.4 para el envío masivo y reutilizado aquí). Si el usuario cancela, no se valida ninguna factura.

## Fase 3: una transacción por factura

Con la confirmación recibida (o sin conflictos), el hook recorre las facturas en el orden de la fase 1. Mantiene una **fecha mínima móvil** (`$rollingMinDate`) que arranca en la fecha de la última factura encadenada y avanza con cada validación correcta:

```text
para cada factura (ordenadas por fecha ASC, id ASC):
    si fecha(factura) < fechaMinimaMovil:
        fecha(factura) = fechaMinimaMovil        # setValueFrom('datef', ...)
        ajustadas++
    db->begin()
    $conf->global->FAC_FORCE_DATE_VALIDATION = true   # solo en memoria
    resultado = factura->validate(usuario)          # dispara BILL_VALIDATE
    si resultado > 0:  db->commit();  ok++;  fechaMinimaMovil = max(fechaMinimaMovil, fecha(factura))
    si resultado == 0: db->rollback(); errores++  # ya validada o estado no válido
    si resultado < 0:  db->rollback(); errores++  # fallo de datos o de VeriFactu
```

Puntos que conviene conocer:

- **`FAC_FORCE_DATE_VALIDATION` se activa en memoria** justo antes de cada `validate()`. En el núcleo (`Facture::validate()`), esa opción hace que una factura con referencia provisional reciba como fecha de factura el momento de la validación (`dol_now()`) y que `datef` se escriba con ese valor. En condiciones normales esa fecha es igual o posterior al mínimo calculado, con lo que el orden cronológico se respeta. Véase [[Fechas y numeración|Fechas-y-numeracion]].
- **El trigger `BILL_VALIDATE` se ejecuta dentro de cada `validate()`**, con todo lo descrito en [[Flujo de validación y envío|Flujo-de-validacion-y-envio]]: comprobaciones del tercero, envío, y reversión a borrador por conexión independiente si la AEAT rechaza el registro. El `rollback` del hook completa esa reversión; el mensaje de error de cada factura se toma de `$objecttmp->error` o `$objecttmp->errors`.
- **El tiempo de espera de la AEAT no se aplica** en la validación (`execVERIFACTUCall(..., false)` desde el trigger), pero el bloqueo de la cadena sí: cada envío espera al anterior, de modo que el lote se procesa en serie.
- **Antes de la primera factura, el trigger vacía la cola de pendientes** si hay conexión, como en cualquier validación.

## Mensajes de resumen

| Situación | Mensaje | Tipo |
|---|---|---|
| Al menos una factura validada | `RecordsModified` (núcleo) con el número | Información |
| Alguna fecha ajustada | `VERIFACTU_MASS_VALIDATE_DATES_ADJUSTED`: «Se ajustó la fecha de %s factura(s) para respetar el orden cronológico de VeriFactu.» | Aviso |
| Éxitos y errores mezclados | `VERIFACTU_MASS_VALIDATE_PARTIAL`: «Se validaron %s facturas correctamente, %s tuvieron errores. Las facturas correctas no se ven afectadas por los errores de otras.» | Aviso |
| Factura ya validada o en estado no válido | `VERIFACTU_MASS_VALIDATE_ALREADY_VALIDATED`: «La factura %s no puede validarse desde su estado actual» | Error |
| Factura que no se pudo cargar | `VERIFACTU_MASS_VALIDATE_FETCH_ERROR` | Error |
| Fallo en la validación o en el envío | `{ref}: {error de la factura}` | Error |
| Siempre | `VERIFACTU_MASS_VALIDATE_INFO`: «El módulo VeriFactu gestiona la validación masiva con transacciones independientes por factura para evitar inconsistencias con la AEAT.» | Información |

Los errores de detalle se muestran todos juntos con `setEventMessages(null, $errorDetails, 'errors')`.

## Límites y consideraciones prácticas

- El código no fija un máximo de facturas por lote: se procesan todas las seleccionadas en la página del listado.
- Cada factura implica al menos dos llamadas al servicio de la AEAT (consulta previa y alta) más el envío previo de las pendientes, todo dentro de una única petición HTTP. Con lotes grandes conviene tener en cuenta los tiempos de ejecución de PHP del servidor y validar en lotes moderados.
- Una factura rechazada por la AEAT queda en borrador con referencia `(PROV{id})`, estado «Error» y el motivo en `verifactu_error`; corrige el problema y vuelve a validarla.
- Si el servicio no está disponible (HTTP 503) o no hay conexión, las facturas se validan igualmente y quedan «Pendiente» o «Sin Conexión» para la cola de reintentos (véase [[Reintentos y envíos pendientes|Reintentos-y-envios-pendientes]]).
- Las facturas proforma se validan sin generar registro VeriFactu, también en lote.

## Envío masivo desde el listado del módulo

No debe confundirse con la validación masiva. El listado **Facturas emitidas** (`views/list.facture.php`) permite seleccionar facturas **ya validadas** y pulsar «Enviar Alta», «Enviar Modificación» o «Enviar Baja» (`EXECUTEVERIFACTU=Alta|Mod|Baja`). Antes de mostrar el diálogo de confirmación (`CONFIRM_SEND_TO_AEAT_TITLE` / `CONFIRM_SEND_TO_AEAT_MESSAGE`, con el número de facturas `NUMBER_INVOICES_SELECTED`):

- Para `Alta` se descartan en silencio las facturas cuyo estado ya contiene «Enviada» (y no «No Enviada»).
- Para `Baja` se añade el desplegable `tipo_anulacion` (`normal`, `rechazo_previo`, `sin_registro_previo`); véase [[Anulación de facturas|Anulacion-de-facturas]].

Al confirmar (`action=confirm_EXECUTEVERIFACTU`), cada factura se procesa con `execVERIFACTUCall($staticFacture, $actionVERIFACTU, false)` tras comprobar que está validada o cerrada (`INVALID_VERIFACTU_STATUS_TO_SEND`), que no está anulada en VeriFactu y que su referencia no contiene `PROV` (`VERIFACTU_VALIDATION_INVOICE_PROVISIONAL`). El tiempo de espera de la AEAT no se aplica porque es una acción explícita del usuario. Las casillas de las facturas anuladas aparecen deshabilitadas en el listado.

## Pruebas

`tests/MassValidateInterceptionTest.php` simula el entorno de Dolibarr y comprueba, entre otros casos: que los contextos distintos de listado no se interceptan; que una acción masiva sin confirmar pasa de largo; que tres facturas producen tres pares `begin`/`commit`; que un fallo en la segunda produce `begin`/`rollback` solo para ella; que las facturas se procesan por fecha ascendente; que un conflicto de fechas provoca el diálogo sin abrir transacciones; que tras confirmar se ajusta la fecha de las facturas anteriores y no la de las posteriores; que la fecha mínima avanza durante el lote; y que `verifactu_toselect` reconstruye la selección. Véase [[Suite de pruebas|Suite-de-pruebas]].

## Véase también

- [[Flujo de validación y envío|Flujo-de-validacion-y-envio]]
- [[Fechas y numeración|Fechas-y-numeracion]]
- [[Encadenamiento y huella|Encadenamiento-y-huella]]
- [[Reintentos y envíos pendientes|Reintentos-y-envios-pendientes]]
- [[Restricciones sobre facturas enviadas|Restricciones-sobre-facturas-enviadas]]
- [[Panel principal|Panel-principal]]
- [[Resolución de problemas|Resolucion-de-problemas]]
