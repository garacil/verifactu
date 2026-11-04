# Glosario

Términos que aparecen en la documentación y en la interfaz del módulo VeriFactu para Dolibarr (2.2.2), ordenados alfabéticamente, con una definición breve y el enlace a la página donde se explican. Los términos en cursiva dentro de una definición tienen su propia entrada.

## A

**Advisory lock**
Bloqueo de aplicación de PostgreSQL (`pg_try_advisory_lock(1007, clave)`) con el que el módulo serializa la sección crítica de la *cadena* desde la 2.1.0; en MySQL/MariaDB se usa `GET_LOCK()`. Vive en la sesión de base de datos y se libera al terminar el proceso. Véase [[Encadenamiento y huella|Encadenamiento-y-huella]].

**AEAT**
Agencia Estatal de Administración Tributaria, destinataria de los *registros de facturación*. Véase [[Marco normativo|Marco-normativo]].

**Alta (registro de alta)**
*Registro de facturación* que declara una factura expedida (`RegistroAlta`). Es lo que envía la acción «Enviar Alta». Véase [[Flujo de validación y envío|Flujo-de-validacion-y-envio]].

**Anulación (registro de anulación)**
*Registro de facturación* que deja sin efecto un alta (`RegistroAnulacion`). Puede ser normal, por *rechazo previo* o *sin registro previo*, y desde la 2.2.0 puede abrir la *cadena*. Véase [[Anulación de facturas|Anulacion-de-facturas]].

## B

**BillingException**
Excepción de la *biblioteca newfenix* con categoría (validation, communication, authentication, processing, configuration), contexto y marca de tiempo. Véase [[Códigos de error|Codigos-de-error]].

## C

**Cadena** (véase *Encadenamiento*).

**CalificacionOperacion**
Calificación de la operación en el desglose (lista L9): `S1` sujeta sin inversión del sujeto pasivo, `S2` sujeta con inversión, `N1` no sujeta (arts. 7 y 14 LIVA), `N2` no sujeta por reglas de localización. Véase [[Desglose de impuestos|Desglose-de-impuestos]].

**Campos adicionales**
Los *extrafields* `verifactu_*` que el módulo crea en factura y tercero para guardar tipo, campos fiscales, estado, CSV, huella, error, carga enviada, entorno e incidencia. No se pueden borrar desde la interfaz. Véase [[Campos adicionales|Campos-adicionales]].

**Certificado PFX / P12 / PEM**
Formatos del certificado electrónico del obligado. PFX y P12 son contenedores PKCS#12 con certificado y clave privada protegidos por contraseña; el módulo los convierte a PEM (texto) para usarlos con la extensión SOAP. Véase [[Certificados digitales|Certificados-digitales]].

**Certificado «normal» / «sello»**
Tipo de certificado configurado en `VERIFACTU_CERT_TYPE`. «Normal» usa los servidores `www1`/`prewww1` y admite cualquier certificado; «sello» usa `www10`/`prewww10` y solo vale para certificados de sello de empresa. Véase [[Conexión SOAP y entornos|Conexion-SOAP-y-entornos]].

**ClaveRegimen**
Clave de régimen del desglose (lista L8): `01` general, `02` exportación, `03` bienes usados, `04` oro de inversión, `05` agencias de viajes, `06` grupo de entidades, `07` criterio de caja, `08` operaciones sujetas a IPSI/IGIC, `09` a `11`, `14`, `15`, `17` OSS/IOSS, `18` recargo de equivalencia, `19` REAGYP, `20` simplificado. Solo se informa con IVA e IGIC. Véase [[Desglose de impuestos|Desglose-de-impuestos]].

**Colaborador social**
Persona o entidad autorizada a presentar declaraciones en nombre de terceros; su certificado puede firmar por varios *obligados tributarios*, motivo por el que el módulo no impide reutilizar un certificado entre entidades. Véase [[Multiempresa|Multiempresa]].

**CSV (Código Seguro de Verificación)**
Código que devuelve la AEAT al aceptar un envío (`CSV` de la respuesta). El módulo lo guarda en `verifactu_csv_factura`; sin CSV la factura no se considera enviada. Véase [[Respuestas de la AEAT|Respuestas-de-la-AEAT]].

## D

**Declaración responsable**
Declaración del *productor* del sistema informático de que cumple el RD 1007/2023 y la Orden HAC/1177/2024 (art. 29.2.j) LGT). El módulo la define en `conf/declaracion_responsable.conf.php` y la muestra y exporta en JSON. Véase [[Declaración responsable|Declaracion-responsable]].

**Desglose (DetalleDesglose)**
Bloque del registro con una línea por combinación de tipo impositivo, recargo y régimen: impuesto, clave de régimen, calificación o exención, base, tipo, cuota y, si procede, recargo de equivalencia. Máximo 12 líneas. Véase [[Desglose de impuestos|Desglose-de-impuestos]].

**Duplicado (error 3000)**
Respuesta de la AEAT cuando el registro ya existe. El módulo consulta la AEAT y compara importes: iguales, «Enviada (Duplicada)»; distintos, «Duplicada (Importes diferentes)». Véase [[Consulta a la AEAT|Consulta-a-la-AEAT]].

## E

**Encadenamiento**
Enlace de cada *registro de facturación* con el anterior del mismo obligado mediante su *huella*, número, fecha y NIF (`RegistroAnterior`) o la marca de *primer registro*. Exigido por el Art. 13 de la Orden HAC/1177/2024. Véase [[Encadenamiento y huella|Encadenamiento-y-huella]].

**Entidad (MultiCompany)**
Cada empresa de una instalación con el módulo MultiCompany. Para VeriFactu cada entidad es un *obligado tributario* con su cadena, certificado y configuración; las consultas se acotan a la entidad activa. Véase [[Multiempresa|Multiempresa]].

**Entorno de pruebas / producción**
Los dos entornos de la AEAT: preproducción (`prewww1`, `prewww2`, `prewww10.aeat.es`) y real (`www1`, `www2`, `www10.agenciatributaria.gob.es`). El módulo los selecciona con `getEnvironment()` y mantiene cadenas separadas (`verifactu_entorno`). Véase [[Conexión SOAP y entornos|Conexion-SOAP-y-entornos]].

**Esquemas WSDL y XSD**
Definición del servicio SOAP (`SistemaFacturacion.wsdl`) y de los mensajes (`SuministroInformacion.xsd`, `SuministroLR.xsd`, `ConsultaLR.xsd`, `RespuestaConsultaLR.xsd`, `RespuestaSuministro.xsd`, `xmldsig-core-schema.xsd`), que el módulo descarga y guarda en local. Véase [[Esquemas WSDL y XSD|Esquemas-WSDL-y-XSD]].

**EstadoEnvio / EstadoRegistro**
Estado global de la respuesta (`Correcto`, `ParcialmenteCorrecto`, `Incorrecto`) y estado de cada línea (`Correcto`, `AceptadoConErrores`, `Incorrecto`). Un envío solo se acepta si todas las líneas están aceptadas. Véase [[Respuestas de la AEAT|Respuestas-de-la-AEAT]].

**Exención (OperacionExenta)**
Causa de exención del desglose (lista L10): `E1` art. 20 LIVA, `E2` art. 21, `E3` art. 22, `E4` arts. 23 y 24, `E5` art. 25, `E6` otros. Véase [[Desglose de impuestos|Desglose-de-impuestos]].

## F

**F1 / F2 / F3**
Tipos de factura no rectificativa (lista L2): `F1` factura completa (art. 6, 7.2 y 7.3 RD 1619/2012), `F2` simplificada o sin identificación del destinatario (art. 6.1.d), `F3` emitida en sustitución de simplificadas. Véase [[Tipos de factura|Tipos-de-factura]].

**Factura simplificada**
Factura sin datos completos del destinatario (tipo `F2`). El módulo la genera para facturas de TakePOS y para terceros con «Cliente de factura simplificada» activado; no admite destinatarios en el registro. Véase [[TakePOS|TakePOS]].

**FechaHoraHusoGenRegistro**
Fecha y hora de generación del registro con huso horario (ISO 8601, `Europe/Madrid`), que forma parte de la *huella*. Se guarda en `verifactu_fecha_hora_generacion`. Véase [[Encadenamiento y huella|Encadenamiento-y-huella]].

## H

**Hook**
Punto de extensión de Dolibarr que el módulo usa en los contextos `main`, `invoicecard`, `invoicelist`, `invoicelistverifactu`, `pdfgeneration`, `takeposfrontend` y `odtgeneration` (clase `ActionsVerifactu`). Véase [[Arquitectura del módulo|Arquitectura-del-modulo]].

**Huella**
Resumen SHA-256 (`Huella`, `TipoHuella = 01`) de los campos clave del registro, incluida la huella del registro anterior. Se transmite en mayúsculas y se guarda en `verifactu_huella`. Véase [[Encadenamiento y huella|Encadenamiento-y-huella]].

## I

**IDOtro**
Identificación de un destinatario no español: código de país, tipo de identificación (`02` NIF-IVA, `03` pasaporte, `04` documento oficial, `05` certificado de residencia, `06` otro documento, `07` no censado) y número. Véase [[Campos adicionales|Campos-adicionales]].

**IdSistemaInformatico**
Identificador del sistema asignado por el *productor*, de dos caracteres como máximo (`sf:TextMax2Type`). El módulo declara y transmite `DV`. Véase [[Declaración responsable|Declaracion-responsable]].

**Incidencia**
Marca `RemisionVoluntaria/Incidencia = S` que indica que el registro no pudo remitirse en el momento de la expedición (sin conexión, servicio caído, envío directo desactivado). El módulo la guarda en `verifactu_incidencia` y la conserva tras el reenvío. Véase [[Reintentos y envíos pendientes|Reintentos-y-envios-pendientes]].

**Integridad del módulo**
Hash SHA-256 de los ficheros críticos listados en `ficheros_verificados` de la declaración responsable, publicado en la declaración, en `integrity.php` y en la API. Véase [[Integridad del módulo|Integridad-del-modulo]].

**IRPF**
Retención del Impuesto sobre la Renta que Dolibarr guarda en `total_localtax2` (negativo) y descuenta del total. VeriFactu no la deduce: `ImporteTotal = total_ttc + abs(total_localtax2)`. Véase [[Desglose de impuestos|Desglose-de-impuestos]].

## L

**Legacy provider (OpenSSL)**
Proveedor de algoritmos antiguos de OpenSSL 3, desactivado por defecto, necesario para abrir contenedores PKCS#12 cifrados con `RC2-40-CBC` y `PBE-SHA1-3DES` (certificados FNMT antiguos). El módulo reintenta con `openssl pkcs12 -legacy`. Véase [[Certificados digitales|Certificados-digitales]].

## M

**MultiCompany** (véase *Entidad*).

## N

**newfenix (biblioteca)**
Biblioteca interna `OpenAEAT\Billing` en `lib/newfenix/src` (`Invoice`, `Cancellation`, `Query`, `Manager`, `SoapClient`, `Config`, `SchemaManager`, `QRGenerator`, `BillingException`), con alias `Sietekas\Verifactu\*` por compatibilidad. Véase [[Biblioteca newfenix|Biblioteca-newfenix]].

**NIF**
Número de identificación fiscal. El del *obligado tributario* se configura en `VERIFACTU_HOLDER_NIF` y queda fijado con la primera huella; el del destinatario español sale del campo CIF/NIF del tercero. Véase [[Configuración|Configuracion]].

**NombreSistemaInformatico**
Nombre del sistema transmitido en cada registro, de 30 caracteres como máximo (`sf:TextMax30Type`). El módulo declara y transmite `Dolibarr Verifactu Module`. Véase [[Declaración responsable|Declaracion-responsable]].

**NumeroInstalacion**
Identificador de la instalación transmitido en `SistemaInformatico`, formado por el identificador único de la instancia de Dolibarr y la entidad, recortado a 100 caracteres. Véase [[Declaración responsable|Declaracion-responsable]].

## O

**Obligado tributario (ObligadoEmision)**
Empresa o profesional que expide las facturas y en cuyo nombre se remiten los registros: `NombreRazon` y `NIF` de la cabecera. Véase [[Configuración|Configuracion]].

## P

**Panel principal**
Página `verifactuindex.php` con estadísticas de envíos, facturas con errores y pendientes. Véase [[Panel principal|Panel-principal]].

**Pendiente (PENDING_SUBMISSION / SERVICE_UNAVAILABLE)**
Estado de una factura validada cuyo registro espera su envío por la cola de reintentos. Véase [[Reintentos y envíos pendientes|Reintentos-y-envios-pendientes]].

**Pestaña VeriFactu**
Pestaña de la ficha de la factura con el estado, los datos en la AEAT, la carga enviada y las acciones Alta, Modificación, Baja y Verificar Estado. Véase [[Pestaña VeriFactu de la factura|Pestana-VeriFactu-de-la-factura]].

**Primer registro (PrimerRegistro)**
Marca `Encadenamiento/PrimerRegistro = S` del primer registro de la *cadena* de un obligado en un entorno. Véase [[Encadenamiento y huella|Encadenamiento-y-huella]].

**Productor**
Quien desarrolla, fabrica o comercializa el sistema informático y suscribe la *declaración responsable*. Quien redistribuye el módulo bajo su nombre pasa a serlo. Véase [[Desarrollo y contribución|Desarrollo-y-contribucion]].

**Proforma**
Factura de tipo proforma de Dolibarr (`TYPE_PROFORMA`), que no es una factura expedida y no genera registro (Art. 6 RD 1007/2023). Excluida por `isVerifactuApplicableInvoice()`. Véase [[Tipos de factura|Tipos-de-factura]].

**PROV**
Prefijo de la referencia provisional de una factura en borrador (`(PROVn)`). Las restricciones del módulo se aplican a toda factura cuya referencia no contenga `PROV`. Véase [[Fechas y numeración|Fechas-y-numeracion]].

## Q

**QR tributario**
Código QR con la URL de cotejo de la AEAT (`nif`, `numserie`, `fecha`, `importe`), impreso con los textos «QR tributario:» y «VeriFactu», de 30 a 40 mm. Véase [[Código QR|Codigo-QR]].

## R

**R1 – R5**
Tipos de factura rectificativa (lista L2): `R1` error fundado en derecho y art. 80 Uno, Dos y Seis LIVA; `R2` art. 80.3; `R3` art. 80.4; `R4` resto; `R5` rectificativa de simplificadas. El módulo envía R1 para abonos y sustituciones y R5 para abonos de TakePOS. Véase [[Facturas rectificativas|Facturas-rectificativas]].

**RechazoPrevio**
Indicador de una *subsanación* o anulación: `S` si la AEAT rechazó antes el registro, `X` si nunca llegó a la AEAT. Véase [[Facturas rectificativas|Facturas-rectificativas]].

**Recargo de equivalencia**
Recargo del régimen de comerciantes minoristas (`localtax1` en Dolibarr). En el desglose se informa con `TipoRecargoEquivalencia` y `CuotaRecargoEquivalencia`, y fuerza la clave de régimen `18`. Véase [[Desglose de impuestos|Desglose-de-impuestos]].

**Rectificativa por sustitución / por diferencias**
Método de rectificación (`TipoRectificativa`): `S` sustituye a la factura rectificada (facturas de sustitución de Dolibarr, con bloque `FacturasRectificadas` e `ImporteRectificacion`) e `I` rectifica por diferencias (abonos). Véase [[Facturas rectificativas|Facturas-rectificativas]].

**Registro de facturación**
Conjunto de datos estructurados que describe una factura expedida (*alta*) o su anulación, con huella y encadenamiento, que el sistema VERI*FACTU remite a la AEAT. Véase [[Marco normativo|Marco-normativo]].

**Reintentos (cola de)**
Proceso `VerifactuUtils::sendInvoicesWithErrors()` que reenvía las facturas marcadas `NO_INTERNET_CONNECTION`, `SERVICE_UNAVAILABLE` o `PENDING_SUBMISSION`, ejecutado por la tarea programada cada 2 horas y antes de cada validación. Véase [[Reintentos y envíos pendientes|Reintentos-y-envios-pendientes]].

**Restricciones sobre facturas enviadas**
Bloqueos del trigger `interface_900_modVerifactu_BillRestrictions`: sin `PROV` en la referencia no se pueden editar líneas, borrar ni invalidar. Véase [[Restricciones sobre facturas enviadas|Restricciones-sobre-facturas-enviadas]].

## S

**Sin registro previo (SinRegistroPrevio)**
Tipo de anulación para una factura que nunca se registró en la AEAT (`SinRegistroPrevio = S`). Véase [[Anulación de facturas|Anulacion-de-facturas]].

**SistemaInformatico**
Bloque de identidad del sistema transmitido en cada registro (nombre, identificador, versión, número de instalación, indicadores de uso). Véase [[Declaración responsable|Declaracion-responsable]].

**SOAP**
Protocolo de los servicios web de la AEAT (`RegFactuSistemaFacturacion` para altas y anulaciones, `ConsultaFactuSistemaFacturacion` para consultas). El módulo usa la extensión SOAP de PHP con autenticación por certificado. Véase [[Conexión SOAP y entornos|Conexion-SOAP-y-entornos]].

**Subsanación**
Reenvío de un registro para corregirlo (`Subsanacion = S`), acompañado de *RechazoPrevio*. Acción «Enviar Modificación». Véase [[Facturas rectificativas|Facturas-rectificativas]].

**Suite de pruebas**
Los ficheros de `tests/`, ejecutables con `php tests/X.php` sin Dolibarr ni base de datos. Véase [[Suite de pruebas|Suite-de-pruebas]].

**Sustituciones ODT**
Variables `verifactuqrcode30_logo`, `verifactuqrcode35_logo` y `verifactuqrcode40_logo` que insertan el QR con sus textos en plantillas ODT. Véase [[Sustituciones ODT y plantillas|Sustituciones-ODT-y-plantillas]].

## T

**TakePOS**
Módulo de TPV de Dolibarr. Sus facturas se envían siempre al validar, como `F2` (o `R5`), sin exigir NIF ni dirección, y su ticket incluye el QR. Véase [[TakePOS|TakePOS]].

**Tiempo de espera (TiempoEsperaEnvio)**
Segundos que la AEAT pide esperar antes del siguiente envío, devueltos en cada respuesta; el módulo los guarda en `VERIFACTU_NEXT_SEND_AT` y los respeta en los reintentos. Véase [[Respuestas de la AEAT|Respuestas-de-la-AEAT]].

**Tiempo de espera del bloqueo (VERIFACTU_CHAIN_LOCK_TIMEOUT)**
Segundos que una validación espera a obtener el bloqueo de la *cadena* (30 por defecto) antes de fallar con «Se está encadenando otra factura…». Véase [[Encadenamiento y huella|Encadenamiento-y-huella]].

**TipoFactura** (véase *F1 / F2 / F3* y *R1 – R5*).

**TipoImpuesto (Impuesto)**
Impuesto del desglose (lista L1): `01` IVA, `02` IPSI (Ceuta y Melilla), `03` IGIC (Canarias), `05` otros. Véase [[Desglose de impuestos|Desglose-de-impuestos]].

**Trigger**
Manejador de eventos de negocio de Dolibarr. El módulo tiene dos: restricciones (prioridad 900) y VeriFactu (999: `BILL_CREATE`, `BILL_VALIDATE`, `COMPANY_MODIFY`). Véase [[Arquitectura del módulo|Arquitectura-del-modulo]].

## V

**VERI*FACTU (sistema)**
Modalidad de sistema informático de facturación que remite cada registro a la AEAT en el momento de la expedición. El módulo funciona exclusivamente en esta modalidad. Véase [[Marco normativo|Marco-normativo]].

**VeriFactu**
Nombre común del sistema de la AEAT y de este módulo. Véase [[Home]].

**Verificar Estado en AEAT**
Acción de la pestaña VeriFactu que consulta la factura en la AEAT, compara importes y corrige el estado local (o limpia las marcas si no existe). Véase [[Consulta a la AEAT|Consulta-a-la-AEAT]].

## W

**WSDL** (véase *Esquemas WSDL y XSD*).

## X

**XSD** (véase *Esquemas WSDL y XSD*).

## Véase también

- [[Marco normativo|Marco-normativo]]
- [[Preguntas frecuentes|Preguntas-frecuentes]]
- [[Códigos de error|Codigos-de-error]]
- [[Campos adicionales|Campos-adicionales]]
- [[Arquitectura del módulo|Arquitectura-del-modulo]]
