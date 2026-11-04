# Permisos y menús

Esta página inventaría todo lo que el descriptor `core/modules/modVerifactu.class.php` registra en Dolibarr al activar el módulo (versión 2.2.2): el permiso, las entradas de menú con sus URL, las pestañas, los campos adicionales (solo el nombre; el detalle está en [[Campos adicionales|Campos-adicionales]]), la tarea programada, la plantilla PDF, los contextos de hook, los triggers, las sustituciones, los ficheros CSS/JS globales y las constantes que crea `init()`.

## Identidad del módulo

| Propiedad | Valor | Comentario |
|---|---|---|
| `$this->numero` | `350100` | Identificador único del módulo; los permisos se numeran a partir de él |
| `$this->rights_class` | `verifactu` | Clave de permisos, menús y tarea programada (`$user->rights->verifactu->...`) |
| `$this->family` / `module_position` | `financial` / `90` | Aparece entre los módulos financieros, al final |
| `$this->name` | `Verifactu` | Constante de activación `MAIN_MODULE_VERIFACTU` |
| `$this->version` / `verifactu_version` / `verifactu_version_date` | `2.2.2` / `2.2.2` / `19/09/2026` | La versión se transmite a la AEAT en `SistemaInformatico.Version` |
| `$this->editor_name` | `Germán Luis Aracil Boned <garacilb@gmail.com>` | Autor |
| `$this->picto` | `bill` | Icono de factura del núcleo |
| `$this->depends` | `modFacture`, `modBlockedLog`, `modApi` | Se activan automáticamente |
| `$this->phpmin` / `need_dolibarr_version` | `7.4` / `10` | Mínimos declarados (véase [[Requisitos|Requisitos]]) |
| `$this->langfiles` | `verifactu@verifactu` | `langs/<idioma>/verifactu.lang` en es_ES, ca_ES, eu_ES, gl_ES y en_US |
| `$this->config_page_url` | `setup.php@verifactu` | Icono de configuración en la lista de módulos |
| `$this->dirs` | `/verifactu/temp`, `/verifactu/certificates` | Carpetas bajo `documents/` |
| `$this->dictionaries`, `$this->boxes` | vacíos | No define diccionarios ni widgets |
| `getLastVersion()` | Devuelve siempre la versión actual con `needupdate = false` | La consulta a un servidor externo está deshabilitada |

## Permisos

El módulo define un único permiso:

| Id | Etiqueta (es_ES) | Clave | Cómo se comprueba |
|---|---|---|---|
| `35010001` | Gestionar módulo VeriFactu (`PERMISSIONS_MANAGE_VERIFACTU`) | `manage` | `$user->rights->verifactu->manage` |

Reglas de acceso que se derivan de él:

- **Menús y pestaña de la factura**: condición `$user->rights->verifactu->manage || $user->admin`. Un usuario sin el permiso y sin ser administrador no ve el menú «Verifactu» ni la pestaña VeriFactu.
- **Campos adicionales**: la misma condición gobierna su visibilidad (`perms` de cada `addExtraField()`).
- **Páginas de administración** (`admin/setup.php`, `managecertificates.php`, `uploadcertificates.php`, `about.php`, `copying.php`): exigen `$user->admin`; el permiso `manage` no basta.
- **Usuarios externos**: todas las entradas de menú llevan `user => 0` (solo usuarios internos) y `moduleforexternal => 0`.

Al activar, `insert_permissions(1, null, 1)` concede el permiso a todos los usuarios con `admin = 1`. Para el resto se asigna desde la ficha de usuario o de grupo, pestaña Permisos, módulo VeriFactu. No hay permisos separados de lectura, escritura o eliminación: todo lo que el módulo permite hacer (enviar, anular, consultar) depende de `manage`.

## Menús

Todas las entradas tienen `enabled => '$conf->verifactu->enabled'`, `perms => '$user->rights->verifactu->manage || $user->admin'`, `user => 0` y posición `1000 + n`.

### Menú superior

| Título | Clave | URL | `mainmenu` |
|---|---|---|---|
| Verifactu | `Verifactu` (literal) | `/verifactu/verifactuindex.php` | `verifactu` |

La página de destino es el panel descrito en [[Panel principal|Panel-principal]].

### Menú izquierdo (bajo `fk_mainmenu=verifactu`)

| Orden | Título (es_ES) | Clave de traducción | `leftmenu` | URL |
|---|---|---|---|---|
| 1 | Declaración responsable | `VERIFACTU_DECLARATION_MENU` | `verifactu_declaration` | `/verifactu/views/declaration.php` |
| 2 | Facturas emitidas | `VERIFACTU_SEND_INVOICES_MENU` | `verifactu_send_invoices` | `/verifactu/views/list.facture.php` |
| 3 | Consulta AEAT | `VERIFACTU_SEND_INVOICES_QUERY_MENU` | `verifactu_query` | `/verifactu/views/query.facture.php` |
| 4 | Documentación | `VERIFACTU_DOCUMENTATION_MENU` | `verifactu_documentation` | `/verifactu/views/documentation.php` |
| 5 | Ayuda | `VERIFACTU_HELP_MENU` | `verifactu_help` | `/verifactu/views/faq.php` |
| 6 | Configuración | `VERIFACTU_SETUP` | `verifactu_documentation` | `/verifactu/admin/setup.php` |

> **Nota:** la entrada «Configuración» reutiliza el código `leftmenu` de «Documentación» y declara el fichero de idioma `verifactu@OpenVerifactu`. La etiqueta se resuelve igualmente porque `VERIFACTU_SETUP` está en `verifactu.lang`, que ya está cargado por las demás entradas. La entrada apunta a una página que exige `$user->admin`, aunque el menú se muestre a cualquier usuario con `manage`.

Las páginas de cada entrada se describen en [[Declaración responsable|Declaracion-responsable]], [[Validación masiva|Validacion-masiva]] (lista de facturas), [[Consulta a la AEAT|Consulta-a-la-AEAT]] y [[Preguntas frecuentes|Preguntas-frecuentes]].

## Pestañas

### En la ficha de la factura de cliente

```
invoice:+factureVERIFACTU_TAB:SUBSTITUTION_FACTUREVERIFACTUTAB:verifactu@verifactu:($user->rights->verifactu->manage || $user->admin):/verifactu/views/tabVERIFACTU.facture.php?id=__ID__
```

| Elemento | Valor |
|---|---|
| Objeto | `invoice` (factura de cliente) |
| Código de pestaña | `factureVERIFACTU_TAB` |
| Título | Resultado de la sustitución `FACTUREVERIFACTUTAB`, que `verifactu_completesubstitutionarray()` (`core/substitutions/functions_verifactu.lib.php`) rellena con `FACTURE_VERIFACTU_TAB` = «VeriFactu» |
| Condición | `$user->rights->verifactu->manage \|\| $user->admin` |
| URL | `/verifactu/views/tabVERIFACTU.facture.php?id=<id_factura>` |

Su contenido se describe en [[Pestaña VeriFactu de la factura|Pestana-VeriFactu-de-la-factura]]. No se añaden pestañas a facturas de proveedor ni a terceros.

### En la administración del módulo

Las construye `verifactuAdminPrepareHead()` (`lib/functions/functions.admin.php`) y las completa `complete_head_from_modules()` con el contexto `verifactu@verifactu`, de modo que otro módulo podría añadir o quitar pestañas:

| Código | Título (es_ES) | Fichero |
|---|---|---|
| `settings` | Ajustes | `admin/setup.php` |
| `manage_certificates` | Gestionar Certificados | `admin/managecertificates.php` |
| `about` | Acerca de VeriFactu | `admin/about.php` |
| `copying` | Acuerdo de licencia | `admin/copying.php` |

`admin/uploadcertificates.php` usa la misma cabecera con el código `upload_certificates`, que no figura en la lista; se abre solo por URL.

## Campos adicionales

`init()` los crea con `ExtraFields::addExtraField()`, posición desde 1000, permiso `($user->rights->verifactu->manage || $user->admin)`, fichero de idioma `verifactu@verifactu` y condición `$conf->verifactu->enabled`. Solo se nombran aquí; tipo, valores y uso están en [[Campos adicionales|Campos-adicionales]].

**Factura de cliente** (`facture`, 20 campos): `verifactu_inicio_separador1`, `verifactu_estado`, `verifactu_error`, `verifactu_csv_factura`, `verifactu_id_factura`, `verifactu_fecha_factura`, `verifactu_ultima_salida`, `verifactu_ultimafecha_modificacion`, `verifactu_huella`, `verifactu_fecha_hora_generacion`, `verifactu_entorno`, `verifactu_modo`, `verifactu_payload`, `verifactu_inicio_separador2`, `verifactu_factura_tipo`, `verifactu_impuesto`, `verifactu_clave_regimen`, `verifactu_calificacion_operacion`, `verifactu_operacion_exenta`, `verifactu_incidencia`.

**Tercero** (`thirdparty`, 7 campos): `verifactu_inicio_separador1`, `verifactu_tipo_identificacion`, `verifactu_impuesto`, `verifactu_clave_regimen`, `verifactu_calificacion_operacion`, `verifactu_operacion_exenta`, `verifactu_factura_simplificada`.

El hook `updateSession()` intercepta `action=delete` en `societe/admin/societe_extrafields.php`, `compta/facture/admin/facture_cust_extrafields.php` y `compta/facture/admin/invoice_cust_extrafields.php` y, si el atributo empieza por `verifactu_`, redirige con el error `VERIFACTU_CANNOT_DELETE_EXTRA_FIELD`.

## Tarea programada

| Campo | Valor |
|---|---|
| Etiqueta | `sendInvoicesWithErrorsCronName` → «VeriFactu - Reenvío de facturas con errores de conexión» |
| Tipo | `method` |
| Clase / objeto / método | `/verifactu/class/verifactu.utils.php` / `VerifactuUtils` / `sendInvoicesWithErrors` |
| Frecuencia | `frequency = 2`, `unitfrequency = 3600`: cada 2 horas |
| Estado | `1` (activa al crearse) |
| Condición | `$conf->verifactu->enabled` |
| Prioridad | `50` |
| Entidad | La activa en el momento de la activación |

La tarea busca facturas validadas de la entidad, sin huella, cuyo `verifactu_error` contenga `NO_INTERNET_CONNECTION`, `SERVICE_UNAVAILABLE` o `PENDING_SUBMISSION`, y las envía por orden de fecha respetando el tiempo de espera de la AEAT. El mismo método se ejecuta también al principio de cada validación si hay conexión. Detalle en [[Reintentos y envíos pendientes|Reintentos-y-envios-pendientes]]. Al reactivar el módulo, la tarea se borra y se vuelve a crear con estos valores.

## Plantilla PDF

`'models' => 1` en `$this->module_parts` hace que Dolibarr busque plantillas en `htdocs/custom/verifactu/core/modules/`. Ahí está `facture/doc/pdf_verifactu.modules.php`, clase `pdf_verifactu` que extiende `ModelePDFFactures`:

| Propiedad | Valor |
|---|---|
| `$this->name` | `verifactu` |
| `$this->description` | `PDFVerifactuDescription` → «Plantilla PDF con código QR VeriFactu y datos fiscales AEAT.» |
| `$this->version` | `dolibarr` |
| Opciones | logo, IVA, modo y condiciones de pago, multilenguaje, descuento, abonos, texto libre, marca de agua de borrador |

Aparece en **Configuración > Módulos > Facturas y abonos > Modelos de documentos**, donde hay que activarla y, si se quiere, marcarla por defecto. La activación del módulo no lo hace. Véase [[Plantilla PDF verifactu|Plantilla-PDF-verifactu]].

## Hooks

### Contextos registrados

`$this->module_parts['hooks']`: `main`, `invoicecard`, `invoicelist`, `invoicelistverifactu`, `pdfgeneration`, `takeposfrontend`, `odtgeneration`. La clase es `ActionsVerifactu` (`class/actions_verifactu.class.php`).

### Métodos implementados

| Método | Contexto habitual | Qué hace |
|---|---|---|
| `getNomUrl()` | Cualquiera con una `Facture` | Añade la insignia de estado VeriFactu junto al enlace de la factura |
| `doActions()` | `thirdpartycard`, `thirdpartycomm`, `invoicelist`, `invoicelistverifactu`, `invoicecard` | Sustituye las etiquetas «CIF/NIF» y «CIF intra» del tercero por versiones con ayuda VeriFactu; intercepta la acción masiva `validate` (una vez confirmada) para validar factura a factura con orden cronológico; acciones de la ficha de factura |
| `dolGetButtonAction()` | `invoicecard` | Controla los botones de acción de la ficha |
| `doMassActions()`, `addMoreMassActions()`, `addMoreActionsButtons()` | Listas y ficha | Puntos de extensión del núcleo; en la 2.2.2 no añaden comportamiento propio |
| `beforePDFCreation()` | `pdfgeneration` | Prepara datos antes de generar el PDF; con salida limpia cuando el objeto no es una factura (informes de pagos) |
| `afterPDFCreation()` | `pdfgeneration` | Añade el QR con TCPDI a cualquier PDF de una factura enviada, según las constantes `VERIFACTU_QR_*` |
| `pdf_build_address()` | `pdfgeneration` | Ajusta el bloque de dirección |
| `loadDataForCustomReports()` | Informes personalizados | Datos para la herramienta de informes del núcleo |
| `updateSession()` | `main` (todas las páginas) | Redirige el ticket del TPV a `views/pos.facture.php`; fuerza `SOCIETE_IDPROF1_INVOICE_MANDATORY` a `false`; protege los campos `verifactu_*` frente al borrado |
| `moreHtmlStatus()`, `formDolBanner()` | `invoicecard` | Muestran el QR con «QR tributario:» y «VeriFactu» en la cabecera de la factura enviada |
| `printCommonFooter()` | `main` | En `admin/facture.php` deshabilita el desplegable `forcedate` |
| `TakeposReceipt()` | `takeposfrontend` | Ticket de TPV con QR (véase [[TakePOS|TakePOS]]) |
| `ODTSubstitution()` | `odtgeneration` | Variables de sustitución para plantillas ODT (véase [[Sustituciones ODT y plantillas|Sustituciones-ODT-y-plantillas]]) |

## Triggers

`'triggers' => 1` activa la carpeta `core/triggers/`:

| Fichero | Clase | Eventos | Cometido |
|---|---|---|---|
| `interface_900_modVerifactu_BillRestrictions.class.php` | `InterfaceBillRestrictions` | `BILL_DELETE`, `LINEBILL_INSERT`, `LINEBILL_UPDATE`, `LINEBILL_DELETE`, `BILL_UNVALIDATE` | Bloquea modificar, eliminar o desvalidar facturas ya enviadas. Prioridad 900 para ejecutarse antes del principal |
| `interface_999_modVerifactu_VerifactuTriggers.class.php` | (trigger principal) | `BILL_CREATE`, `BILL_VALIDATE`, `COMPANY_MODIFY` | Inicializa los campos VeriFactu al crear, envía al validar (o deja la factura pendiente), impide cambiar el NIF de un tercero con facturas enviadas |

Detalle en [[Restricciones sobre facturas enviadas|Restricciones-sobre-facturas-enviadas]] y [[Flujo de validación y envío|Flujo-de-validacion-y-envio]].

## Sustituciones, CSS y JS

- `'substitutions' => 1`: `core/substitutions/functions_verifactu.lib.php` define `verifactu_completesubstitutionarray()`, que añade la clave `FACTUREVERIFACTUTAB` usada por el título de la pestaña.
- `'css' => ['/verifactu/css/verifactu.css.php']`: hoja de estilos cargada en todas las páginas.
- `'js' => ['/verifactu/js/verifactu.js.php']`: script cargado en todas las páginas. Sustituye el campo de búsqueda `search_options_verifactu_estado` de la lista de facturas por un desplegable de estados y envuelve la función `Validate()` de la ficha de factura para mostrar una superposición de espera mientras se envía a la AEAT.
- `'tpl' => 0`, `'menus' => 0`, `'login' => 0`, `'printing' => 0`, `'theme' => 0`, `'barcode' => 0`.

## Constantes creadas por `init()`

Las 21 constantes de `$this->const`, todas de tipo `chaine`, visibles y para la entidad activa. Ninguna se elimina al desactivar el módulo.

| Constante | Valor | Ámbito |
|---|---|---|
| `INVOICE_DISALLOW_REOPEN` | `1` | Núcleo: facturas no reabribles |
| `INVOICE_CAN_NEVER_BE_REMOVED` | `1` | Núcleo: facturas no eliminables |
| `BLOCKEDLOG_DISABLE_NOT_ALLOWED_FOR_COUNTRY` | `ES` | Núcleo: registro inalterable obligatorio en España |
| `VERIFACTU_DIRECT_CALL_ON_VALIDATE` | `1` | Envío al validar |
| `VERIFACTU_QR_SIZE` | `30` | QR |
| `VERIFACTU_QR_POSITION_X` | `custom` | QR |
| `VERIFACTU_QR_POSITION_X_CUSTOM` | `70` | QR |
| `VERIFACTU_QR_POSITION_Y` | `2` | QR |
| `VERIFACTU_QR_SHOW_TEXT` | `1` | QR |
| `VERIFACTU_QR_TEXT_SIZE` | `8` | QR |
| `VERIFACTU_MODE` | `verifactu` | Modo |
| `SOCIETE_IDPROF1_INVOICE_MANDATORY` | `0` | Núcleo: NIF no exigido por el núcleo |
| `INVOICE_CHECK_POSTERIOR_DATE` | `1` | Núcleo: orden de fechas al validar |
| `VERIFACTU_DEFAULT_TAX_TYPE` | vacío | Valor fiscal por defecto |
| `VERIFACTU_DEFAULT_TAX_REGIME` | vacío | Valor fiscal por defecto |
| `VERIFACTU_DEFAULT_OPERATION_QUALIFICATION` | vacío | Valor fiscal por defecto |
| `VERIFACTU_DEFAULT_EXEMPT_OPERATION` | vacío | Valor fiscal por defecto |
| `VERIFACTU_CERT_TYPE` | `normal` | Certificado |
| `FAC_FORCE_DATE_VALIDATION` | `1` | Núcleo: fecha forzada al validar |
| `VERIFACTU_COMPANY_TYPE` | `sociedad` | Fecha de paso a producción |
| `VERIFACTU_QR_SHOW_TEXT_TPV` | `1` | QR en TPV |

Significado y efecto de cada una en [[Configuración|Configuracion]].

## Otros registros de la activación

- **Valor por defecto** (`DefaultValues`, Dolibarr 14 o superior): tipo `mandatory`, página `societe/card.php`, parámetro `country_id`, entidad activa. El país del tercero pasa a ser obligatorio.
- **Inicialización de facturas**: `UPDATE llx_facture_extrafields SET verifactu_estado = <insignia No Enviada> WHERE verifactu_estado IS NULL AND fk_object IN (facturas de la entidad)`.
- **Tablas**: `_load_tables('/verifactu/sql/')` no crea ninguna; `sql/dolibarr_allversions.sql` solo contiene un comentario.
- **API REST**: al depender de `modApi`, la clase `VerifactuApi` (`class/api_verifactu.class.php`) queda disponible con los recursos `GET /verifactuapi/integrity` (público) y `POST /verifactuapi/toProduction` y `POST /verifactuapi/toTest`, que desde la 2.2.2 exigen la clave de API de un administrador. Véase [[API REST|API-REST]].
- **Página pública de integridad**: `integrity.php` en la raíz del módulo (véase [[Integridad del módulo|Integridad-del-modulo]]).

## Véase también

- [[Instalación|Instalacion]]
- [[Configuración|Configuracion]]
- [[Campos adicionales|Campos-adicionales]]
- [[Pestaña VeriFactu de la factura|Pestana-VeriFactu-de-la-factura]]
- [[Reintentos y envíos pendientes|Reintentos-y-envios-pendientes]]
- [[Plantilla PDF verifactu|Plantilla-PDF-verifactu]]
- [[Arquitectura del módulo|Arquitectura-del-modulo]]
- [[Panel principal|Panel-principal]]
