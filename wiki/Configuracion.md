# Configuración

Esta página documenta todas las opciones de la pantalla de configuración del módulo (`admin/setup.php`) en la versión 2.2.2, las constantes que hay detrás de cada una, su valor por defecto y su efecto, el cambio entre entorno de pruebas y producción, el envío directo al validar, el tiempo de espera que impone la AEAT y los avisos que la pantalla puede mostrar. También recoge las constantes que el módulo crea o lee pero que no aparecen en el formulario.

## Acceso a la pantalla

- **Inicio > Configuración > Módulos/Aplicaciones > VeriFactu > icono de configuración**, o
- **menú Verifactu > Configuración**.

Ambos llevan a `/custom/verifactu/admin/setup.php`. La página exige `$user->admin` (`accessforbidden()` en caso contrario). La cabecera de pestañas la construye `verifactuAdminPrepareHead()` (`lib/functions/functions.admin.php`):

| Pestaña | Fichero | Contenido |
|---|---|---|
| Ajustes | `admin/setup.php` | Esta página |
| Gestionar Certificados | `admin/managecertificates.php` | Subida y validación del certificado (véase [[Certificados digitales|Certificados-digitales]]) |
| Acerca de | `admin/about.php` | El README renderizado |
| Acuerdo de licencia | `admin/copying.php` | El fichero `COPYING` (GPL v3) |

El formulario usa la clase `FormSetup` del núcleo: los valores se muestran en modo lectura y el botón **Modificar** los abre para edición; al guardar, la página recarga. Las constantes se almacenan en `llx_const` con la entidad activa (`$conf->entity`).

## Flujo recomendado de configuración inicial

1. Sube el certificado en **Gestionar Certificados**. Si se valida, el módulo fija `VERIFACTU_CERTIFICATE` y `VERIFACTU_CERTIFICATE_KEY` automáticamente.
2. En **Ajustes**, pulsa **Modificar** y rellena la **Configuración básica**: razón social, NIF, tipo de empresa. Comprueba que el desplegable de certificado muestra el fichero subido.
3. Guarda. Si razón social, NIF y certificado están informados, la cabecera muestra «Estado de la configuración: Completa» y aparecen las secciones **Configuración adicional**, **Código QR** y **Configuración avanzada**.
4. Comprueba que «Entorno activo» es **Pruebas** y pulsa **Descargar/Actualizar esquemas**.
5. Revisa el tipo de certificado (`normal` salvo que uses un certificado de sello), las opciones del QR y, si procede, los valores fiscales por defecto.
6. Valida una factura de prueba y revisa su pestaña VeriFactu ([[Pestaña VeriFactu de la factura|Pestana-VeriFactu-de-la-factura]]).
7. Cuando corresponda, pulsa **Cambiar a producción**.

> **Importante:** el NIF se bloquea en cuanto la entidad tiene una huella, aunque se haya generado en pruebas. Verifica el NIF antes de validar la primera factura de prueba.

## Cabecera de la página

Antes del formulario, `setup.php` muestra dos tablas informativas:

**Estado del entorno**

| Fila | Valor | Acción disponible |
|---|---|---|
| Entorno activo | «Producción» o «Pruebas», según `getEnvironment()` | Botón **Cambiar a pruebas** (`action=settest`) o **Cambiar a producción** (`action=setproduction`), con confirmación |
| Estado de la configuración | «Completa» si `VERIFACTU_HOLDER_COMPANY_NAME`, `VERIFACTU_HOLDER_NIF` y `VERIFACTU_CERTIFICATE` no están vacíos; «Incompleta» en caso contrario | — |

**Esquemas WSDL/XSD**

| Fila | Valor | Acción disponible |
|---|---|---|
| Estado de los esquemas | «Descargados» con «Última descarga: fecha» si `SchemaManager::schemasExist()`; «No descargados» si no | Botón **Descargar/Actualizar esquemas** (`action=downloadschemas`), que llama a `downloadSchemas(true)` sobre `documents/verifactu/schemas` y responde «Esquemas descargados correctamente (N archivos)» o «Error al descargar esquemas: ...» |

Detalle de la caché en [[Esquemas WSDL y XSD|Esquemas-WSDL-y-XSD]].

## Configuración básica

Sección siempre visible (`BasicConfig`, título «📋 Configuración Básica»).

| Etiqueta | Constante | Valor por defecto | Significado y efecto |
|---|---|---|---|
| Razón social | `VERIFACTU_HOLDER_COMPANY_NAME` | Se propone `$mysoc->name` (nombre de la empresa en Inicio > Configuración > Empresa) | `NombreRazon` del obligado tributario en cada registro (`getSystemConfig()`); también `NombreRazonEmisor`. Obligatoria: sin ella `execVERIFACTUCall()` responde «Configuración VeriFactu incompleta» |
| NIF | `VERIFACTU_HOLDER_NIF` | Se propone `$mysoc->idprof1` | `NIF` del obligado tributario e `IDEmisorFactura`. Obligatorio. **Se bloquea** cuando la entidad tiene registros (véase «Avisos») |
| Tipo de Empresa | `VERIFACTU_COMPANY_TYPE` | `sociedad` (creada al activar) | `sociedad` o `autonomo`. Decide la fecha de paso automático a producción: 1 de enero de 2027 o 1 de julio de 2027 |
| Certificado digital | `VERIFACTU_CERTIFICATE` | Vacío hasta que se sube un certificado | Desplegable con los ficheros `.pfx`, `.p12` y `.pem` de `documents/verifactu/certificates` (`dol_dir_list()` con filtro `\.(pfx\|p12\|pem)$`). Tras el primer uso el módulo lo sustituye por el PEM convertido |
| Clave del certificado | `VERIFACTU_CERTIFICATE_KEY` | Vacío | Contraseña del PFX/P12. Tras la conversión a PEM pasa a contener la clave con la que se protegió el PEM (`$dolibarr_main_instance_unique_id`); véase [[Certificados digitales|Certificados-digitales]] |
| Caducidad del certificado | (solo lectura) | — | Aparece cuando hay certificado configurado. Lee `validTo` con `openssl_x509_parse()` y muestra «Caducado», «Caduca pronto» (menos de 30 días) o «Válido», con la fecha y los días restantes; o «No se pudo leer el certificado» / «Archivo de certificado no encontrado» |

Los textos de ayuda son `VERIFACTU_HOLDER_COMPANY_NAME_HELP`, `VERIFACTU_HOLDER_NIF_HELP`, `VERIFACTU_COMPANY_TYPE_HELP` («Tipo de empresa. Sociedades: producción 01/01/2027. Autónomos: producción 01/07/2027.»), `VERIFACTU_CERTIFICATE_HELP` y `VERIFACTU_CERTIFICATE_KEY_HELP` («Contraseña o clave del certificado digital para la firma electrónica.»).

## Configuración adicional

Visible solo cuando la configuración básica está completa (`AdditionalConfig`, «⚙️ Configuración Adicional»).

| Etiqueta | Constante | Valor por defecto | Significado y efecto |
|---|---|---|---|
| Modo de operación | `VERIFACTU_MODE` | `verifactu` | Desplegable con una única opción, «Verifactu». El texto de ayuda indica que el modo NO-VeriFactu está en desarrollo. Se copia al campo `verifactu_modo` de cada factura |
| NIF del representante | `VERIFACTU_HOLDER_REPRESENTATIVE_NIF` | Se propone `$mysoc->idprof2` | Se guarda en `llx_const`. En la 2.2.2 ningún otro fichero del módulo lo lee: no se transmite a la AEAT |
| Información del entorno | (solo lectura) | — | «Producción (Forzado)» si `VERIFACTU_FORCE_PRODUCTION_ENVIRONMENT` está activa; «Producción» si `getEnvironment()` ya devuelve producción por fecha; o «Pruebas (Producción obligatoria desde 1 enero 2027 / 1 julio 2027)» |
| Tipo de certificado | `VERIFACTU_CERT_TYPE` | `normal` | `normal` («Compatible con todos los certificados (recomendado)») o `sello` («Solo certificados empresariales de sello»). Elige el servidor de la AEAT: `www1`/`prewww1` con `normal`, `www10`/`prewww10` con `sello` (`OpenAEAT\Billing\Config`). La ayuda añade: «Si obtiene errores "no XML document" en consultas, use 'normal'» |

## Código QR

Sección `QRConfig` («📱 Código QR»). Estas opciones gobiernan el QR que el hook `afterPDFCreation()` y la plantilla `pdf_verifactu` dibujan en el PDF, y el texto del ticket de TPV. El QR de la ficha de factura (150 px) no depende de ellas.

| Etiqueta | Constante | Valor creado al activar | Opciones del formulario | Efecto |
|---|---|---|---|---|
| Tamaño (mm) | `VERIFACTU_QR_SIZE` | `30` | 30 a 40 mm (el formulario propone 35 si la constante estuviera vacía) | Lado del QR en el PDF. Ayuda: «AEAT especifica 30-40mm (recomendado: 35mm)» |
| Posición horizontal | `VERIFACTU_QR_POSITION_X` | `custom` | Izquierda, Centro, Derecha, Personalizada | `calculateQRPositionX()`: izquierda = 10 mm; derecha = ancho − 10 − tamaño; centro = centrado; personalizada = valor de la siguiente constante (50 mm si está vacía) |
| Posición X personalizada (mm) | `VERIFACTU_QR_POSITION_X_CUSTOM` | `70` | 0 a 210 mm | Solo se usa con «Personalizada» |
| Posición vertical (mm) | `VERIFACTU_QR_POSITION_Y` | `2` | 0 a 100 mm | Distancia desde el borde superior |
| Mostrar texto | `VERIFACTU_QR_SHOW_TEXT` | `1` | No / Sí | Texto «QR tributario:» arriba y «VeriFactu» abajo. La ayuda lo califica de obligatorio según la AEAT; el código fuente indica que ocultarlo no está permitido |
| Mostrar texto en TPV | `VERIFACTU_QR_SHOW_TEXT_TPV` | `1` | No / Sí | Mismo texto en el ticket de TPV |
| Tamaño de texto | `VERIFACTU_QR_TEXT_SIZE` | `8` | Campo libre | Tamaño de fuente del texto. Ayuda: «Debe ser igual o mayor que el resto de datos de la factura» |
| QR en todas las páginas | `VERIFACTU_QR_ALL_PAGES` | No se crea al activar (equivale a `0`) | No / Sí | Si está activo, el QR se repite en cada página; la ayuda recuerda que la especificación oficial es solo la primera |
| Herramienta para normalizar PDF | `VERIFACTU_PDF_NORMALIZER` | Vacía | Campo libre | Solo interviene cuando el PDF no se puede reimportar para estamparle el QR. Vacía: busca `gs` o `qpdf` en el servidor; una ruta fija cuál usar; `off` lo desactiva. Desde la 2.2.4 |

Detalle del contenido y la URL del QR en [[Código QR|Codigo-QR]]; de la plantilla, en [[Plantilla PDF verifactu|Plantilla-PDF-verifactu]]; del ticket, en [[TakePOS|TakePOS]].

## Configuración avanzada

Sección `AdvancedConfig` («🔧 Configuración Avanzada»). Los cuatro desplegables comparten la primera opción «Automático (recomendado)», que corresponde a la cadena vacía, y las listas de `lib/verifactu-types.array.php`.

| Etiqueta | Constante | Valor por defecto | Lista de valores | Efecto |
|---|---|---|---|---|
| Impuesto por defecto | `VERIFACTU_DEFAULT_TAX_TYPE` | vacío | `01` IVA, `02` IPSI, `03` IGIC, `05` Otros | Si no está vacío, el trigger `billCreate()` lo escribe en `verifactu_impuesto` de cada factura nueva |
| Clave Régimen por defecto | `VERIFACTU_DEFAULT_TAX_REGIME` | vacío | `01` a `20` (régimen general, exportación, bienes usados, criterio de caja, recargo de equivalencia...) | Ídem sobre `verifactu_clave_regimen` |
| Calificación por defecto | `VERIFACTU_DEFAULT_OPERATION_QUALIFICATION` | vacío | `S1`, `S2`, `N1`, `N2` | Ídem sobre `verifactu_calificacion_operacion` |
| Operación exenta por defecto | `VERIFACTU_DEFAULT_EXEMPT_OPERATION` | vacío | `E1` a `E6` | Ídem sobre `verifactu_operacion_exenta` |

Orden de precedencia al crear una factura (`billCreate()` en `core/triggers/interface_999_modVerifactu_VerifactuTriggers.class.php`):

1. Valor enviado en el formulario de creación (`GETPOST('options_verifactu_...')`); si no hay, los valores base de la biblioteca: impuesto `01`, régimen `01`, calificación `S1`, exenta vacía.
2. Si la constante `VERIFACTU_DEFAULT_*` correspondiente no está vacía, sustituye al anterior.
3. Si el tercero tiene informado el mismo campo en su ficha (`verifactu_impuesto`, `verifactu_clave_regimen`, `verifactu_calificacion_operacion`, `verifactu_operacion_exenta`), sustituye a todo lo anterior y se muestra un aviso «... de '<tercero>' aplicado».

En el envío, `getVerifactuParams()` toma únicamente lo que haya en la factura; no infiere nada. Véase [[Desglose de impuestos|Desglose-de-impuestos]].

> **Aviso:** el separador «CAMPOS FISCALES» de la ficha de factura y el texto de confirmación de validación recuerdan que impuesto, clave de régimen, calificación y exención son responsabilidad de la empresa. Estas opciones solo fijan un valor inicial.

## Entorno de pruebas y producción

### Cómo se decide el entorno

`getEnvironment()` (`lib/functions/functions.configuration.php`) devuelve `'test'` o `'production'`:

1. Si `VERIFACTU_FORCE_PRODUCTION_ENVIRONMENT` no está vacía, **producción**.
2. Si no, compara la hora actual en `Europe/Madrid` con la fecha de transición: `2027-07-01 00:00:00` si `VERIFACTU_COMPANY_TYPE === 'autonomo'`, `2027-01-01 00:00:00` en cualquier otro caso. Antes de esa fecha, **pruebas**; desde ella, **producción**.

El entorno se guarda en cada factura enviada (campo `verifactu_entorno`, valores `test`/`production`) y la cadena de huellas es independiente por entorno: `getLastInvoiceHash()` filtra por `verifactu_entorno = <entorno actual>` y el bloqueo se llama `verifactu_chain_<entidad>_<entorno>`.

### Cambiar de entorno desde la pantalla

| Botón | Acción | Efecto | Mensaje |
|---|---|---|---|
| Cambiar a producción | `action=setproduction` | `dolibarr_set_const('VERIFACTU_FORCE_PRODUCTION_ENVIRONMENT', '1')` en la entidad | «Entorno cambiado a PRODUCCIÓN. Las facturas se enviarán al servidor real de la AEAT.» |
| Cambiar a pruebas | `action=settest` | `dolibarr_del_const('VERIFACTU_FORCE_PRODUCTION_ENVIRONMENT')` | «Entorno cambiado a PRUEBAS. Las facturas se enviarán al servidor de test de la AEAT.» |

Ambos piden confirmación (`ConfirmSwitchToProduction` / `ConfirmSwitchToTest`).

> **Importante:** «Cambiar a pruebas» solo borra la constante que fuerza producción. A partir de la fecha de transición de tu tipo de empresa, `getEnvironment()` devuelve producción por calendario y el módulo no ofrece forma de volver a pruebas.

La API REST expone lo mismo: `POST /api/index.php/verifactuapi/toProduction` y `POST /api/index.php/verifactuapi/toTest` (`class/api_verifactu.class.php`), que desde la 2.2.2 exigen la clave de API de un administrador. Véase [[API REST|API-REST]] y, para los servidores de cada entorno, [[Conexión SOAP y entornos|Conexion-SOAP-y-entornos]].

## Envío directo al validar

`VERIFACTU_DIRECT_CALL_ON_VALIDATE` (creada a `1` al activar) decide **cómo** llega a la AEAT el registro de una factura validada, nunca si existe. El elemento de formulario correspondiente está comentado en `setup.php`, así que para cambiarla hay que editar la constante en **Inicio > Configuración > Otros** (o con `dolibarr_set_const`).

| Valor | Comportamiento de `billValidate()` |
|---|---|
| `1` | Comprueba NIF/NIF intracomunitario/dirección del tercero según el caso, comprueba la conexión a Internet y llama a `execVERIFACTUCall($object, 'Alta', false)`. Si el envío falla, la factura vuelve a borrador con referencia `(PROVn)` y se muestra `VERIFACTU_ERROR_INVOICE_NOT_VALIDATED`. Sin conexión, la factura se valida y queda «Sin Conexión» con `Incidencia = S` |
| `0` | La factura se valida y queda «Pendiente» con `verifactu_error = PENDING_SUBMISSION`, `Incidencia = S` y la fecha de modificación; el mensaje `VERIFACTU_PENDING_SUBMISSION_INFO` avisa de que se enviará desde la cola de reintentos |

Las facturas procedentes de TakePOS (`module_source == 'takepos'`) se envían siempre de forma directa, independientemente de la constante. El flujo completo está en [[Flujo de validación y envío|Flujo-de-validacion-y-envio]] y la cola en [[Reintentos y envíos pendientes|Reintentos-y-envios-pendientes]].

## Tiempo de espera de la AEAT

Cada respuesta de la AEAT incluye `TiempoEsperaEnvio`, los segundos que pide esperar antes del siguiente envío. El módulo no lo configura: lo **registra**.

- `registerAEATWaitTime()` (`lib/functions/functions.response.php`) guarda en `VERIFACTU_NEXT_SEND_AT` el instante (`dol_now() + segundos`) a partir del cual se admite otro envío, por entidad.
- `getAEATWaitTimeRemaining()` devuelve los segundos que faltan.
- `execVERIFACTUCall($facture, $accion, $enforceWaitTime)` comprueba ese valor al empezar. Con `$enforceWaitTime = true` (reintentos automáticos de la tarea programada) aborta con el aviso «La AEAT requiere esperar %s segundos antes del siguiente envío VeriFactu.» Con `false` (validación) solo lo anota en el registro, porque abortar revertiría la factura a borrador. Los envíos que el usuario pide explícitamente tampoco lo aplican, según el registro de cambios de la 2.0.0.

No edites `VERIFACTU_NEXT_SEND_AT` a mano salvo para diagnosticar; el módulo la sobrescribe con cada respuesta. Véase [[Respuestas de la AEAT|Respuestas-de-la-AEAT]].

## Bloqueo de la cadena

`VERIFACTU_CHAIN_LOCK_TIMEOUT` (segundos, 30 si no existe) es el tiempo máximo que una validación espera a que otra libere la cadena. Se lee en `acquireVerifactuChainLock()`; si vence, el envío falla con «Se está encadenando otra factura en este momento y ha vencido el tiempo de espera...» (`VERIFACTU_CHAIN_LOCK_BUSY`). No aparece en el formulario; créala en Inicio > Configuración > Otros si necesitas otro valor. Véase [[Encadenamiento y huella|Encadenamiento-y-huella]].

## Constantes fuera del formulario

| Constante | Quién la escribe | Uso |
|---|---|---|
| `VERIFACTU_FORCE_PRODUCTION_ENVIRONMENT` | Botones de entorno, API | Fuerza producción |
| `VERIFACTU_DIRECT_CALL_ON_VALIDATE` | Activación (`1`) | Envío directo al validar |
| `VERIFACTU_CHAIN_LOCK_TIMEOUT` | Manual | Espera del bloqueo de la cadena |
| `VERIFACTU_NEXT_SEND_AT` | `registerAEATWaitTime()` | Próximo instante de envío permitido |
| `VERIFACTU_CERTIFICATE_PRIVATE_KEY` | `admin/managecertificates.php` | Clave privada en PEM extraída del P12 |
| `INVOICE_DISALLOW_REOPEN`, `INVOICE_CAN_NEVER_BE_REMOVED`, `INVOICE_CHECK_POSTERIOR_DATE`, `FAC_FORCE_DATE_VALIDATION` | Activación (`1`) | Opciones del núcleo sobre facturas (véase [[Restricciones sobre facturas enviadas|Restricciones-sobre-facturas-enviadas]] y [[Fechas y numeración|Fechas-y-numeracion]]). El hook `printCommonFooter()` además deshabilita el desplegable `forcedate` en `admin/facture.php` |
| `SOCIETE_IDPROF1_INVOICE_MANDATORY` | Activación (`0`); el hook `updateSession()` la fuerza a `false` en cada petición | El control del NIF lo hace el trigger del módulo |
| `BLOCKEDLOG_DISABLE_NOT_ALLOWED_FOR_COUNTRY` | Activación (`ES`) | Impide desactivar el registro inalterable en España |
| `VERIFACTU_MODE`, `VERIFACTU_CERT_TYPE`, `VERIFACTU_COMPANY_TYPE`, `VERIFACTU_QR_*`, `VERIFACTU_DEFAULT_*` | Activación | Descritas en las secciones anteriores |

Las constantes creadas por la activación no se borran al desactivar el módulo (ninguna lleva `deleteonunactive`).

## Avisos y bloqueos de la pantalla

| Situación | Cuándo se comprueba | Función | Mensaje |
|---|---|---|---|
| Cambio de NIF con registros existentes | Al guardar (`action=update`), antes de que `FormSetup` escriba nada | `hasVerifactuFiscalRecords($conf->entity)`, comparando ambos NIF con `normalizeVerifactuTaxIdentifier()` (mayúsculas, sin separadores) | Error `VERIFACTU_TAX_IDENTITY_LOCKED`: «No se puede modificar el NIF del obligado tributario porque esta entidad ya contiene registros VeriFactu encadenados. El certificado sí se puede sustituir.» No se guarda ningún campo del formulario |
| NIF ya usado por otra entidad con VeriFactu | Al guardar | `getVerifactuEntityWithSameTaxId()` (solo con Multicompany activo) | Error `VERIFACTU_TAX_IDENTITY_ALREADY_USED`: «El NIF del obligado tributario ya está configurado en la entidad VeriFactu %s...». No se guarda nada |
| Numeración de facturas compartida entre entidades | En cada carga de la página | `isVerifactuInvoiceNumberingShared()` (`MULTICOMPANY_SHARINGS_ENABLED` y `MULTICOMPANY_INVOICENUMBER_SHARING_ENABLED`) | Aviso `VERIFACTU_SHARED_INVOICE_NUMBERING_WARNING`; no bloquea |
| Certificado caducado o próximo a caducar | En cada carga | Lectura de `validTo` | Insignia «Caducado» / «Caduca pronto» en «Caducidad del certificado» |
| Configuración incompleta | En cada carga | Tres constantes básicas vacías | «Estado de la configuración: Incompleta»; no se muestran las secciones adicionales |

`hasVerifactuFiscalRecords()` busca una factura de la entidad con `verifactu_huella` no vacía; si la consulta falla, devuelve `true` (falla cerrado) para no permitir el cambio de NIF mientras no pueda comprobarse la cadena. `getVerifactuEntityWithSameTaxId()` hace lo contrario (falla abierto): un error de consulta no bloquea la pantalla. El detalle multiempresa está en [[Multiempresa|Multiempresa]].

## Identidad del sistema informático

Los datos del bloque `SistemaInformatico` no se configuran en esta pantalla. `getSystemConfig()` los compone así:

| Campo | Valor | Origen |
|---|---|---|
| `NombreRazon` | Razón social configurada | `VERIFACTU_HOLDER_COMPANY_NAME` |
| `NIF` | NIF configurado | `VERIFACTU_HOLDER_NIF` |
| `NombreSistemaInformatico` | `Dolibarr Verifactu Module` | `conf/declaracion_responsable.conf.php`, clave `nombre_sistema_informatico` (máximo 30 caracteres) |
| `IdSistemaInformatico` | `DV` | Misma fuente, clave `id_sistema_informatico` (máximo 2 caracteres) |
| `Version` | `2.2.2` | Misma fuente, clave `version` (máximo 50 caracteres) |
| `NumeroInstalacion` | `<dolibarr_main_instance_unique_id>_<entidad>`, recortado a 100 caracteres | `conf.php` y `$conf->entity` |
| `TipoUsoPosibleSoloVerifactu` | `S` | Fijo |
| `TipoUsoPosibleMultiOT` | `N` | Fijo |
| `IndicadorMultiplesOT` | `N` | Fijo |

Si un valor de la declaración excede el límite del esquema, `getDeclaredSystemIdentity()` lo recorta con `mb_substr()` y escribe un error en el registro indicando que lo transmitido ya no coincide con lo declarado. Quien redistribuya el módulo edita `conf/declaracion_responsable.conf.php`; véase [[Declaración responsable|Declaracion-responsable]].

## Dónde se guarda todo

Cada opción es una fila de `llx_const` con `entity = <entidad activa>` y tipo `chaine`. Dolibarr carga `llx_const` con `WHERE entity IN (0, n)`, de modo que la configuración es independiente por entidad. Puedes revisarla completa en **Inicio > Configuración > Otros**, filtrando por `VERIFACTU_`. Ten en cuenta que ahí aparecen también `VERIFACTU_CERTIFICATE_KEY` y `VERIFACTU_CERTIFICATE_PRIVATE_KEY` en claro; el acceso a esa pantalla está reservado a administradores.

## Véase también

- [[Instalación|Instalacion]]
- [[Certificados digitales|Certificados-digitales]]
- [[Conexión SOAP y entornos|Conexion-SOAP-y-entornos]]
- [[Esquemas WSDL y XSD|Esquemas-WSDL-y-XSD]]
- [[Flujo de validación y envío|Flujo-de-validacion-y-envio]]
- [[Código QR|Codigo-QR]]
- [[Desglose de impuestos|Desglose-de-impuestos]]
- [[Multiempresa|Multiempresa]]
- [[Declaración responsable|Declaracion-responsable]]
