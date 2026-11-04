# Marco normativo

Orientación sobre las normas que regulan VeriFactu y sobre cómo el módulo VeriFactu para Dolibarr (2.2.2) las aplica: qué es un sistema VERI*FACTU, qué es un registro de facturación, qué son la huella, el encadenamiento y el QR tributario, qué exige la declaración responsable, y qué artículos concretos cita el código. Se indica también lo que el módulo no hace.

> **Aviso:** esta página es una orientación técnica para entender el módulo. No sustituye al texto de las normas ni al criterio de un asesor fiscal. Las fechas y umbrales que se mencionan son los que el módulo lleva incorporados; compruébalos siempre en las fuentes oficiales, la sede electrónica de la AEAT (https://sede.agenciatributaria.gob.es) y el BOE (https://www.boe.es), porque pueden cambiar.

## Normas que cita el módulo

| Norma | Qué regula | Dónde la cita el módulo |
|---|---|---|
| **Ley 11/2021**, de medidas de prevención y lucha contra el fraude fiscal | Origen de la obligación de usar sistemas de facturación que garanticen integridad, conservación, accesibilidad, legibilidad, trazabilidad e inalterabilidad | README (descripción) |
| **Ley 58/2003**, General Tributaria, **art. 29.2.j)** | Obligación de los sistemas informáticos de facturación y base de la declaración responsable | `conf/declaracion_responsable.conf.php`, README |
| **Real Decreto 1007/2023**, de 5 de diciembre | Reglamento que establece los requisitos de los sistemas y programas informáticos que soporten los procesos de facturación y la estandarización de los registros de facturación (el «Reglamento VeriFactu») | Todo el módulo; en el código, **Art. 6** (proformas) y **Art. 8** (requisitos) |
| **Real Decreto 254/2025** | Modificaciones y plazos del anterior | README, `conf/declaracion_responsable.conf.php` |
| **Orden HAC/1177/2024**, de 17 de octubre | Especificaciones técnicas y funcionales: registros, huella, encadenamiento, QR, declaración responsable, formatos | En el código, **Art. 13** (encadenamiento), **Art. 14** (huella y firma), **Art. 15** (declaración responsable e identidad del sistema), **Art. 20.1.b y 21** (QR) |
| **Real Decreto 1619/2012** (Reglamento de facturación) | Tipos de factura: completa (art. 6, 7.2 y 7.3), simplificada y sin identificación del destinatario (art. 6.1.d) | `lib/verifactu-types.array.php`, ayuda del módulo |
| **Ley 37/1992** del IVA (LIVA) | Rectificativas (art. 80 Uno, Dos, Tres, Cuatro y Seis), exenciones (arts. 20 a 25), no sujeción (arts. 7 y 14), localización (arts. 69 y 70), inversión del sujeto pasivo (art. 84.Uno.2º.f) | Tipos R1 a R4, claves E1 a E6, calificaciones S1, S2, N1, N2 |

Las URL concretas que el propio código enlaza son la sección de sistemas informáticos de facturación de la sede de la AEAT (`https://sede.agenciatributaria.gob.es/Sede/iva/sistemas-informaticos-facturacion-verifactu/`), el texto de la Orden en el BOE (`https://www.boe.es/diario_boe/txt.php?id=BOE-A-2024-22138`) y el portal de pruebas `https://preportal.aeat.es/`.

## Conceptos básicos

### Sistema informático de facturación (SIF) y sistemas VERI*FACTU

El RD 1007/2023 regula los programas que generan facturas. Un sistema puede funcionar de dos maneras:

- **VERI*FACTU** (sistema de emisión de facturas verificables): remite a la AEAT, de forma inmediata y automática, cada registro de facturación en el momento de expedir la factura. La AEAT conserva los registros; el sistema no necesita firmarlos electrónicamente uno a uno.
- **No VERI*FACTU**: conserva los registros en el propio sistema, firmados electrónicamente, y los remite solo a requerimiento de la AEAT.

El módulo es un sistema VERI*FACTU. Lo declara en `especificaciones_tecnicas.modalidad_funcionamiento = 'verifactu'`, lo transmite en `TipoUsoPosibleSoloVerifactu = S` y la pantalla de configuración indica que el modo no VERI*FACTU está en desarrollo.

### Registro de facturación de alta y de anulación

Por cada factura expedida se genera un **registro de alta** (`RegistroAlta`) con la identificación de la factura (`IDFactura`: NIF del emisor, número de serie y fecha de expedición), el tipo (`TipoFactura`), la descripción de la operación, los destinatarios, el desglose de impuestos, las cuotas y el importe total, la identidad del sistema informático, el encadenamiento, la huella y la fecha y hora de generación con huso horario. Para dejar sin efecto un registro de alta se genera un **registro de anulación** (`RegistroAnulacion`), que también se encadena y lleva huella. El módulo construye ambos en `lib/newfenix/src/Invoice.php` y `Cancellation.php`.

### Huella y encadenamiento

Cada registro lleva una **huella** (`Huella`, `TipoHuella = 01`, SHA-256) calculada sobre una cadena de campos fija. Para el alta, el módulo la calcula así:

```text
IDEmisorFactura=…&NumSerieFactura=…&FechaExpedicionFactura=…&TipoFactura=…&CuotaTotal=…&ImporteTotal=…&Huella=<huella del registro anterior>&FechaHoraHusoGenRegistro=…
```

y para la anulación sobre `IDEmisorFacturaAnulada`, `NumSerieFacturaAnulada`, `FechaExpedicionFacturaAnulada`, `Huella` anterior y `FechaHoraHusoGenRegistro`. El resultado se transmite en mayúsculas (64 caracteres hexadecimales).

El **encadenamiento** (`Encadenamiento`) enlaza cada registro con el anterior del mismo obligado: o bien `PrimerRegistro = S` (el primero de la cadena), o bien `RegistroAnterior` con el NIF, el número, la fecha y la huella del registro precedente. Así, alterar o eliminar un registro rompe la cadena. El módulo lee el registro anterior de la propia base de datos (`getLastInvoiceHash()`), por entidad y por entorno, y serializa las validaciones concurrentes para que dos facturas no se encadenen sobre el mismo registro. Véase [[Encadenamiento y huella|Encadenamiento-y-huella]].

### QR tributario

Toda factura expedida por un sistema VERI*FACTU incluye un código QR con la URL de cotejo de la AEAT y los datos de la factura. El módulo genera la URL con `QRGenerator::generateVerifiableUrl()`:

```text
https://www2.agenciatributaria.gob.es/wlpl/TIKE-CONT/ValidarQR?nif=<NIF emisor>&numserie=<número>&fecha=<DD-MM-AAAA>&importe=<total con dos decimales>
```

(en pruebas, `https://prewww2.aeat.es/wlpl/TIKE-CONT/ValidarQR`). El código se imprime con corrección de errores M, un tamaño físico entre 30 y 40 mm (35 recomendado) y un margen mínimo de 2 mm, con el texto «QR tributario:» encima y «VeriFactu» debajo, según los Art. 20.1.b y 21 de la Orden. Véase [[Código QR|Codigo-QR]].

### Declaración responsable

El **productor** del sistema (quien lo desarrolla o comercializa) debe suscribir una declaración responsable de que el sistema cumple el Reglamento y la Orden (Art. 15 de la Orden HAC/1177/2024, en relación con el art. 29.2.j) LGT). Debe identificar al productor, al sistema (nombre, `IdSistemaInformatico`, versión), sus componentes, si funciona como VERI*FACTU, si es multiusuario, el tipo de firma y la fecha y lugar de suscripción. El módulo la incluye en `conf/declaracion_responsable.conf.php`, la muestra en el menú VeriFactu > Declaración responsable y la exporta en JSON. Véase [[Declaración responsable|Declaracion-responsable]].

### Identidad del sistema informático

Cada registro transmite el bloque `SistemaInformatico`: `NombreRazon` y `NIF` del obligado, `NombreSistemaInformatico` (máximo 30 caracteres), `IdSistemaInformatico` (máximo 2), `Version` (máximo 50), `NumeroInstalacion` (máximo 100), `TipoUsoPosibleSoloVerifactu`, `TipoUsoPosibleMultiOT` e `IndicadorMultiplesOT`. El Art. 15.2 exige que lo declarado y lo transmitido coincidan; desde la 2.0.0 el módulo lee ambos del mismo fichero.

### Envío inmediato, incidencias y subsanación

En un sistema VERI*FACTU el registro se remite al expedir la factura. Cuando no es posible (sin conexión, servicio no disponible), el registro debe existir igualmente y remitirse en cuanto se pueda, indicando que hubo una **incidencia** (`RemisionVoluntaria/Incidencia = S`). El módulo marca la factura (`NO_INTERNET_CONNECTION`, `SERVICE_UNAVAILABLE`, `PENDING_SUBMISSION`), pone `verifactu_incidencia = S` y la reenvía desde la cola respetando la fecha de expedición original; la marca de incidencia se conserva después del reenvío para documentar que existió. Véase [[Reintentos y envíos pendientes|Reintentos-y-envios-pendientes]].

Una **subsanación** (`Subsanacion = S`) es el reenvío de un registro para corregirlo. Va acompañada de `RechazoPrevio`, que distingue si la AEAT rechazó antes ese registro (`S`) o si nunca llegó a la AEAT (`X`). El módulo la ofrece como «Enviar Modificación» en la pestaña VeriFactu.

### Control de flujo

Cada respuesta de la AEAT incluye `TiempoEsperaEnvio`, los segundos que el sistema debe esperar antes de volver a enviar. El módulo lo registra y lo respeta en los reintentos automáticos; no en la validación, donde abortar revertiría una factura ya expedida, ni en los envíos que el usuario pide expresamente.

### Entornos

La AEAT ofrece un entorno de pruebas (preproducción) con los mismos servicios que producción. El módulo trabaja contra pruebas hasta la fecha de transición y mantiene cadenas separadas por entorno. Véase [[Conexión SOAP y entornos|Conexion-SOAP-y-entornos]].

## Artículos concretos y cómo los cumple el módulo

| Artículo | Exigencia | Implementación |
|---|---|---|
| **RD 1007/2023, Art. 6** | Solo las facturas expedidas generan registro de facturación | `isVerifactuApplicableInvoice()` excluye las proformas de todas las vías de envío (trigger, pestaña, listado, envío masivo, reintentos). Con el envío directo desactivado, el registro se marca pendiente en lugar de no existir (2.1.0) |
| **RD 1007/2023, Art. 8** | Integridad, conservación, accesibilidad, legibilidad, trazabilidad e inalterabilidad | Declarados en `cumplimiento.requisitos_cumplidos`; las facturas remitidas no se pueden modificar, borrar ni invalidar (trigger de restricciones) y el NIF del tercero con facturas enviadas no cambia |
| **Orden HAC/1177/2024, Art. 13** | Encadenamiento secuencial de los registros | `getLastInvoiceHash()` por entidad y entorno; error de lectura = excepción, nunca «primer registro» por accidente; bloqueo de la cadena (`GET_LOCK` / *advisory lock*) |
| **Orden HAC/1177/2024, Art. 14** | Huella SHA-256 y, en la declaración, tipo de firma | `Invoice::generateHash()` y `Cancellation::generateHash()`; `algoritmo_huella = SHA-256` en la declaración |
| **Orden HAC/1177/2024, Art. 15.1** | Datos del productor | `productor` en la declaración responsable |
| **Orden HAC/1177/2024, Art. 15.2 a–g** | Nombre, identificador, versión, componentes, modalidad, multiusuario y firma del sistema; coincidencia entre lo declarado y lo transmitido | `sistema` y `especificaciones_tecnicas` en la declaración; `getDeclaredSystemIdentity()` lee de ahí lo que se transmite y anota cualquier recorte |
| **Orden HAC/1177/2024, Art. 15.2.c** | La versión transmitida es la del sistema de facturación | `Version` = versión del módulo (no `DOL_VERSION`); comprobada por el flujo de publicación |
| **Orden HAC/1177/2024, Art. 15.3 y 15.4** | Declaración de cumplimiento; fecha y lugar de suscripción | `cumplimiento` y `suscripcion` en la declaración |
| **Orden HAC/1177/2024, Art. 20.1.b** | Texto «VeriFactu» (o «Factura verificable en la sede electrónica de la AEAT») junto al QR | Texto «VeriFactu» bajo el QR en ficha, PDF, ticket y sustituciones ODT |
| **Orden HAC/1177/2024, Art. 21** | Texto «QR tributario:», tamaño y colocación del QR | Texto encima del QR; tamaño 30–40 mm; `VERIFACTU_QR_SIZE`, posición configurable, comprobación de márgenes en el PDF |
| **RD 1619/2012, art. 6, 7.2, 7.3 y 6.1.d** | Factura completa y simplificada | F1 para facturas estándar, de situación y de anticipo; F2 para TakePOS y clientes de factura simplificada; F2 sin destinatarios |
| **LIVA art. 80 Uno, Dos y Seis** | Rectificativa por error fundado en derecho | R1 (`TYPE_CREDIT_NOTE_LEGAL`) para abonos (por diferencias, `I`) y facturas de sustitución (por sustitución, `S`) |
| **LIVA art. 80.3 y 80.4** | Rectificativas por concurso e impago / operaciones sin efecto | R2 y R3, seleccionables en el campo «Tipo de factura» |
| **LIVA arts. 20–25** | Exenciones | Claves `E1` a `E6` (`OperacionExenta`) |
| **LIVA arts. 7, 14, 69, 70 y 84.Uno.2º.f** | No sujeción, localización, inversión del sujeto pasivo | Calificaciones `N1`, `N2`, `S2`; para N1/N2 solo se informa la base sin campos de IVA |

Los límites numéricos (120 caracteres del nombre del emisor, 500 de la descripción, 1000 destinatarios, 12 líneas de desglose, huellas de 64 hexadecimales, `IdSistemaInformatico` de 2 caracteres) no salen de los artículos sino de los esquemas oficiales `SuministroInformacion.xsd` y `RespuestaSuministro.xsd`, que el módulo descarga y aplica antes de enviar. Véase [[Esquemas WSDL y XSD|Esquemas-WSDL-y-XSD]].

## Plazos que incorpora el módulo

El módulo recoge, desde la 1.0.3, estas fechas de transición automática al entorno de producción «según el anuncio del Gobierno»:

| Tipo de empresa (`VERIFACTU_COMPANY_TYPE`) | Fecha incorporada |
|---|---|
| Sociedades (`sociedad`) | 1 de enero de 2027 |
| Autónomos (`autonomo`) | 1 de julio de 2027 |

Hasta esa fecha `getEnvironment()` devuelve `test`; después, `production`. Puedes forzar producción antes con `VERIFACTU_FORCE_PRODUCTION_ENVIRONMENT` (botón de la configuración o API), y la pantalla lo advierte: «Las facturas se enviarán a la AEAT real». Estas fechas están escritas en el código; si la normativa las cambia, habrá que actualizar el módulo. Compruébalas en la sede de la AEAT antes de planificar la puesta en producción.

> **Importante:** el módulo automatiza el envío, pero la calificación fiscal de cada operación (impuesto, clave de régimen, calificación, exención, tipo de factura) la decide la empresa. La pantalla de activación y la de validación lo recuerdan expresamente.

## Qué no hace el módulo

- **No funciona como sistema no VERI*FACTU.** La biblioteca conoce la URL de cotejo de facturas no verificables (`ValidarQRNoVerifactu`), pero el módulo siempre usa la de VERI*FACTU y siempre remite.
- **No firma electrónicamente los registros (XAdES).** La declaración responsable describe un tipo de firma XAdES, pero en el código no hay firma de registros: la autenticación con la AEAT se hace con el certificado en la conexión TLS. En modalidad VERI*FACTU la firma de cada registro no es lo que garantiza la integridad; lo es la huella encadenada remitida a la AEAT.
- **No gestiona facturas recibidas ni de proveedor.** Existen claves de idioma para «Facturas recibidas», pero no hay menú ni lógica de envío.
- **No responde a requerimientos.** `SoapClient::sendRequirement()` existe en la biblioteca, pero ninguna función del módulo lo utiliza.
- **No infiere los campos fiscales.** No hay inferencia automática de impuesto, régimen, calificación ni exención; solo valores por defecto y la clave 18 cuando la línea lleva recargo de equivalencia.
- **No sustituye al libro registro, al SII ni a otros sistemas territoriales.** Remite registros de facturación VeriFactu; nada más.
- **No envía proformas** ni facturas de terceros marcados como simplificados con datos de destinatario.
- **No es asesoramiento fiscal.**

## Fuentes oficiales

- Sede electrónica de la AEAT, sección de sistemas informáticos de facturación y VeriFactu: https://sede.agenciatributaria.gob.es
- Boletín Oficial del Estado (RD 1007/2023, RD 254/2025, Orden HAC/1177/2024, RD 1619/2012, Ley 37/1992, Ley 58/2003, Ley 11/2021): https://www.boe.es
- Portal de pruebas de la AEAT: https://preportal.aeat.es/
- Esquemas oficiales que el módulo descarga: `SistemaFacturacion.wsdl`, `SuministroInformacion.xsd`, `SuministroLR.xsd`, `ConsultaLR.xsd`, `RespuestaConsultaLR.xsd`, `RespuestaSuministro.xsd` (desde `prewww2.aeat.es`) y `xmldsig-core-schema.xsd` (W3C).

## Véase también

- [[Glosario|Glosario]]
- [[Declaración responsable|Declaracion-responsable]]
- [[Encadenamiento y huella|Encadenamiento-y-huella]]
- [[Código QR|Codigo-QR]]
- [[Tipos de factura|Tipos-de-factura]]
- [[Facturas rectificativas|Facturas-rectificativas]]
- [[Desglose de impuestos|Desglose-de-impuestos]]
- [[Conexión SOAP y entornos|Conexion-SOAP-y-entornos]]
- [[Preguntas frecuentes|Preguntas-frecuentes]]
