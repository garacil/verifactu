# Conexión SOAP y entornos

El módulo remite los registros de facturación al servicio web `SistemaFacturacion` de la AEAT mediante SOAP 1.1 sobre HTTPS, autenticándose con el certificado digital del obligado tributario en la capa TLS. La clase `SoapClient` de la biblioteca interna envuelve al `\SoapClient` de PHP; `Config` resuelve las direcciones según el entorno (pruebas o producción) y el tipo de certificado; y las funciones del módulo deciden el entorno, preparan el certificado y tratan los errores de comunicación. Esta página recoge las URL exactas, las opciones del cliente, la autenticación, los tiempos de espera, las trazas, la herencia del entorno del sistema al invocar OpenSSL, los errores habituales y qué implica cambiar de entorno.

## Entornos y direcciones

Las direcciones están en `lib/newfenix/src/Config.php` (`ENDPOINT_REGISTRY`, `SERVICE_PATHS`, `VALIDATION_HOSTS`, `QR_PATHS`). El módulo usa siempre el servicio `verifactu`; el de requerimientos (`RequerimientoSOAP`) existe en la biblioteca pero ninguna función del módulo lo invoca.

| Uso | Pruebas (`test`) | Producción (`production`) |
|---|---|---|
| Servicio VeriFactu, certificado `normal` | `https://prewww1.aeat.es/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSOAP` | `https://www1.agenciatributaria.gob.es/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSOAP` |
| Servicio VeriFactu, certificado `sello` | `https://prewww10.aeat.es/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSOAP` | `https://www10.agenciatributaria.gob.es/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSOAP` |
| Requerimientos (no usado por el módulo) | mismo host, `/wlpl/TIKE-CONT/ws/SistemaFacturacion/RequerimientoSOAP` | ídem |
| Cotejo del QR (VeriFactu) | `https://prewww2.aeat.es/wlpl/TIKE-CONT/ValidarQR` | `https://www2.agenciatributaria.gob.es/wlpl/TIKE-CONT/ValidarQR` |
| Cotejo del QR (no VeriFactu) | `https://prewww2.aeat.es/wlpl/TIKE-CONT/ValidarQRNoVerifactu` | `https://www2.agenciatributaria.gob.es/wlpl/TIKE-CONT/ValidarQRNoVerifactu` |
| WSDL remoto de respaldo | `https://prewww2.aeat.es/static_files/common/internet/dep/aplicaciones/es/aeat/tikeV1.0/cont/ws/SistemaFacturacion.wsdl` (mismo para ambos entornos) | |

`QRGenerator` lleva las mismas URL de cotejo en su propia constante `ENDPOINTS`.

### Cómo se decide el entorno

`getEnvironment()` (`lib/functions/functions.configuration.php`) devuelve `'production'` si la constante `VERIFACTU_FORCE_PRODUCTION_ENVIRONMENT` de la entidad no está vacía. Si no, compara la fecha actual en la zona `Europe/Madrid` con la fecha de transición: `2027-07-01 00:00:00` cuando `VERIFACTU_COMPANY_TYPE` es `autonomo` y `2027-01-01 00:00:00` en el resto de casos (`sociedad`); antes de esa fecha devuelve `'test'` y después `'production'`. Estas fechas se ajustaron en la 1.0.3 (README: «Fechas obligatorias VeriFactu actualizadas»).

La pantalla de configuración muestra el resultado en `VERIFACTU_ENVIRONMENT_INFO` («Producción (Forzado)», «Producción» o «Pruebas (producción obligatoria desde …)») y ofrece las acciones `setproduction` y `settest`, que crean o borran la constante forzada. La API REST expone lo mismo en `toProduction` y `toTest` (ver [[API REST|API-REST]]).

### Tipo de certificado

`VERIFACTU_CERT_TYPE` (`normal` por defecto, o `sello`) se pasa al constructor de `VerifactuManager` como modo de autenticación. `Config::AUTH_MAPPING` traduce `normal`/`standard` a los servicios «personal» (`www1`/`prewww1`) y `sello`/`seal` a los «automated» (`www10`/`prewww10`). El fichero de idioma lo explica en `VERIFACTU_CERT_TYPE_TOOLTIP`: «Normal: compatible con todos los certificados (recomendado). Sello: solo certificados empresariales de sello. Si obtiene errores "no XML document" en consultas, use 'normal'».

## Construcción del cliente SOAP

`Manager::provisionTransport()` crea un `Config` (entorno, servicio, tipo de certificado, credenciales y, si hay caché, ruta del WSDL local) y con él un `SoapClient` (`lib/newfenix/src/SoapClient.php`). Cada operación construye un `\SoapClient` nuevo en `buildTransport()`:

```php
$client = new \SoapClient($wsdlLocation, $options);
$client->__setLocation($endpoint);   // dirección del servicio según entorno y certificado
$result = $client->__soapCall($operation, [$data]);
```

| Opción | Valor en la biblioteca (`TRANSPORT_SETTINGS`) | Valor efectivo cuando llama el módulo |
|---|---|---|
| `soap_version` | `SOAP_1_1` | `SOAP_1_1` |
| `encoding` | `UTF-8` | `UTF-8` |
| `trace` | `true` | `true` |
| `exceptions` | `true` | `true` |
| `cache_wsdl` | `WSDL_CACHE_DISK` con WSDL local, `WSDL_CACHE_NONE` sin él | `WSDL_CACHE_NONE` (lo impone `getCertificateOptions()`) |
| `stream_context` | `createSecurityContext()`: `verify_peer` y `verify_peer_name` a `true`, más `local_cert` y `passphrase` | El contexto de `getCertificateOptions()`: `verify_peer` `false`, `verify_peer_name` `false`, `allow_self_signed` `true` |
| `local_cert`, `passphrase` | Tomados de `Config` | Ruta del PEM y contraseña que devuelve `getCertificateOptions()` |

La segunda columna se explica por el orden de fusión: `assembleTransportOptions()` hace `array_merge($base, $certSettings)`, de modo que cualquier clave presente en el array de credenciales que el módulo pasa a `sendInvoice()` prevalece sobre la de la biblioteca. `getCertificateOptions()` (`lib/functions/functions.certificates.php`) devuelve `local_cert`, `passphrase` (si hay), `trace`, `exceptions`, `cache_wsdl => WSDL_CACHE_NONE` y su propio `stream_context` sin verificación del par.

> **Nota:** con la configuración que envía el módulo, PHP no verifica el certificado del servidor de la AEAT en el enlace TLS; la autenticación del cliente con el certificado del obligado sí se realiza. Quien use la biblioteca de forma autónoma y no pase `stream_context` en las credenciales obtiene el contexto con verificación activada.

Las operaciones SOAP son dos: `RegFactuSistemaFacturacion` (`OP_REGISTRATION`, usada para altas, subsanaciones y anulaciones, que comparten servicio) y `ConsultaFactuSistemaFacturacion` (`OP_QUERY`). `extractPayload()` admite el payload envuelto en la clave de la operación o desnudo.

## Autenticación con certificado

La AEAT autentica al obligado por el certificado de cliente del enlace TLS. El módulo entrega a PHP un único fichero PEM con certificado y clave privada:

1. `getCertificateOptions()` llama a `prepareCertificateForVerifactu()` → `prepareCertificateLocalForVerifactu()`, que lee `VERIFACTU_CERTIFICATE` (nombre del fichero en `documents/…/verifactu/certificates/`) y `VERIFACTU_CERTIFICATE_KEY` (contraseña).
2. Si el fichero no termina en `.pem` y hay contraseña, lo convierte con `prepareLocalCertificate()`: la clave privada resultante se cifra con `$dolibarr_main_instance_unique_id` (o con la contraseña original si aquel está vacío), y las constantes `VERIFACTU_CERTIFICATE` y `VERIFACTU_CERTIFICATE_KEY` se actualizan para apuntar al PEM. La extracción prueba primero `openssl_pkcs12_read()` de PHP y solo recurre al binario `openssl` cuando hace falta (contenedores *legacy*), sin exigir `proc_open` (2.0.0). Los PEM se escriben con permisos `0600`.
3. `validateCertificateAndKey()` comprueba que el PEM se lee como X.509, que la fecha actual está dentro de `validFrom`/`validTo` (si no, `VERIFACTU_CERT_ERROR_EXPIRED`), que la clave privada se abre con la contraseña y que corresponde al certificado. Se ejecuta dos veces antes de cada envío: dentro de `getCertificateOptions()` y de nuevo en `execVERIFACTUCall()`.
4. El array resultante viaja hasta `Config::setCertOptions()` (que toma de él `local_cert` y `passphrase`) y se fusiona con las opciones del `\SoapClient`.

Si algo falla, la causa queda en `$GLOBALS['verifactu_cert_error']` y `execVERIFACTUCall()` muestra «Certificate error: …» sin llegar a conectar. El detalle de formatos, conversión y alojamientos compartidos está en [[Certificados digitales|Certificados-digitales]].

## Tiempos de espera

| Dónde | Valor | Notas |
|---|---|---|
| Conexión y lectura SOAP | No se fija `connection_timeout` ni ningún otro tiempo en las opciones del cliente | Rige el valor de `default_socket_timeout` de PHP |
| Descarga de esquemas | 30 segundos por fichero (`SchemaManager::downloadSchemas()`) | |
| Comprobación de conexión a Internet | 3 segundos por sonda (`checkInternetConnection($timeout = 3)`) | Varias sondas encadenadas hasta que una responde |
| Bloqueo de la cadena | `VERIFACTU_CHAIN_LOCK_TIMEOUT`, 30 segundos por defecto | Espera a que termine otra validación concurrente; si vence, `VERIFACTU_CHAIN_LOCK_BUSY` |
| Control de flujo de la AEAT | `TiempoEsperaEnvio` de cada respuesta, guardado en `VERIFACTU_NEXT_SEND_AT` | Solo lo respetan los reintentos automáticos; ver más abajo |

### `TiempoEsperaEnvio`

Cada respuesta de la AEAT incluye `TiempoEsperaEnvio` (segundos que pide esperar antes del siguiente envío). `registerAEATWaitTime()` (`lib/functions/functions.response.php`) guarda `dol_now() + segundos` en la constante `VERIFACTU_NEXT_SEND_AT` de la entidad, y `getAEATWaitTimeRemaining()` devuelve lo que queda. `execVERIFACTUCall()` recibe un tercer parámetro `$enforceWaitTime`: el trigger de validación lo llama con `false` (abortar allí revertiría la factura a borrador) y los reintentos automáticos de `VerifactuUtils` con `true`, en cuyo caso el envío se pospone con el aviso `VERIFACTU_AEAT_WAIT_REQUIRED`. Incorporado en la 2.0.0 (PR #37); `tests/AEATWaitTimeTest.php` lo cubre.

## Cabeceras, trazas y registro

- La biblioteca activa `trace`, y tras cada llamada `captureTrace()` guarda en memoria las cabeceras y cuerpos de petición y respuesta. Están disponibles con `SoapClient::getLastRequest()`, `getLastResponse()`, `getLastRequestHeaders()`, `getLastResponseHeaders()` y `getDebugInfo($asHtml)`, también a través de `Manager::getDebugInfo()`. El módulo no muestra ni persiste estas trazas XML.
- Lo que sí persiste el módulo es el **registro enviado** como array JSON en el campo `verifactu_payload` (`Manager::getLastSendInvoiceData()`), que la [[pestaña VeriFactu|Pestana-VeriFactu-de-la-factura]] muestra con `dumper()`, y un **resumen** de la respuesta en `verifactu_ultima_salida` (`buildVerifactuOutputSummary()`).
- Todo el proceso escribe en el registro de Dolibarr con `dol_syslog()` y el prefijo `VERIFACTU`: configuración del sistema, entorno y tipo de certificado, SQL de la cadena, desglose línea a línea, huella capturada y resultado. Ver [[Registro y depuración|Registro-y-depuracion]].
- `prettyXML()` (`lib/functions/functions.utilities.php`) formatea un XML para lectura; está disponible para depuración.

## Herencia del entorno y OpenSSL en el servidor web

Cuando el certificado exige el binario `openssl` (contenedores PKCS#12 con cifrado *legacy* bajo OpenSSL 3), `runOpensslCommand()` lo lanza con `proc_open()`. En el SAPI web, `variables_order` suele ser `GPCS` sin `E`, así que `$_ENV` llega vacío; por eso la función construye el entorno del proceso hijo a partir de `getenv()`, completa con `$_ENV` y, si aún falta `PATH`, con `$_SERVER['PATH']`. Sin esto `proc_open` no encontraría el binario. La contraseña se pasa en la variable `VERIFACTU_P12_PASS` y el comando usa `-password env:VERIFACTU_P12_PASS`, de modo que no aparece en la línea de órdenes ni en la lista de procesos (2.0.0). En Windows el comando se pasa como cadena con `escapeshellarg()`; en el resto de sistemas como array, sin *shell*.

`runOpensslPkcs12()` reintenta con `-legacy` solo si el error de la primera pasada es de cifrado *legacy* (`isLegacyCipherOpensslError()`) y el binario es OpenSSL 3 o superior (`opensslCliSupportsLegacyFlag()`, expresión regular sobre `openssl version`): en OpenSSL 1.1.1 o LibreSSL el modificador no existe. En alojamientos donde `proc_open` está en `disable_functions` (`isPhpFunctionAvailable()` lo comprueba también en `php.ini`), solo funciona la vía nativa `openssl_pkcs12_read()`. La conexión SOAP en sí no depende del binario: usa la extensión `openssl` de PHP.

## Comprobación de conexión a Internet

`billValidate()` llama una vez a `checkInternetConnection()` antes de enviar. La función prueba en orden: peticiones HTTP `HEAD` (con cURL si está disponible, sin verificar TLS) a `https://www.google.com/generate_204`, `https://httpbin.org/status/200`, `https://1.1.1.1/`, `https://www.microsoft.com/favicon.ico` y `http://detectportal.firefox.com/success.txt`; resolución DNS de `google.com`, `cloudflare.com`, `microsoft.com`, `github.com` y `stackoverflow.com`; conexiones de socket al puerto 53 de `8.8.8.8`, `1.1.1.1`, `9.9.9.9`, `208.67.222.222` y `8.8.4.4`; y, si `exec` existe y no es un Windows sin `ping`, un `ping` a `8.8.8.8`, `1.1.1.1` y `9.9.9.9`. Devuelve `true` en cuanto una sonda responde. No comprueba la disponibilidad de los servidores de la AEAT: un cortafuegos que permita salir a esos destinos pero no a `*.aeat.es` o `*.agenciatributaria.gob.es` dará conexión «disponible» y después un error SOAP.

## Errores típicos

| Síntoma | Origen | Tratamiento en el módulo |
|---|---|---|
| Error SOAP **4118** | Según el README, «un problema con la estructura del mensaje SOAP»: revisar el formato de fecha (`DD-MM-AAAA`), los datos de emisor y destinatario y la configuración del certificado | No hay tratamiento específico en el código; llega como respuesta de error de la AEAT y se guarda en `verifactu_error` |
| `SOAP-ERROR: Parsing WSDL: …` | No se pudo leer el WSDL local ni el remoto | Excepción genérica; revisar la caché de [[esquemas|Esquemas-WSDL-y-XSD]] |
| Errores TLS (`SSL: Handshake`, `Could not connect to host`, `unable to get local issuer`…) | Certificado de cliente rechazado, servicio inaccesible o cortafuegos | Llegan como `\SoapFault` → `BillingException` «AEAT communication failed: …» → estado «Error» con `Exception: …` |
| «Certificate error: …» antes de conectar | `getCertificateOptions()` o `validateCertificateAndKey()` fallaron (fichero ausente, contraseña incorrecta, fuera de validez) | No se conecta; ver [[Certificados digitales|Certificados-digitales]] |
| Sin conexión a Internet | `checkInternetConnection()` devuelve `false` en la validación | La factura se valida igualmente con estado «Sin Conexión» (`VERIFACTU_STATUS_NO_INTERNET`), `verifactu_error = NO_INTERNET_CONNECTION`, `Incidencia = S`; la recoge la cola de reintentos |
| Servicio no disponible | En `handleInvoiceCreationOrSubsanation()`, excepción con código 503 o cuyo mensaje contiene `service unavailable`, `Service Unavailable` o `HTTP 503` | Estado «Pendiente» (`VERIFACTU_STATUS_PENDING_SERVICE`), `verifactu_error = SERVICE_UNAVAILABLE`, `Incidencia = S`; la función devuelve `true`, así que la factura queda validada y pendiente de reenvío |
| Envío directo desactivado | `VERIFACTU_DIRECT_CALL_ON_VALIDATE` vacío y factura no procedente de TakePOS | Estado «Pendiente», `verifactu_error = PENDING_SUBMISSION`, `Incidencia = S` (desde la 2.1.0) |
| «No response received from VeriFactu server» | `sendInvoice()` devolvió un valor vacío | Estado «Error» |
| Bloqueo de la cadena ocupado | Otra validación en curso más de `VERIFACTU_CHAIN_LOCK_TIMEOUT` segundos | Excepción `VERIFACTU_CHAIN_LOCK_BUSY`; reintentar |
| «La AEAT requiere esperar %s segundos» | `TiempoEsperaEnvio` pendiente en un reintento automático | El reintento se pospone |
| Error **3000** (registro duplicado) | La AEAT ya tiene el registro | `manejarFacturaDuplicada()` consulta la AEAT y compara importes; ver [[Respuestas de la AEAT|Respuestas-de-la-AEAT]] |

La excepción que envuelve un `\SoapFault` conserva el fault original como excepción previa (`getPrevious()`), y su código es `(int) $fault->getCode()`, que para los faults con código no numérico (por ejemplo `HTTP`) vale `0`. La detección de servicio no disponible se apoya por eso sobre todo en el texto del mensaje. Los marcadores `NO_INTERNET_CONNECTION`, `SERVICE_UNAVAILABLE` y `PENDING_SUBMISSION` son los que busca `VerifactuUtils::getInvoicesWithConnectionErrors()` para reintentar, y se limpian de `verifactu_error` tras un envío correcto manteniendo `Incidencia = S`. Ver [[Reintentos y envíos pendientes|Reintentos-y-envios-pendientes]] y [[Códigos de error|Codigos-de-error]].

## Cambiar de entorno y consecuencias

Formas de cambiar: la acción «cambiar a producción / a pruebas» de la pantalla de configuración (constante `VERIFACTU_FORCE_PRODUCTION_ENVIRONMENT` de la entidad), la API REST (`toProduction`/`toTest`), o dejar que la fecha de transición lo haga sola según `VERIFACTU_COMPANY_TYPE`.

Qué cambia al hacerlo:

- **Cadena separada por entorno.** `getLastInvoiceHash()` (`lib/functions/functions.hash.php`) busca el registro anterior con `verifactu_entorno = <entorno actual>`, y el nombre del bloqueo es `verifactu_chain_<entidad>_<entorno>`. La primera factura remitida en producción se envía como `PrimerRegistro` aunque existan facturas de pruebas, y las cadenas de pruebas y producción no se esperan entre sí. Ver [[Encadenamiento y huella|Encadenamiento-y-huella]].
- **Marca en cada factura.** El campo `verifactu_entorno` guarda a qué entorno se remitió cada registro, y la URL del QR (y por tanto el QR impreso) apunta a `prewww2.aeat.es` o a `www2.agenciatributaria.gob.es` según el entorno vigente **en el momento de generarlo** (`getQRUrl()` llama a `getEnvironment()`).
- **Identidad fiscal fijada.** `hasVerifactuFiscalRecords()` no distingue entornos: la primera huella, aunque sea de pruebas, bloquea el cambio de `VERIFACTU_HOLDER_NIF` en la configuración (`VERIFACTU_TAX_IDENTITY_LOCKED`). El certificado sí puede sustituirse.
- **Misma caché de esquemas y mismo certificado.** Los esquemas se descargan del servidor estático de pruebas para ambos entornos; el certificado es el mismo fichero. Lo único que cambia en el transporte es la dirección fijada con `__setLocation()`.
- **Multiempresa.** La constante forzada, la cadena, el bloqueo y la caché son por entidad; cambiar el entorno de una entidad no afecta a las demás. Ver [[Multiempresa|Multiempresa]].

> **Importante:** las facturas de pruebas ya validadas conservan su numeración definitiva en Dolibarr y sus datos VeriFactu de pruebas. Conviene decidir el entorno antes de emitir facturas reales y no alternar entre entornos con la misma serie.

## Véase también

- [[Esquemas WSDL y XSD|Esquemas-WSDL-y-XSD]] — el WSDL que se pasa al cliente y su caché local.
- [[Certificados digitales|Certificados-digitales]] — formatos, conversión a PEM y errores de certificado.
- [[Biblioteca newfenix|Biblioteca-newfenix]] — `Config`, `SoapClient`, `Manager` y `BillingException`.
- [[Configuración|Configuracion]] — entorno, tipo de empresa y tipo de certificado.
- [[Encadenamiento y huella|Encadenamiento-y-huella]] — cadena por entidad y entorno.
- [[Reintentos y envíos pendientes|Reintentos-y-envios-pendientes]] — qué pasa con `NO_INTERNET_CONNECTION` y `SERVICE_UNAVAILABLE`.
- [[Respuestas de la AEAT|Respuestas-de-la-AEAT]] y [[Códigos de error|Codigos-de-error]] — interpretación de las respuestas y códigos.
- [[Registro y depuración|Registro-y-depuracion]] — dónde ver las trazas.
- [[Requisitos|Requisitos]] — extensiones y salida a Internet necesarias.
