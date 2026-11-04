# Requisitos

Esta página recoge lo que necesita el servidor para que el módulo VeriFactu para Dolibarr (versión 2.2.2) se active y funcione: versión de Dolibarr y de PHP, extensiones, motor de base de datos, OpenSSL, funciones de PHP que algunos alojamientos desactivan, conectividad con la AEAT y certificado digital. Cada requisito indica dónde lo comprueba o lo usa el código, para que puedas cotejarlo en tu instalación.

## Resumen

| Componente | Requisito | Dónde lo usa o comprueba el módulo |
|---|---|---|
| Dolibarr | Verificado sobre 17.0.2, 19.0.3 y 22.0.5. El descriptor declara mínimo 10 y el README 13.0; la pantalla de configuración necesita la clase `FormSetup` del núcleo (Dolibarr 15 o superior) | `core/modules/modVerifactu.class.php`, `admin/setup.php`, tabla de compatibilidad del README |
| PHP | 7.4 o superior; verificado con 7.4, 8.2 y 8.4 | `$this->phpmin = array(7, 4)` |
| Extensión `soap` | Obligatoria: sin ella el módulo no se activa | `init()` devuelve `-1` con el mensaje `SOAP_EXTENSION_NOT_INSTALLED` |
| Extensión `openssl` | Obligatoria para leer el certificado y firmar la conexión | `lib/functions/functions.certificates.php` |
| Extensión `gd` | Necesaria para el QR en PNG (vista de factura, TPV) | `lib/newfenix/src/QRGenerator.php`, `lib/functions/functions.qr.php` |
| Extensión `mbstring` | Usada al recortar la identidad del sistema a los límites del esquema | `getDeclaredSystemIdentity()` |
| Extensiones `dom`/`libxml`, `json` | Formateo de XML y trazas | `prettyXML()`, `json_encode()` |
| Extensión `curl` | Recomendada; si falta se usa `file_get_contents()` | `checkHttpConnection()` |
| `allow_url_fopen` | Necesario para descargar los esquemas WSDL/XSD | `SchemaManager::downloadSchemas()` |
| Base de datos | MySQL/MariaDB o PostgreSQL | `lib/functions/functions.hash.php` (bloqueo de la cadena) |
| OpenSSL del sistema | Opcional; imprescindible solo para certificados FNMT con cifrado *legacy* bajo OpenSSL 3 | `runOpensslPkcs12()` |
| `proc_open` | Opcional desde la 2.0.0; solo para el binario `openssl` externo | `isPhpFunctionAvailable('proc_open')` |
| Red | Salida HTTPS hacia los servidores de la AEAT y hacia los servicios que usa la comprobación de conexión | `lib/newfenix/src/Config.php`, `checkInternetConnection()` |
| Certificado | PFX/P12 o PEM, dentro de su periodo de validez | `validateCertificateAndKey()` |

## Dolibarr

### Versiones

El descriptor del módulo declara `$this->need_dolibarr_version = array(10, -3)` y el README pide «Dolibarr 13.0 o superior». En la práctica el límite real lo pone la pantalla de administración: `admin/setup.php` construye el formulario con la clase `FormSetup` de `core/class/html.formsetup.class.php` (el propio fichero avisa de que ese mecanismo «será compatible solo con la versión v15+»). Para versiones anteriores a la 16.0 intenta cargar una copia de respaldo en `backport/v16/core/class/html.formsetup.class.php`, carpeta que no se distribuye con el módulo.

El registro de cambios de la 2.0.0 documenta las comprobaciones sobre instalaciones reales:

| Entorno | Resultado |
|---|---|
| Dolibarr 22.0.5 + PHP 8.4 + PostgreSQL 16 | 22/22 comprobaciones en vivo |
| Dolibarr 17.0.2 + PHP 8.2 + PostgreSQL 16 | 20/20 comprobaciones en vivo |
| Dolibarr 17.0.2 + PHP 8.4 + PostgreSQL 16 | 20/20 comprobaciones en vivo |
| Dolibarr 17.0.2 + PHP 7.4 (mínimo declarado) | 20/20 comprobaciones en vivo |
| Dolibarr 19.0.3 + PHP 8.4 (fuente oficial) | 69/69, constantes y hooks contrastados |

Por tanto el rango recomendado es Dolibarr 17 a 22.

> **Nota:** en `core/modules/modVerifactu.class.php` la posición de algunos campos adicionales depende de la versión (`versioncompare(explode('.', DOL_VERSION), array(12))`), y los valores por defecto de terceros solo se registran a partir de Dolibarr 14 (`DefaultValues`). Son ramas de compatibilidad con instalaciones antiguas; no amplían el rango soportado.

### Módulos de Dolibarr

Al activarse, el módulo exige (y Dolibarr activa automáticamente) estos módulos del núcleo, definidos en `$this->depends`:

| Clave | Módulo | Para qué lo necesita |
|---|---|---|
| `modFacture` | Facturas y abonos | Todo el módulo trabaja sobre `Facture` y sus campos adicionales |
| `modBlockedLog` | Registro de eventos inalterable | La activación fija `BLOCKEDLOG_DISABLE_NOT_ALLOWED_FOR_COUNTRY = ES` para que no pueda desactivarse en España |
| `modApi` | API REST | Expone `class/api_verifactu.class.php` (véase [API REST](API-REST)) |

Además conviene tener operativo el módulo **Tareas programadas** (cron) con su lanzador configurado en el sistema: la activación registra la tarea `VeriFactu - Reenvío de facturas con errores de conexión` (véase [Reintentos y envíos pendientes](Reintentos-y-envios-pendientes)). TakePOS y Multicompany son opcionales; su integración se describe en [TakePOS](TakePOS) y [Multiempresa](Multiempresa).

## PHP

### Versión

Mínimo 7.4 (`$this->phpmin = array(7, 4)`). La suite de regresión y las comprobaciones en vivo de la 2.0.0 cubren 7.4, 8.2 y 8.4. La biblioteca interna `lib/newfenix` usa `declare(strict_types=1)` y tipos escalares, por lo que un PHP anterior a 7.4 no la carga.

### Extensiones

| Extensión | Obligatoria | Uso en el código |
|---|---|---|
| `soap` | Sí | `init()` comprueba `extension_loaded('soap')` y, si falta, devuelve `-1` con el texto «La extensión SOAP no está instalada. Instálela y reinicie el servidor web.» El transporte con la AEAT es `\SoapClient` (`lib/newfenix/src/SoapClient.php`) |
| `openssl` | Sí | `openssl_pkcs12_read()`, `openssl_x509_read()`, `openssl_x509_parse()`, `openssl_pkey_get_private()`, `openssl_pkey_export()`, `openssl_x509_check_private_key()` en `lib/functions/functions.certificates.php`. Sin ella, `prepareCertificateLocalForVerifactu()` devuelve el error «Neither the PHP OpenSSL extension nor proc_open is available on your server» |
| `gd` | Sí, para el QR en pantalla y en el ticket de TPV | `QRGenerator` genera PNG con `chillerlan/php-qrcode` (`imagecreatetruecolor()`, `imagepng()`). El QR del PDF lo dibuja TCPDF y no depende de GD. El README la lista entre los requisitos |
| `mbstring` | Sí | `getDeclaredSystemIdentity()` usa `mb_strlen()` y `mb_substr()` para respetar los límites del esquema sin partir caracteres multibyte |
| `dom` / `libxml` | Sí | `prettyXML()` usa `DOMDocument`; `SoapClient` necesita libxml |
| `json` | Sí | Serialización de trazas, metadatos de esquemas y respuesta de la API |
| `curl` | Recomendada | `checkHttpConnection()` usa cURL si existe `curl_init()`; si no, recurre a `file_get_contents()` con contexto HTTP |
| `imagick` | Opcional | `QRGenerator` comprueba `extension_loaded('imagick')` como alternativa a GD |

### Directivas de `php.ini`

- `allow_url_fopen = On`: `SchemaManager::downloadSchemas()` descarga los ficheros WSDL/XSD con `file_get_contents()` y un contexto de 30 segundos de tiempo de espera. Sin esta directiva la caché local de esquemas no se puede construir (véase [Esquemas WSDL y XSD](Esquemas-WSDL-y-XSD)).
- `disable_functions`: véase más abajo el apartado sobre `proc_open` y `exec`.
- Tiempo máximo de ejecución: la validación masiva procesa cada factura en su propia transacción y espera la respuesta de la AEAT en cada una; en lotes grandes conviene un `max_execution_time` holgado (véase [Validación masiva](Validacion-masiva)).

## Base de datos

El módulo funciona sobre **MySQL/MariaDB** y **PostgreSQL**. No crea tablas propias (`sql/dolibarr_allversions.sql` solo contiene un comentario); guarda su información en `llx_const`, `llx_extrafields`, `llx_facture_extrafields` y `llx_societe_extrafields`.

### Bloqueo de la cadena por motor

`acquireVerifactuChainLock()` (`lib/functions/functions.hash.php`) serializa la sección crítica del encadenamiento según `$db->type`:

| Motor | Mecanismo |
|---|---|
| `mysqli` | `SELECT GET_LOCK('verifactu_chain_<entidad>_<entorno>', <segundos>)` |
| `pgsql` | `SELECT pg_try_advisory_lock(1007, <clave>)` en bucle, con `usleep(200000)` hasta agotar el tiempo |
| Otros (SQLite, etc.) | No hay bloqueo de aplicación; se continúa y se escribe un aviso `LOG_WARNING` en el registro |

El tiempo de espera lo fija `VERIFACTU_CHAIN_LOCK_TIMEOUT` (30 segundos por defecto). Véase [Encadenamiento y huella](Encadenamiento-y-huella).

### Correcciones para PostgreSQL

El registro de cambios del README documenta lo que se ha corregido para PostgreSQL:

- **1.0.1**: mejora de la gestión de conexiones para evitar errores «connection already closed» durante la validación masiva.
- **2.1.0**: `getLastInvoiceHash()` usaba `DATE_FORMAT()`, función que solo existe en MySQL y que `convertSQLFromMysql()` no traduce; en PostgreSQL la consulta fallaba en silencio y cada factura se encadenaba como primer registro. Ahora la fecha se formatea en PHP con `$db->jdate()` y un error de lectura lanza una excepción. El panel principal dejó de usar `MONTH()` y `YEAR()`. Se añadió el *advisory lock*.
- **2.2.1**: el panel principal (`verifactuindex.php`) devolvía un error 500 porque usaba `dol_get_first_day()` sin incluir `core/lib/date.lib.php`; el fichero se incluye ahora explícitamente.

> **Importante:** si tu instalación usa PostgreSQL y venía de una versión anterior a la 2.1.0, el README recomienda revisar los registros ya enviados: es posible que se remitieran como primer registro de cadena. Véase [Actualización](Actualizacion).

### Conexiones adicionales

Para que el error de un envío no se pierda con la transacción de la validación, `saveVerifactuErrorData()` (`lib/functions/functions.response.php`) y la reversión a borrador del trigger `billValidate()` abren una **segunda conexión** con `getDoliDBInstance()` usando las mismas credenciales de `conf.php`. El servidor de base de datos debe admitir esa conexión adicional por petición.

## OpenSSL del sistema

Hay dos OpenSSL distintos en juego:

1. **La extensión OpenSSL de PHP**, obligatoria. Con ella el módulo lee el PKCS#12 sin lanzar ningún proceso (`extractPkcs12WithPhp()`).
2. **El binario `openssl`** del sistema, opcional. Solo se invoca cuando la lectura nativa falla, normalmente por un contenedor cifrado con algoritmos *legacy* (`RC2-40-CBC`, `PBE-SHA1-3DES`), habituales en los `.p12` de la FNMT.

### OpenSSL 1.1.1 frente a OpenSSL 3

OpenSSL 3 (el de Debian 12 y de la imagen oficial `dolibarr/dolibarr`) mueve esos algoritmos al proveedor *legacy*, desactivado por defecto. La lectura nativa falla entonces con `error:0308010C: digital envelope routines::unsupported`, que `isLegacyCipherOpensslError()` reconoce. El módulo reintenta con `openssl pkcs12 -legacy`, pero solo si `opensslCliSupportsLegacyFlag()` detecta que `openssl version` responde 3.x o superior: en OpenSSL 1.1.1 o LibreSSL el modificador no existe y añadirlo convertiría una extracción correcta en un error de «opción desconocida».

| Certificado | Servidor normal | Alojamiento compartido sin `proc_open` |
|---|---|---|
| Cifrado moderno | Funciona | Funciona (lectura nativa) |
| Cifrado *legacy* (FNMT antiguo) | Funciona vía `-legacy` | No es posible: hay que reexportar el certificado |

Si el binario tampoco tiene disponible el proveedor *legacy*, el README ofrece dos salidas: activarlo en `/etc/ssl/openssl.cnf` o reexportar el certificado con un algoritmo moderno desde otro ordenador. Ambas se detallan en [Certificados digitales](Certificados-digitales).

### Windows

`getOpensslBinary()` usa `openssl` del `PATH` en Linux y, en Windows, la ruta por defecto `C:\laragon\bin\apache\httpd-2.4.54-win64-VS16\bin\openssl.exe`. Si el binario no existe en esa ruta, las funciones de extracción externa devuelven «OpenSSL binary not found at: ...» y solo queda la vía nativa de PHP.

## `proc_open` y `exec` en alojamiento compartido

Muchos alojamientos compartidos incluyen `exec()` y `proc_open()` en `disable_functions`. Desde la 2.0.0 esto no impide usar el módulo:

- La extracción del certificado intenta **primero** `openssl_pkcs12_read()`, que no lanza procesos.
- `isPhpFunctionAvailable()` comprueba tanto `function_exists()` como la lista `disable_functions`, porque algunos SAPI dejan la función definida pero rechazan ejecutarla.
- Se eliminó el requisito duro de `proc_open` en la conversión a PEM (`prepareCertificateLocalForVerifactu()`).
- Cuando el binario hace falta y `proc_open` no está disponible, el mensaje lo dice de forma explícita: «proc_open is disabled on this server and PHP could not read the certificate: ...».
- `checkInternetConnection()` solo intenta el `ping` si existe `exec()`; los demás métodos (HTTP, DNS, *sockets*) no lo necesitan.

El único caso sin solución desde el módulo es un certificado *legacy* en un servidor sin binario `openssl` ni acceso a `openssl.cnf`; ahí hay que reexportarlo con un algoritmo moderno.

## Conectividad de red

### Servidores de la AEAT

Los puntos de conexión los resuelve `OpenAEAT\Billing\Config` (`lib/newfenix/src/Config.php`) a partir del entorno y del tipo de certificado:

| Entorno | Tipo de certificado | Servidor SOAP |
|---|---|---|
| Pruebas | `normal` | `https://prewww1.aeat.es` |
| Pruebas | `sello` | `https://prewww10.aeat.es` |
| Producción | `normal` | `https://www1.agenciatributaria.gob.es` |
| Producción | `sello` | `https://www10.agenciatributaria.gob.es` |

La ruta del servicio de facturación es `/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSOAP` (y `/wlpl/TIKE-CONT/ws/SistemaFacturacion/RequerimientoSOAP` para el servicio de requerimiento). Las URL de validación del QR son `https://prewww2.aeat.es/wlpl/TIKE-CONT/ValidarQR` en pruebas y `https://www2.agenciatributaria.gob.es/wlpl/TIKE-CONT/ValidarQR` en producción. Todo por HTTPS (puerto 443), con autenticación TLS mediante el certificado configurado (`local_cert` en las opciones del `SoapClient`).

### Descarga de esquemas

`SchemaManager` descarga siete ficheros desde `https://prewww2.aeat.es/static_files/common/internet/dep/aplicaciones/es/aeat/tikeV1.0/cont/ws/` (`SistemaFacturacion.wsdl`, `SuministroInformacion.xsd`, `SuministroLR.xsd`, `ConsultaLR.xsd`, `RespuestaConsultaLR.xsd`, `RespuestaSuministro.xsd`) y `xmldsig-core-schema.xsd` desde `http://www.w3.org/TR/xmldsig-core/`. Esta descarga ocurre en el primer envío o al pulsar «Descargar/Actualizar esquemas» en la configuración; después el módulo trabaja con la copia local.

### Comprobación de conexión a Internet

Antes de validar o reintentar, `checkInternetConnection()` (`lib/functions/functions.utilities.php`) necesita que **al menos uno** de estos métodos funcione, en este orden:

1. Petición HTTP HEAD a `https://www.google.com/generate_204`, `https://httpbin.org/status/200`, `https://1.1.1.1/`, `https://www.microsoft.com/favicon.ico` o `http://detectportal.firefox.com/success.txt` (3 segundos por intento).
2. Resolución DNS de `google.com`, `cloudflare.com`, `microsoft.com`, `github.com` o `stackoverflow.com`.
3. Conexión TCP al puerto 53 de `8.8.8.8`, `1.1.1.1`, `9.9.9.9`, `208.67.222.222` o `8.8.4.4`.
4. `ping` a `8.8.8.8`, `1.1.1.1` o `9.9.9.9`, solo si `exec()` está disponible.

> **Aviso:** en un servidor cuyo cortafuegos permita salir únicamente hacia los dominios de la AEAT, esta comprobación fallará aunque la AEAT sea alcanzable. En ese caso el trigger de validación marca la factura como «Sin Conexión» (`NO_INTERNET_CONNECTION`) y la tarea programada de reintentos no llega a enviarla. Autoriza al menos uno de los destinos anteriores.

### Reloj del servidor

`getEnvironment()` decide el entorno con la hora de `Europe/Madrid`, y cada registro lleva la fecha y hora de generación de la huella. Mantén el reloj del servidor sincronizado.

## Certificado digital

- **Formatos**: `.pfx`, `.p12` o `.pem`. La pestaña «Gestionar Certificados» solo admite `.pfx` y `.p12`; `admin/uploadcertificates.php` admite también `.pem`.
- **Emisor y titular**: el README pide un certificado válido y no revocado, de persona jurídica y emitido por una autoridad reconocida (FNMT u otras). Desde la 2.2.0 se admite expresamente que un certificado de representante o de colaborador social firme por varios obligados tributarios, por lo que no se bloquea su reutilización entre entidades.
- **Validez**: `validateCertificateAndKey()` rechaza el certificado si la fecha actual está fuera de `validFrom`/`validTo` (mensaje `VERIFACTU_CERT_ERROR_EXPIRED`), y comprueba que la clave privada corresponde al certificado.
- **Tipo**: `VERIFACTU_CERT_TYPE` distingue `normal` (compatible con todos) y `sello` (solo certificados de sello de empresa); el tipo decide el servidor de la AEAT al que se conecta.

El detalle de subida, conversión y errores está en [Certificados digitales](Certificados-digitales).

## Carpetas y espacio en disco

La activación crea `documents/verifactu/temp` y `documents/verifactu/certificates` (declaradas en `$this->dirs`); la caché de esquemas se guarda en `documents/verifactu/schemas`. Con Multicompany y una entidad distinta de la principal la raíz pasa a `documents/<id_entidad>/verifactu`. El usuario del servidor web necesita permiso de escritura sobre `documents/`; el módulo escribe los PEM convertidos con permisos `0600`. El espacio necesario es pequeño (certificados, siete ficheros de esquema y trazas en el registro de Dolibarr).

## Véase también

- [Instalación](Instalacion)
- [Configuración](Configuracion)
- [Certificados digitales](Certificados-digitales)
- [Conexión SOAP y entornos](Conexion-SOAP-y-entornos)
- [Esquemas WSDL y XSD](Esquemas-WSDL-y-XSD)
- [Multiempresa](Multiempresa)
- [Resolución de problemas](Resolucion-de-problemas)
