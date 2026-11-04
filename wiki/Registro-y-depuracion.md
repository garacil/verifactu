# Registro y depuración

Cómo activar el registro de eventos de Dolibarr para el módulo VeriFactu (2.2.2), qué escribe el módulo y con qué prefijos, dónde quedan los datos de cada envío a la AEAT, cómo leer los errores fatales del servidor web, cómo reproducir un problema contra el entorno de pruebas de la AEAT y qué información adjuntar al abrir una incidencia en GitHub.

## Activar el registro de eventos de Dolibarr

El módulo escribe con `dol_syslog()`, así que depende del módulo **Registro de eventos (Syslog)** de Dolibarr:

1. Inicio > Configuración > Módulos/Aplicaciones: activar **Registro de eventos (Syslog)** si no lo está.
2. Inicio > Configuración > Registro de eventos: elegir la salida **Fichero**. El destino por defecto es `DOL_DATA_ROOT/dolibarr.log`, es decir, `documents/dolibarr.log` en la carpeta de datos de Dolibarr (constante `SYSLOG_FILE`).
3. En la misma pantalla, poner el **nivel** en `LOG_DEBUG` (7). Dolibarr descarta cualquier mensaje cuyo nivel sea superior al configurado (`SYSLOG_LEVEL`), así que con el nivel por defecto solo verás avisos y errores del módulo, no el recorrido completo de un envío.

Cuando termines de depurar, vuelve a `LOG_WARNING` o `LOG_ERR`: en `LOG_DEBUG` el módulo escribe las consultas SQL de la cadena y el JSON de la identidad del sistema en cada envío.

> **Importante:** el registro contiene el NIF del obligado, referencias de facturas, importes y consultas SQL. Antes de compartirlo, anonimiza lo que proceda. La contraseña del certificado nunca se escribe en el registro ni pasa por la línea de comandos (se entrega a OpenSSL por variable de entorno desde la 2.0.0).

Para seguir el registro en directo:

```bash
tail -f /ruta/a/documents/dolibarr.log | grep -i verifactu
```

## Qué escribe el módulo

### Prefijos

| Prefijo | Dónde se usa | Ejemplos reales |
|---|---|---|
| `VERIFACTU:` | Casi todo el módulo (`lib/functions/*.php`) | `VERIFACTU: getLastInvoiceHash SQL: SELECT fe.verifactu_huella as hash, …` · `VERIFACTU: Certificate and key validated successfully for invoice FA2026-0001` · `VERIFACTU: System configured: {"NombreRazon":…}` · `VERIFACTU: Environment: test, Cert type: normal` · `VERIFACTU: PRE-CHECK - Invoice FA2026-0001 does NOT exist in AEAT` · `VERIFACTU: Hash captured during submission for invoice FA2026-0001: 3A9F…` · `VERIFACTU: chain lock verifactu_chain_1_production still busy after 30s` · `VERIFACTU: could not read the chain to find the previous record: …` · `VERIFACTU: Detected error 3000 (duplicate) - Querying AEAT to verify amounts` |
| `VERIFACTU execVERIFACTUCall` | Inicio y fin de cada envío | `VERIFACTU execVERIFACTUCall START: Invoice id=12 ref=FA2026-0001 action=Alta` · `VERIFACTU execVERIFACTUCall: SUCCESS, returning TRUE for invoice FA2026-0001 - EstadoEnvio=Correcto` · `VERIFACTU execVERIFACTUCall: AEAT error response, returning FALSE for invoice FA2026-0001` |
| `VERIFACTU TRIGGER:` / `VERIFACTU TRIGGER billValidate` | `core/triggers/interface_999_modVerifactu_VerifactuTriggers.class.php` | `VERIFACTU TRIGGER billValidate START: Invoice id=12 ref=(PROV12) newref=FA2026-0001 status=0 thirdparty_id=3` · `VERIFACTU TRIGGER: About to call execVERIFACTUCall for invoice id=12 …` · `VERIFACTU TRIGGER: execVERIFACTUCall returned: false for invoice FA2026-0001` · `VERIFACTU TRIGGER: VeriFactu failed for invoice FA2026-0001 - Reverting to draft status` · `VERIFACTU TRIGGER: Invoice 12 reverted to draft with ref (PROV12)` · `VERIFACTU TRIGGER billValidate: direct submission disabled for FA2026-0001, invoice left pending for the retry queue` |
| `VERIFACTU ERROR:` | Rechazos y ausencia de respuesta | `VERIFACTU ERROR: Error in VeriFactu submission - Status: Incorrecto - Code: 3000 - Registro duplicado` · `VERIFACTU ERROR: No response received from VeriFactu server` |
| `Verifactu Error:` | Excepciones capturadas en `execVERIFACTUCall()` | `Verifactu Error: Se está encadenando otra factura en este momento…` |
| `VERIFACTU DEBUG:` | Extracción de certificados en `admin/managecertificates.php` | `VERIFACTU DEBUG: Iniciando extracción PHP nativa - openssl_pkcs12_read…` · `VERIFACTU DEBUG: Resultado openssl_pkcs12_read: …` |
| `VERIFACTU DEBUG SURCHARGE:` / `VERIFACTU DEBUG NORMAL:` | Desglose de líneas en `functions.submission.php` | `VERIFACTU DEBUG NORMAL: Calling addDesglose with taxType='01' (length: 2)` |
| `VerifactuUtils::…` | Cola de reintentos (`class/verifactu.utils.php`) | `VerifactuUtils::sendInvoicesWithErrors - Starting scheduled task` · `VerifactuUtils::getInvoicesWithConnectionErrors SQL: …` · `VerifactuUtils::processInvoiceWithConnectionError - Processing invoice FA2026-0001 with original date: 03/09/2026` · `VerifactuUtils::clearConnectionError - Connection error cleared for invoice FA2026-0001 (Incident='S' maintained per regulations)` |
| `billValidate:` | Procesado de pendientes antes de validar | `billValidate: No pending invoices to process before validating invoice FA2026-0001` |
| `Verifactu TCPDI:` / `Verifactu afterPDFCreation QR Error:` | QR en el PDF (`class/actions_verifactu.class.php`) | `Verifactu TCPDI: QR added successfully to PDF` · `Verifactu TCPDI: QR position out of page bounds with required margins` · `Verifactu TCPDI: TCPDI class not found` |
| `ActionsVerifactu::moreHtmlStatus error:` / `::formDolBanner error:` | QR en la ficha de la factura | Errores del generador de QR (por ejemplo, NIF con formato inválido) |
| `Trigger 'VerifactuTriggers' for action 'BILL_VALIDATE' launched by …` | Todos los triggers (mensaje estándar de Dolibarr) | Identifica qué trigger se ejecutó y para qué `id` |
| `VERIFACTU: ficheros declarados para el hash de integridad no encontrados: …` | `conf/declaracion_responsable.conf.php` | Un fichero de la lista de integridad no existe |

### Niveles

- `LOG_DEBUG`: recorrido interno (SQL, configuración, cada línea de desglose, respuestas de consulta).
- `LOG_INFO`: hitos (factura enviada, anulación creada, pendientes procesados, huella ausente al abrir la cadena).
- `LOG_WARNING`: situaciones que no detienen el proceso (sin conexión, servicio no disponible, espera de la AEAT, bloqueo ocupado, línea al 0 % sin exención, retroceso a `insertExtraFields()`).
- `LOG_ERR`: rechazos de la AEAT, errores de certificado, fallos de base de datos, excepciones, reversión a borrador, recorte de la identidad declarada.

### Recorrido esperado de una validación con envío directo

Con el nivel en `LOG_DEBUG`, una validación correcta deja esta secuencia (abreviada):

```text
Trigger 'VerifactuTriggers' for action 'BILL_VALIDATE' launched by … id=12
VERIFACTU TRIGGER billValidate START: Invoice id=12 ref=(PROV12) newref=FA2026-0001 status=0 thirdparty_id=3
billValidate: No pending invoices to process before validating invoice (PROV12)
VERIFACTU TRIGGER: About to call execVERIFACTUCall for invoice id=12 …
VERIFACTU execVERIFACTUCall START: Invoice id=12 ref=FA2026-0001 action=Alta
VERIFACTU: PRE-CHECK - Verifying if invoice FA2026-0001 exists in AEAT
VERIFACTU: Starting query
…
VERIFACTU: PRE-CHECK - Invoice FA2026-0001 does NOT exist in AEAT
VERIFACTU: Validating certificate and private key
VERIFACTU: Certificate and key validated successfully for invoice FA2026-0001
VERIFACTU: System configured: {"NombreRazon":"…","NIF":"…","NombreSistemaInformatico":"Dolibarr Verifactu Module","IdSistemaInformatico":"DV","Version":"2.2.2",…}
VERIFACTU: Environment: test, Cert type: normal
VERIFACTU: getLastInvoiceHash SQL: SELECT fe.verifactu_huella as hash, f.ref as invoice_number, f.datef as invoice_date FROM llx_facture f …
VERIFACTU DEBUG NORMAL: Calling addDesglose with taxType='01' (length: 2)
VERIFACTU: Normal breakdown - Base: 100, VAT: 21
VERIFACTU: Invoice FA2026-0001 sent successfully
VERIFACTU: Hash captured during submission for invoice FA2026-0001: 3A9F…
VERIFACTU: Success data saved successfully in independent DB for invoice FA2026-0001
VERIFACTU execVERIFACTUCall: SUCCESS, returning TRUE for invoice FA2026-0001 - EstadoEnvio=Correcto
VERIFACTU TRIGGER: execVERIFACTUCall returned: true for invoice FA2026-0001
VERIFACTU TRIGGER: Returning 1 (success) for invoice FA2026-0001
```

Si algo falla, la secuencia se corta en el punto exacto y aparece `VERIFACTU ERROR:` o `Verifactu Error:` seguido de `VERIFACTU TRIGGER: VeriFactu failed … - Reverting to draft status`.

## Qué queda guardado de cada envío

El módulo no escribe el XML SOAP en disco. Lo que persiste está en los campos adicionales de la factura ([[Campos adicionales|Campos-adicionales]]):

| Campo | Contenido |
|---|---|
| `verifactu_payload` («Datos XML») | La estructura enviada (`RegFactuSistemaFacturacion`) en JSON, tal como la devolvió `Manager::getLastSendInvoiceData()` |
| `verifactu_ultima_salida` («Última respuesta») | Resumen `Status: Correcto \| CSV: … \| Sent: 2026-09-19T12:00:00+02:00 \| Record: Correcto`; en errores añade `\| Error Code: N \| Error: descripción`; en anulaciones, la respuesta completa en JSON |
| `verifactu_error` | Último error o marcador interno |
| `verifactu_csv_factura`, `verifactu_huella`, `verifactu_fecha_hora_generacion`, `verifactu_id_factura`, `verifactu_entorno`, `verifactu_modo` | Datos del registro aceptado |

### `lib/dumper.php`

Es una utilidad de presentación: `dumper($data, $return = false, $title = '')` imprime (o devuelve, con `$return = true`) un bloque `<pre>` con arrays u objetos recorridos recursivamente, valores booleanos y nulos legibles y cadenas de más de 200 caracteres recortadas. La pestaña VeriFactu la usa para mostrar la carga enviada («Payload Sent to AEAT», decodificando `verifactu_payload`) y el registro que devuelve la consulta a la AEAT («AEAT Query Response»). Puedes usarla en cualquier página del módulo con `dol_include_once('/verifactu/lib/dumper.php')`.

### Ver el XML real de la petición y la respuesta

`OpenAEAT\Billing\SoapClient` crea el `\SoapClient` de PHP con `trace => true` y guarda en memoria las cabeceras y cuerpos de la última operación (`getLastRequest()`, `getLastResponse()`, `getLastRequestHeaders()`, `getLastResponseHeaders()`, `getDebugInfo()`). El módulo no vuelca esos datos al registro. Si necesitas el XML para una incidencia, puedes añadir temporalmente, justo después de `sendInvoice()` en `handleInvoiceCreationOrSubsanation()`:

```php
dol_syslog("VERIFACTU SOAP:\n" . $manager->getDebugInfo(false), LOG_DEBUG);
```

Hazlo solo en un entorno de pruebas y retíralo después: el volcado incluye la petición completa con los datos fiscales. `Manager::getDebugInfo()` devuelve cadena vacía si aún no se ha creado el transporte.

## Leer el registro del servidor web

Los errores fatales de PHP (error 500 o pantalla en blanco) no pasan por `dol_syslog()`. Dónde buscarlos:

| Instalación | Fichero |
|---|---|
| Apache con mod_php | `/var/log/apache2/error.log` (Debian/Ubuntu) o `/var/log/httpd/error_log` (RHEL) |
| nginx + PHP-FPM | `/var/log/nginx/error.log` y el `error_log` del pool de PHP-FPM (por ejemplo `/var/log/php8.2-fpm.log`) |
| Docker (`dolibarr/dolibarr`) | `docker logs <contenedor>` |
| Alojamiento compartido | El visor de registros del panel de control, o `error_log` en la carpeta de la web |

Busca `PHP Fatal error` o `Uncaught` junto a `custom/verifactu`. Ejemplo del fallo de la 2.1.0/2.2.0:

```text
PHP Fatal error:  Uncaught Error: Call to undefined function dol_get_first_day() in /var/www/htdocs/custom/verifactu/verifactuindex.php:NNN
```

Para ver el error en pantalla mientras depuras, activa `display_errors = On` en el `php.ini` del entorno de pruebas (nunca en producción); no hace falta tocar ningún ajuste de Dolibarr.

## Reproducir un problema en el entorno de pruebas de la AEAT

El entorno lo decide `getEnvironment()` en `lib/functions/functions.configuration.php`:

- devuelve `production` si `VERIFACTU_FORCE_PRODUCTION_ENVIRONMENT` está activa;
- si no, devuelve `test` hasta la fecha de transición (1 de enero de 2027 para `VERIFACTU_COMPANY_TYPE = sociedad`, 1 de julio de 2027 para `autonomo`, hora peninsular) y `production` después.

Para reproducir un fallo sin tocar producción:

1. Usa una instalación de pruebas (copia de la base de datos y de `documents/`). No desactives la producción forzada en una instalación real: las cadenas de prueba y producción son independientes (`verifactu_entorno`), pero las facturas que valides seguirán siendo facturas.
2. En la instalación de pruebas, Configuración del módulo > botón de entorno «Pruebas» (`action=settest`, borra `VERIFACTU_FORCE_PRODUCTION_ENVIRONMENT`) o `POST /api/index.php/verifactuapi/toTest` con la clave de API de un administrador. El entorno activo se muestra en la propia pantalla y en el panel principal.
3. Sube el mismo certificado. El portal de preproducción de la AEAT (`prewww1.aeat.es` / `prewww10.aeat.es` para el servicio, `prewww2.aeat.es` para los esquemas y la validación del QR) admite los certificados reales.
4. Activa `LOG_DEBUG`, borra o rota `dolibarr.log`, repite la operación y guarda el fragmento del registro.
5. Comprueba que los esquemas locales existen (sección «Esquemas WSDL/XSD» de la configuración); si el fallo es de transporte, fuerza su descarga.
6. Si el problema depende del motor de base de datos, indícalo: la cadena y el bloqueo tienen ramas distintas para MySQL/MariaDB (`GET_LOCK`) y PostgreSQL (`pg_try_advisory_lock`).

Para pruebas unitarias sin Dolibarr ni AEAT, la [[suite de pruebas|Suite-de-pruebas]] cubre la cadena, las respuestas, los certificados y la validación masiva con dobles.

## Qué adjuntar al abrir una incidencia

Abre la incidencia en [github.com/garacil/verifactu/issues](https://github.com/garacil/verifactu/issues) con esta información; es la que ha permitido resolver las issues #30 a #33 con rapidez:

1. **Versiones**: del módulo (lista de módulos o pestaña Acerca de), de Dolibarr, de PHP (`php -v`), del motor de base de datos (MySQL/MariaDB/PostgreSQL y versión) y de OpenSSL (`openssl version`, y `php -i | grep -i openssl` para la versión con la que se compiló PHP).
2. **Entorno**: sistema operativo o imagen Docker, alojamiento compartido o propio, valor de `disable_functions` si aplica (`exec`, `proc_open`), si hay MultiCompany y si la factura viene de TakePOS.
3. **Entorno VeriFactu**: pruebas o producción, tipo de certificado (`normal`/`sello`), si el certificado es de persona física, jurídica, representante o sello.
4. **Síntoma exacto**: el mensaje emergente, y los campos **Estado**, **Error** y **Última respuesta** de la pestaña VeriFactu de la factura.
5. **Fragmento del registro** en `LOG_DEBUG` desde `VERIFACTU TRIGGER billValidate START` o `VERIFACTU execVERIFACTUCall START` hasta el final de la operación, con NIF e importes anonimizados si lo prefieres.
6. **Registro del servidor web** si hay error 500 o pantalla en blanco: la línea `PHP Fatal error` completa con fichero y número de línea.
7. **Pasos para reproducirlo** y si ocurre siempre o de forma intermitente (los problemas de concurrencia de la cadena suelen serlo).
8. **Resultado de la suite de pruebas** en ese servidor (`for f in tests/*.php; do php "$f" >/dev/null 2>&1 && echo "OK $f" || echo "FALLA $f"; done`), que descarta problemas de extensiones o de binarios.
9. Si propones una corrección, la referencia al esquema oficial (`SuministroInformacion.xsd`, `RespuestaSuministro.xsd`) o al artículo de la Orden HAC/1177/2024 en que se apoya; véase [[Desarrollo y contribución|Desarrollo-y-contribucion]].

## Véase también

- [[Resolución de problemas|Resolucion-de-problemas]]
- [[Códigos de error|Codigos-de-error]]
- [[Conexión SOAP y entornos|Conexion-SOAP-y-entornos]]
- [[Campos adicionales|Campos-adicionales]]
- [[Suite de pruebas|Suite-de-pruebas]]
- [[Desarrollo y contribución|Desarrollo-y-contribucion]]
