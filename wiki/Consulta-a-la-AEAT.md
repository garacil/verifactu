# Consulta a la AEAT

Además de enviar registros, el módulo consulta al servicio `ConsultaFactuSistemaFacturacion` de la AEAT para saber qué tiene registrado de cada factura. Esa consulta se usa en cuatro sitios: la pantalla «Consulta AEAT» del menú del módulo (`views/query.facture.php`), el bloque «Datos en la AEAT» de la pestaña VeriFactu, la comprobación previa que se hace antes de cada alta y el tratamiento del error 3000 (registro duplicado). En los tres últimos casos el módulo coteja el importe total que tiene Dolibarr con el que devuelve la AEAT y actúa en consecuencia. Toda la lógica vive en `lib/functions/functions.query.php` y en la clase `Query` de la biblioteca (`lib/newfenix/src/Query.php`).

## Qué se puede consultar

La clase `Query` construye la petición con una cabecera `ObligadoEmision` (NIF y razón social configurados en `VERIFACTU_HOLDER_NIF` y `VERIFACTU_HOLDER_COMPANY_NAME`) y un bloque `FiltroConsulta` con estos criterios:

| Criterio | Método de `Query` | Contenido |
|---|---|---|
| Periodo de imputación | `setFiscalPeriod($año, $mes)` | `PeriodoImputacion.Ejercicio` y `.Periodo` (mes con dos cifras) |
| Factura concreta | `setSpecificInvoice($numero, $fecha, $nif)` | `IDFactura.NumSerieFacturaEmisor`, `.FechaExpedicionFacturaEmisor` y `.NIF` |
| Rango de fechas de expedición | `setDateRange($desde, $hasta)` | `FechaExpedicionFactura.RangoFechaExpedicion.Desde` y `.Hasta` |
| Contraparte | `setCounterparty($nif, $nombre)` | `Contraparte.NIF` y, opcionalmente, `.NombreRazon` |
| Huella | `setFingerprint($hash)` | `Huella` (disponible en la biblioteca; ninguna pantalla del módulo lo usa) |

Las fechas van en formato `dd-mm-aaaa` y se validan al asignarlas. Si al generar la petición no hay periodo de imputación pero sí una fecha de factura, `getData()` deduce el ejercicio y el mes de esa fecha (`resolvePeriodAutomatically()`). La consulta se envía con `Manager::queryInvoice()` al mismo punto de acceso que las altas (`SistemaFacturacion/VerifactuSOAP` del entorno activo) y con el mismo certificado. El módulo no implementa la paginación de la respuesta (no gestiona `ClavePaginacion`): cada consulta devuelve lo que la AEAT entregue en la primera página.

## `execVERIFACTUQuery()`: el punto común

Todas las consultas pasan por `execVERIFACTUQuery($filtroConsulta)`, que:

1. Obtiene y valida el certificado (`getCertificateOptions()`, `validateCertificateAndKey()`).
2. Comprueba que el NIF y la razón social del obligado están configurados.
3. Crea el `VerifactuManager` con el entorno de `getEnvironment()` y el tipo de certificado `VERIFACTU_CERT_TYPE`.
4. Reconstruye el objeto `Query` a partir del filtro recibido (`buildQueryFromFilter()`), que acepta tanto la estructura completa `ConsultaFactuSistemaFacturacion.FiltroConsulta` como un array simplificado. Si el filtro trae `IDFactura.NumSerieFacturaEmisor`, usa `buildQueryForSpecificInvoice()`: con número y fecha, consulta el rango de fechas [fecha, fecha + 1 día] más la factura concreta; solo con número, consulta por periodo de imputación y filtra después.
5. Ejecuta la consulta y aplica `applyPostFilters()` a la respuesta:
   - `applyInvoiceIdFilter()` se queda únicamente con los registros cuyo `NumSerieFactura` coincide **exactamente** con el buscado (y la fecha, si se indicó). Si no hay coincidencia, marca la respuesta como `SinDatos` para no devolver por error otras facturas del mismo día.
   - `applyCounterpartyFilter()` se queda con los registros cuyo destinatario tiene el NIF indicado y, si se dio nombre, lo contiene (sin distinguir mayúsculas).
6. Devuelve el objeto de respuesta, o `false` si algo falla (en ese caso muestra el error con `setEventMessage`).

La respuesta de la AEAT trae `ResultadoConsulta` (`ConDatos` o `SinDatos`) y, con datos, `RegistroRespuestaConsultaFactuSistemaFacturacion`, que puede ser un único objeto o un array; las funciones del módulo normalizan ambos casos. A diferencia de los envíos, las consultas no registran `TiempoEsperaEnvio`.

## La pantalla «Consulta AEAT»

Se abre desde el menú VeriFactu, entrada «Consulta AEAT» (`views/query.facture.php`, título «Consulta de Facturas»). Requiere el permiso `verifactu->manage` o ser administrador y la extensión SOAP.

### Filtros

| Filtro | Campo | Comportamiento |
|---|---|---|
| Ejercicio facturación | `search_year` (desplegable de 2020 a 2030) | Año del periodo de imputación; por defecto el actual |
| Periodo facturación | `search_month` (01 a 12) | Mes del periodo; por defecto el actual |
| Referencia | `search_facture` | Número de serie de la factura |
| NIF | `search_nif` | NIF de la contraparte (`setCounterparty`) |
| Nombre | `search_nom` | Nombre de la contraparte; solo se envía si también hay NIF |

La consulta siempre lleva `PeriodoImputacion`. Cuando se indica una referencia, la pantalla busca en `llx_facture` de la entidad activa la fecha de la factura (`datef`, o `datec` si no la tiene); si esa fecha cae en otro mes, cambia el periodo consultado al de la factura, y añade al filtro `IDFactura.NumSerieFacturaEmisor` (y `FechaExpedicionFacturaEmisor` cuando la ha encontrado). Como `applyInvoiceIdFilter()` exige coincidencia exacta del número, la referencia debe escribirse completa; los filtros locales de coincidencia parcial que la pantalla aplica después solo son útiles para NIF y nombre.

### Columnas

| Columna | Origen |
|---|---|
| ID Factura | `IDFactura.NumSerieFactura` devuelto por la AEAT, con botón para copiar al portapapeles |
| Factura asociada | Enlace a la factura de Dolibarr si existe una con esa referencia |
| Fecha | Fecha de la factura en Dolibarr (si se ha encontrado) |
| Fecha presentación | `IDFactura.FechaExpedicionFactura` devuelto por la AEAT |
| NIF / Nombre | Destinatario del registro; si la factura existe en Dolibarr se muestra el tercero enlazado y su NIF |
| Base | Suma de `BaseImponibleOImporteNoSujeto` del desglose devuelto |
| IVA *n* | Una columna por cada tipo activo del diccionario de IVA del país de la empresa (`llx_c_tva`, excluidos los tipos 4 y 5); suma de `CuotaRepercutida` de las entradas con ese `TipoImpositivo` |
| RE | Suma de `CuotaRecargoEquivalencia` |
| Total IVA | Suma de `CuotaRepercutida` |
| Total | Base + IVA + RE |
| Estado registro | `EstadoRegistro.EstadoRegistro` del registro en la AEAT |

Los importes salen siempre del `Desglose` que devuelve la AEAT, no de Dolibarr. Las entradas del desglose sin `TipoImpositivo` (exentas o no sujetas) se omiten de las sumas. Al pie se muestra una fila de totales de la página. Si no hay registros aparece «Sin resultados» (`NO_RECORDS_FOUNDS`).

> **Nota:** el README describe filtros por rango de fechas y por contraparte. En la versión 2.2.2 la pantalla ofrece periodo, referencia, NIF y nombre; el rango de fechas lo usa internamente la consulta por factura concreta.

## El bloque «Datos en la AEAT» de la pestaña VeriFactu

Cada vez que se abre la pestaña de una factura con `verifactu_id_factura` informado, `views/tabVERIFACTU.facture.php` consulta a la AEAT en el momento. Toma del JSON del campo el número, la fecha y el NIF emisor, construye una consulta por rango [fecha, fecha + 1 día] con `setSpecificInvoice()` y muestra:

- Con `ConDatos`: el número de registros, la referencia y la fecha, y dos paneles en formato `<pre>` generados con `dumper()` (`lib/dumper.php`): a la izquierda «Payload Sent to AEAT» (el contenido de `verifactu_payload`) y a la derecha «AEAT Query Response» (el primer registro devuelto).
- Con `SinDatos`: un aviso de que la factura no consta en los registros de la AEAT.
- En cualquier otro caso: un error de consulta invitando a revisar la configuración.

Es la forma más directa de comparar, campo a campo, lo enviado con lo registrado. Véase [[Pestaña VeriFactu de la factura|Pestana-VeriFactu-de-la-factura]].

## Cómo se cotejan los importes

`compararImportesFactura($facture, $facturaAEAT, $tolerancia = 0.01)` (alias `compareInvoiceAmounts()`) compara:

- **Lado Dolibarr**: `getVerifactuImporteTotal($facture)`, es decir `total_ttc + abs(total_localtax2)`, para que la retención de IRPF no altere la comparación. Véase [[Desglose de impuestos|Desglose-de-impuestos]].
- **Lado AEAT**: `extraerImporteTotalAEAT()` busca `DatosRegistroFacturacion.ImporteTotal`; si no está, `DatosRegistroFacturacion.DatosFactura.ImporteTotal`; y como último recurso suma base más cuota de cada `DetalleDesglose` (sin recargo de equivalencia).

Se comparan los valores absolutos, de modo que un abono negativo en Dolibarr coincide con su importe positivo en la AEAT, y se consideran iguales si la diferencia no supera 0,01. La función devuelve `coinciden`/`match`, `importe_dolibarr`, `importe_aeat` y `diferencia` (con alias en inglés); si no puede extraer el importe de la AEAT, devuelve `coinciden = false` con `error`.

`consultarFacturaEnAEAT($numero, $fecha)` (alias `queryInvoiceInAEAT()`) es la consulta unitaria que usan los tres flujos siguientes: busca por número, fecha y NIF del obligado y devuelve el primer registro encontrado o `false` si no hay datos o la consulta falla. Esa segunda parte es importante: un fallo de certificado o de conexión se comporta igual que «no existe».

## Comprobación previa antes de cada alta

`execVERIFACTUCall()` llama a `preCheckInvoiceInAEAT()` antes de cualquier alta (validación, pestaña, listado, envío masivo y reintentos) para evitar el error 3000 y los estados incoherentes que provoca:

| Resultado de la consulta | Acción |
|---|---|
| No existe (o la consulta falló) | Se continúa con el envío normal |
| Existe y los importes coinciden | No se envía nada. Si el estado no era ya «Enviada», se guarda el badge de enviada, `verifactu_ultima_salida` = «Factura verificada - Ya existía en AEAT» y la huella que devuelve la AEAT. Mensaje `VERIFACTU_PRECHECK_SKIP_SEND` («La factura %s ya existe en AEAT. No se realizó envío adicional.») y la operación se da por correcta |
| Existe con importes distintos | Mensaje de error `VERIFACTU_PRECHECK_AMOUNTS_DIFFER` con ambos importes y la operación devuelve `false`; si se estaba validando, la factura vuelve a borrador |

## Duplicados: el error 3000

Si a pesar de la comprobación previa la AEAT responde con `CodigoErrorRegistro` = 3000 (registro duplicado), `processInvoiceSendResponse()` llama a `manejarFacturaDuplicada()` (alias `handleDuplicateInvoice()`), que vuelve a consultar y devuelve una de tres decisiones:

| Decisión (`accion`) | Condición | Efecto en la factura | Mensajes |
|---|---|---|---|
| `marcar_enviada` | Los importes coinciden | Badge `badge-status4` «Enviada (Duplicada)», `verifactu_error` vacío, huella de la AEAT si la trae; la operación se considera correcta | `VERIFACTU_DUPLICATE_AMOUNTS_MATCH` |
| `preguntar_usuario` | Los importes difieren | Badge `badge-status1` «Duplicada (Importes diferentes)», `verifactu_error` con `VERIFACTU_DUPLICATE_AMOUNTS_DIFFER_DETAIL` (importes y diferencia); la operación devuelve `false`, así que en una validación la factura vuelve a borrador | `VERIFACTU_DUPLICATE_AMOUNTS_DIFFER` y `VERIFACTU_DUPLICATE_ACTION_REQUIRED`: «ACCIÓN REQUERIDA: Si Dolibarr es correcto, cree factura rectificativa. Si AEAT es correcto, ajuste Dolibarr.» |
| `error` | No se pudo consultar | Se sigue el flujo de error normal (badge «Error» y detalle en `verifactu_error`) | `VERIFACTU_DUPLICATE_QUERY_ERROR` |

La decisión queda en manos del usuario: el módulo no crea rectificativas ni modifica importes por su cuenta. Véanse [[Facturas rectificativas|Facturas-rectificativas]] y [[Respuestas de la AEAT|Respuestas-de-la-AEAT]].

## El botón «Verificar Estado en AEAT»

En la pestaña VeriFactu, cuando la factura tiene algún estado VeriFactu, aparece «Verificar Estado en AEAT» (`VERIFACTU_BUTTON_VERIFY_STATUS`), que ejecuta `verificarEstadoFacturaAEAT()` (alias `verifyInvoiceStatusInAEAT()`) sin diálogo de confirmación:

| Resultado | Acción sobre la factura | Mensaje |
|---|---|---|
| No existe en la AEAT | Se vacían `verifactu_estado`, `verifactu_error`, `verifactu_ultima_salida`, `verifactu_id_factura`, `verifactu_huella` y `verifactu_ultimafecha_modificacion`; el CSV no se borra. La factura queda como si el módulo no la hubiera procesado y puede enviarse de nuevo | `VERIFACTU_VERIFY_NOT_FOUND_CLEARED` |
| Existe y los importes coinciden | Badge «Enviada», `verifactu_ultima_salida` con `VERIFACTU_VERIFY_SUCCESS_SUMMARY` (ambos importes) y la huella de la AEAT | `VERIFACTU_VERIFY_STATUS_CORRECTED` |
| Existe con importes distintos | No se cambia nada | Aviso `VERIFACTU_VERIFY_AMOUNTS_DIFFER` con ambos importes y la diferencia |

Sirve para reconciliar una factura cuyo estado en Dolibarr quedó desactualizado (por ejemplo, tras un error de comunicación posterior a un alta aceptada).

## Uso en el entorno de pruebas

`getEnvironment()` (`lib/functions/functions.configuration.php`) devuelve `test` hasta el 1 de enero de 2027 (sociedades) o el 1 de julio de 2027 (`VERIFACTU_COMPANY_TYPE` = `autonomo`), salvo que `VERIFACTU_FORCE_PRODUCTION_ENVIRONMENT` esté activo. En pruebas, las consultas van a los servidores `prewww1.aeat.es` (certificados de tipo `normal`) o `prewww10.aeat.es` (tipo `sello`), y solo devuelven registros enviados a ese mismo entorno. El campo `verifactu_entorno` guarda el entorno con el que se envió cada factura, y `getLastInvoiceHash()` filtra por él, de modo que las cadenas de pruebas y de producción no se mezclan. Si al pasar a producción se consultan facturas enviadas en pruebas, la AEAT responderá `SinDatos`.

Los textos de ayuda del módulo advierten de que los certificados personales pueden dar el error «no XML document» en las consultas cuando se configura el tipo `sello`; la recomendación es usar `normal` (`VERIFACTU_TROUBLESHOOTING_XML_ERROR`). Véanse [[Conexión SOAP y entornos|Conexion-SOAP-y-entornos]] y [[Certificados digitales|Certificados-digitales]].

## Véase también

- [[Pestaña VeriFactu de la factura|Pestana-VeriFactu-de-la-factura]]
- [[Respuestas de la AEAT|Respuestas-de-la-AEAT]]
- [[Flujo de validación y envío|Flujo-de-validacion-y-envio]]
- [[Desglose de impuestos|Desglose-de-impuestos]]
- [[Conexión SOAP y entornos|Conexion-SOAP-y-entornos]]
- [[Certificados digitales|Certificados-digitales]]
- [[Biblioteca newfenix|Biblioteca-newfenix]]
- [[Códigos de error|Codigos-de-error]]
- [[Resolución de problemas|Resolucion-de-problemas]]
