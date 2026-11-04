# Plantilla PDF verifactu

El módulo aporta una plantilla de factura en PDF llamada `verifactu` (`core/modules/facture/doc/pdf_verifactu.modules.php`) y, de forma independiente de la plantilla que se use, añade el código QR de cotejo al PDF ya generado mediante el hook `afterPDFCreation`. Esta página describe cómo activar la plantilla, qué contiene y qué opciones del núcleo respeta, y detalla el mecanismo de inserción del QR con TCPDI, la posición que calcula, cuándo se omite y qué errores conocidos se han corregido.

## Dos mecanismos distintos

Conviene separar dos cosas que el módulo hace en el PDF:

| Mecanismo | Fichero | Se aplica a |
|---|---|---|
| Plantilla `verifactu` | `core/modules/facture/doc/pdf_verifactu.modules.php` | Solo si se elige como plantilla de la factura |
| Inserción del QR y textos con TCPDI | `ActionsVerifactu::afterPDFCreation()` en `class/actions_verifactu.class.php` | A cualquier plantilla de factura de cliente (`crabe`, `sponge`, `verifactu` o una propia) |
| Título «factura simplificada» y bloque de destinatario | `ActionsVerifactu::beforePDFCreation()` y `ActionsVerifactu::pdf_build_address()` | A cualquier plantilla que use las funciones estándar del núcleo |

La plantilla `verifactu` **no dibuja el QR por sí misma**: al terminar dispara el hook `afterPDFCreation`, exactamente igual que las plantillas del núcleo, y es ese hook el que lo añade. Por tanto no es imprescindible usar la plantilla del módulo para obtener el QR en el PDF.

## Activar la plantilla y hacerla predeterminada

El descriptor del módulo declara `'models' => 1` en `module_parts`, de modo que Dolibarr explora `custom/verifactu/core/modules/facture/doc/` al listar plantillas de factura.

1. Ve a Inicio > Configuración > Módulos/Aplicaciones > Facturas y abonos > Configuración.
2. En la tabla «Plantillas de documentos» aparece la fila `verifactu` con la descripción «Plantilla PDF con código QR VeriFactu y datos fiscales AEAT.» (clave `PDFVerifactuDescription`).
3. Actívala con el interruptor de la columna Estado y, si quieres que sea la predeterminada para las facturas nuevas, marca la columna Por defecto (constante `FACTURE_ADDON_PDF`).
4. En cada factura puedes elegir otra plantilla desde el desplegable del recuadro de documentos antes de generar el PDF.

> **Nota:** en esa misma página de configuración de facturas, el hook `printCommonFooter()` del módulo desactiva el desplegable «Forzar fecha de validación» (`forcedate`): el módulo fija `FAC_FORCE_DATE_VALIDATION` para que la fecha de validación coincida con la de la factura. Véase [[Fechas y numeración|Fechas-y-numeracion]].

## Qué incluye la plantilla

La clase `pdf_verifactu` extiende `ModelePDFFactures` y deriva de las plantillas estándar del núcleo (los autores originales figuran en la cabecera del fichero). Propiedades relevantes del constructor:

- `name = "verifactu"`, `type = 'pdf'`, `update_main_doc_field = 1` (el PDF generado se registra como documento principal de la factura).
- Formato de página de `pdf_getFormat()`; márgenes de `MAIN_PDF_MARGIN_LEFT`, `_RIGHT`, `_TOP` y `_BOTTOM` (10 mm por defecto).
- Opciones declaradas: logotipo, IVA, modo y condiciones de pago, multilenguaje, descuento, abonos, texto libre y marca de agua de borrador.

### Estructura del documento

Cabecera (`_pagehead()`):

- Logotipo de la empresa (`logos/thumbs/` o `logos/` con `MAIN_PDF_USE_LARGE_LOGO`; se oculta con `PDF_DISABLE_MYCOMPANY_LOGO`; si no hay logotipo, el nombre de la empresa).
- Título según el tipo Dolibarr de la factura: `PdfInvoiceTitle` (factura), `InvoiceReplacement`, `InvoiceAvoir`, `InvoiceDeposit`, `InvoiceProForma` o `PDFInvoiceSituation`, seguido de la referencia; los borradores añaden `NotValidated` en rojo. Con `PDF_USE_ALSO_LANGUAGE_CODE` el título se repite en el segundo idioma.
- Referencia del cliente, proyecto (`PDF_SHOW_PROJECT_TITLE`, `PDF_SHOW_PROJECT`), factura sustituida o rectificada, fecha de factura, fecha de devengo (`INVOICE_POINTOFTAX_DATE`), vencimiento (no en abonos), código de cliente (salvo `MAIN_PDF_HIDE_CUSTOMER_CODE`), comercial (`DOC_SHOW_FIRST_SALES_REP`) y objetos vinculados.
- Recuadros de emisor y destinatario, con las variantes `MAIN_PDF_USE_ISO_LOCATION`, `MAIN_INVERT_SENDER_RECIPIENT`, `MAIN_PDF_NO_SENDER_FRAME`, `MAIN_PDF_HIDE_SENDER_NAME`, `MAIN_PDF_NO_RECIPENT_FRAME` y `MAIN_USE_COMPANY_NAME_OF_CONTACT`. Si la factura tiene un contacto de tipo facturación, se usa como destinatario. Las direcciones se construyen con `pdf_build_address()` del núcleo, lo que permite al módulo intervenir (véase más abajo).

Cuerpo (`write_file()`):

- Códigos QR ZATCA o suizo si están activas `INVOICE_ADD_ZATCA_QR_CODE` o `INVOICE_ADD_SWISS_QR_CODE` (heredados del núcleo; no tienen relación con el QR VeriFactu).
- Incoterms, nota pública (con firma del comercial si `MAIN_ADD_SALE_REP_SIGNATURE_IN_NOTE`) y campos adicionales marcados para imprimirse en el documento, con control de salto de página.
- Tabla de líneas con el sistema de columnas de `defineColumnField()`:

| Columna | Ancho | Se muestra si |
|---|---|---|
| `desc` (Designación) | Resto | Siempre |
| `photo` | `MAIN_DOCUMENTS_WITH_PICTURE_WIDTH` o 20 mm | `MAIN_GENERATE_INVOICES_WITH_PICTURE` y alguna línea con foto |
| `vat` (IVA) | 16 mm | Salvo `MAIN_GENERATE_DOCUMENTS_WITHOUT_VAT` o `_COLUMN` |
| `subprice` (Precio unitario) | 19 mm, ampliable según el texto | Siempre |
| `qty` (Cantidad) | 16 mm | Siempre |
| `progress` (Avance) | 19 mm | Facturas de situación |
| `unit` (Unidad) | 11 mm | `PRODUCT_USE_UNITS` |
| `discount` (Descuento) | 13 mm | Alguna línea con descuento |
| `totalexcltax` (Total sin IVA) | 26 mm | Salvo `PDF_PROPAL_HIDE_PRICE_EXCL_TAX` |
| `totalincltax` (Total con IVA) | 26 mm | `PDF_PROPAL_SHOW_PRICE_INCL_TAX` |
| Campos adicionales de línea | Según definición | Según `defineColumnExtrafield()` |

  Las dos últimas dependen de constantes con prefijo `PDF_PROPAL_`, tal como están en el código. Los hooks `printPDFline`, `defineColumnField` y `printUnderHeaderPDFline` se ejecutan como en las plantillas del núcleo; `MAIN_PDF_DASH_BETWEEN_LINES`, `MAIN_PDF_DATA_ON_FIRST_PAGE`, `MAIN_PDF_DONOTREPEAT_HEAD` y `MAIN_PDF_DISABLE_COL_HEAD_TITLE` se respetan.

- Bloque de información (`drawInfoTable()`): condiciones y modo de pago, enlace de pago en línea (`PDF_SHOW_LINK_TO_ONLINE_PAYMENT` con PayPal, Stripe o Paybox), datos para cheque (`FACTURE_CHQ_NUMBER`, `MAIN_PDF_HIDE_CHQ_ADDRESS`) y cuenta bancaria (`FACTURE_RIB_NUMBER` o la cuenta de la factura). Si el modo de pago exige datos que no están configurados, imprime el error en rojo, como hace el núcleo.
- Bloque de totales (`drawTotalTable()`): total sin IVA, descuentos (`MAIN_SHOW_AMOUNT_DISCOUNT`, `MAIN_SHOW_AMOUNT_BEFORE_DISCOUNT`), impuestos locales antes y después del IVA, desglose de IVA por tipo (`PDF_VAT_LABEL_IS_CODE_OR_RATE`), corrección de situaciones (`INVOICE_USE_SITUATION`), timbre, total con IVA, retención de garantía, pagado, abonos aplicados y resto a pagar (`INVOICE_NO_PAYMENT_DETAILS`, `INVOICE_POSITIVE_CREDIT_NOTE`). Con `MAIN_GENERATE_DOCUMENTS_WITHOUT_VAT` o `_IFNULL` se omite el desglose.
- Tabla de pagos ya realizados (`drawPaymentsTable()`) cuando hay pagos, abonos o anticipos aplicados.
- Pie (`_pagefoot()`) con `pdf_pagefoot()` del núcleo: texto libre `INVOICE_FREE_TEXT`, detalles de empresa (`MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS`), numeración de páginas y marca de agua `FACTURE_DRAFT_WATERMARK` en borradores.
- Otras opciones del núcleo respetadas: `MAIN_ADD_PDF_BACKGROUND` (PDF de fondo), `MAIN_USE_FPDF`, `MAIN_DISABLE_PDF_COMPRESSION`, `MAIN_PDF_FREETEXT_HEIGHT`, `MAIN_PDF_TITLE_BACKGROUND_COLOR`, `MAIN_UMASK`.

### Firma digital del PDF

`write_file()` busca un fichero `certificado.pem` en la misma carpeta que la plantilla (`__DIR__ . '/certificado.pem'`) y, si existe, lo usa como certificado y clave privada para firmar el PDF con `setSignature()` (motivo «Certificado de calidad»). El módulo no distribuye ese fichero, así que por defecto el PDF no se firma. Este certificado no tiene relación con el certificado con el que el módulo se comunica con la AEAT ([[Certificados digitales|Certificados-digitales]]).

## Lo que aporta VeriFactu a cualquier plantilla

### Título de factura simplificada (`beforePDFCreation`)

Antes de generar el PDF, `beforePDFCreation()` carga los campos adicionales de la factura y, si `verifactu_factura_tipo` vale `F2` (`VerifactuInvoice::TYPE_SIMPLIFIED`), sustituye la traducción `PdfInvoiceTitle` en el objeto global `$langs` por la de la clave `verifactu_FACTURA_Simplificada` («Factura Simplificada»; hasta la 2.2.1 la clave no estaba definida y el título mostraba la clave). Cualquier plantilla que titule la factura con `PdfInvoiceTitle` recoge el cambio. Como la sustitución se hace sobre `$langs`, solo surte efecto cuando el PDF se genera con ese mismo objeto de traducción (es decir, en el idioma de la sesión); si Dolibarr crea otro objeto para el idioma del tercero (`MAIN_MULTILANGS`), la plantilla sigue usando su título habitual. Véase [[Tipos de factura|Tipos-de-factura]].

El método incorpora desde la versión 2.0.0 una guarda al principio:

```php
if (!is_object($object) || !method_exists($object, 'fetch_optionals')) {
    return 0;
}
```

El hook `beforePDFCreation` no lo disparan solo los modelos de factura: los informes de pagos (`pdf_paiement`, `pdf_paiement_fourn`, menú Facturas > Informes) también lo lanzan, y lo que pasan como `$object` no es una factura (en Dolibarr 22 es el propio modelo PDF, que no tiene `fetch_optionals()`; en Dolibarr 17 es `null`). Sin la guarda, generar el informe de pagos terminaba con `Call to undefined method pdf_paiement_fourn::fetch_optionals()` (issue #31). La corrección también deja de leer el campo adicional cuando no existe, lo que eliminaba un aviso de PHP 8. `tests/IssueFixesTest.php` reproduce ambos casos.

### Destinatario en facturas simplificadas (`pdf_build_address`)

Cuando el núcleo construye el bloque de dirección del destinatario (`pdf_build_address()` con `mode = 'target'`), el hook `pdf_build_address()` del módulo comprueba el tipo VeriFactu de la factura; si es `F2`, devuelve como bloque de dirección la traducción de la clave `verifactu_SIMPLIFIED_INVOICE_CUSTOMER` («Cliente de factura simplificada», definida desde la 2.2.2) y detiene el tratamiento estándar (`return 1`). El nombre del destinatario se imprime igualmente, porque lo genera `pdfBuildThirdpartyName()`, que no pasa por este hook. Se aplica a todas las plantillas que usen `pdf_build_address()`.

### Inserción del QR con TCPDI (`afterPDFCreation`)

Al terminar cualquier generación de PDF en el contexto `pdfgeneration`, `afterPDFCreation()` comprueba que `$parameters['object']` es una `Facture` con `id > 0` y llama a `addQRToPDFWithTCPDI()`. Este método abandona sin hacer nada, dejando constancia en el registro, cuando:

| Condición | Mensaje en el registro |
|---|---|
| La factura no consta como enviada (`isFactureSendToVerifactu()` devuelve `false`) | (ninguno; salida silenciosa) |
| El fichero PDF no existe | `Verifactu TCPDI: PDF file not found: ...` |
| La clase `TCPDI` no está disponible (por ejemplo con `MAIN_USE_FPDF` o `MAIN_DISABLE_TCPDI`) | `Verifactu TCPDI: TCPDI class not found` |
| La URL del QR está vacía | `Verifactu TCPDI: QR URL is empty` |
| Cualquier excepción (NIF inválido, error de TCPDF, etc.) | `Verifactu TCPDI Error: ...` o `Verifactu afterPDFCreation QR Error: ...` |

Si todo está en orden:

1. Lee la configuración: `VERIFACTU_QR_SIZE` (35 mm si está vacía), `VERIFACTU_QR_POSITION_X` (`center` si no existe), `VERIFACTU_QR_POSITION_Y`, `VERIFACTU_QR_SHOW_TEXT` y `VERIFACTU_QR_TEXT_SIZE` (8 pt si está vacía).
2. Obtiene la URL con `getQRUrl($object)` ([[Código QR|Codigo-QR]]).
3. Crea una instancia con `pdf_getInstance()`, desactiva cabecera y pie automáticos de TCPDF y abre el PDF recién generado como fuente (`setSourceFile()`).
4. Recorre todas las páginas: importa cada una como plantilla, añade una página nueva del mismo tamaño y orientación y la calca con `useTemplate()`.
5. Dibuja el QR en la primera página o, si `VERIFACTU_QR_ALL_PAGES` está activa, en todas.
6. Sobrescribe el fichero original con `Output($file, 'F')`.

El PDF final es, por tanto, una copia página a página del original con el QR superpuesto.

#### Posición y márgenes

`addQRToTCPDIPage()` calcula la posición así (todas las medidas en milímetros):

- **X** (`calculateQRPositionX()`): si `VERIFACTU_QR_POSITION_X` es numérica se usa tal cual; `left` devuelve 10; `right` devuelve `ancho de página − 10 − tamaño`; `custom` devuelve `VERIFACTU_QR_POSITION_X_CUSTOM` (50 si está vacía); `center` o cualquier otro valor devuelve `(ancho − tamaño) / 2`.
- **Y**: el valor de `VERIFACTU_QR_POSITION_Y` es la ordenada del texto superior. El QR empieza 5 mm más abajo (`4` de altura reservada al texto «QR tributario:» más `1` de separación).
- **Margen de seguridad**: 2 mm alrededor del QR (mínimo que citan los comentarios del código). Si `x − 2 < 0`, `x + tamaño + 2 > ancho`, `y − 2 < 0` o `y + tamaño + 2 > alto`, el QR de esa página se omite con el aviso `Verifactu TCPDI: QR position out of page bounds with required margins`.

Con los valores que crea el módulo al activarse (`custom`, X personalizada 70, Y 2, tamaño 30) el QR queda en la parte superior de la página, entre 70 y 100 mm del borde izquierdo y a 7 mm del borde superior; el texto de ayuda de `VERIFACTU_QR_POSITION_Y` indica que la especificación lo sitúa próximo al margen superior.

#### Textos y estilo

Si `VERIFACTU_QR_SHOW_TEXT` está activa:

- `QR tributario:` en negrita, tamaño `VERIFACTU_QR_TEXT_SIZE`, centrado sobre el ancho del QR, en la ordenada configurada.
- `VeriFactu` en negrita, un punto menor, centrado, 1 mm por debajo del QR.

El QR se dibuja con `write2DBarcode($qrUrl, 'QRCODE,M', $x, $y, $size, $size, $style, 'N')` y el estilo `border 0`, `vpadding 0`, `hpadding 0`, primer plano negro, fondo blanco y módulos de 1 × 1. El nivel de corrección M es el mismo que usa la biblioteca de QR del módulo.

## Regeneración del PDF tras el envío

El módulo **no** llama nunca a `generateDocument()`; la (re)generación del PDF la hace Dolibarr:

- Al validar desde la ficha, `compta/facture/card.php` regenera el PDF con la plantilla de la factura salvo que esté activa `MAIN_DISABLE_PDF_AUTOUPDATE`. Como el envío a la AEAT se produce dentro del trigger `BILL_VALIDATE`, es decir, antes de que se genere ese PDF, el documento sale ya con el QR cuando el envío directo está activo (`VERIFACTU_DIRECT_CALL_ON_VALIDATE`, siempre en TakePOS). Véase [[Flujo de validación y envío|Flujo-de-validacion-y-envio]].
- Si la factura se envía más tarde (envío directo desactivado, factura pendiente por falta de conexión, reintento del trabajo programado o envío manual desde la pestaña o el listado), el PDF existente no tiene QR. Hay que regenerarlo desde el recuadro de documentos de la ficha. Véase [[Reintentos y envíos pendientes|Reintentos-y-envios-pendientes]].
- Tras una anulación en la AEAT el estado deja de ser «Enviada», por lo que una regeneración posterior produce un PDF sin QR; el PDF que ya existía no se modifica. Véase [[Anulación de facturas|Anulacion-de-facturas]].

> **Importante:** las facturas enviadas no pueden modificarse ni eliminarse (hook `dolGetButtonAction()` y trigger de restricciones), pero sí regenerar su PDF con otra plantilla. Cada regeneración vuelve a pasar por `afterPDFCreation`, así que el QR se añade siempre que el estado siga siendo «Enviada». Véase [[Restricciones sobre facturas enviadas|Restricciones-sobre-facturas-enviadas]].

## Personalización

- **Usar otra plantilla.** No es necesario usar `verifactu`: `crabe`, `sponge` o una plantilla propia reciben el QR por el mismo hook, siempre que generen el PDF con TCPDI (el comportamiento por defecto de `pdf_getInstance()`).
- **Ajustar el QR.** Tamaño, posición, textos y páginas se controlan solo con las constantes `VERIFACTU_QR_*` descritas en [[Código QR|Codigo-QR]] y [[Configuración|Configuracion]]. Si el QR se solapa con el logotipo o el recuadro de dirección, cambia `VERIFACTU_QR_POSITION_X` o `VERIFACTU_QR_POSITION_X_CUSTOM`.
- **Modificar la plantilla.** Si necesitas cambios en el diseño, copia `pdf_verifactu.modules.php` con otro nombre y otra clase (`pdf_<nombre>`) dentro de un módulo que declare `'models' => 1`, para no perder los cambios al actualizar. La plantilla usa el sistema de columnas de `CommonDocGenerator`, por lo que las columnas pueden añadirse o quitarse en `defineColumnField()` o desde el hook `defineColumnField`.
- **Plantillas ODT.** Para documentos ODT el QR se inserta con las etiquetas `verifactuqrcode30_logo`, `verifactuqrcode35_logo` y `verifactuqrcode40_logo`; véase [[Sustituciones ODT y plantillas|Sustituciones-ODT-y-plantillas]].

## Errores conocidos y su estado

| Síntoma | Causa | Estado |
|---|---|---|
| `Call to undefined method pdf_paiement_fourn::fetch_optionals()` al generar el informe de pagos | `beforePDFCreation()` daba por hecho que recibía una factura | Corregido en 2.0.0 (issue #31), con prueba de regresión |
| El PDF se genera sin QR y el registro muestra `TCPDI class not found` | Dolibarr configurado con `MAIN_USE_FPDF` o `MAIN_DISABLE_TCPDI` | Comportamiento esperado: sin TCPDI no se puede reabrir el PDF |
| El PDF se genera sin QR y el registro muestra `QR position out of page bounds` | La combinación de tamaño y posición no cabe en la página con 2 mm de margen | Ajusta `VERIFACTU_QR_SIZE`, `VERIFACTU_QR_POSITION_X` o `VERIFACTU_QR_POSITION_Y` |
| El PDF se generó antes del envío | Envío diferido o manual | Regenera el PDF |

## Véase también

- [[Código QR|Codigo-QR]]
- [[Configuración|Configuracion]]
- [[Sustituciones ODT y plantillas|Sustituciones-ODT-y-plantillas]]
- [[Tipos de factura|Tipos-de-factura]]
- [[Flujo de validación y envío|Flujo-de-validacion-y-envio]]
- [[Reintentos y envíos pendientes|Reintentos-y-envios-pendientes]]
- [[Restricciones sobre facturas enviadas|Restricciones-sobre-facturas-enviadas]]
- [[Anulación de facturas|Anulacion-de-facturas]]
- [[Historial de versiones|Historial-de-versiones]]
- [[Resolución de problemas|Resolucion-de-problemas]]
