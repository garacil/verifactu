# Historial de versiones

Resumen de todas las versiones publicadas del módulo VeriFactu para Dolibarr, de la más reciente a la más antigua, elaborado a partir del registro de cambios del `README.md` y de las etiquetas del repositorio. Cada sección indica la fecha, los cambios principales y el enlace a la release de GitHub cuando existe etiqueta. Para saber cómo se numeran y publican las versiones, consulta [[Publicación de versiones|Publicacion-de-versiones]]; para actualizar una instalación, [[Actualización|Actualizacion]].

## Tabla resumen

| Versión | Fecha | Tipo | Etiqueta / release | Resumen |
|---|---|---|---|---|
| 2.2.4 | 2026-11-03 | Parche | [2.2.4](https://github.com/garacil/verifactu/releases/tag/2.2.4) | QR que no llegaba al PDF con Crabe; rechazo 4102 en facturas antiguas; estado del listado en Dolibarr 20 y posteriores |
| 2.2.3 | 2026-09-24 | Parche | [2.2.3](https://github.com/garacil/verifactu/releases/tag/2.2.3) | Fecha de operación transmitida a la AEAT; ficha de factura plegada; listados sin 16 columnas vacías |
| 2.2.2 | 2026-09-19 | Parche | [2.2.2](https://github.com/garacil/verifactu/releases/tag/2.2.2) | API REST protegida; la excepción ya no borra la huella; claves de idioma completas |
| 2.2.1 | 2026-09-19 | Parche | [2.2.1](https://github.com/garacil/verifactu/releases/tag/2.2.1) | Error 500 en la página principal del módulo |
| 2.2.0 | 2026-09-19 | Menor | [2.2.0](https://github.com/garacil/verifactu/releases/tag/2.2.0) | Anulación como primer registro; NIF único entre entidades; aviso de numeración compartida |
| 2.1.0 | 2026-09-19 | Menor | [2.1.0](https://github.com/garacil/verifactu/releases/tag/2.1.0) | Cadena en PostgreSQL; bloqueo de la cadena; registro pendiente al validar; identidad sin recortes silenciosos |
| 2.0.0 | 2026-09-19 | Mayor | [2.0.0](https://github.com/garacil/verifactu/releases/tag/2.0.0) | Numeración unificada; issues #30 a #33; PR #35 a #38, #40 y #41; seguridad en OpenSSL |
| 1.1 | 2026-03-06 | Etiqueta | [1.1](https://github.com/garacil/verifactu/releases/tag/1.1) | Sin entrada propia en el registro de cambios; el módulo seguía declarando 1.0.3 |
| 1.0.4 | 2026-03-04 | Parche | [1.0.4](https://github.com/garacil/verifactu/releases/tag/1.0.4) | IRPF; validación masiva con transacciones independientes; orden cronológico; caché de esquemas |
| 1.0.3 | 2025-12-20 | Parche | [1.0.3](https://github.com/garacil/verifactu/releases/tag/1.0.3) | Fechas de transición 2027; declaración responsable con datos del productor |
| 1.0.2 | 2025-12-12 | Parche | [1.0.2](https://github.com/garacil/verifactu/releases/tag/1.0.2) | Declaración responsable configurable y exportación JSON |
| 1.0.1 | 2025-12-06 | Parche | sin etiqueta | Gestión de errores en la validación masiva |

Las fechas de la tabla son las del registro de cambios del README; las etiquetas 1.0.2, 1.0.3 y 1.1 se crearon el 2025-12-13, el 2025-12-20 y el 2026-03-06 respectivamente, las cinco primeras de la serie 2.x el 2026-09-19, y la 2.2.3 y la 2.2.4 en sus propias fechas.

## v2.2.4 (2026-11-03)

Tres fallos reportados sobre la 2.2.3, cada uno con el diagnóstico ya hecho por quien lo encontró.

- **El QR no llegaba al PDF con la plantilla Crabe** ([issue #55](https://github.com/garacil/verifactu/issues/55), reportada por **[@perey2002](https://github.com/perey2002)**, Sergio Pereiro, Check 4 Cyber SARL-S, con el error del parser aislado y la reproducción hecha). El hook `afterPDFCreation` se dispara con el documento ya escrito, así que hay que reimportarlo para estamparle el QR, y esa reimportación falla en algunos PDF. Ahora se comprueba sobre la instancia que devuelve `pdf_getInstance()` si admite `setSourceFile()` e `importPage()` —preguntar antes por `class_exists('TCPDI')` podía contestar que no, porque es `pdf_getInstance()` quien carga la biblioteca—; el resultado se escribe en un temporal y solo reemplaza al PDF de la factura si está completo; se captura `Throwable` y no solo `Exception`; si la importación falla se reintenta sobre una copia normalizada con Ghostscript o `qpdf`, que no son dependencia del módulo (opción `VERIFACTU_PDF_NORMALIZER`); y si aun así no se consigue, se avisa en pantalla, porque el QR es parte obligatoria de la factura. Véase [[Código QR|Codigo-QR]].
- **Rechazo 4102 de la AEAT al enviar facturas creadas antes de instalar el módulo** ([issue #54](https://github.com/garacil/verifactu/issues/54), reportada por **[@miguelpicher](https://github.com/miguelpicher)**). El campo que la AEAT señalaba, `BaseImponibleOimporteNoSujeto`, no era el que faltaba: el esquema coloca `CalificacionOperacion` y `OperacionExenta` en un `xs:choice` justo antes, y el registro salía sin ninguno de los dos. Los cuatro parámetros fiscales se resuelven ahora de la factura, del cliente y de los valores por defecto del módulo, por ese orden; si no hay nada se transmite `S1` avisando; si están configurados los dos viaja la exención; y la biblioteca rechaza una línea de desglose sin calificación ni exención, o con ambas, antes de salir a la AEAT. Véase [[Desglose de impuestos|Desglose-de-impuestos]].
- **Facturas validadas sin cobrar que el listado mostraba como borrador** ([issue #53](https://github.com/garacil/verifactu/issues/53), reportada por **[@jose73wrc](https://github.com/jose73wrc)**, que ya había localizado la inicialización del objeto). `getLibStatut()` lee `$this->status` desde Dolibarr 20 y el listado solo rellenaba `$this->statut`, de modo que con el estado a 0 solo se veían «Borrador» y «Pagada». Se rellenan ambas propiedades. Fallo de presentación: el estado almacenado y el envío nunca estuvieron afectados.

Release: [2.2.4](https://github.com/garacil/verifactu/releases/tag/2.2.4).

## v2.2.3 (2026-09-24)

Atiende la [issue #51](https://github.com/garacil/verifactu/issues/51), propuesta por **[@xabitrigo](https://github.com/xabitrigo)**, y las observaciones sobre la interfaz que planteó en el mismo hilo.

- **Fecha de operación.** La fecha en que se efectúa la operación determina el devengo del IVA, mientras que la de expedición ordena la numeración (Art. 10.1.d del RD 1007/2023, Art. 6.1.f del RD 1619/2012). Se transmite en `FechaOperacion`, tomada de `date_pointoftax`, y **solo cuando difiere de la fecha de expedición**. No interviene en la huella. Hay una opción nueva, **Transmitir la fecha de operación**, desactivada por defecto, que al activarse habilita también `INVOICE_POINTOFTAX_DATE`. Véase [[Fechas y numeración|Fechas-y-numeracion]].
- **La ficha de factura ya no queda sepultada.** Los separadores de los bloques fiscales se declaraban desplegados y ocupaban 730 px de los 1870 de la página; pasan a declararse plegados (clave `2` del array de opciones, que `showSeparator()` interpreta) y la página baja a 1209 px, conservando la preferencia de quien los despliegue.
- **Los listados vuelven a caber en la pantalla.** El módulo añadía 16 columnas casi todas vacías, que dejaban la tabla en 8153 px de ancho. Con la visibilidad en negativo —patrón del propio Dolibarr— la columna sigue en el selector pero no aparece marcada: 2190 px y 15 columnas. El distintivo de estado junto al número de factura lo gobierna `VERIFACTU_STATUS_BADGE_ON_REF` (`card` por defecto, `always`, `never`).
- Una instalación que actualice recibe estos ajustes mediante `alignExtraFieldDisplay()`, porque `addExtraField()` no reescribe un campo que ya existe.

Release: [2.2.3](https://github.com/garacil/verifactu/releases/tag/2.2.3).

## v2.2.2 (2026-09-19)

Correcciones propias del autor del módulo, Germán Luis Aracil Boned, detectadas al revisar el código fuente para redactar esta wiki.

- **API REST protegida.** `class/api_verifactu.class.php` declaraba `@access public` en la clase y en sus tres métodos, así que `toProduction` y `toTest` cambiaban el entorno de la entidad sin clave de API. Ahora son `@access protected`, como la API del núcleo, y rechazan con 403 a quien no sea administrador antes de tocar la configuración. El hash de integridad sigue siendo público. La ruta correcta, `verifactuapi`, queda documentada en el README. En Dolibarr 17 la API del módulo no llega a ejecutarse por un fallo del núcleo, anterior e independiente (véase [[API REST|API-REST]]).
- **Una excepción ya no borra la huella.** El `catch` de `execVERIFACTUCall()` vaciaba `verifactu_huella` y `verifactu_csv_factura` fuera cual fuera la operación fallida: una baja o una subsanación con excepción borraba el registro de un alta aceptada, rompía la cadena local y hacía desaparecer el QR. Ahora la huella y el CSV no se tocan, y una factura con registro aceptado conserva su estado (`buildVerifactuExceptionErrorData()`).
- **Claves de idioma completas.** Se definen en los cinco idiomas las claves que el código pedía con otro nombre (`VERIFACTU_INVOICE_CAN_NOT_BE_*`, `VERIFACTU_STATUS_ANULADA`, `SOCIETE_HAVE_INVOICES_NOT_*`, `verifactu_FACTURA_Simplificada`…) y se traducen al catalán, euskera y gallego los textos de la validación masiva, que solo existían en español e inglés.
- Tests nuevos `tests/ApiAccessTest.php`, `tests/ExceptionKeepsRecordTest.php` y `tests/LanguageKeysTest.php`, que fallan con la 2.2.1; la suite pasa a 15 ficheros.

Release: [2.2.2](https://github.com/garacil/verifactu/releases/tag/2.2.2).

## v2.2.1 (2026-09-19)

Corrección propia del autor del módulo, Germán Luis Aracil Boned, detectada al actualizar a la 2.2.0 una instalación en producción sobre PostgreSQL.

- La página principal del módulo (`verifactuindex.php`, entrada de menú «VeriFactu») devolvía un **error 500** desde la 2.1.0: `Call to undefined function dol_get_first_day()`. El gráfico mensual se había reescrito para PostgreSQL con `dol_get_first_day()` y `dol_get_last_day()`, que viven en `core/lib/date.lib.php`, y la página no incluía esa librería.
- La configuración, la validación, el envío a la AEAT y la pestaña VeriFactu no estaban afectados, aunque el fallo se percibía como «el módulo ha dejado de funcionar».
- La página carga ahora la librería junto al resto de dependencias.
- Nuevo test `tests/DashboardDependenciesTest.php`: comprueba que cualquier fichero del módulo que use un ayudante de `date.lib.php` lo incluya antes de llamarlo.

Release: [2.2.1](https://github.com/garacil/verifactu/releases/tag/2.2.1).

## v2.2.0 (2026-09-19)

Resuelve los dos últimos PR abiertos de @braito4, el #34 y el #39, aplicando el fondo del hallazgo con otra implementación (el motivo está explicado en cada PR).

- **Una anulación puede abrir la cadena (PR #39).** El esquema declara `Encadenamiento` como `choice` entre `PrimerRegistro` y `RegistroAnterior` también en `RegistroFacturacionAnulacionType`, y existe `SinRegistroPrevioType` para anular registros que nunca se remitieron. Sin registro anterior, el módulo no configuraba el encadenamiento y `validate()` fallaba con un error genérico; ahora la anulación se marca como primer registro.
- **Un obligado tributario no puede tener dos entidades VeriFactu (PR #34).** La pantalla de configuración rechaza un NIF ya configurado en otra entidad con VeriFactu activado, leyendo solo `llx_const`. Falla en abierto ante un error de consulta y **no** se ejecuta en el camino del envío, donde un `false` revierte la factura a borrador.
- Aviso en la configuración cuando MultiCompany comparte la numeración de facturas: dos obligados tomarían su serie del mismo contador.
- **No se incorpora** la unicidad de la huella del certificado entre entidades: un certificado de representante o de colaborador social puede firmar por varios obligados.
- Nuevo test `tests/MultiEntityAndCancellationTest.php`. El flujo de limpieza de ramas deja de acotarse a un prefijo concreto.

Release: [2.2.0](https://github.com/garacil/verifactu/releases/tag/2.2.0).

## v2.1.0 (2026-09-19)

Corrige el punto 4 de la issue #30, planteado por @hisie, y un defecto que apareció al verificarlo y que afecta a toda instalación sobre PostgreSQL.

- **La cadena no se construía en PostgreSQL.** `getLastInvoiceHash()` usaba `DATE_FORMAT()`, que solo existe en MySQL y Dolibarr no traduce; la consulta fallaba en silencio y cada factura se encadenaba como primer registro (incumplimiento del Art. 13 de la Orden HAC/1177/2024). La fecha se formatea ahora en PHP con `$db->jdate()` y un error de lectura lanza excepción en lugar de pasar por «no hay registro anterior». El panel tenía el mismo problema con `MONTH()` y `YEAR()`.
- **Condición de carrera en el encadenamiento.** Dos validaciones simultáneas leían el mismo registro anterior. La sección crítica se serializa con `GET_LOCK()` en MySQL y un *advisory lock* en PostgreSQL, por entidad y entorno, con espera configurable en `VERIFACTU_CHAIN_LOCK_TIMEOUT` (30 s por defecto); se libera también cuando el envío lanza una excepción.
- **El registro existe siempre que se valida una factura.** Con `VERIFACTU_DIRECT_CALL_ON_VALIDATE` desactivado y una factura no procedente de TakePOS, la validación no dejaba rastro VeriFactu. Ahora queda marcada como pendiente (`PENDING_SUBMISSION`, `Incidencia = S`) y la recoge la cola de reintentos (Art. 6 RD 1007/2023).
- **La identidad declarada ya no se recorta en silencio.** `getDeclaredSystemIdentity()` anota como error cualquier recorte y usa `mb_substr()` para contar caracteres como el esquema.
- La parametrización de los datos del productor desde la pantalla de administración (segunda parte de la issue #30) no se implementa: quien redistribuya el módulo edita `conf/declaracion_responsable.conf.php`.
- Verificación: consultas antigua y nueva contra PostgreSQL 16 real, `convertSQLFromMysql()` de Dolibarr 19.0.3, exclusión mutua con dos sesiones concurrentes, suite completa (10 ficheros). Nuevo test `tests/ChainIntegrityTest.php`.

> **Aviso:** si tu instalación usa PostgreSQL, conviene revisar los registros enviados con versiones anteriores: es posible que se remitieran como primer registro de cadena.

Release: [2.1.0](https://github.com/garacil/verifactu/releases/tag/2.1.0).

## v2.0.0 (2026-09-19)

Primera versión publicada por el flujo automático. El número declarado por el módulo llevaba desde diciembre de 2025 congelado en `1.0.3`; el salto a 2.0.0 deja atrás sin ambigüedad la etiqueta 1.1 y refleja cambios de comportamiento: registros que antes se transmitían ahora se rechazan localmente, las consultas dejan de abarcar entidades compartidas, un envío parcial con líneas rechazadas ya no se da por enviado y el NIF del obligado queda fijado con la primera huella.

**Correcciones**

- Error fatal en el **informe de pagos** (issue #31, @jose73wrc): `beforePDFCreation()` recibe modelos de informe que no son facturas; añadido un guard.
- **TypeError del QR en PHP 8** (issue #32, @hippo16214): `renderQrCode()` declaraba `int $outputType` y las constantes de `chillerlan/php-qrcode` son cadenas.
- **Certificados en alojamiento compartido** (PR #42): extracción primero con `openssl_pkcs12_read()`, sin `proc_open`; eliminado el requisito duro de `proc_open` en la conversión a PEM.
- **Certificados FNMT `.p12` legacy** (issue #33, @hippo16214, con la aportación de @jose73wrc): reintento con `openssl pkcs12 -legacy` (solo con OpenSSL 3 o superior) y mensajes que distinguen contraseña incorrecta, algoritmo legacy y fallo genérico.
- **Identidad del sistema declarada frente a transmitida** (issue #30, puntos 1 y 6, @hisie): `getSystemConfig()` lee la identidad de la declaración responsable (`DV`, `Dolibarr Verifactu Module`) y transmite la versión del módulo en vez de `DOL_VERSION`.
- **Hash de integridad incompleto** (issue #30, punto 2): lista de ficheros corregida y ampliada; los ficheros ausentes se registran en `integridad.ficheros_no_encontrados`.
- **Proformas enviadas como F1** (issue #30, punto 3): nueva `isVerifactuApplicableInvoice()` las excluye de todas las vías de envío.
- **Tipo rectificativo divergente** (issue #30, punto 5): abonos y sustituciones se almacenan como R1, que es lo que se transmite.

**Seguridad**

- La contraseña del certificado ya no se interpola en la línea de comandos: `proc_open` con argumentos en array y `-password env:`.
- `prepareLocalCertificate()` lanza una excepción en lugar de `die()`.

**Correcciones normativas aportadas por la comunidad** (@braito4, verificadas contra los XSD oficiales y Dolibarr 19.0.3)

- Respuestas parciales de la AEAT (PR #36): `isAEATResponseAccepted()` exige todas las líneas aceptadas.
- `RechazoPrevio` `S`/`X` (PR #38).
- Validación del registro contra los límites del XSD antes de enviarlo (PR #40).
- Control de flujo: `TiempoEsperaEnvio` registrado y respetado por los reintentos automáticos (PR #37).
- Certificado fuera de validez rechazado, PEM con permisos `0600`, NIF fijado con la primera huella (PR #41).
- Aislamiento entre entidades MultiCompany: consultas acotadas a la entidad activa (PR #34, parte).
- Columna `datec` en la vista de consulta (PR #35).

Quedaron fuera, para tratarlas con sus autores, el resto del PR #34 y el PR #39, resueltos en la 2.2.0.

**Compatibilidad verificada**

| Entorno | Resultado |
|---|---|
| Dolibarr 22.0.5 + PHP 8.4 + PostgreSQL 16 | 22/22 comprobaciones en vivo |
| Dolibarr 17.0.2 + PHP 8.2 + PostgreSQL 16 | 20/20 |
| Dolibarr 17.0.2 + PHP 8.4 + PostgreSQL 16 | 20/20 |
| Dolibarr 17.0.2 + PHP 7.4 (mínimo declarado) | 20/20 |
| Dolibarr 19.0.3 + PHP 8.4 (fuente oficial) | 69/69, constantes y hooks contrastados |
| Suite de regresión completa (9 ficheros) | 100 % |

Nuevo test `tests/IssueFixesTest.php` (65 comprobaciones para las issues #30, #31, #32 y #33). Los flujos `release.yml` y `limpiar-ramas.yml` se incorporaron con esta versión.

Release: [2.0.0](https://github.com/garacil/verifactu/releases/tag/2.0.0).

## 1.1 (2026-03-06)

Etiqueta creada dos días después de la 1.0.4, sin entrada propia en el registro de cambios del README. Entre ambas etiquetas el repositorio recoge la incorporación de alias de claves de idioma heredadas en español para todos los ficheros de idioma. El módulo instalado seguía declarándose como `1.0.3`, lo que motivó la unificación de la numeración en la 2.0.0.

Release: [1.1](https://github.com/garacil/verifactu/releases/tag/1.1).

## v1.0.4 (2026-03-04)

**Correcciones**

- **Retención IRPF**: `ImporteTotal` = base + IVA + recargo, sin deducir el IRPF, en el QR y en las consultas; nueva `getVerifactuImporteTotal()`.
- **Validación masiva con transacciones independientes**: se intercepta `massvalidation` y cada factura se procesa en su propia transacción.
- **Orden cronológico en la validación masiva**: detección de conflictos de fecha, diálogo de confirmación, ajuste automático y procesado por fecha ascendente con mínimo rodante.
- **Orden cronológico en la validación individual**: fecha ajustada a `max(hoy, última_factura_validada)` con aviso en el diálogo.
- **Envío masivo desde el listado**: los IDs seleccionados se transmiten en el campo oculto `verifactu_toselect`; las ya enviadas se filtran.
- **Ficheros PEM al renovar el certificado**: `deleteExistingPemFiles()` y visualización de la caducidad con colores.

**Nuevas funcionalidades**

- **Caché local de esquemas WSDL/XSD**: clase `SchemaManager` que descarga los siete ficheros, reescribe `schemaLocation` y evita la limitación de peticiones de w3.org; botón de actualización en la configuración.

Tests nuevos: `tests/MassValidateInterceptionTest.php` (52) y `tests/SchemaManagerTest.php` (19). Todos los ficheros de idioma actualizados.

Release: [1.0.4](https://github.com/garacil/verifactu/releases/tag/1.0.4).

## v1.0.3 (2025-12-20)

- **Fechas de transición automática a producción** ajustadas de 2026 a 2027 según el anuncio del Gobierno: sociedades el 1 de enero de 2027, autónomos el 1 de julio de 2027 (`getEnvironment()`, `admin/setup.php`, textos de idioma).
- Documentación del proyecto simplificada, manteniendo la atribución GPL v3 al autor original.
- Declaración responsable con los datos del productor 7Kas Servicios de Internet SL.

Release: [1.0.3](https://github.com/garacil/verifactu/releases/tag/1.0.3).

## v1.0.2 (2025-12-12)

- **Declaración responsable configurable**: nuevo `conf/declaracion_responsable.conf.php` con datos del productor, del sistema, especificaciones técnicas, hash de integridad calculado dinámicamente y declaración de cumplimiento.
- **Exportación JSON** en `views/declaration_json.php`.
- Documentación `docs/responsible_declaration.md`.
- La página de declaración responsable usa los datos del fichero, valida los campos obligatorios y muestra el hash de integridad.

Release: [1.0.2](https://github.com/garacil/verifactu/releases/tag/1.0.2).

## v1.0.1 (2025-12-06)

- **Gestión de errores en la validación masiva**: una factura con error de VeriFactu ya no cancela todo el lote; permanece como borrador con referencia `PROV` en lugar de validarse y revertirse, las demás continúan, se mejora la gestión de conexiones PostgreSQL («connection already closed») y los errores se muestran al usuario.

Sin etiqueta en el repositorio.

## Véase también

- [[Publicación de versiones|Publicacion-de-versiones]]
- [[Actualización|Actualizacion]]
- [[Suite de pruebas|Suite-de-pruebas]]
- [[Encadenamiento y huella|Encadenamiento-y-huella]]
- [[Certificados digitales|Certificados-digitales]]
- [[Multiempresa|Multiempresa]]
- [[Home]]
