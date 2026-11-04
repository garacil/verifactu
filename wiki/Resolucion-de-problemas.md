# Resolución de problemas

Guía por síntomas para diagnosticar y resolver los fallos más habituales del módulo VeriFactu para Dolibarr (versión 2.2.2). Cada apartado indica qué se ve en pantalla, cuál es la causa, dónde mirar (registros y campos de la factura) y qué hacer. Si el mensaje que buscas no está aquí, consulta la tabla de [[códigos de error|Codigos-de-error]] y, para activar la traza detallada, [[Registro y depuración|Registro-y-depuracion]].

## Antes de empezar: dónde mirar

El módulo deja rastro en cuatro sitios. Conviene revisarlos en este orden:

| Dónde | Qué contiene | Cómo llegar |
|---|---|---|
| Pestaña **VeriFactu** de la factura | Estado (insignia), **Error** (`verifactu_error`), **Última respuesta** (`verifactu_ultima_salida`), CSV, huella, **Datos XML** (carga enviada) | Ficha de la factura > pestaña VeriFactu |
| Registro de eventos de Dolibarr | Todo lo que el módulo escribe con `dol_syslog()`: mensajes con prefijo `VERIFACTU`, consultas SQL, respuestas de la AEAT, excepciones | Módulo **Registro de eventos (Syslog)** de Dolibarr; por defecto escribe en `documents/dolibarr.log` (constante `SYSLOG_FILE`, valor por defecto `DOL_DATA_ROOT/dolibarr.log`) |
| Registro del servidor web / PHP | Errores fatales de PHP (error 500, pantalla en blanco), que **no** llegan a `dolibarr.log` | `/var/log/apache2/error.log`, `/var/log/nginx/error.log`, registro de PHP-FPM o `docker logs` según la instalación |
| Pantalla de configuración del módulo | Caducidad del certificado, estado de los esquemas WSDL/XSD, entorno activo, avisos de Multiempresa | Inicio > Configuración > Módulos > VeriFactu > Configuración |

> **Nota:** para que el registro de Dolibarr recoja los mensajes de depuración hay que poner el nivel del módulo Syslog en `LOG_DEBUG`. Sin ese nivel solo se guardan avisos y errores. Los detalles están en [[Registro y depuración|Registro-y-depuracion]].

## Índice por síntomas

| Síntoma | Apartado |
|---|---|
| Error 500 al pulsar el menú VeriFactu | [Error 500 en la página principal](#error-500-al-abrir-el-menú-verifactu-210-y-220) |
| Página en blanco al ver o generar facturas | [Pantalla en blanco](#pantalla-en-blanco) |
| La AEAT devuelve el código 4118 | [Error SOAP 4118](#error-soap-4118) |
| Fallos al crear el cliente SOAP tras muchas facturas seguidas | [Limitación de peticiones de W3C](#errores-al-crear-el-cliente-soap-limitación-de-peticiones-de-w3c) |
| El módulo rechaza el certificado o su contraseña | [Problemas con el certificado](#problemas-con-el-certificado) |
| La factura vuelve a borrador `(PROVn)` al validar | [La factura vuelve a borrador](#la-factura-vuelve-a-borrador-prov-al-validarla) |
| Importes que no cuadran en facturas con IRPF | [Facturas con retención IRPF](#facturas-con-retención-irpf) |
| Estado «Sin Conexión» o «Pendiente» | [NO_INTERNET_CONNECTION, SERVICE_UNAVAILABLE y PENDING_SUBMISSION](#estados-sin-conexión-y-pendiente) |
| «Se está encadenando otra factura en este momento…» | [Bloqueo de la cadena](#se-está-encadenando-otra-factura-bloqueo-de-la-cadena) |
| «No se pudo leer la cadena VeriFactu…» | [VERIFACTU_CHAIN_READ_FAILED](#verifactu_chain_read_failed) |
| La AEAT responde que el registro ya existe (3000) | [Duplicados en la AEAT](#duplicados-en-la-aeat-error-3000) |
| El QR no sale en la ficha, en el PDF o en el ticket | [El QR no aparece](#el-qr-no-aparece) |
| La AEAT responde 4102 y señala `BaseImponibleOimporteNoSujeto` | [Rechazo 4102](#la-aeat-rechaza-con-4102-y-señala-baseimponibleoimportenosujeto) |
| Facturas validadas que el listado VeriFactu muestra como borrador | [Estado del listado](#facturas-validadas-que-el-listado-muestra-como-borrador) |
| Error fatal al generar el informe de pagos | [Informe de pagos](#error-fatal-en-el-informe-de-pagos) |
| Se ve una clave en mayúsculas en vez de un texto | [Claves de traducción en bruto](#aparece-una-clave-de-traducción-en-lugar-de-un-mensaje) |

## Error 500 al abrir el menú VeriFactu (2.1.0 y 2.2.0)

**Síntoma.** Al hacer clic en la entrada de menú «VeriFactu» (`verifactuindex.php`) el navegador muestra un error 500 o una página vacía. La configuración del módulo sí se abre, y las facturas se validan y se envían con normalidad, por lo que la impresión es que «el módulo ha dejado de funcionar».

**Causa.** En la 2.1.0 el gráfico de facturas por mes del panel se reescribió para funcionar también en PostgreSQL, sustituyendo `MONTH()` y `YEAR()` por los ayudantes `dol_get_first_day()` y `dol_get_last_day()`. Esos ayudantes viven en `core/lib/date.lib.php`, que Dolibarr no carga en todas las páginas, y `verifactuindex.php` no lo incluía. Afecta a la 2.1.0 y a la 2.2.0.

**Dónde mirar.** En el registro del servidor web aparece:

```text
PHP Fatal error:  Uncaught Error: Call to undefined function dol_get_first_day() in .../custom/verifactu/verifactuindex.php
```

**Solución.** Actualizar a la **2.2.1**, que carga la librería junto al resto de dependencias de la página. La suite de regresión (`tests/DashboardDependenciesTest.php`) comprueba desde entonces que cualquier fichero del módulo que use un ayudante de `date.lib.php` lo incluya antes de llamarlo. Mientras se actualiza, el resto del módulo (validación, envío, pestaña VeriFactu, configuración) sigue operativo.

## Pantalla en blanco

**Síntoma.** Una página del módulo, la ficha de una factura o la generación de su PDF termina en blanco, sin mensaje.

**Causa.** Un error fatal de PHP con `display_errors` desactivado. Las causas más repetidas son una extensión de PHP ausente y un error de tipos en PHP 8 (como el `TypeError` del generador de QR corregido en la 2.0.0).

**Dónde mirar.** El registro del servidor web o de PHP-FPM. Ahí está la línea `PHP Fatal error` con el fichero y la línea exacta.

**Solución.**

1. Comprobar que están cargadas las extensiones que el módulo necesita: `soap`, `openssl`, `gd`, `mbstring` y `json`. Si falta SOAP, el módulo lo avisa con el texto «La extensión SOAP no está instalada. Instálela y reinicie el servidor web.» (`SOAP_EXTENSION_NOT_INSTALLED`).
2. Comprobar la versión del módulo: si es anterior a la 2.0.0 y el error habla de `QRGenerator::renderQrCode()`, es el fallo de la issue #32; actualizar.
3. Si el error está en un fichero del módulo y la versión es la 2.2.2, abrir una incidencia con la traza (véase [[Registro y depuración|Registro-y-depuracion]]).

## Error SOAP 4118

**Síntoma.** La AEAT rechaza el envío con el código 4118.

**Causa.** Según el README, indica un problema con la estructura del mensaje SOAP.

**Qué revisar.**

- El formato de las fechas: el módulo envía `FechaExpedicionFactura` como `DD-MM-AAAA` (`date('d-m-Y', $facture->date)`).
- Los datos del emisor (`VERIFACTU_HOLDER_NIF`, `VERIFACTU_HOLDER_COMPANY_NAME`) y del destinatario (NIF del tercero español o identificación extranjera con su tipo).
- La configuración del certificado.

El módulo no interpreta este código de forma especial: lo guarda tal cual en **Error** y **Última respuesta** de la factura. Para el significado exacto, consulta el listado oficial de errores de la AEAT enlazado desde la página de ayuda del módulo (véase [[Códigos de error|Codigos-de-error]]).

## Errores al crear el cliente SOAP (limitación de peticiones de W3C)

**Síntoma.** Al procesar muchas facturas seguidas aparecen errores al construir el `SoapClient`, normalmente mencionando `xmldsig-core-schema.xsd` o `w3.org`.

**Causa.** El esquema `SuministroInformacion.xsd` de la AEAT importa `http://www.w3.org/TR/xmldsig-core/xmldsig-core-schema.xsd`. Si el WSDL se lee en remoto, PHP descarga ese fichero en cada envío y el servidor de W3C limita las peticiones repetidas.

**Solución.** Desde la 1.0.4 el módulo descarga y guarda en local los siete ficheros de esquema (1 WSDL + 5 XSD de la AEAT + 1 XSD de W3C) y reescribe la referencia `schemaLocation` para que apunte al fichero local. Se descargan solos la primera vez que se envía una factura, pero puedes forzarlo:

1. Inicio > Configuración > Módulos > VeriFactu > Configuración.
2. En la sección **Esquemas WSDL/XSD**, comprobar el estado («Descargados» / «No descargados») y pulsar **Descargar/Actualizar esquemas**.

Los ficheros quedan en la carpeta de documentos del módulo, subcarpeta `schemas` de la entidad (`documents/verifactu/schemas` en una instalación sin Multiempresa), junto a `schemas.meta.json` con la fecha de descarga. Si el servidor no puede salir a `prewww2.aeat.es` ni a `www.w3.org`, la descarga falla con «Error al descargar esquemas: Failed to download: …»; revisa el cortafuegos o el proxy. Más detalle en [[Esquemas WSDL y XSD|Esquemas-WSDL-y-XSD]].

## Problemas con el certificado

Todos los envíos y consultas pasan por `getCertificateOptions()` y `validateCertificateAndKey()`. Cuando fallan, el mensaje llega al usuario con el prefijo `Certificate error:` y el detalle se guarda en `$GLOBALS['verifactu_cert_error']` y en el registro. La página completa sobre formatos y conversión es [[Certificados digitales|Certificados-digitales]]; aquí solo los fallos.

### La contraseña no es correcta

**Mensaje.** «Error: la contraseña del certificado no es correcta. Compruébela y vuelva a intentarlo.» (`VERIFACTU_CERT_ERROR_WRONG_PASSWORD`).

**Causa.** OpenSSL devuelve textos como `Mac verify error`, `invalid password`, `bad decrypt` o `wrong final block length`. El módulo los reconoce con `isWrongPasswordOpensslError()` y solo entonces culpa a la contraseña.

**Solución.** Volver a escribir la contraseña. Si estás seguro de que es correcta y el fichero es un `.p12` de la FNMT, lee el apartado siguiente: hasta la 2.0.0 el módulo achacaba a la contraseña un problema de cifrado.

### Certificado `.p12` de la FNMT con cifrado antiguo (OpenSSL 3)

**Mensaje.** «Error: el certificado está cifrado con un algoritmo antiguo (RC2-40-CBC / PBE-SHA1-3DES) que OpenSSL 3 desactiva por defecto…» (`VERIFACTU_CERT_ERROR_LEGACY_ALGORITHM`).

**Causa.** Los contenedores PKCS#12 de la FNMT se han protegido históricamente con `RC2-40-CBC` y `PBE-SHA1-3DES`. OpenSSL 3 (Debian 12, imagen oficial `dolibarr/dolibarr`) desactiva esos algoritmos salvo que se active el proveedor *legacy*, y `openssl_pkcs12_read()` falla con `error:0308010C:digital envelope routines::unsupported`.

**Qué hace el módulo desde la 2.0.0.** Intenta primero las funciones OpenSSL de PHP; si detecta el error de algoritmo, reintenta con el binario `openssl pkcs12 -legacy` (solo si el binario es OpenSSL 3 o superior: en OpenSSL 1.1.1 o LibreSSL el modificador no existe y no se añade). En la mayoría de servidores el certificado se acepta sin tocar nada.

**Si sigue fallando**, hay dos salidas:

Opción A: activar el proveedor *legacy* en `/etc/ssl/openssl.cnf`:

```ini
[provider_sect]
default = default_sect
legacy = legacy_sect

[default_sect]
activate = 1

[legacy_sect]
activate = 1
```

Opción B: reexportar el certificado con un algoritmo moderno en una máquina donde sí se pueda abrir, y subir el fichero nuevo desde la pestaña Certificados:

```bash
openssl pkcs12 -legacy -in certificado_fnmt.p12 -nodes -out temporal.pem -password pass:TU_CLAVE
openssl pkcs12 -export -in temporal.pem -out certificado_moderno.p12 -password pass:TU_CLAVE
rm temporal.pem
```

### `exec()` y `proc_open()` desactivados (alojamiento compartido)

**Mensaje.** «Neither the PHP OpenSSL extension nor proc_open is available on your server. Please contact your administrator.» o, con certificados legacy, el mensaje de algoritmo antiguo aunque la contraseña sea correcta.

**Causa.** En muchos alojamientos compartidos ambas funciones están en `disable_functions`, así que el módulo no puede lanzar el binario `openssl`.

**Qué hace el módulo desde la 2.0.0.** La extracción usa **primero** `openssl_pkcs12_read()`, que no lanza ningún proceso, y solo recurre al binario cuando hace falta. Resultado:

| Certificado | Servidor normal | Compartido sin `proc_open` |
|---|---|---|
| Cifrado moderno | funciona | funciona |
| Cifrado legacy (FNMT antiguo) | funciona (vía `-legacy`) | no es posible |

**Solución** para el único caso sin salida (legacy + sin binario): reexportar el certificado con la opción B del apartado anterior desde otro ordenador.

### Certificado caducado o aún no válido

**Mensaje.** «Error: el certificado está fuera de su periodo de validez. Compruebe sus fechas de emisión y caducidad y, si ha caducado, renuévelo y suba el nuevo.» (`VERIFACTU_CERT_ERROR_EXPIRED`).

**Causa.** Desde la 2.0.0 `validateCertificateAndKey()` lee `validFrom_time_t` y `validTo_time_t` del X.509 y rechaza el certificado antes de llamar a la AEAT, que lo rechazaría en el saludo TLS.

**Dónde mirar.** La pantalla de configuración muestra la caducidad con una insignia: roja si ya caducó, naranja si quedan menos de 30 días, verde si es válido.

**Solución.** Renovar el certificado y subirlo. El NIF del obligado no cambia y la cadena continúa.

### La clave privada no coincide

**Mensaje.** «Certificate error: The certificate passphrase is incorrect or the certificate is invalid». En el registro, alguno de estos textos: `Error reading private key. Incorrect passphrase or key not found`, `Private key does not match certificate`, `Error reading X.509 certificate - invalid format`.

**Causa.** El fichero PEM que usa el módulo no contiene una clave privada que corresponda al certificado, o la contraseña con la que se cifró la clave no es la guardada en `VERIFACTU_CERTIFICATE_KEY`.

**Solución.** Volver a subir el `.p12`/`.pfx` desde la pestaña Certificados para regenerar el PEM. Si el certificado se subió como PEM sin clave, pegar la clave privada en el campo correspondiente (el módulo añade las etiquetas PEM si faltan).

### El NIF del obligado no coincide o no se puede cambiar

- **«No se puede modificar el NIF del obligado tributario porque esta entidad ya contiene registros VeriFactu encadenados. El certificado sí se puede sustituir.»** (`VERIFACTU_TAX_IDENTITY_LOCKED`). Desde la 2.0.0, en cuanto existe la primera huella de la entidad, el NIF queda fijado: cada registro se encadenó bajo esa identidad. Si la lectura de la cadena falla, el módulo también bloquea el cambio (falla cerrado). Es intencionado; si el NIF era erróneo desde el principio, hay que tratarlo con el asesor y la AEAT, no con el módulo.
- **«El NIF del obligado tributario ya está configurado en la entidad VeriFactu %s…»** (`VERIFACTU_TAX_IDENTITY_ALREADY_USED`). Con Multiempresa, dos entidades con VeriFactu activado no pueden declarar el mismo NIF (véase [[Multiempresa|Multiempresa]]).
- **La AEAT rechaza el envío por el certificado.** El módulo no comprueba que el NIF del certificado coincida con el del obligado: eso lo decide la AEAT, que admite certificados de representante o de colaborador social. Si el rechazo llega de la AEAT, revisa qué certificado subiste y a quién representa.

### Tras renovar el certificado se sigue usando el antiguo

**Causa.** Antes de la 1.0.4, al subir un nuevo `.p12` no se borraban los PEM anteriores y el módulo seguía usando el certificado viejo, posiblemente caducado.

**Solución.** Desde la 1.0.4 `deleteExistingPemFiles()` limpia los PEM antes de procesar el nuevo fichero. Si vienes de una versión anterior, usa el botón **Limpiar caché de certificado** (`VERIFACTU_PURGE_CERT_CACHE`) o vuelve a subir el certificado tras actualizar.

### Errores «no XML document» en las consultas

**Causa.** El tipo de certificado configurado es «sello» (`VERIFACTU_CERT_TYPE = sello`, servidores `www10`/`prewww10`) y el certificado es personal o de representante.

**Solución.** Cambiar a «normal» (servidores `www1`/`prewww1`), compatible con todos los certificados, tal como indica la propia pantalla de configuración (`VERIFACTU_TROUBLESHOOTING_XML_ERROR`).

## La factura vuelve a borrador (PROV) al validarla

**Síntoma.** Al validar, Dolibarr muestra «El envío a VeriFactu falló para la factura %s. La factura permanece como borrador - corrija los problemas e inténtelo de nuevo.» (`VERIFACTU_ERROR_INVOICE_NOT_VALIDATED`) y la referencia vuelve a `(PROVn)`.

**Causa.** Con el envío directo en la validación activado (`VERIFACTU_DIRECT_CALL_ON_VALIDATE`, o siempre en facturas de TakePOS), el trigger `billValidate()` llama a `execVERIFACTUCall()`. Si devuelve `false`, el trigger revierte la factura a borrador con una conexión independiente (`fk_statut = 0`, `ref = '(PROVn)'`, `date_valid = NULL`) y bloquea la validación. Es el comportamiento deseado: una factura validada sin registro de facturación no debe existir.

Motivos que devuelven `false`, de más a menos frecuente:

| Motivo | Mensaje que se ve | Dónde mirar |
|---|---|---|
| Rechazo de la AEAT | «Error in VeriFactu submission - Status: Incorrecto - Code: NNNN - descripción» | Campos **Error** y **Última respuesta** de la factura |
| Campos fiscales sin configurar | «ERROR: Tax Type not configured…», «ERROR: Regime Key not configured…», «ERROR: Regime Key must be empty for IPSI and Other taxes…» | Pestaña VeriFactu: Impuesto, Clave Régimen, Calificación, Exención |
| Certificado | «Certificate error: …» | Apartados anteriores |
| Configuración incompleta | «Configuración VeriFactu incompleta. Revise los ajustes del módulo.» | `VERIFACTU_HOLDER_NIF` y `VERIFACTU_HOLDER_COMPANY_NAME` vacíos |
| Cadena ocupada | `VERIFACTU_CHAIN_LOCK_BUSY` | Apartado [Bloqueo de la cadena](#se-está-encadenando-otra-factura-bloqueo-de-la-cadena) |
| Cadena ilegible | `VERIFACTU_CHAIN_READ_FAILED` | Apartado [VERIFACTU_CHAIN_READ_FAILED](#verifactu_chain_read_failed) |
| Duplicado con importes distintos | «Duplicada (Importes diferentes)» | Apartado [Duplicados](#duplicados-en-la-aeat-error-3000) |
| Registro inválido antes de enviarlo | «Error VeriFactu: Simplified invoices cannot have recipients», «…cannot exceed 500 characters», etc. | Excepciones de `Invoice::validate()` en [[Códigos de error|Codigos-de-error]] |

Además, antes de llamar a la AEAT el trigger exige, salvo en TakePOS y en clientes marcados como de factura simplificada: CIF/NIF si el tercero es español, CIF intracomunitario si no lo es, y dirección. También comprueba la referencia que se va a asignar: sin espacios, solo alfanuméricos, guiones, guiones bajos y barras, y máximo 60 caracteres (`VERIFACTU_INVOICE_REF_*`).

**Solución.** Corregir la causa indicada y validar de nuevo. Los datos del error se guardan con una conexión independiente, así que sobreviven a la reversión y siguen visibles en la pestaña VeriFactu de la factura en borrador.

> **Nota:** la validación **no** aplica el tiempo de espera que pide la AEAT (`TiempoEsperaEnvio`): abortar aquí revertiría la factura por una ventana de espera. Solo lo respetan los reintentos automáticos.

## Facturas con retención IRPF

**Síntoma.** El QR o la consulta a la AEAT muestran un importe distinto del total de Dolibarr en facturas con retención.

**Causa.** El `total_ttc` de Dolibarr descuenta la retención (`total_localtax2` negativo), pero el `ImporteTotal` de VeriFactu es base + IVA + recargo de equivalencia, sin deducir IRPF.

**Solución.** Desde la 1.0.4 `getVerifactuImporteTotal()` devuelve `total_ttc + abs(total_localtax2)` y se usa en el QR, en la comparación de importes y en la comprobación previa al envío. Si una factura con IRPF se envió con una versión anterior, usa **Verificar Estado en AEAT** en su pestaña VeriFactu para cotejar los importes; la tolerancia es de 0,01.

## Estados «Sin Conexión» y «Pendiente»

Tres marcadores internos del campo `verifactu_error` describen una factura validada cuyo registro aún no ha llegado a la AEAT. Las tres se marcan con `Incidencia = 'S'` y las recoge la cola de reintentos ([[Reintentos y envíos pendientes|Reintentos-y-envios-pendientes]]).

| Marcador | Insignia | Cuándo se pone | Qué significa |
|---|---|---|---|
| `NO_INTERNET_CONNECTION` | Sin Conexión | `checkInternetConnection()` devuelve `false` durante la validación | La factura se validó en local; «Realice el envío manual posteriormente» |
| `SERVICE_UNAVAILABLE` | Pendiente | La AEAT respondió con HTTP 503 o «Service Unavailable» | El servicio no estaba disponible; se reintentará |
| `PENDING_SUBMISSION` | Pendiente | `VERIFACTU_DIRECT_CALL_ON_VALIDATE` desactivado y factura no procedente de TakePOS (desde 2.1.0) | El registro existe y espera su envío desde la cola |

**Cómo se reenvían.** La tarea programada «VeriFactu - Reenvío de facturas con errores de conexión» (`VerifactuUtils::sendInvoicesWithErrors()`, cada 2 horas) y cada validación con conexión procesan las facturas validadas, sin `PROV` en la referencia, sin huella y con uno de esos marcadores, de la más antigua a la más nueva. Los reintentos sí respetan el `TiempoEsperaEnvio` de la AEAT; cuando queda espera, el registro dice «AEAT asked to wait N more seconds, submission of FA… postponed».

**Si todas las facturas quedan «Sin Conexión» aunque el servidor tenga salida a la AEAT.** `checkInternetConnection()` prueba, por este orden, peticiones HTTP HEAD a `www.google.com/generate_204`, `httpbin.org`, `1.1.1.1`, `www.microsoft.com` y `detectportal.firefox.com`; resoluciones DNS de `google.com`, `cloudflare.com`, `microsoft.com`, `github.com` y `stackoverflow.com`; conexiones al puerto 53 de `8.8.8.8`, `1.1.1.1`, `9.9.9.9`, `208.67.222.222` y `8.8.4.4`; y `ping` si `exec()` está disponible. Un cortafuegos que solo permita salir hacia la AEAT hace fallar todas las pruebas y el módulo concluye que no hay conexión. Permite al menos una de esas comprobaciones.

## «Se está encadenando otra factura…»: bloqueo de la cadena

**Mensaje.** «Se está encadenando otra factura en este momento y ha vencido el tiempo de espera. Inténtelo de nuevo en unos segundos: la cadena es estrictamente secuencial y no se pueden encadenar dos facturas a la vez.» (`VERIFACTU_CHAIN_LOCK_BUSY`).

**Causa.** Desde la 2.1.0, leer la huella anterior, enviar y guardar la nueva es una sección crítica serializada con un bloqueo de sesión de base de datos: `GET_LOCK()` en MySQL/MariaDB y `pg_try_advisory_lock(1007, clave)` en PostgreSQL, uno por entidad y entorno (`verifactu_chain_<entidad>_<entorno>`). El tiempo de espera es `VERIFACTU_CHAIN_LOCK_TIMEOUT`, 30 segundos por defecto (mínimo 1). Si otra validación mantiene el bloqueo más tiempo (por ejemplo, un envío que espera a la AEAT), la segunda falla con este mensaje y, si estaba validando, vuelve a borrador.

**Dónde mirar.** En el registro: `VERIFACTU: chain lock verifactu_chain_1_production still busy after 30s`.

**Solución.** Reintentar pasados unos segundos. El bloqueo vive en la sesión de base de datos: se libera solo si el proceso termina o muere, y también cuando el envío lanza una excepción (`finally`). Si el problema es sistemático (muchas validaciones simultáneas contra una AEAT lenta), sube `VERIFACTU_CHAIN_LOCK_TIMEOUT` desde Inicio > Configuración > Varios. Con SQLite u otros motores sin bloqueos de aplicación no hay serialización y el registro lo avisa.

## VERIFACTU_CHAIN_READ_FAILED

**Mensaje.** «No se pudo leer la cadena VeriFactu para obtener el registro anterior, por lo que la factura no se ha enviado. Consulte el registro de eventos de Dolibarr para ver el error de base de datos.»

**Causa.** `getLastInvoiceHash()` no pudo ejecutar la consulta que busca la última factura con huella de la entidad y el entorno. Hasta la 2.0.0 ese error pasaba en silencio y la factura se encadenaba como primer registro; desde la 2.1.0 se lanza una excepción, porque es preferible no enviar a enviar con la cadena rota (Art. 13 de la Orden HAC/1177/2024).

**Dónde mirar.** En el registro: `VERIFACTU: could not read the chain to find the previous record: <error SQL>`, precedido de `VERIFACTU: getLastInvoiceHash SQL: SELECT fe.verifactu_huella …`.

**Causas típicas y solución.**

- Instalación en PostgreSQL con una versión anterior a la 2.1.0: la consulta usaba `DATE_FORMAT()`, que solo existe en MySQL. Actualizar. Si tu instalación usa PostgreSQL, revisa además los registros ya enviados: es posible que se remitieran como primer registro de cadena.
- Faltan las columnas `verifactu_huella` o `verifactu_entorno` en `llx_facture_extrafields` (campos adicionales borrados o módulo activado a medias). Reactivar el módulo para que vuelva a crearlos; el módulo impide borrar los campos `verifactu_*` desde la interfaz precisamente por esto.
- Un fallo transitorio de base de datos. Reintentar.

## Duplicados en la AEAT (error 3000)

**Síntoma.** La AEAT responde con `CodigoErrorRegistro = 3000` (registro duplicado), o la factura aparece con la insignia «Enviada (Duplicada)» o «Duplicada (Importes diferentes)».

**Qué hace el módulo.**

1. **Antes de cada alta** consulta a la AEAT si la factura ya existe (`preCheckInvoiceInAEAT()`). Si existe con los mismos importes, no la reenvía, la marca como enviada y avisa: «La factura %s ya existe en AEAT. No se realizó envío adicional.». Si existe con importes distintos, no envía y muestra «La factura %s existe en AEAT con importes diferentes: Dolibarr=%s, AEAT=%s. Revise los datos.».
2. **Si aun así llega un 3000**, vuelve a consultar y compara importes con `manejarFacturaDuplicada()`:
   - coinciden: insignia «Enviada (Duplicada)», se recupera la huella que consta en la AEAT si viene en la respuesta, y el envío se da por bueno;
   - no coinciden: insignia «Duplicada (Importes diferentes)», mensaje «ACCIÓN REQUERIDA: Si Dolibarr es correcto, cree factura rectificativa. Si AEAT es correcto, ajuste Dolibarr.» y el envío falla (en validación, la factura vuelve a borrador);
   - la consulta falla: «No se pudo verificar la factura duplicada. Consulte manualmente en el portal AEAT.» y se trata como error normal.

**Solución.** El botón **Verificar Estado en AEAT** de la pestaña VeriFactu repite el cotejo: si la factura no consta en la AEAT, limpia todas las marcas VeriFactu para poder enviarla; si consta con los mismos importes, la deja como enviada y guarda la huella; si difieren, lo indica. La decisión final (rectificativa o corrección en Dolibarr) es tuya y de tu asesor. Véase [[Consulta a la AEAT|Consulta-a-la-AEAT]].

## El QR no aparece

El QR se dibuja en la ficha de la factura (`moreHtmlStatus`/`formDolBanner`), en el PDF (`afterPDFCreation`, mediante TCPDI) y en el ticket de TakePOS (`TakeposReceipt`). Compruébalo por orden:

1. **La factura tiene que constar como enviada.** `isInvoiceSentToVerifactu()` exige que `verifactu_csv_factura` no esté vacío y que la insignia de estado contenga `attr-status="Enviada"`, es decir, la traducción de `VERIFACTU_STATUS_SEND` en el idioma del usuario actual. Los estados «Pendiente», «Parcial», «Sin Conexión», «Error» y «Enviada (Duplicada)» no muestran QR. Si la factura se envió desde una sesión en otro idioma, la insignia guardada lleva ese otro texto y la comparación falla: repite «Verificar Estado en AEAT» desde el idioma habitual para regenerar la insignia.
2. **El NIF del obligado debe tener un formato válido.** `QRGenerator` valida el NIF antes de construir la URL: 9 caracteres, letra de control correcta en DNI/NIE y patrón de CIF. Un valor con guiones o espacios en `VERIFACTU_HOLDER_NIF` lanza `Tax ID must be 9 characters` o `Unrecognized tax ID format`; la ficha lo captura y lo escribe en el registro (`ActionsVerifactu::moreHtmlStatus error: …`) sin mostrar nada.
3. **Extensión GD** cargada y versión del módulo 2.0.0 o posterior (PHP 8 rompía el generador antes).
4. **En el PDF**, además: que la instalación pueda reimportar documentos (registro: `Verifactu TCPDI: this installation cannot import PDFs, check MAIN_DISABLE_TCPDI`) y que la posición configurada quepa en la página con 2 mm de margen (`Verifactu TCPDI: QR position out of page bounds with required margins`). Revisa `VERIFACTU_QR_SIZE`, `VERIFACTU_QR_POSITION_X`, `VERIFACTU_QR_POSITION_X_CUSTOM` y `VERIFACTU_QR_POSITION_Y`. Los errores del proceso quedan como `Verifactu afterPDFCreation QR Error:` o `Verifactu TCPDI:`. Si el registro dice `TCPDF_PARSER ERROR: decodeFilterFlateDecode: invalid code`, el parser no consigue leer el PDF que ha escrito la plantilla: desde la 2.2.4 se reintenta sobre una copia normalizada con Ghostscript o `qpdf` (opción `VERIFACTU_PDF_NORMALIZER`) y, si no se consigue, aparece un aviso en pantalla en lugar de entregar la factura sin QR. Diagnosticado por **[@perey2002](https://github.com/perey2002)** (Sergio Pereiro) sobre la plantilla Crabe, [issue #55](https://github.com/garacil/verifactu/issues/55).
5. **En el ticket del TPV**, el texto explicativo depende de `VERIFACTU_QR_SHOW_TEXT_TPV`; el QR en sí solo depende de que la factura conste como enviada.

Más detalle en [[Código QR|Codigo-QR]] y [[Plantilla PDF verifactu|Plantilla-PDF-verifactu]].

## La AEAT rechaza con 4102 y señala `BaseImponibleOimporteNoSujeto`

**Síntoma.** Al enviar una factura creada antes de instalar el módulo: `Error VeriFactu: AEAT communication failed: Codigo[4102].El XML no cumple el esquema. Falta informar campo obligatorio.: BaseImponibleOimporteNoSujeto` ([issue #54](https://github.com/garacil/verifactu/issues/54), reportada por **[@miguelpicher](https://github.com/miguelpicher)**).

**Causa.** Ese campo no es el que falta. `SuministroInformacion.xsd` coloca `CalificacionOperacion` y `OperacionExenta` en un `xs:choice` justo antes, y en cada línea del desglose tiene que viajar una de las dos. Los campos fiscales los rellena el disparador de creación de la factura, de modo que una factura anterior a la instalación del módulo no tiene ninguno: el registro salía sin la elección y el validador señalaba el siguiente campo obligatorio de la secuencia.

**Solución.** Actualizar a la 2.2.4, que resuelve esos valores de la factura, del cliente y de los valores por defecto del módulo por ese orden, y transmite `S1` avisando si no hay nada configurado en ninguno de los tres sitios. Conviene además dejar puestos los **valores por defecto** en la configuración del módulo antes de enviar facturas antiguas, y revisar la pestaña VeriFactu de cada una si su tributación no es la general. Detalle en [[Desglose de impuestos|Desglose-de-impuestos]].

## Facturas validadas que el listado muestra como borrador

**Síntoma.** En el listado de facturas VeriFactu, la columna «Estado» (la de Dolibarr, no la de VeriFactu) solo muestra «Borrador» o «Pagada»: una factura validada y pendiente de cobro aparece como borrador aunque en la base de datos tenga `fk_statut = 1` y `paye = 0` ([issue #53](https://github.com/garacil/verifactu/issues/53), reportada por **[@jose73wrc](https://github.com/jose73wrc)**).

**Causa.** `getLibStatut()` construye la etiqueta a partir de `$this->status` desde Dolibarr 20, mientras que la 19 usa `$this->statut` (`htdocs/core/class/commoninvoice.class.php`). El listado del módulo solo rellenaba la segunda, así que en las versiones nuevas el estado llegaba como 0 —borrador— y únicamente el indicador de cobro distinguía las pagadas.

**Solución.** Actualizar a la 2.2.4: `views/list.facture.php` rellena ambas propiedades y el listado dice lo mismo en la 19 que en la 24. Es un fallo de presentación; el estado almacenado y el envío a la AEAT nunca estuvieron afectados.

## Error fatal en el informe de pagos

**Síntoma.** Al generar el informe de pagos de facturas de cliente o de proveedor (Facturas > Informes > Informe de pagos): `Call to undefined method pdf_paiement_fourn::fetch_optionals()` (issue #31).

**Causa.** El hook `beforePDFCreation` no lo disparan solo los modelos PDF de factura: los modelos de informe `pdf_paiement` y `pdf_paiement_fourn` también, y lo que pasan como `$object` no es una factura (en Dolibarr 22 el propio modelo PDF; en Dolibarr 17 un `null`).

**Solución.** Actualizar a la 2.0.0 o posterior: el hook comprueba que el objeto tenga `fetch_optionals()` antes de usarlo y deja de acceder al campo adicional cuando no existe.

## Aparece una clave de traducción en lugar de un mensaje

**Corregido en la 2.2.2.** Hasta la 2.2.1 algunas rutas del código usaban claves que no estaban definidas en los ficheros de idioma, y los textos de la validación masiva solo existían en español e inglés, de modo que Dolibarr mostraba la clave en bruto. La 2.2.2 define todas esas claves en los cinco idiomas y `tests/LanguageKeysTest.php` comprueba que cualquier clave del módulo que pida el código existe y tiene texto en todos ellos. Si ves una clave en mayúsculas, comprueba primero la versión instalada; si es la 2.2.2 o posterior, abre una incidencia indicando la clave y la pantalla.

En versiones anteriores, el significado de cada clave es el de su equivalente:

| Clave que se ve | Significado |
|---|---|
| `VERIFACTU_INVOICE_CAN_NOT_BE_VALID_CIF_REQUIRED` | El CIF/NIF del cliente es obligatorio para VeriFactu (`VERIFACTU_INVOICE_CANNOT_BE_VALID_CIF_REQUIRED`) |
| `VERIFACTU_INVOICE_CAN_NOT_BE_VALID_TVA_INTRA_REQUIRED` | El CIF intracomunitario es obligatorio (`…_VAT_INTRA_REQUIRED`) |
| `VERIFACTU_INVOICE_CAN_NOT_BE_VALID_ADDRESS_REQUIRED` | La dirección del cliente es obligatoria (`…_ADDRESS_REQUIRED`) |
| `VERIFACTU_INVOICE_CAN_NOT_BE_MODIFIED` | La factura no puede modificarse (`VERIFACTU_INVOICE_CANNOT_BE_MODIFIED`) |
| `SOCIETE_HAVE_INVOICES_NOT_IDPROF1_MODIFIED` / `…_TVAINTRA_MODIFIED` | El NIF / CIF intra del tercero no se actualizó porque tiene facturas en VeriFactu (`THIRD_PARTY_HAS_INVOICES_*`) |
| `VERIFACTU_STATUS_ANULADA` | Estado «Anulada» (`VERIFACTU_STATUS_CANCELLED`) |
| `VERIFACTU_ERROR_FACTURA_YA_ANULADA` | La factura ya está anulada (`VERIFACTU_ERROR_INVOICE_ALREADY_CANCELLED`) |
| `VERIFACTU_DEFAULT_SOCIETE_*_SET` | Se aplicó el valor fiscal por defecto del tercero (`VERIFACTU_DEFAULT_THIRD_PARTY_*_SET`) |
| `verifactu_FACTURA_Simplificada`, `verifactu_SIMPLIFIED_INVOICE_CUSTOMER` | Título «Factura simplificada» / destinatario de factura simplificada en el PDF |
| `ERROR_FETCHING_INVOICE` | No se pudo recargar la factura antes de enviarla |

No afectan al envío; son solo textos. La solución es actualizar a la 2.2.2.

## Otros mensajes frecuentes

| Mensaje | Origen | Qué hacer |
|---|---|---|
| «La AEAT requiere esperar %s segundos antes del siguiente envío VeriFactu.» | `TiempoEsperaEnvio` de la última respuesta, guardado en `VERIFACTU_NEXT_SEND_AT`; solo se aplica a los reintentos automáticos | Esperar; la cola lo reintenta sola |
| «La factura %s debe estar validada o cerrada para enviar a VeriFactu.» | Envío desde el listado | Validar la factura primero |
| «No se pueden enviar facturas provisionales (con "PROV" en referencia)» | Pestaña VeriFactu | Validar la factura |
| «Factura ya enviada correctamente. Solo puede enviar una Baja para anularla.» | Pestaña VeriFactu | No es un error: el alta y la modificación quedan deshabilitadas |
| «La factura %s no puede modificarse (restricción VeriFactu)» | Trigger de restricciones al editar, borrar o invalidar una factura sin `PROV` | Emitir rectificativa o anulación ([[Restricciones sobre facturas enviadas|Restricciones-sobre-facturas-enviadas]]) |
| «Multicompany está compartiendo la numeración de facturas entre entidades…» | Pantalla de configuración | Desactivar la compartición de numeración: cada obligado debe tener su serie |
| «Validación parcial: %s facturas validadas, %s con errores.» | Validación masiva | Revisar cada factura fallida; las correctas no se ven afectadas ([[Validación masiva|Validacion-masiva]]) |
| «La fecha de factura %s no puede ser anterior a la última fecha válida %s. Fecha ajustada a %s.» | Orden cronológico | No es un error: la fecha se ajustó ([[Fechas y numeración|Fechas-y-numeracion]]) |

## Véase también

- [[Códigos de error|Codigos-de-error]]
- [[Registro y depuración|Registro-y-depuracion]]
- [[Preguntas frecuentes|Preguntas-frecuentes]]
- [[Certificados digitales|Certificados-digitales]]
- [[Reintentos y envíos pendientes|Reintentos-y-envios-pendientes]]
- [[Encadenamiento y huella|Encadenamiento-y-huella]]
- [[Respuestas de la AEAT|Respuestas-de-la-AEAT]]
- [[Historial de versiones|Historial-de-versiones]]
