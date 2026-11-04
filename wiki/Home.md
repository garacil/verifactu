# VeriFactu para Dolibarr

Documentación del módulo **VeriFactu para Dolibarr ERP/CRM**, que integra la facturación de Dolibarr con el sistema VeriFactu de la Agencia Estatal de Administración Tributaria (AEAT) conforme al Real Decreto 1007/2023 y a la Orden HAC/1177/2024. Esta wiki describe la versión **2.2.4** (rama `main` del repositorio [garacil/verifactu](https://github.com/garacil/verifactu)); cuando un comportamiento cambió en una versión concreta, la página correspondiente lo indica.

El módulo se distribuye bajo licencia **GPL-3.0-or-later**. Tiene su origen en el módulo `verifactu` de Alberto Luque Rivas (easysoft.es), también GPL v3, sobre el que se reorganizó el código, se creó la biblioteca interna de comunicación con la AEAT y se han ido incorporando las correcciones normativas y técnicas recogidas en el [[historial de versiones|Historial-de-versiones]].

## Qué hace el módulo

- Genera el **registro de facturación de alta** de cada factura de cliente en el momento de validarla y lo remite a la AEAT por su servicio web SOAP, con el certificado digital del obligado tributario.
- Mantiene el **encadenamiento** de los registros (huella SHA-256 enlazada con el registro anterior), por entidad y por entorno, y serializa las validaciones concurrentes para que la cadena no se rompa.
- Imprime el **QR tributario** con los textos exigidos en la ficha de la factura, en el PDF y en el ticket del TPV (TakePOS).
- Gestiona **facturas rectificativas** (R1 por sustitución o por diferencias), **anulaciones** y **consultas** del estado de los registros en la AEAT.
- Guarda el resultado de cada envío (estado, huella, CSV, incidencias, errores) en **campos adicionales** de la factura, y reintenta automáticamente los envíos que quedaron pendientes por falta de conexión o por indisponibilidad del servicio.
- Protege las facturas ya remitidas frente a modificaciones que dejarían el registro remitido sin correspondencia con la factura.
- Publica la **declaración responsable** del sistema informático y una comprobación de **integridad** de sus ficheros.

## Por dónde empezar

1. Comprueba los [[requisitos|Requisitos]] del servidor (versiones de PHP y Dolibarr, extensiones, OpenSSL, base de datos).
2. Sigue la guía de [[instalación|Instalacion]] y activa el módulo.
3. Sube y valida el [[certificado digital|Certificados-digitales]] del obligado tributario.
4. Recorre la [[configuración|Configuracion]] completa: datos del obligado, entorno de **pruebas**, opciones de envío, QR y PDF.
5. Valida algunas facturas contra el entorno de pruebas de la AEAT y revisa la [[pestaña VeriFactu|Pestana-VeriFactu-de-la-factura]] de cada una y el [[panel principal|Panel-principal]].
6. Cuando todo cuadre, cambia al entorno de producción. Ten presente que la [[cadena de registros|Encadenamiento-y-huella]] es independiente en cada entorno.

> **Importante:** en cuanto existe la primera huella de la entidad (también si se generó contra el entorno de pruebas), el NIF del obligado queda fijado en la configuración; y las facturas remitidas ya no pueden modificarse ni borrarse desde Dolibarr: cualquier corrección se hace mediante [[rectificativa|Facturas-rectificativas]] o [[anulación|Anulacion-de-facturas]].

## Mapa de la wiki

### Primeros pasos
- [[Requisitos|Requisitos]] — software, extensiones y conectividad necesarios.
- [[Instalación|Instalacion]] — despliegue en `htdocs/custom`, activación y qué crea el módulo.
- [[Actualización|Actualizacion]] — cómo pasar a una versión nueva sin perder nada.
- [[Configuración|Configuracion]] — todas las opciones de la pantalla de ajustes, con sus constantes.
- [[Certificados digitales|Certificados-digitales]] — formatos, conversión, FNMT con OpenSSL 3, alojamiento compartido.
- [[Permisos y menús|Permisos-y-menus]] — permisos, entradas de menú, pestañas, tareas programadas.
- [[Multiempresa|Multiempresa]] — comportamiento con MultiCompany: aislamiento por entidad y comprobaciones.

### Facturación
- [[Flujo de validación y envío|Flujo-de-validacion-y-envio]] — qué ocurre, paso a paso, al validar una factura.
- [[Encadenamiento y huella|Encadenamiento-y-huella]] — la huella SHA-256, el registro anterior y el bloqueo de la cadena.
- [[Validación masiva|Validacion-masiva]] — validación de varias facturas con una transacción por factura.
- [[Fechas y numeración|Fechas-y-numeracion]] — orden cronológico, fecha forzada y referencias provisionales.
- [[Reintentos y envíos pendientes|Reintentos-y-envios-pendientes]] — cola de reenvío y tarea programada.
- [[Respuestas de la AEAT|Respuestas-de-la-AEAT]] — cómo se interpreta cada respuesta del servicio.
- [[Restricciones sobre facturas enviadas|Restricciones-sobre-facturas-enviadas]] — qué bloquea el módulo y por qué.

### Tipos de operación
- [[Tipos de factura|Tipos-de-factura]] — correspondencia entre los tipos de Dolibarr y F1, F2, R1…
- [[Facturas rectificativas|Facturas-rectificativas]] — sustitución, diferencias y facturas rectificadas.
- [[Desglose de impuestos|Desglose-de-impuestos]] — IVA, exenciones, recargo de equivalencia e IRPF.
- [[Anulación de facturas|Anulacion-de-facturas]] — registro de anulación y sus efectos.
- [[Consulta a la AEAT|Consulta-a-la-AEAT]] — cotejo de lo remitido con lo que consta en la AEAT.
- [[Pestaña VeriFactu de la factura|Pestana-VeriFactu-de-la-factura]] — información y acciones disponibles en cada factura.
- [[Campos adicionales|Campos-adicionales]] — los campos `verifactu_*` y su significado.

### Documentos y TPV
- [[Código QR|Codigo-QR]] — contenido, textos obligatorios y opciones.
- [[Plantilla PDF verifactu|Plantilla-PDF-verifactu]] — la plantilla propia y la inserción del QR en otras plantillas.
- [[TakePOS|TakePOS]] — tickets, facturas simplificadas y abonos desde el TPV.
- [[Sustituciones ODT y plantillas|Sustituciones-ODT-y-plantillas]] — etiquetas disponibles para plantillas ODT.
- [[Panel principal|Panel-principal]] — estadísticas y listas del menú VeriFactu.

### Interior del módulo
- [[Arquitectura del módulo|Arquitectura-del-modulo]] — carpetas, orden de carga, hooks y triggers.
- [[Biblioteca newfenix|Biblioteca-newfenix]] — la biblioteca interna que construye y envía los registros.
- [[Esquemas WSDL y XSD|Esquemas-WSDL-y-XSD]] — caché local de esquemas y validación previa al envío.
- [[Conexión SOAP y entornos|Conexion-SOAP-y-entornos]] — servicios de pruebas y producción, TLS y certificado.
- [[Declaración responsable|Declaracion-responsable]] — identidad del sistema informático declarada y transmitida.
- [[Integridad del módulo|Integridad-del-modulo]] — hash de los ficheros verificados.
- [[API REST|API-REST]] — puntos de acceso expuestos a través de la API de Dolibarr.

### Soporte
- [[Resolución de problemas|Resolucion-de-problemas]] — guía por síntomas.
- [[Preguntas frecuentes|Preguntas-frecuentes]]
- [[Códigos de error|Codigos-de-error]] — errores del módulo, de la biblioteca y de la AEAT.
- [[Registro y depuración|Registro-y-depuracion]] — qué escribe el módulo en el registro y cómo leerlo.

### Proyecto
- [[Suite de pruebas|Suite-de-pruebas]] — qué cubre cada test y cómo ejecutarlos.
- [[Desarrollo y contribución|Desarrollo-y-contribucion]] — entorno, convenciones y cómo aportar.
- [[Publicación de versiones|Publicacion-de-versiones]] — dónde se declara la versión y cómo se publica.
- [[Historial de versiones|Historial-de-versiones]] — de la 1.0.1 a la 2.2.4.
- [[Marco normativo|Marco-normativo]] — RD 1007/2023, Orden HAC/1177/2024 y los artículos que aplica el módulo.
- [[Glosario|Glosario]]

## Convenciones de esta wiki

- Las rutas de ficheros son relativas a la carpeta del módulo (`htdocs/custom/verifactu/`), por ejemplo `lib/functions/functions.hash.php`.
- Las constantes de configuración se escriben en mayúsculas (`VERIFACTU_HOLDER_NIF`) y corresponden a entradas de la tabla `llx_const` de Dolibarr.
- Las funciones se citan con paréntesis (`getLastInvoiceHash()`); las clases de la biblioteca interna, con su espacio de nombres cuando importa.
- «Entidad» se refiere a una entidad de MultiCompany; sin ese módulo, la entidad es siempre la 1.

## Cómo obtener ayuda

Las incidencias se gestionan en el [gestor de incidencias del repositorio](https://github.com/garacil/verifactu/issues). Antes de abrir una, consulta la [[resolución de problemas|Resolucion-de-problemas]] y reúne la información que se indica en [[registro y depuración|Registro-y-depuracion]] (versión del módulo y de Dolibarr, base de datos, entorno de la AEAT, mensajes del registro y del servidor web). Las propuestas de cambio se explican en [[desarrollo y contribución|Desarrollo-y-contribucion]].
