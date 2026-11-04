# Preguntas frecuentes

Respuestas breves a las dudas más habituales sobre el módulo VeriFactu para Dolibarr (2.2.2), agrupadas por tema. Cada respuesta remite a la página de la wiki donde el asunto se trata a fondo. Parte de este contenido reelabora la página de ayuda integrada en el módulo (menú VeriFactu > Ayuda, fichero `views/faq.php`) y el README del repositorio.

> **Aviso:** las respuestas sobre tipos de factura, claves de régimen, calificaciones y exenciones son orientativas. La configuración fiscal es responsabilidad de la empresa; en caso de duda, consulta con tu asesor fiscal.

## General

**¿Qué es VeriFactu y qué hace este módulo?**
VeriFactu es el sistema de la AEAT para verificar la integridad y autenticidad de las facturas, regulado por el RD 1007/2023 y la Orden HAC/1177/2024. El módulo genera el registro de facturación de cada factura de cliente al validarla, lo remite a la AEAT por SOAP con el certificado del obligado, mantiene el encadenamiento de huellas SHA-256, imprime el QR tributario y gestiona rectificativas, anulaciones y consultas. Véase [[Marco normativo|Marco-normativo]] y [[Home]].

**¿El módulo infiere automáticamente los datos fiscales de cada operación?**
No. Impuesto, Clave de Régimen, Calificación y Operación Exenta se configuran manualmente (valores por defecto globales, por tercero o por factura). El módulo solo aplica dos automatismos: usa la clave de régimen 18 en las líneas con recargo de equivalencia y rellena valores por defecto al crear la factura. Véase [[Desglose de impuestos|Desglose-de-impuestos]] y [[Campos adicionales|Campos-adicionales]].

**¿Funciona en modo no VERI*FACTU?**
No. La configuración indica «Actualmente solo disponible el modo VeriFactu. Modo NO-VeriFactu en desarrollo». El módulo transmite `TipoUsoPosibleSoloVerifactu = S` en la identidad del sistema.

**¿Qué idiomas incluye?**
Español, catalán, euskera, gallego e inglés (`langs/es_ES`, `ca_ES`, `eu_ES`, `gl_ES`, `en_US`).

**¿Qué licencia tiene?**
GPL-3.0-or-later. Al activarlo aceptas sus términos, y la pantalla de activación recuerda que los campos fiscales son responsabilidad de la empresa. Véase [[Desarrollo y contribución|Desarrollo-y-contribucion]].

**¿Dónde está el código y cómo sé qué versión tengo?**
En [github.com/garacil/verifactu](https://github.com/garacil/verifactu). La versión se muestra en la lista de módulos de Dolibarr y en la pestaña «Acerca de» de la configuración; en la 2.2.2 coincide en `core/modules/modVerifactu.class.php`, en la declaración responsable y en el README. Véase [[Historial de versiones|Historial-de-versiones]].

## Instalación y actualización

**¿Qué necesita el servidor?**
PHP 7.4 o superior con las extensiones SOAP, OpenSSL, GD, mbstring y JSON; un certificado digital válido; y salida a los servidores de la AEAT. Las versiones de Dolibarr sobre las que se ha verificado la 2.0.0 figuran en el README (17.0.2, 19.0.3 y 22.0.5). Véase [[Requisitos|Requisitos]].

**¿Cómo se instala?**
Copiando la carpeta `verifactu` en `htdocs/custom/` y activando el módulo en Configuración > Módulos. La activación crea los campos adicionales `verifactu_*`, las constantes, la tarea programada de reintentos y los menús. Véase [[Instalación|Instalacion]].

**¿Cómo se actualiza?**
Sustituyendo la carpeta por la nueva versión y volviendo a activar el módulo si hace falta. Tras actualizar, comprueba que la lista de módulos muestra la versión nueva: entre diciembre de 2025 y marzo de 2026 los paquetes 1.0.4 y 1.1 seguían declarándose como 1.0.3. Véase [[Actualización|Actualizacion]].

**Tras actualizar a la 2.1.0 o la 2.2.0, el menú VeriFactu da error 500.**
Es un fallo conocido de esas dos versiones (faltaba `core/lib/date.lib.php` en `verifactuindex.php`), corregido en la 2.2.1. Véase [[Resolución de problemas|Resolucion-de-problemas]].

**¿Funciona con PostgreSQL?**
Sí, desde la 2.1.0. Antes, la consulta de la cadena usaba `DATE_FORMAT()` y fallaba en silencio, de modo que cada factura se encadenaba como primer registro. Si tu instalación usa PostgreSQL y enviaste facturas con una versión anterior, revísalas. Véase [[Encadenamiento y huella|Encadenamiento-y-huella]].

## Certificados

**¿Qué certificado necesito y en qué formato?**
Un certificado electrónico cualificado del obligado tributario o de su representante, en formato PFX/P12 o PEM. Los P12/PFX se convierten automáticamente a PEM al subirlos. Véase [[Certificados digitales|Certificados-digitales]].

**Subo un `.p12` de la FNMT y el módulo dice que la contraseña es incorrecta, pero es correcta.**
Hasta la 2.0.0 el módulo confundía el cifrado antiguo de esos contenedores (`RC2-40-CBC`, `PBE-SHA1-3DES`), que OpenSSL 3 desactiva, con una contraseña errónea. Desde la 2.0.0 reintenta con `openssl pkcs12 -legacy` y distingue ambos casos. Si sigue fallando, activa el proveedor *legacy* en `openssl.cnf` o reexporta el certificado con un algoritmo moderno. Véase [[Resolución de problemas|Resolucion-de-problemas]].

**Mi alojamiento tiene `exec()` y `proc_open()` desactivados. ¿Puedo usar el módulo?**
Sí, desde la 2.0.0, con certificados de cifrado moderno: la extracción usa primero `openssl_pkcs12_read()`, que no lanza procesos. Los certificados legacy siguen necesitando el binario `openssl`; reexpórtalos desde otro ordenador.

**¿Qué tipo de certificado elijo: «normal» o «sello»?**
«Normal» es compatible con todos los certificados y es el recomendado. «Sello» solo vale para certificados de sello de empresa; con uno personal produce errores «no XML document» en las consultas. Véase [[Configuración|Configuracion]].

**¿Puedo renovar el certificado sin romper la cadena?**
Sí. El certificado se puede sustituir siempre; lo que queda fijado en cuanto existe la primera huella es el NIF del obligado. Al subir el nuevo P12 el módulo borra los PEM anteriores (desde la 1.0.4).

**¿El módulo comprueba que el certificado corresponda al NIF del obligado?**
No. Comprueba que el certificado y la clave coincidan y que esté dentro de su periodo de validez; la correspondencia con el obligado (o con su representante o colaborador social) la comprueba la AEAT.

## Facturación

**¿Cuándo se envía la factura a la AEAT?**
Al validarla, si `VERIFACTU_DIRECT_CALL_ON_VALIDATE` está activo (y siempre en facturas de TakePOS). Si está desactivado, desde la 2.1.0 la factura queda marcada como pendiente (`PENDING_SUBMISSION`, Incidencia = S) y la envía la cola de reintentos; también puedes enviarla a mano desde la pestaña VeriFactu o desde el listado. Véase [[Flujo de validación y envío|Flujo-de-validacion-y-envio]].

**¿Por qué la factura vuelve a borrador al validar?**
Porque el envío falló y el módulo no deja una factura validada sin registro. El motivo está en los campos Error y Última respuesta de la pestaña VeriFactu. Véase [[Resolución de problemas|Resolucion-de-problemas]].

**¿Por qué me cambia la fecha de la factura al validarla?**
VeriFactu exige orden cronológico: la fecha se ajusta a `max(hoy, fecha de la última factura validada)` y el diálogo de confirmación lo avisa. En la validación masiva se pide confirmación antes de ajustar. Véase [[Fechas y numeración|Fechas-y-numeracion]].

**¿Puedo validar muchas facturas a la vez?**
Sí. La validación masiva se intercepta y cada factura se procesa en su propia transacción, ordenadas por fecha ascendente; si una falla, las demás continúan. Véase [[Validación masiva|Validacion-masiva]].

**¿Se envían las facturas proforma?**
No. Una proforma no es una factura expedida y no genera registro de facturación (Art. 6 RD 1007/2023); `isVerifactuApplicableInvoice()` las excluye de todas las vías de envío desde la 2.0.0. Se validan con normalidad en Dolibarr.

**¿Cómo se tratan las facturas con retención IRPF?**
El `ImporteTotal` enviado y el del QR son base + IVA + recargo, sin deducir el IRPF (`getVerifactuImporteTotal()`, desde la 1.0.4). Véase [[Desglose de impuestos|Desglose-de-impuestos]].

**¿Puedo modificar o borrar una factura ya enviada?**
No. El trigger de restricciones bloquea la edición de líneas, el borrado y la invalidación de cualquier factura sin `PROV` en la referencia, y el NIF de un tercero con facturas enviadas no se puede cambiar. Cualquier corrección se hace con una rectificativa o una anulación. Véase [[Restricciones sobre facturas enviadas|Restricciones-sobre-facturas-enviadas]].

**Un cliente extranjero no pasa la validación.**
Los terceros no españoles necesitan el CIF intracomunitario o identificación extranjera (campo «CIF intra/ID extranjero») y el tipo de identificación (02 NIF-IVA, 03 pasaporte, 04 documento oficial, 05 certificado de residencia, 06 otro documento, 07 no censado). Reino Unido ya no es intracomunitario: usa 06 o 04. Véase [[Campos adicionales|Campos-adicionales]].

**¿Qué pasa si no hay Internet al validar?**
La factura se valida en local, queda como «Sin Conexión» (`NO_INTERNET_CONNECTION`, Incidencia = S) y la cola de reintentos la envía cuando vuelve la conexión, respetando la fecha de expedición original. Véase [[Reintentos y envíos pendientes|Reintentos-y-envios-pendientes]].

## Tipos de factura, rectificativas y anulaciones

**¿Qué tipo VeriFactu recibe cada factura de Dolibarr?**
Estándar, de situación y de anticipo: F1. Facturas de TakePOS o de terceros marcados como «cliente de factura simplificada»: F2 (y R5 sus abonos). Abonos y facturas de sustitución: R1 (por diferencias y por sustitución respectivamente). Proformas: ninguno. Véase [[Tipos de factura|Tipos-de-factura]].

**¿Por qué la pestaña muestra R1 y no R2 para un abono?**
Porque R1 es lo que se transmite. Hasta la 2.0.0 se almacenaba R2 pero se enviaba R1 (issue #30, punto 5); se unificó al tipo real. Véase [[Facturas rectificativas|Facturas-rectificativas]].

**¿Cuándo uso R2, R3 o R4?**
R2 corresponde al Art. 80.3 LIVA, R3 al 80.4 y R4 al resto de casos; R1 a los errores fundados en derecho y al Art. 80 Uno, Dos y Seis. El módulo envía R1 para los abonos y sustituciones de Dolibarr; otros tipos requieren fijar el campo «Tipo de factura» en la pestaña VeriFactu antes de validar y el criterio de tu asesor.

**¿Cómo anulo una factura en la AEAT?**
Desde la pestaña VeriFactu, botón «Enviar Baja», eligiendo el tipo: normal, por rechazo previo o sin registro previo. Desde la 2.2.0 una anulación puede abrir la cadena si no hay registro anterior, tal como admite el esquema oficial. Véase [[Anulación de facturas|Anulacion-de-facturas]].

**¿Qué diferencia hay entre `RechazoPrevio = S` y `X`?**
`S` significa que la AEAT rechazó antes ese registro; `X` que el registro nunca llegó a la AEAT (por ejemplo, al acogerse a VeriFactu desde un sistema que no lo usaba). Hasta la 2.0.0 el módulo enviaba siempre `X`. Véase [[Facturas rectificativas|Facturas-rectificativas]].

## Errores y estados

**¿Qué significan las insignias de estado?**
«No Enviada», «Enviada», «Parcial», «Error», «Anulada», «Sin Conexión», «Pendiente», «Enviada (Duplicada)» y «Duplicada (Importes diferentes)». Cada una y su origen están en [[Códigos de error|Codigos-de-error]].

**La AEAT dice que la factura ya existe (error 3000).**
El módulo consulta la AEAT y compara importes: si coinciden la marca como enviada; si no, la deja como «Duplicada (Importes diferentes)» y te pide decidir entre rectificativa o corrección en Dolibarr. Antes de cada alta ya comprueba si existe para evitarlo. Véase [[Consulta a la AEAT|Consulta-a-la-AEAT]].

**Un envío salió «ParcialmenteCorrecto». ¿Está enviado?**
Solo si todas las líneas de la respuesta están en `Correcto` o `AceptadoConErrores`. Desde la 2.0.0 una línea `Incorrecto` dentro de una respuesta parcial no se da por enviada (PR #36). Véase [[Respuestas de la AEAT|Respuestas-de-la-AEAT]].

**¿Dónde veo la respuesta exacta de la AEAT?**
En la pestaña VeriFactu: «Última respuesta» (resumen con estado, CSV, código y descripción de error), «Error» y «Datos XML» (la carga enviada, en JSON). Con el registro de Dolibarr en `LOG_DEBUG` queda además todo el recorrido. Véase [[Registro y depuración|Registro-y-depuracion]].

**¿Qué hago con «Se está encadenando otra factura en este momento…»?**
Reintentar en unos segundos. Es el bloqueo que serializa la cadena (2.1.0); su espera se ajusta con `VERIFACTU_CHAIN_LOCK_TIMEOUT` (30 s por defecto). Véase [[Encadenamiento y huella|Encadenamiento-y-huella]].

**¿Dónde están los códigos oficiales de error de la AEAT?**
El módulo solo interpreta el 3000. Los demás se muestran tal cual; la página de ayuda del módulo enlaza el fichero oficial `errores.properties` de la AEAT. Véase [[Códigos de error|Codigos-de-error]].

## Normativa y declaración responsable

**¿Qué normas aplica el módulo?**
RD 1007/2023, Orden HAC/1177/2024, RD 254/2025 y el artículo 29.2.j) de la Ley 58/2003, más el RD 1619/2012 y la LIVA para los tipos de factura. Véase [[Marco normativo|Marco-normativo]].

**¿Desde cuándo es obligatorio?**
El módulo muestra los plazos que recogió en la 1.0.3: sociedades el 1 de enero de 2027 y autónomos el 1 de julio de 2027, y conmuta solo al entorno de producción en esas fechas según `VERIFACTU_COMPANY_TYPE`. Compruébalos siempre en la sede de la AEAT y en el BOE, porque pueden cambiar. Véase [[Conexión SOAP y entornos|Conexion-SOAP-y-entornos]].

**¿Qué es la declaración responsable y quién la firma?**
Es la declaración del productor del sistema informático exigida por la Orden HAC/1177/2024. El módulo la incluye en `conf/declaracion_responsable.conf.php` y la muestra en el menú VeriFactu > Declaración responsable, con un hash de integridad de sus ficheros críticos. Quien redistribuya el módulo bajo su nombre edita ese fichero. Véase [[Declaración responsable|Declaracion-responsable]] e [[Integridad del módulo|Integridad-del-modulo]].

**¿La identidad declarada coincide con la enviada a la AEAT?**
Sí, desde la 2.0.0: `getSystemConfig()` lee `IdSistemaInformatico` («DV»), `NombreSistemaInformatico` («Dolibarr Verifactu Module») y `Version` de la propia declaración, respetando los límites del esquema (2, 30 y 50 caracteres). Si un valor se recorta, desde la 2.1.0 queda anotado como error en el registro.

## Multiempresa

**¿Puedo usar el módulo con MultiCompany?**
Sí. Cada entidad es un obligado tributario con su propia cadena, certificado y configuración (`VERIFACTU_HOLDER_NIF` y `VERIFACTU_HOLDER_COMPANY_NAME` son constantes por entidad). Desde la 2.0.0 todas las consultas se acotan a la entidad activa, sin incluir las entidades con las que se comparten facturas. Véase [[Multiempresa|Multiempresa]].

**¿Dos entidades pueden tener el mismo NIF?**
No. Desde la 2.2.0 la pantalla de configuración lo rechaza («El NIF del obligado tributario ya está configurado en la entidad VeriFactu %s…»), leyendo solo tablas del núcleo. La comprobación no se ejecuta en el envío.

**Me avisa de que la numeración de facturas está compartida.**
Con la numeración compartida, dos obligados tomarían su serie del mismo contador, algo que VeriFactu no puede conciliar. Desactiva esa compartición en MultiCompany.

## TPV (TakePOS)

**¿Qué tipo de factura genera el TPV?**
F2 (simplificada) y R5 para los abonos. Las facturas de TakePOS se envían siempre al validar, sin exigir NIF ni dirección del cliente. Véase [[TakePOS|TakePOS]].

**¿El ticket lleva el QR?**
Sí, en la cabecera, cuando la factura consta como enviada. Los textos «QR tributario:» y «VeriFactu» se controlan con `VERIFACTU_QR_SHOW_TEXT_TPV`. Véase [[Código QR|Codigo-QR]].

**¿Y si el TPV no tiene conexión?**
La factura queda «Sin Conexión» con Incidencia = S y se reenvía después con su fecha original. Véase [[Reintentos y envíos pendientes|Reintentos-y-envios-pendientes]].

## Desarrollo y API

**¿Hay una API?**
Sí: `GET /api/index.php/verifactuapi/integrity` devuelve el hash de integridad sin autenticación, y `POST …/verifactuapi/toProduction` y `…/toTest` fuerzan el entorno de la entidad; desde la 2.2.2 estos dos exigen la clave de API (`DOLAPIKEY`) de un administrador. En Dolibarr 17 la API del módulo no funciona por un fallo del núcleo. Véase [[API REST|API-REST]].

**¿Cómo ejecuto los tests?**
`php tests/NombreDelTest.php` desde la carpeta del módulo; no necesitan PHPUnit, Dolibarr ni base de datos. Véase [[Suite de pruebas|Suite-de-pruebas]].

**¿Cómo se publica una versión?**
Al integrar en `main` con el número declarado en los tres ficheros de versión y una entrada en el README, GitHub Actions ejecuta la suite y crea la etiqueta y la release. Véase [[Publicación de versiones|Publicacion-de-versiones]].

**¿Puedo redistribuir el módulo con mi marca?**
Sí, respetando la GPL-3.0-or-later. En ese caso eres el productor: edita la declaración responsable con tus datos y mantén coherentes la identidad del sistema y la versión. Véase [[Desarrollo y contribución|Desarrollo-y-contribucion]].

## Véase también

- [[Resolución de problemas|Resolucion-de-problemas]]
- [[Códigos de error|Codigos-de-error]]
- [[Glosario|Glosario]]
- [[Marco normativo|Marco-normativo]]
- [[Configuración|Configuracion]]
