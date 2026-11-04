# Códigos de error

Referencia de todos los mensajes, marcadores y códigos que el módulo VeriFactu para Dolibarr (2.2.2) puede mostrar o interpretar: los mensajes propios definidos en `langs/es_ES/verifactu.lang`, los textos fijos del código, los marcadores internos guardados en el campo adicional `verifactu_error`, las excepciones de la biblioteca `lib/newfenix` y los elementos de la respuesta de la AEAT que el módulo trata de forma explícita. Para el diagnóstico paso a paso, ve a [[Resolución de problemas|Resolucion-de-problemas]].

## Cómo llega un error al usuario

1. **Mensajes emergentes de Dolibarr** (`setEventMessage`), en verde, naranja o rojo, tras validar, enviar o consultar.
2. **Campos adicionales de la factura**, visibles en la [[pestaña VeriFactu|Pestana-VeriFactu-de-la-factura]]: `verifactu_estado` (insignia), `verifactu_error` (último error o marcador), `verifactu_ultima_salida` (resumen de la última respuesta). Los errores se guardan con una conexión de base de datos independiente para que sobrevivan a la reversión de la factura a borrador.
3. **Registro de eventos** de Dolibarr, con el detalle técnico ([[Registro y depuración|Registro-y-depuracion]]).

## Insignias de estado

El campo `verifactu_estado` guarda una insignia HTML con la traducción de una de estas claves:

| Clave | Texto (es_ES) | Color | Cuándo |
|---|---|---|---|
| `VERIFACTU_STATUS_NOT_SEND` | No Enviada | rojo (`badge-status8`) | Al crear la factura y al activar el módulo |
| `VERIFACTU_STATUS_SEND` | Enviada | verde (`badge-status4`) | `EstadoEnvio = Correcto`, cotejo correcto o comprobación previa que encuentra la factura con los mismos importes |
| `VERIFACTU_STATUS_SEND_PARTIAL` | Parcial | naranja (`badge-status1`) | `EstadoEnvio = ParcialmenteCorrecto` con todas las líneas aceptadas |
| `VERIFACTU_STATUS_ERROR` | Error | rojo | Rechazo de la AEAT, excepción o ausencia de respuesta |
| `VERIFACTU_STATUS_ANULADA` | Anulada | `badge-status6` | Anulación aceptada. Es un alias de `VERIFACTU_STATUS_CANCELLED` con el mismo texto; hasta la 2.2.1 no estaba definido y el badge mostraba la clave |
| `VERIFACTU_STATUS_NO_INTERNET` | Sin Conexión | naranja | Validación sin conexión |
| `VERIFACTU_STATUS_PENDING_SERVICE` | Pendiente | naranja | Servicio no disponible (503) o envío directo desactivado |
| `VERIFACTU_STATUS_SENT_DUPLICATE` | Enviada (Duplicada) | verde | Error 3000 con importes coincidentes |
| `VERIFACTU_STATUS_DUPLICATE_DIFF` | Duplicada (Importes diferentes) | naranja | Error 3000 con importes distintos |

> **Nota:** el QR y el bloqueo de reenvío solo consideran «enviada» una factura cuya insignia contiene exactamente `attr-status="Enviada"` (la traducción de `VERIFACTU_STATUS_SEND`) y que tiene CSV.

## Errores propios del módulo (claves `VERIFACTU_*`)

### Validación y envío

| Clave | Texto | Cuándo salta |
|---|---|---|
| `VERIFACTU_CONFIG_INCOMPLETE` | Configuración VeriFactu incompleta. Revise los ajustes del módulo. | `VERIFACTU_HOLDER_NIF` o `VERIFACTU_HOLDER_COMPANY_NAME` vacíos al enviar |
| `VERIFACTU_ERROR_INVOICE_NOT_VALIDATED` | El envío a VeriFactu falló para la factura %s. La factura permanece como borrador - corrija los problemas e inténtelo de nuevo. | El trigger revierte la factura a `(PROVn)` tras un envío fallido |
| `VERIFACTU_EXCEPTION` | Error VeriFactu: %s | Cualquier excepción capturada en `execVERIFACTUCall()` |
| `INVALID_VERIFACTU_ACTION` | Acción no válida: %s | Acción distinta de `Alta`, `Mod` o `Baja` |
| `VERIFACTU_VALIDATION_INVOICE_NOT_VALIDATED` | La factura debe estar validada para enviar a VeriFactu | Pestaña VeriFactu con factura en borrador |
| `VERIFACTU_VALIDATION_INVOICE_PROVISIONAL` | No se pueden enviar facturas provisionales (con "PROV" en referencia) | Pestaña VeriFactu |
| `INVALID_VERIFACTU_STATUS_TO_SEND` | La factura %s debe estar validada o cerrada para enviar a VeriFactu. | Envío desde el listado |
| `VERIFACTU_ERROR_FACTURA_YA_ANULADA` | La factura ya está anulada en VeriFactu | Intento de enviar una factura anulada (alias de `VERIFACTU_ERROR_INVOICE_ALREADY_CANCELLED`, definido desde la 2.2.2) |
| `VERIFACTU_INVOICE_ALREADY_SENT_CORRECTLY` | Factura ya enviada correctamente. Solo puede enviar una Baja para anularla. | Pestaña VeriFactu; informativo |
| `VERIFACTU_INVOICES_ALREADY_SENT_SKIPPED` | Las siguientes facturas ya están enviadas y se han omitido: %s | Envío masivo desde el listado |
| `VERIFACTU_NO_INVOICES_TO_SEND` | No hay facturas pendientes de enviar. Todas las facturas seleccionadas ya están enviadas. | Envío masivo |
| `VERIFACTU_INVOICE_CANNOT_BE_VALID_CIF_REQUIRED` | Factura %s: CIF/NIF del cliente obligatorio para VeriFactu | Tercero español sin NIF (botón Validar deshabilitado y trigger) |
| `VERIFACTU_INVOICE_CANNOT_BE_VALID_VAT_INTRA_REQUIRED` | Factura %s: CIF Intracomunitario obligatorio para VeriFactu | Tercero no español sin CIF intra |
| `VERIFACTU_INVOICE_CANNOT_BE_VALID_ADDRESS_REQUIRED` | Factura %s: Dirección del cliente obligatoria para VeriFactu | Tercero sin dirección |
| `VERIFACTU_INVOICE_REF_CONTAINS_SPACES` | La máscara de numeración contiene espacios. Modifíquela para continuar. | La siguiente referencia contiene espacios |
| `VERIFACTU_INVOICE_REF_NOT_ALPHANUMERIC` | Referencia "%s" no válida. Solo caracteres alfanuméricos, guiones (-), guiones bajos (_) y barras (/). | Referencia con otros caracteres |
| `VERIFACTU_INVOICE_REF_TOO_LONG` | Referencia "%s" demasiado larga (%s caracteres). Máximo 60 caracteres. | Referencia de más de 60 caracteres |
| `VERIFACTU_INVOICE_CANNOT_EXCEED_MAX_DATE` | La fecha de factura %s no puede ser anterior a la última fecha válida %s. Fecha ajustada a %s. | Orden cronológico |
| `VERIFACTU_SINGLE_VALIDATE_DATE_ADJUSTED` | Ajuste de fecha: La fecha original de la factura (%s) se ha ajustado a %s… | Diálogo de validación individual |
| `VERIFACTU_HAS_NO_INTERNET_CONNECTION` | Sin conexión a internet. La factura %s se validará localmente. Realice el envío manual posteriormente. | `checkInternetConnection()` falla al validar |
| `VERIFACTU_SERVICE_UNAVAILABLE_INFO` | Factura %s validada localmente. No se pudo enviar a VeriFactu. Marcada como "Pendiente" para reintento. Error: %s | HTTP 503 de la AEAT |
| `VERIFACTU_PENDING_SUBMISSION_INFO` | La factura %s se ha validado con el registro VeriFactu pendiente de envío, porque el envío directo en la validación está desactivado. Se enviará desde la cola de reintentos. | Desde la 2.1.0 |
| `VERIFACTU_AEAT_WAIT_REQUIRED` | La AEAT requiere esperar %s segundos antes del siguiente envío VeriFactu. | Reintento automático dentro de `TiempoEsperaEnvio` |
| `VERIFACTU_CHAIN_LOCK_BUSY` | Se está encadenando otra factura en este momento y ha vencido el tiempo de espera… | Bloqueo de la cadena no obtenido en `VERIFACTU_CHAIN_LOCK_TIMEOUT` segundos |
| `VERIFACTU_CHAIN_READ_FAILED` | No se pudo leer la cadena VeriFactu para obtener el registro anterior, por lo que la factura no se ha enviado… | Error SQL en `getLastInvoiceHash()` |
| `VERIFACTU_SUCCESS` | Operación completada correctamente | Envío correcto al validar |
| `VERIFACTU_WARNING_OPERATION_COMPLETED` | Operación completada - la factura ya tenía el estado solicitado | Informativo |

### Validación masiva

| Clave | Texto | Cuándo |
|---|---|---|
| `VERIFACTU_MASS_VALIDATE_INFO` | El módulo VeriFactu gestiona la validación masiva con transacciones independientes por factura… | Siempre, al terminar |
| `VERIFACTU_MASS_VALIDATE_PARTIAL` | Se validaron %s facturas correctamente, %s tuvieron errores… | Éxitos y fallos mezclados |
| `VERIFACTU_MASS_VALIDATE_FETCH_ERROR` | No se pudo cargar la factura con ID %s | `fetch()` falla |
| `VERIFACTU_MASS_VALIDATE_ALREADY_VALIDATED` | La factura %s no puede validarse desde su estado actual | `validate()` devuelve 0 |
| `VERIFACTU_MASS_VALIDATE_DATE_CONFIRM_MSG` | Se han detectado %s factura(s) con fecha anterior al %s (última factura validada: %s)… | Diálogo de confirmación de fechas |
| `VERIFACTU_MASS_VALIDATE_DATES_ADJUSTED` | Se ajustó la fecha de %s factura(s) para respetar el orden cronológico de VeriFactu. | Tras confirmar |
| `VERIFACTU_BATCH_VALIDATION_FAILED` / `_STOPPED` / `_SUCCESS` / `_PARTIAL` | Error validando factura %s: %s / Validación por lotes detenida… / %s facturas validadas correctamente. / Validación parcial… | Mensajes heredados de la 1.0.1 |

### Duplicados, comprobación previa y cotejo

| Clave | Texto | Cuándo |
|---|---|---|
| `VERIFACTU_PRECHECK_SKIP_SEND` | La factura %s ya existe en AEAT. No se realizó envío adicional. | Comprobación previa: existe con los mismos importes |
| `VERIFACTU_PRECHECK_ALREADY_EXISTS` | Factura verificada - Ya existía en AEAT | Se guarda en «Última respuesta» |
| `VERIFACTU_PRECHECK_AMOUNTS_DIFFER` | La factura %s existe en AEAT con importes diferentes: Dolibarr=%s, AEAT=%s. Revise los datos. | Comprobación previa: importes distintos; no se envía |
| `VERIFACTU_DUPLICATE_AMOUNTS_MATCH` | La factura %s ya existe en AEAT con los mismos importes. Marcada como enviada. | Error 3000, importes iguales |
| `VERIFACTU_DUPLICATE_AMOUNTS_DIFFER` | La factura %s existe en AEAT con importes diferentes: Dolibarr=%s, AEAT=%s (diferencia: %s) | Error 3000, importes distintos |
| `VERIFACTU_DUPLICATE_AMOUNTS_DIFFER_DETAIL` | Factura %s duplicada con importes diferentes: Dolibarr=%s €, AEAT=%s € (diferencia: %s €). Requiere acción manual. | Se guarda en `verifactu_error` |
| `VERIFACTU_DUPLICATE_ACTION_REQUIRED` | ACCIÓN REQUERIDA: Si Dolibarr es correcto, cree factura rectificativa. Si AEAT es correcto, ajuste Dolibarr. | Error 3000, importes distintos |
| `VERIFACTU_DUPLICATE_QUERY_ERROR` | No se pudo verificar la factura duplicada. Consulte manualmente en el portal AEAT. | Error 3000 y la consulta falla |
| `VERIFACTU_VERIFY_NOT_FOUND_CLEARED` | Factura %s no existe en AEAT. Errores anteriores limpiados. Puede enviarla. | Botón Verificar Estado |
| `VERIFACTU_VERIFY_STATUS_CORRECTED` | Factura %s verificada. Estado: Enviada. Importe: %s | Botón Verificar Estado, importes iguales |
| `VERIFACTU_VERIFY_AMOUNTS_DIFFER` | Factura %s en AEAT con importes diferentes: Dolibarr=%s, AEAT=%s (diferencia: %s). Requiere revisión. | Botón Verificar Estado, importes distintos |
| `VERIFACTU_VERIFY_SUCCESS_SUMMARY` | Verificación exitosa: Importes coinciden (Dolibarr: %s, AEAT: %s) | Se guarda en «Última respuesta» |
| `VERIFACTU_ERROR_DUPLICATE_REGISTRATION` | Registro duplicado | Etiqueta genérica |

### Certificados

| Clave | Texto | Cuándo |
|---|---|---|
| `VERIFACTU_CERT_ERROR_WRONG_PASSWORD` | Error: la contraseña del certificado no es correcta. Compruébela y vuelva a intentarlo. | OpenSSL devuelve `Mac verify error`, `invalid password`, `bad decrypt`, `wrong final block length` |
| `VERIFACTU_CERT_ERROR_LEGACY_ALGORITHM` | Error: el certificado está cifrado con un algoritmo antiguo (RC2-40-CBC / PBE-SHA1-3DES) que OpenSSL 3 desactiva por defecto… | OpenSSL devuelve `0308010C`, `digital envelope routines`, `unsupported algorithm`, `rc2-cbc`, `pbe-sha1-3des` |
| `VERIFACTU_CERT_ERROR_EXTRACTION_FAILED` | Error: no se pudo extraer el certificado público del archivo. Revise el registro de eventos de Dolibarr… | Fallo no clasificado |
| `VERIFACTU_CERT_ERROR_EXPIRED` | Error: el certificado está fuera de su periodo de validez… | Fecha actual fuera de `validFrom`/`validTo` (desde 2.0.0) |
| `VERIFACTU_CERT_KEY_MISMATCH` | Clave privada incorrecta o contraseña errónea | Pantalla de certificados |
| `VERIFACTU_CERTIFICATE_ERROR` | Error procesando certificado '%s': %s | Pantalla de certificados |
| `VerifactuConnectorPrivateKeyError` | No se pudo obtener la clave privada: %s. Verifique el conector de certificados. | Clave privada no disponible |
| `VERIFACTU_TROUBLESHOOTING_XML_ERROR` | Si obtiene errores "no XML document", su certificado personal no es compatible con 'sello'. Use 'normal'. | Ayuda de configuración |
| `CertificateExpired` / `CertificateExpiresSoon` / `CertificateValid` | Caducado / Caduca pronto / Válido | Insignia de caducidad en configuración (menos de 30 días = «Caduca pronto») |
| `CertificateExpirationUnreadable` / `CertificateUnreadable` / `CertificateFileNotFound` | No se pudo leer la fecha de caducidad / No se pudo leer el certificado / Archivo de certificado no encontrado | Configuración |

### Configuración, entidades y restricciones

| Clave | Texto | Cuándo |
|---|---|---|
| `VERIFACTU_TAX_IDENTITY_LOCKED` | No se puede modificar el NIF del obligado tributario porque esta entidad ya contiene registros VeriFactu encadenados. El certificado sí se puede sustituir. | Guardar configuración con otro NIF cuando ya hay huellas (2.0.0) |
| `VERIFACTU_TAX_IDENTITY_ALREADY_USED` | El NIF del obligado tributario ya está configurado en la entidad VeriFactu %s… | Otra entidad con VeriFactu declara el mismo NIF (2.2.0) |
| `VERIFACTU_SHARED_INVOICE_NUMBERING_WARNING` | Multicompany está compartiendo la numeración de facturas entre entidades… | `MULTICOMPANY_INVOICENUMBER_SHARING_ENABLED` activo (2.2.0) |
| `VERIFACTU_INVOICE_CANNOT_BE_MODIFIED` | La factura %s no puede modificarse (restricción VeriFactu) | Editar, borrar o invalidar una factura sin `PROV` |
| `THIRD_PARTY_HAS_INVOICES_NIF_NOT_MODIFIED` | CIF/NIF no actualizado. %s tiene %s facturas en VeriFactu. | Cambio de NIF de un tercero con facturas enviadas |
| `THIRD_PARTY_HAS_INVOICES_VAT_INTRA_NOT_MODIFIED` | CIF Intra no actualizado. %s tiene %s facturas en VeriFactu. | Ídem para el CIF intracomunitario |
| `VERIFACTU_CANNOT_DELETE_EXTRA_FIELD` | No se puede eliminar este campo. Los campos 'verifactu_' son esenciales para el módulo. | Borrar un campo adicional `verifactu_*` |
| `SOAP_EXTENSION_NOT_INSTALLED` | La extensión SOAP no está instalada. Instálela y reinicie el servidor web. | Falta `ext-soap` |
| `VERIFACTU_SCHEMAS_DOWNLOAD_ERROR` | Error al descargar esquemas: %s | Botón Descargar/Actualizar esquemas |
| `NOCIF_PREPAREINVOICE` / `NOLINES_PREPAREINVOICE` | Falta CIF/NIF del tercero %s en factura %s… / La factura %s no tiene líneas… | Preparación de la factura |
| `VERIFACTU_RECTIFIED_*`, `VERIFACTU_ISSUER_ID_REQUIRED`, `VERIFACTU_SERIAL_NUMBER_REQUIRED`, `VERIFACTU_ISSUE_DATE_REQUIRED`, `VERIFACTU_ISSUE_DATE_INVALID_FORMAT`, `VERIFACTU_SURCHARGE_NEGATIVE` | Validaciones del bloque de facturas rectificadas (campos obligatorios, formato `dd-mm-yyyy`, importes no negativos) | Facturas rectificativas |

### Textos fijos del código (sin traducir)

Algunos mensajes se generan directamente en inglés en `lib/functions/*.php`:

| Texto | Origen | Significado |
|---|---|---|
| `Certificate error: The certificate passphrase is incorrect or the certificate is invalid` | `execVERIFACTUCall()`, `execVERIFACTUQuery()` | `validateCertificateAndKey()` devolvió `false`; el detalle está en el registro |
| `Certificate error: No certificate configured in module settings` | `prepareCertificateLocalForVerifactu()` | `VERIFACTU_CERTIFICATE` vacío |
| `Certificate error: Certificate file not found: …` | Ídem | El fichero no está en `documents/verifactu/certificates` |
| `Neither the PHP OpenSSL extension nor proc_open is available on your server.` | Ídem | Sin `openssl_pkcs12_read()` ni `proc_open()` |
| `Private key does not match certificate or passphrase is incorrect` | `getCertificateOptions()` | Clave y certificado no casan |
| `ERROR: Tax Type not configured. Must be manually configured in the VeriFactu tab…` | `handleInvoiceCreationOrSubsanation()` | Campo Impuesto vacío |
| `ERROR: Regime Key not configured. For VAT and IGIC it is mandatory…` | Ídem | Clave de régimen vacía con IVA o IGIC |
| `ERROR: Regime Key must be empty for IPSI and Other taxes…` | Ídem | Clave de régimen informada con IPSI u Otros |
| `Error in VeriFactu submission - Status: X - Code: N - descripción` | `processInvoiceSendResponse()` | Rechazo de la AEAT; se guarda en `verifactu_error` |
| `No response received from VeriFactu server` | Ídem | `sendInvoice()` devolvió vacío |
| `Error in VERIFACTU cancellation - Status: … - Record Status: … - Code: … - Description: …` | `processCancellationError()` | Rechazo de una anulación |
| `Error: Issuer information not configured in VeriFactu` | `execVERIFACTUQuery()` | NIF o razón social vacíos al consultar |
| `Error in VeriFactu query: …` | Ídem | Excepción durante la consulta |

## Marcadores internos en `verifactu_error`

| Valor | Quién lo escribe | Efecto |
|---|---|---|
| `NO_INTERNET_CONNECTION` | Trigger `billValidate()` cuando no hay conexión | Insignia «Sin Conexión», `verifactu_incidencia = S`; la cola de reintentos la recoge |
| `SERVICE_UNAVAILABLE` | `handleInvoiceCreationOrSubsanation()` ante HTTP 503 / «Service Unavailable» | Insignia «Pendiente», incidencia S; reintento |
| `PENDING_SUBMISSION` | Trigger `billValidate()` con envío directo desactivado (2.1.0) | Insignia «Pendiente», incidencia S; reintento |
| `Exception: <mensaje>` | `execVERIFACTUCall()` al capturar una excepción | Insignia «Error» si la factura no tiene registro aceptado; si lo tiene, conserva su estado. CSV y huella no se tocan (hasta la 2.2.1 se vaciaban) |
| `Error in VeriFactu submission - Status: …` | Rechazo de la AEAT | Insignia «Error» |
| `Factura %s duplicada con importes diferentes…` | Error 3000 con diferencia | Insignia «Duplicada (Importes diferentes)» |
| `No response received from server` | Anulación sin respuesta | `verifactu_estado = Error` |
| Vacío / `NULL` | Envío correcto, cotejo correcto o `clearConnectionError()` tras un reintento | La incidencia S se conserva para documentar que hubo incidencia |

La cola de reintentos (`VerifactuUtils::getInvoicesWithConnectionErrors()`) selecciona las facturas validadas, sin `PROV`, sin huella y cuyo `verifactu_error` contiene alguno de los tres primeros marcadores. Al reenviar con éxito, `clearConnectionError()` los elimina del campo.

## Excepciones de la biblioteca (`lib/newfenix`)

### `OpenAEAT\Billing\BillingException`

Extiende `\Exception` y añade categoría, contexto y marca de tiempo (`getCategory()`, `getContext()`, `getTimestamp()`, `toArray()`). Se construye con estos métodos de fábrica:

| Método | Código | Mensaje | Categoría | Causa |
|---|---|---|---|---|
| `invalidTaxId($taxId)` | 400 | `Invalid tax ID: …` | validation | NIF inválido |
| `invalidDate($date)` | 400 | `Invalid date format: …` | validation | Fecha mal formada |
| `missingRequiredField($field)` | 400 | `Missing required field: …` | validation | Campo obligatorio ausente |
| `invalidInvoiceData($reason)` | 400 | `Invalid invoice data: …` | validation | Datos de factura no válidos |
| `authenticationFailed($reason)` | 401 | `Authentication failed: …` | authentication | Fallo de autenticación |
| `certificateError($msg, $path)` | 403 | `Certificate error: …` | authentication | Certificado |
| `serviceUnavailable($endpoint)` | 503 | `Service unavailable: …` | communication | Servicio caído; el módulo lo convierte en `SERVICE_UNAVAILABLE` |
| `soapError($msg, $prev)` | 0 | `SOAP error: …` | communication | Fallo SOAP |
| `configurationError($msg)` | 0 | `Configuration error: …` | configuration | Configuración |
| `hashGenerationFailed($reason)` | 0 | `Hash generation failed: …` | processing | Huella |
| `chainLinkageError($msg)` | 0 | `Chain linkage error: …` | processing | Encadenamiento |
| `processingFailed($msg)` | 0 | mensaje libre | processing | Genérico |

`SoapClient::executeOperation()` envuelve además cualquier `SoapFault` en una `BillingException` con el mensaje `AEAT communication failed: <fallo>` y el código numérico del `SoapFault`. Ese es el texto que verás como `Error VeriFactu: AEAT communication failed: …` cuando la conexión con la AEAT falla.

### `InvalidArgumentException` de `Invoice::validate()` (límites del XSD)

Desde la 2.0.0 el registro se valida contra los límites de `SuministroInformacion.xsd` antes de llamar al servicio:

| Mensaje | Regla |
|---|---|
| `Required field missing: <campo>` | `IDVersion`, `IDFactura`, `NombreRazonEmisor`, `TipoFactura`, `DescripcionOperacion`, `FechaHoraHusoGenRegistro`, `TipoHuella` |
| `IDFactura incomplete` | Faltan `NumSerieFactura`, `FechaExpedicionFactura` o `IDEmisorFactura` |
| `Invalid invoice type` | Tipo distinto de F1, F2, F3, R1, R2, R3, R4, R5 |
| `Issuer name cannot exceed 120 characters` | `NombreRazonEmisor` |
| `Operation description cannot exceed 500 characters` | `DescripcionOperacion` |
| `Simplified invoices cannot have recipients` | F2 y R5 no llevan destinatarios |
| `Must have at least one recipient` | El resto de tipos exige al menos uno |
| `An invoice cannot have more than 1000 recipients` | Límite del esquema |
| `Must have at least one tax breakdown detail` / `An invoice cannot have more than 12 tax breakdown details` | Entre 1 y 12 líneas de desglose |
| `Invoice must be the first record or link to a previous record` | Falta `Encadenamiento` |
| `Previous fingerprint must be a 64-character uppercase SHA-256 hash` | Huella anterior mal formada |
| `Fingerprint must be a 64-character uppercase SHA-256 hash` | Huella propia mal formada |
| `The previous fingerprint cannot be empty.` | `setChainLink()` con huella vacía |
| `Incidence must be "S" or "N"` | `setIncidence()` |

`Cancellation::validate()` lanza `Required field missing: …`, `IDFactura incomplete` y `Must have chain link to previous record or be marked as first in chain`.

### `QRGenerator`

| Mensaje | Regla |
|---|---|
| `Tax ID must be 9 characters: …` | NIF del obligado |
| `Invalid DNI control letter: …` / `Invalid NIE control letter: …` | Letra de control |
| `Unrecognized tax ID format: …` | Ni DNI, ni NIE, ni patrón de CIF |
| `Serial number must be 1-60 characters, got N` / `Serial number contains invalid characters` | Referencia de la factura (solo ASCII imprimible) |
| `Date must be DD-MM-YYYY format: …` / `Invalid calendar date: …` | Fecha |
| `Amount integer part exceeds 12 digits` | Importe |
| `QR generation failed: …` (`RuntimeException`) | Fallo de `chillerlan/php-qrcode` |

### Otras

- `RuntimeException` de `prepareLocalCertificate()` con el texto de `describeCertificateExtractionFailure()`: «The certificate uses a legacy encryption algorithm…», «The certificate password is not correct…» o «Could not extract the certificate…», siempre seguido de `OpenSSL output: …`. Desde la 2.0.0 esta función ya no hace `die()`.
- `Exception` con `VERIFACTU_CHAIN_LOCK_BUSY` o `VERIFACTU_CHAIN_READ_FAILED` (ya traducidos) desde `functions.submission.php` y `functions.hash.php`.

## Códigos y elementos de la respuesta de la AEAT que el módulo interpreta

El módulo no mantiene una tabla propia de códigos de la AEAT. Interpreta los campos estructurales de `RespuestaSuministro.xsd` y `RespuestaConsultaLR.xsd` y un único código numérico, el 3000. Cualquier otro `CodigoErrorRegistro` se guarda y se muestra tal cual, junto con su `DescripcionErrorRegistro`.

| Elemento | Valores | Qué hace el módulo |
|---|---|---|
| `EstadoEnvio` | `Correcto` | Envío aceptado: insignia «Enviada», CSV, huella, carga y resumen guardados |
| | `ParcialmenteCorrecto` | Solo se acepta si **todas** las `RespuestaLinea` están en `Correcto` o `AceptadoConErrores`; entonces insignia «Parcial». Con alguna línea `Incorrecto`, se trata como rechazo (PR #36, 2.0.0) |
| | `Incorrecto` (u otro) | Rechazo: insignia «Error», `verifactu_error` con estado, código y descripción |
| `RespuestaLinea/EstadoRegistro` | `Correcto`, `AceptadoConErrores`, `Incorrecto` | Determina la aceptación línea a línea (`isAEATResponseAccepted()`) |
| `RespuestaLinea/CodigoErrorRegistro` | `3000` | Registro duplicado: consulta la AEAT y compara importes (véase «Duplicados») |
| | resto | Se guarda como `Code: N - descripción` |
| `RespuestaLinea/IDFactura` | `IDEmisorFactura`, `NumSerieFactura`, `FechaExpedicionFactura` | Se guarda en `verifactu_id_factura` (JSON) y `verifactu_fecha_factura` |
| `CSV` | texto | `verifactu_csv_factura` |
| `DatosPresentacion/TimestampPresentacion` | fecha-hora | Aparece en «Última respuesta» (`Sent: …`); en anulaciones se guarda como fecha de generación |
| `TiempoEsperaEnvio` | segundos | `registerAEATWaitTime()` guarda `VERIFACTU_NEXT_SEND_AT = ahora + segundos`; lo respetan solo los reintentos automáticos (PR #37) |
| `RespuestaLinea/RegistroDuplicado` | `IdPeticionRegistroDuplicado`, `EstadoRegistroDuplicado` | En anulaciones, `EstadoRegistroDuplicado = Anulada` se considera éxito («la factura ya estaba anulada») |
| `RespuestaLinea/Operacion/TipoOperacion` | texto | `verifactu_tipo_operacion` en anulaciones |
| `ResultadoConsulta` (consultas) | `ConDatos` / `SinDatos` | Con datos, se aplican filtros posteriores por número de factura y contraparte; si la factura buscada no aparece, el módulo fuerza `SinDatos` para no devolver otra factura del mismo día |
| `RegistroRespuestaConsultaFactuSistemaFacturacion/DatosRegistroFacturacion` | `ImporteTotal`, `Huella`, `Desglose` | Importe para el cotejo (con tolerancia 0,01) y huella para recuperarla en duplicados |
| `SoapFault` (transporte) | cualquier | `BillingException` «AEAT communication failed: …»; si el mensaje o el código indican 503 / «Service Unavailable» / «HTTP 503», se marca `SERVICE_UNAVAILABLE` y la factura queda pendiente |

Valores que el módulo **envía** y que conviene reconocer en los rechazos:

| Campo enviado | Valores | Notas |
|---|---|---|
| `RechazoPrevio` | `S` (la AEAT rechazó antes el registro), `X` (nunca llegó a la AEAT) | Corregido en la 2.0.0 (PR #38): antes se enviaba siempre `X` |
| `Subsanacion` | `S` | En la acción «Enviar Modificación» |
| `SinRegistroPrevio` | `S` | Anulación «sin registro previo» |
| `RemisionVoluntaria/Incidencia` | `S` / `N` | `S` en reenvíos tras incidencia |
| `Encadenamiento` | `PrimerRegistro = S` o `RegistroAnterior{…, Huella}` | Desde la 2.2.0 también en anulaciones sin registro anterior |
| `TipoHuella` | `01` | SHA-256 |

> **Importante:** el error 4118 que menciona el README (estructura del mensaje SOAP) no recibe tratamiento especial en el código; revisa formato de fechas, datos de emisor y destinatario y certificado. Para el significado oficial de cualquier código, la página de ayuda del módulo enlaza el fichero `errores.properties` publicado por la AEAT en `prewww2.aeat.es/static_files/common/internet/dep/aplicaciones/es/aeat/tikeV1.0/cont/ws/errores.properties`.

## Véase también

- [[Resolución de problemas|Resolucion-de-problemas]]
- [[Respuestas de la AEAT|Respuestas-de-la-AEAT]]
- [[Registro y depuración|Registro-y-depuracion]]
- [[Reintentos y envíos pendientes|Reintentos-y-envios-pendientes]]
- [[Biblioteca newfenix|Biblioteca-newfenix]]
- [[Certificados digitales|Certificados-digitales]]
- [[Glosario|Glosario]]
