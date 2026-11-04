# Respuestas de la AEAT

El servicio de suministro de registros de facturación responde a cada envío con un documento definido en el esquema `RespuestaSuministro.xsd`. El módulo lo recibe como objeto PHP desde el cliente SOAP (`lib/newfenix/src/SoapClient.php`), decide si el registro se acepta con `isAEATResponseAccepted()` (`lib/functions/functions.response.php`) y traduce el resultado a un estado, a un conjunto de campos adicionales de la factura y a mensajes en pantalla (`processInvoiceSendResponse()` en `lib/functions/functions.submission.php` para altas y subsanaciones; `processCancellationResponse()` en `lib/functions/functions.cancellation.php` para anulaciones). Esta página describe esa traducción en la versión 2.2.2.

## Estructura de la respuesta

Los esquemas oficiales se descargan y guardan en `documents/verifactu/schemas` (véase [[Esquemas WSDL y XSD|Esquemas-WSDL-y-XSD]]). De la respuesta, el módulo utiliza estos elementos:

| Elemento | Valores | Uso en el módulo |
|---|---|---|
| `EstadoEnvio` | `Correcto`, `ParcialmenteCorrecto`, `Incorrecto` | Estado global del envío |
| `CSV` | Código seguro de verificación | Se guarda en `verifactu_csv_factura` |
| `DatosPresentacion.TimestampPresentacion` | Marca de tiempo de la presentación | Aparece en el resumen «Última respuesta»; en anulaciones se guarda como fecha/hora de generación |
| `TiempoEsperaEnvio` | Segundos | Control de flujo: `registerAEATWaitTime()` |
| `RespuestaLinea` | Un objeto (una línea) o un array (varias) | Detalle por registro |
| `RespuestaLinea.IDFactura` | `IDEmisorFactura`, `NumSerieFactura`, `FechaExpedicionFactura` | Se guarda como JSON en `verifactu_id_factura` |
| `RespuestaLinea.EstadoRegistro` | `Correcto`, `AceptadoConErrores`, `Incorrecto` | Estado del registro concreto |
| `RespuestaLinea.CodigoErrorRegistro` | Código numérico de la AEAT | Mensaje de error y tratamiento del 3000 |
| `RespuestaLinea.DescripcionErrorRegistro` | Texto | Mensaje de error |
| `RespuestaLinea.RegistroDuplicado` | `IdPeticionRegistroDuplicado`, `EstadoRegistroDuplicado` | Solo en anulaciones: detecta registros ya anulados |
| `RespuestaLinea.Operacion.TipoOperacion` | Tipo de operación | Solo en anulaciones (`verifactu_tipo_operacion`) |

Esquemáticamente:

```text
RespuestaRegFactuSistemaFacturacion
├── CSV
├── DatosPresentacion
│   └── TimestampPresentacion
├── EstadoEnvio                 Correcto | ParcialmenteCorrecto | Incorrecto
├── TiempoEsperaEnvio
└── RespuestaLinea (1..n)
    ├── IDFactura {IDEmisorFactura, NumSerieFactura, FechaExpedicionFactura}
    ├── EstadoRegistro          Correcto | AceptadoConErrores | Incorrecto
    ├── CodigoErrorRegistro
    ├── DescripcionErrorRegistro
    └── RegistroDuplicado {IdPeticionRegistroDuplicado, EstadoRegistroDuplicado}
```

Cuando la comunicación SOAP falla, no hay respuesta: `SoapClient::executeOperation()` captura el `SoapFault` y lanza `BillingException` con el mensaje `AEAT communication failed: …` y el código del fallo. `execVERIFACTUCall()` la trata como excepción, salvo el caso 503 descrito en [[Reintentos y envíos pendientes|Reintentos-y-envios-pendientes]].

## Regla de aceptación

`isAEATResponseAccepted($response)` devuelve `true` únicamente en estos casos:

| `EstadoEnvio` | Líneas | Resultado |
|---|---|---|
| `Correcto` | Cualquiera | Aceptada |
| `ParcialmenteCorrecto` | Todas con `EstadoRegistro` `Correcto` o `AceptadoConErrores` | Aceptada |
| `ParcialmenteCorrecto` | Alguna con `Incorrecto` | **Rechazada** |
| `ParcialmenteCorrecto` | Sin `RespuestaLinea` | Rechazada |
| `Incorrecto` | Cualquiera | Rechazada |
| Respuesta nula o sin `EstadoEnvio` | — | Rechazada |

La función admite que `RespuestaLinea` llegue como objeto o como array. Estos casos están cubiertos por `tests/PartialResponseTest.php`.

### Respuestas parciales

Hasta la 1.0.x el módulo miraba solo `EstadoEnvio`, de modo que un envío `ParcialmenteCorrecto` con una línea `Incorrecto` dejaba la factura almacenada como enviada. Desde la 2.0.0 (PR #36) **una línea rechazada no cuenta como enviada**: la respuesta se trata como error, no se guarda huella ni CSV y, si el envío venía de la validación, la factura vuelve a borrador. Como el módulo envía un registro por llamada, en la práctica la línea rechazada es la de la propia factura. La misma regla se aplica a las anulaciones.

Una respuesta `ParcialmenteCorrecto` cuyas líneas están todas en `Correcto` o `AceptadoConErrores` se acepta y se distingue con el estado «Parcial».

## Traducción a estado y campos adicionales (altas y subsanaciones)

### Respuesta aceptada

| Campo adicional | Valor guardado |
|---|---|
| `verifactu_estado` | «Enviada» (`badge-status4`) si `EstadoEnvio = Correcto`; «Parcial» (`badge-status1`) si `ParcialmenteCorrecto` |
| `verifactu_csv_factura` | `CSV` |
| `verifactu_payload` | JSON del registro transmitido (`Manager::getLastSendInvoiceData()`) |
| `verifactu_entorno` | `test` o `production` |
| `verifactu_modo` | Badge con `VERIFACTU_MODE` (`verifactu` por defecto) |
| `verifactu_ultima_salida` | Resumen construido por `buildVerifactuOutputSummary()` |
| `verifactu_ultimafecha_modificacion` | `dol_now()` |
| `verifactu_id_factura` | JSON de `RespuestaLinea.IDFactura` |
| `verifactu_fecha_factura` | `FechaExpedicionFactura` convertida de `dd-mm-aaaa` a `aaaa-mm-dd` |
| `verifactu_huella` | Huella generada (`getLastGeneratedHash()`) |
| `verifactu_fecha_hora_generacion` | `FechaHoraHusoGenRegistro` (`getLastGeneratedTimestamp()`) |
| `verifactu_error` | Se vacía |

El guardado se hace con `saveVerifactuSuccessData()` por conexión independiente, para que sobreviva a un posible *rollback*; si esa conexión no se puede abrir, se recurre a `insertExtraFields()`. El mensaje en pantalla es `Invoice {ref} sent successfully to VeriFactu` o `… sent to VeriFactu with partial success (some records may have warnings)` (textos fijos en inglés en el código).

El resumen de «Última respuesta» tiene este formato:

```text
Status: Correcto | CSV: A-XXXXXXXXXXXXXXXX | Sent: 2026-09-19T10:15:31+02:00 | Record: Correcto
```

y, en caso de error, añade `| Error Code: {código} | Error: {descripción}`.

### Respuesta rechazada

Se compone el mensaje `Error in VeriFactu submission - Status: {EstadoEnvio}` y, si hay `CodigoErrorRegistro`, ` - Code: {código} - {DescripcionErrorRegistro}`. Salvo en el caso especial del 3000, se guarda con `saveVerifactuErrorData()`:

| Campo adicional | Valor |
|---|---|
| `verifactu_estado` | «Error» (`badge-status8`) |
| `verifactu_error` | El mensaje anterior |
| `verifactu_ultima_salida` | Resumen con código y descripción |
| `verifactu_ultimafecha_modificacion` | `dol_now()` |

La función devuelve `false`; en la validación eso provoca la reversión a borrador. Los códigos habituales están en [[Códigos de error|Codigos-de-error]].

### Error 3000: registro duplicado

El código 3000 significa que la AEAT ya tiene un registro con ese emisor, número y fecha. `manejarFacturaDuplicada()` (`lib/functions/functions.query.php`) consulta la factura en la AEAT y compara importes con `compararImportesFactura()` (que usa `getVerifactuImporteTotal()`, es decir, base más cuotas sin descontar la retención de IRPF):

| Resultado de la consulta | Acción | Estado | Devuelve |
|---|---|---|---|
| Existe con los mismos importes (`marcar_enviada`) | Se marca como enviada; si la AEAT devuelve `DatosRegistroFacturacion.Huella`, se adopta esa huella | «Enviada (Duplicada)» (`badge-status4`) | `true` |
| Existe con importes distintos (`preguntar_usuario`) | `verifactu_error` recibe `VERIFACTU_DUPLICATE_AMOUNTS_DIFFER_DETAIL` («Factura %s duplicada con importes diferentes: Dolibarr=%s €, AEAT=%s € (diferencia: %s €). Requiere acción manual.») y se avisa con `VERIFACTU_DUPLICATE_ACTION_REQUIRED` («ACCIÓN REQUERIDA: Si Dolibarr es correcto, cree factura rectificativa. Si AEAT es correcto, ajuste Dolibarr.») | «Duplicada (Importes diferentes)» (`badge-status1`) | `false` |
| No se pudo consultar (`error`) | Aviso `VERIFACTU_DUPLICATE_QUERY_ERROR` y flujo de error normal | «Error» | `false` |

La consulta previa que hace `execVERIFACTUCall()` antes de cada alta (`preCheckInvoiceInAEAT()`) evita la mayoría de los 3000; este tratamiento cubre los que se producen igualmente (por ejemplo, un envío que la AEAT registró pero cuya respuesta no llegó).

### Sin respuesta y excepciones

- **Sin respuesta** (`$response` vacío): estado «Error», `verifactu_error = 'No response received from VeriFactu server'`.
- **Excepción** durante el proceso (validación del registro contra el esquema, fallo SOAP, cadena ilegible, bloqueo ocupado, certificado…): `verifactu_error = 'Exception: {mensaje}'` y `verifactu_ultima_salida = 'VeriFactu Exception: {mensaje}'`. Si la factura no tiene huella, el estado pasa a «Error»; si ya tiene un registro aceptado por la AEAT (por ejemplo, falla una baja o una subsanación posterior), conserva su estado. `verifactu_csv_factura` y `verifactu_huella` no se tocan nunca desde la 2.2.2 (hasta la 2.2.1 se vaciaban). Aviso `VERIFACTU_EXCEPTION` («Error VeriFactu: %s»).

## `RechazoPrevio` y subsanación

El registro de alta admite dos indicadores relacionados con envíos anteriores:

| Indicador | Valores | Cómo lo pone el módulo |
|---|---|---|
| `Subsanacion` | `S` | `Invoice::setAsCorrection(true)`; lo activa `VerifactuInvoice::createSubsanacion()`, que es lo que usa la acción `Mod` («Enviar Modificación») |
| `RechazoPrevio` | `S`: la AEAT rechazó antes el registro; `X`: el registro nunca llegó a la AEAT | `Invoice::setAsPreviousRejection(true)` → `S`; `setAsPreviousRejection(null)` → `X`; `false` lo elimina. Activarlo implica `Subsanacion = 'S'` |

Hasta la 1.0.x el módulo transmitía siempre `X`; desde la 2.0.0 (PR #38) la biblioteca distingue ambos valores conforme a `RechazoPrevioType` del esquema, y `tests/PriorRejectionTest.php` lo comprueba. En el flujo de la 2.2.2, `handleInvoiceCreationOrSubsanation()` llama a `createSubsanacion($ref, $fecha, $nif, $nombre)` sin quinto parámetro, cuyo valor por defecto es `false`: la subsanación enviada con «Enviar Modificación» lleva `Subsanacion = 'S'` y **no incluye** `RechazoPrevio`.

En las anulaciones, la clase `Cancellation` usa indicadores distintos, elegidos con el desplegable «tipo de anulación»:

| Tipo de anulación | Elemento del registro |
|---|---|
| `normal` | Ninguno |
| `rechazo_previo` | `RechazoPrevio = 'S'` |
| `sin_registro_previo` | `SinRegistroPrevio = 'S'` |

Véase [[Anulación de facturas|Anulacion-de-facturas]].

## Respuesta de una anulación

`processCancellationResponse()` aplica la misma regla de aceptación:

- **Aceptada:** estado «Anulada» (`badge-status6`, clave `VERIFACTU_STATUS_ANULADA`, traducida desde la 2.2.2), `verifactu_csv_factura = CSV`, `verifactu_error` vacío, `verifactu_id_factura` con el `IDFactura` devuelto, `verifactu_tipo_operacion` si llega, `verifactu_fecha_hora_generacion = TimestampPresentacion` y `verifactu_ultima_salida` con la respuesta completa en JSON.
- **Rechazada:** mensaje `Error in VERIFACTU cancellation - Status: … - Record Status: … - Code: … - Description: …`; el estado se guarda como texto `Error` o `Incorrecto`. Si la respuesta indica que el registro duplicado ya está anulado (`RegistroDuplicado.EstadoRegistroDuplicado = 'Anulada'`), se considera éxito y se pone el badge «Anulada».
- **Sin respuesta:** `verifactu_error = 'No response received from server'` y estado `Error`.

## Tiempo de espera (`TiempoEsperaEnvio`)

Tras cada envío (alta, subsanación o anulación) se llama a `registerAEATWaitTime($response)`, que guarda en la constante `VERIFACTU_NEXT_SEND_AT` de la entidad el instante `dol_now() + TiempoEsperaEnvio` cuando el valor es mayor que cero. `getAEATWaitTimeRemaining()` devuelve los segundos pendientes. Solo la cola de reintentos respeta esa espera; la validación, la pestaña VeriFactu y el envío masivo la anotan en el registro y continúan (véase [[Reintentos y envíos pendientes|Reintentos-y-envios-pendientes]]).

## Dónde queda cada cosa

| Qué | Dónde | Cómo verlo |
|---|---|---|
| CSV | `verifactu_csv_factura` («Código CSV») | Ficha de la factura, pestaña VeriFactu, listado; forma parte de la URL del [[Código QR|Codigo-QR]] |
| Registro enviado (estructura antes de serializar a SOAP) | `verifactu_payload` («Datos XML»), en JSON | Pestaña VeriFactu, bloque «Payload Sent to AEAT» |
| Identificación devuelta por la AEAT | `verifactu_id_factura`, en JSON | Pestaña VeriFactu; se usa para la consulta específica de la factura |
| Resumen de la última respuesta | `verifactu_ultima_salida` («Última respuesta») | Ficha y pestaña VeriFactu |
| Respuesta completa de una anulación | `verifactu_ultima_salida`, en JSON | Pestaña VeriFactu |
| XML SOAP real (petición y respuesta) | Solo en memoria, en el búfer de traza de `SoapClient` (`captureTrace()`, `getDebugInfo()`) | No se persiste ni se muestra en la interfaz de la 2.2.2 |
| Trazas de proceso | Registro de Dolibarr (`dol_syslog`), prefijo `VERIFACTU` | Véase [[Registro y depuración|Registro-y-depuracion]] |

El módulo no escribe ficheros XML ni de respuesta en disco: las únicas escrituras en disco del flujo de envío son los esquemas WSDL/XSD y los ficheros PEM del certificado.

## Véase también

- [[Flujo de validación y envío|Flujo-de-validacion-y-envio]]
- [[Códigos de error|Codigos-de-error]]
- [[Reintentos y envíos pendientes|Reintentos-y-envios-pendientes]]
- [[Encadenamiento y huella|Encadenamiento-y-huella]]
- [[Anulación de facturas|Anulacion-de-facturas]]
- [[Consulta a la AEAT|Consulta-a-la-AEAT]]
- [[Pestaña VeriFactu de la factura|Pestana-VeriFactu-de-la-factura]]
- [[Esquemas WSDL y XSD|Esquemas-WSDL-y-XSD]]
- [[Conexión SOAP y entornos|Conexion-SOAP-y-entornos]]
- [[Registro y depuración|Registro-y-depuracion]]
