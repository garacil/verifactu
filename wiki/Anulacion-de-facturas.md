# Anulación de facturas

La anulación es el registro con el que se comunica a la AEAT que un registro de alta anterior queda sin efecto. El módulo la envía como `RegistroAnulacion` dentro del mismo servicio `RegFactuSistemaFacturacion` que usa para las altas, con su propia huella y encadenada al último registro de la entidad. Se lanza desde la pestaña VeriFactu de la factura o desde el listado del módulo con el botón «Enviar Baja», que abre un diálogo para elegir entre tres tipos de anulación: normal, por rechazo previo y sin registro previo. Esta página describe qué se envía, cómo se encadena, qué estado queda en Dolibarr y en qué se diferencia de emitir una rectificativa.

## Desde dónde se anula

### Pestaña VeriFactu de la factura

En `views/tabVERIFACTU.facture.php`, el bloque «Acciones VeriFactu» muestra el botón «Enviar Baja» (`VERIFACTU_BUTTON_SENDTO_BAJA`) siempre que la factura pueda enviarse, es decir, cuando:

- está validada (`$object->statut > 0`);
- su referencia no contiene `PROV`;
- su estado VeriFactu no indica ya anulación (el texto guardado en `verifactu_estado` no contiene `Anulada` ni `VERIFACTU_STATUS_ANULADA`).

A diferencia de «Enviar Alta» y «Enviar Modificación», que se desactivan cuando la factura ya está enviada correctamente, «Enviar Baja» permanece activo (`$canSendBaja` no se desactiva nunca mientras `$canSend` sea cierto). También aparece en facturas que nunca se han enviado, porque uno de los tipos de anulación sirve precisamente para ese caso.

Al pulsar el botón se muestra un diálogo de confirmación (`Form::formconfirm`) con el título «Confirmar envío» y el texto «¿Enviar las facturas seleccionadas a VeriFactu?» (`CONFIRM_SEND_TO_AEAT_TITLE` y `CONFIRM_SEND_TO_AEAT_MESSAGE`), un desplegable `tipo_anulacion` con el valor `normal` preseleccionado y un recuadro informativo con la descripción de cada tipo. Al confirmar, la pestaña llama a `execVERIFACTUCall($object, 'Baja', false)`: el tercer parámetro indica que un envío pedido expresamente por el usuario no espera el tiempo de espera que la AEAT haya pedido (`TiempoEsperaEnvio`).

### Listado del módulo

En `views/list.facture.php` (menú «Facturas emitidas») se pueden seleccionar varias facturas y pulsar «Enviar Baja». El diálogo es el mismo, con el mismo desplegable `tipo_anulacion` aplicado a todas las seleccionadas. Antes de enviar cada una, el listado descarta las que no están validadas ni cerradas (`STATUS_VALIDATED` o `STATUS_CLOSED`, mensaje `INVALID_VERIFACTU_STATUS_TO_SEND`), las que ya constan como anuladas y las que llevan `PROV` en la referencia.

> **Nota:** el README describe la acción como «Anular en AEAT»; el texto real del botón en la interfaz es «Enviar Baja».

## Los tres tipos de anulación

El valor elegido en `tipo_anulacion` se lee en `execVERIFACTUCall()` (`GETPOST('tipo_anulacion', 'alpha') ?: 'normal'`) y llega a `createCancellationByType()` en `lib/functions/functions.cancellation.php`:

| Valor interno | Descripción en el fichero de idioma | Método de la biblioteca | Marca en el registro |
|---|---|---|---|
| `normal` | Anulación normal: para facturas registradas correctamente que necesitan cancelarse | `Cancellation::createNormal()` | Ninguna |
| `rechazo_previo` | Por rechazo previo: para facturas rechazadas previamente por VeriFactu | `Cancellation::createForPreviousRejection()` | `RechazoPrevio` = `S` |
| `sin_registro_previo` | Sin registro previo: para facturas no registradas previamente en VeriFactu | `Cancellation::createWithoutPreviousRecord()` | `SinRegistroPrevio` = `S` |

Las dos marcas son excluyentes: `setAsPreviousRejection(true)` elimina `SinRegistroPrevio` y `setWithoutPreviousRecord(true)` elimina `RechazoPrevio` (`lib/newfenix/src/Cancellation.php`). Un valor desconocido hace que `createCancellationByType()` devuelva `false` y la operación termine sin enviar nada.

## Qué se envía

`handleInvoiceCancellation()` construye el registro con estos datos:

| Elemento | Origen |
|---|---|
| `IDFactura.IDEmisorFacturaAnulada` | `VERIFACTU_HOLDER_NIF` (el NIF del obligado configurado) |
| `IDFactura.NumSerieFacturaAnulada` | `$facture->ref` |
| `IDFactura.FechaExpedicionFacturaAnulada` | `$facture->date` en formato `dd-mm-aaaa` |
| `IDVersion` | `1.0` |
| `FechaHoraHusoGenRegistro` | Fecha y hora de generación en ISO 8601 con zona `Europe/Madrid` |
| `TipoHuella` | `01` (SHA-256) |
| `Encadenamiento` | `RegistroAnterior` o `PrimerRegistro` (véase más abajo) |
| `SistemaInformatico` | Lo añade `Manager::sendCancellation()` a partir de `getSystemConfig()` |
| `RechazoPrevio` / `SinRegistroPrevio` | Según el tipo elegido |
| `Huella` | Calculada por `Cancellation::generateHash()` al obtener los datos |

La huella de una anulación se calcula sobre una cadena distinta de la de un alta:

```
IDEmisorFacturaAnulada=...&NumSerieFacturaAnulada=...&FechaExpedicionFacturaAnulada=...&Huella=<huella del registro anterior o vacío>&FechaHoraHusoGenRegistro=...
```

y se envuelve en la cabecera habitual:

```xml
<RegFactuSistemaFacturacion>
  <Cabecera>
    <IDVersion>1.0</IDVersion>
    <ObligadoEmision>
      <NombreRazon>Mi Empresa SL</NombreRazon>
      <NIF>B12345678</NIF>
    </ObligadoEmision>
  </Cabecera>
  <RegistroFactura>
    <RegistroAnulacion>
      <IDVersion>1.0</IDVersion>
      <IDFactura>
        <IDEmisorFacturaAnulada>B12345678</IDEmisorFacturaAnulada>
        <NumSerieFacturaAnulada>FA2603-0042</NumSerieFacturaAnulada>
        <FechaExpedicionFacturaAnulada>03-03-2026</FechaExpedicionFacturaAnulada>
      </IDFactura>
      <Encadenamiento>
        <RegistroAnterior>...</RegistroAnterior>
      </Encadenamiento>
      <SistemaInformatico>...</SistemaInformatico>
      <FechaHoraHusoGenRegistro>2026-03-12T10:15:30+01:00</FechaHoraHusoGenRegistro>
      <TipoHuella>01</TipoHuella>
      <Huella>...</Huella>
    </RegistroAnulacion>
  </RegistroFactura>
</RegFactuSistemaFacturacion>
```

Antes de llegar aquí, `execVERIFACTUCall()` aplica las mismas comprobaciones que a un alta: la factura no debe ser proforma, el certificado debe existir y su clave privada ser válida, el NIF y la razón social del obligado deben estar configurados, y se toma el bloqueo de la cadena (`acquireVerifactuChainLock()`); si el bloqueo no se obtiene en el tiempo configurado se lanza `VERIFACTU_CHAIN_LOCK_BUSY`. La comprobación previa de existencia en la AEAT (`preCheckInvoiceInAEAT()`) solo se hace para altas, no para bajas.

## Encadenamiento de la anulación

La anulación es un eslabón más de la cadena de la entidad. `handleInvoiceCancellation()` llama a `getLastInvoiceHash()` (`lib/functions/functions.hash.php`), que devuelve la huella, la referencia y la fecha de la última factura validada de la entidad y del entorno actual que tenga `verifactu_huella`:

- Si existe, `setChainLink()` rellena `Encadenamiento.RegistroAnterior` con `IDEmisorFactura`, `NumSerieFactura`, `FechaExpedicionFactura` y `Huella` de ese registro.
- Si no existe ningún registro previo, desde la versión 2.2.0 la anulación se marca como primer registro (`setAsFirstInChain()`, `Encadenamiento.PrimerRegistro` = `S`). Antes de esa versión el encadenamiento quedaba sin configurar y `Cancellation::validate()` fallaba con un error genérico. El esquema oficial declara `Encadenamiento` en `RegistroFacturacionAnulacionType` como una elección entre `PrimerRegistro` y `RegistroAnterior`, y el tipo `SinRegistroPrevio` existe justamente para anular un registro que nunca se remitió, que es el caso de un sistema que empieza a usar VeriFactu con una anulación (README, v2.2.0, PR #39). El test `tests/MultiEntityAndCancellationTest.php` comprueba que una anulación marcada como primer registro valida correctamente.

> **Aviso:** la huella del registro de anulación no se guarda en ningún campo adicional. `processCancellationSuccess()` conserva en `verifactu_huella` la huella del alta original, y `getLastInvoiceHash()` lee ese campo, de modo que el siguiente registro que emita el módulo se encadenará con la última huella de alta que exista en Dolibarr, no con la del registro de anulación. Tenlo en cuenta al conciliar la cadena con lo que la AEAT tiene registrado. Véase [[Encadenamiento y huella|Encadenamiento-y-huella]].

## Respuesta de la AEAT y estados resultantes

`processCancellationResponse()` aplica `isAEATResponseAccepted()`, que desde la 2.0.0 exige que el envío sea `Correcto` o que, siendo `ParcialmenteCorrecto`, todas las líneas estén en `Correcto` o `AceptadoConErrores`. Después de cada envío se registra `TiempoEsperaEnvio` con `registerAEATWaitTime()`.

### Anulación aceptada

`processCancellationSuccess()` guarda en la factura:

| Campo adicional | Valor |
|---|---|
| `verifactu_estado` | Badge `badge-status6` con el texto «Anulada» (clave `VERIFACTU_STATUS_ANULADA`, definida en los cinco idiomas desde la 2.2.2; antes el badge mostraba la clave en bruto) |
| `verifactu_csv_factura` | El `CSV` devuelto para la anulación (sustituye al del alta) |
| `verifactu_error` | Vacío |
| `verifactu_id_factura` | JSON con `IDEmisorFactura`, `NumSerieFactura` y `FechaExpedicionFactura` de `RespuestaLinea.IDFactura` |
| `verifactu_fecha_hora_generacion` | `DatosPresentacion.TimestampPresentacion` |
| `verifactu_ultimafecha_modificacion` | Fecha y hora actuales |
| `verifactu_ultima_salida` | La respuesta completa en JSON |

También intenta guardar `RespuestaLinea.Operacion.TipoOperacion` en `options_verifactu_tipo_operacion`, pero el módulo no define ese campo adicional, por lo que Dolibarr lo ignora al persistir. El guardado se hace con `insertExtraFields()` sobre la conexión normal, no con la conexión independiente que usan las altas.

### Anulación rechazada

`processCancellationError()` compone un mensaje `Error in VERIFACTU cancellation - Status: <EstadoEnvio> - Record Status: <EstadoRegistro> - Code: <CodigoErrorRegistro> - Description: <DescripcionErrorRegistro>` y, si la AEAT informa de un registro duplicado, añade `- Duplicate record (ID: ...) - Duplicate status: ...`. Guarda, con la conexión independiente de `saveVerifactuErrorData()`:

| Campo adicional | Valor |
|---|---|
| `verifactu_estado` | El texto `Error`, o `Incorrecto` si `EstadoRegistro` es `Incorrecto` (sin formato de badge) |
| `verifactu_error` | El mensaje anterior |
| `verifactu_ultima_salida` | La respuesta completa en JSON |
| `verifactu_ultimafecha_modificacion` | Fecha y hora actuales |
| `verifactu_fecha_hora_generacion` | `TimestampPresentacion` si viene en la respuesta |

Hay un caso que se considera éxito aunque la AEAT responda con error: si `EstadoRegistro` es `Anulada`, o el registro duplicado indica `EstadoRegistroDuplicado` = `Anulada`, la factura ya estaba anulada en la AEAT; el módulo pone entonces el badge de anulada y devuelve la respuesta como si la operación hubiera funcionado.

Si no llega respuesta del servidor, `verifactu_estado` queda con el texto `Error` y `verifactu_error` con `No response received from server`.

## Qué queda en Dolibarr después

- La factura sigue **validada** en Dolibarr: el módulo no cambia `fk_statut` ni la abandona. La anulación afecta al registro en la AEAT, no al documento contable; lo que se haga con la factura en Dolibarr (clasificarla como abandonada, por ejemplo) queda fuera del módulo.
- La pestaña VeriFactu y el listado bloquean cualquier nuevo envío (alta, modificación o baja) porque el estado contiene la marca de anulada; el mensaje que se muestra usa la clave `VERIFACTU_ERROR_FACTURA_YA_ANULADA`.
- El código QR deja de mostrarse en la ficha: `isInvoiceSentToVerifactu()` (`lib/functions/functions.qr.php`) exige que el estado contenga `attr-status="Enviada"`, y tras la anulación el badge es otro.
- La huella y la fecha de generación del alta permanecen en `verifactu_huella` y `verifactu_fecha_hora_generacion` hasta que esta última se sobrescribe con el `TimestampPresentacion` de la anulación.
- Las restricciones de edición se mantienen: una factura sin `PROV` sigue sin poder modificarse ni borrarse. Véase [[Restricciones sobre facturas enviadas|Restricciones-sobre-facturas-enviadas]].

## Anulación, rectificativa o modificación

El módulo ofrece tres operaciones distintas sobre una factura ya registrada; conviene no confundirlas:

| Operación | Botón | Registro enviado | Efecto sobre el número de factura |
|---|---|---|---|
| Rectificativa | Ninguno: se crea una nueva factura de abono o de sustitución | Nuevo `RegistroAlta` de tipo R1/R5 con otra referencia | La original conserva su registro; la corrección tiene su propio número |
| Modificación (subsanación) | «Enviar Modificación» | Nuevo `RegistroAlta` de la misma referencia con `Subsanacion` = `S` | Mismo número; sustituye los datos del registro anterior en la AEAT |
| Anulación | «Enviar Baja» | `RegistroAnulacion` de la misma referencia | Mismo número; el registro de alta queda anulado |

La propia interfaz orienta la elección en el caso de duplicados: cuando una factura ya existe en la AEAT con importes distintos, el mensaje `VERIFACTU_DUPLICATE_ACTION_REQUIRED` indica «Si Dolibarr es correcto, cree factura rectificativa. Si AEAT es correcto, ajuste Dolibarr». La anulación es la vía cuando el registro de alta no debe existir (una factura que nunca debió emitirse o que se registró por error), incluida la que la AEAT rechazó (`rechazo_previo`) o la que nunca llegó (`sin_registro_previo`). Qué tratamiento corresponde a cada situación es una decisión fiscal que el módulo no toma. Véanse [[Facturas rectificativas|Facturas-rectificativas]] y [[Flujo de validación y envío|Flujo-de-validacion-y-envio]].

## Restricciones y errores habituales

| Situación | Comportamiento |
|---|---|
| Factura en borrador o con `PROV` | El botón no se ofrece; el listado la descarta con `VERIFACTU_VALIDATION_INVOICE_PROVISIONAL` |
| Factura ya anulada | Bloqueada en pestaña y listado |
| Factura proforma | `execVERIFACTUCall()` sale sin hacer nada (`isVerifactuApplicableInvoice()`) |
| Certificado ausente, caducado o con contraseña incorrecta | Mensaje de error de certificado y no se envía |
| Otra validación encadenando en ese momento | `VERIFACTU_CHAIN_LOCK_BUSY` tras el tiempo de espera (`VERIFACTU_CHAIN_LOCK_TIMEOUT`, 30 s por defecto) |
| Anulación normal de una factura que no está en la AEAT | La AEAT responde con error; el estado queda en `Error`/`Incorrecto` con el detalle en `verifactu_error`. Repite con el tipo `sin_registro_previo` si procede |
| Anulación repetida | Si la AEAT informa de que el registro ya está `Anulada`, se trata como éxito |
| Servicio no disponible (HTTP 503) o fallo SOAP | En las bajas no existe la marca de «Pendiente» que sí se aplica a las altas: la excepción llega al bloque `catch` de `execVERIFACTUCall()`, que anota `Exception: <mensaje>` en `verifactu_error` y en `verifactu_ultima_salida`; la factura conserva su estado «Enviada», su huella y su CSV, y basta con repetir la baja |

> **Nota:** hasta la 2.2.1 ese bloque `catch` vaciaba `verifactu_csv_factura` y `verifactu_huella` sea cual fuera la acción que falló, y ponía la insignia «Error». Un intento de anulación que terminaba en excepción (fallo de comunicación, del certificado o del bloqueo de cadena) borraba en Dolibarr la huella y el CSV de un alta que la AEAT sí tenía registrada, `getLastInvoiceHash()` dejaba de considerarla al encadenar el siguiente registro y el QR desaparecía. Desde la 2.2.2 una excepción no toca nunca la huella ni el CSV y, si la factura ya tiene un registro aceptado, conserva también su estado (`buildVerifactuExceptionErrorData()`, cubierto por `tests/ExceptionKeepsRecordTest.php`). Si una factura perdió su huella con una versión anterior, «Verificar Estado en AEAT» permite recuperar la que la AEAT tiene registrada; véase [[Consulta a la AEAT|Consulta-a-la-AEAT]].

Los códigos de error de la AEAT que pueden aparecer en `verifactu_error` se recogen en [[Códigos de error|Codigos-de-error]].

## Véase también

- [[Pestaña VeriFactu de la factura|Pestana-VeriFactu-de-la-factura]]
- [[Facturas rectificativas|Facturas-rectificativas]]
- [[Encadenamiento y huella|Encadenamiento-y-huella]]
- [[Respuestas de la AEAT|Respuestas-de-la-AEAT]]
- [[Restricciones sobre facturas enviadas|Restricciones-sobre-facturas-enviadas]]
- [[Campos adicionales|Campos-adicionales]]
- [[Biblioteca newfenix|Biblioteca-newfenix]]
- [[Códigos de error|Codigos-de-error]]
