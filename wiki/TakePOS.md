# TakePOS

Las ventas hechas desde el terminal punto de venta generan facturas que también deben remitirse a la AEAT. El módulo trata las facturas cuyo `module_source` es `takepos` como facturas simplificadas (F2) y sus abonos como rectificativas simplificadas (R5), las envía siempre en el momento de la validación y sustituye el ticket de TakePOS por uno propio que incluye el código QR de cotejo. Además mantiene compatibilidad con el módulo POS externo (`pos/`), cuyo ticket redirige a una vista del módulo. Esta página recoge ambos casos, los requisitos de configuración y las limitaciones.

## Dos integraciones distintas

| Terminal | Cómo lo detecta el módulo | Qué hace |
|---|---|---|
| TakePOS (módulo del núcleo de Dolibarr) | `$object->module_source == 'takepos'` en los triggers; hook `TakeposReceipt` en el contexto `takeposfrontend` | Tipo F2/R5, envío inmediato al validar, ticket propio con QR |
| Módulo POS externo (`pos/`, con clases `Cash`, `Place`, `Facturesim`) | Hook `updateSession` detecta la URL `pos/frontend/tpl/facture.tpl.php`; el trigger contempla `$object instanceof Facturesim` | Redirección del ticket a `views/pos.facture.php` con QR |

El resto de esta página trata TakePOS salvo en la sección dedicada al POS externo.

## Tipo de factura: F2 y R5

El trigger `billCreate()` (`core/triggers/interface_999_modVerifactu_VerifactuTriggers.class.php`) fija el campo adicional `verifactu_factura_tipo` en el momento de crear la factura. La condición es:

```php
if ($object->module_source == 'takepos' || $object instanceof Facturesim
    || $object->thirdparty->array_options['options_verifactu_factura_simplificada'] == 1) {
    if ($object->type == $object::TYPE_CREDIT_NOTE) {
        // abono de TakePOS -> R5
        $object->array_options['options_verifactu_factura_tipo'] = VerifactuInvoice::TYPE_CREDIT_NOTE_SIMPLIFIED;
    } else {
        // venta de TakePOS -> F2
        $object->array_options['options_verifactu_factura_tipo'] = VerifactuInvoice::TYPE_SIMPLIFIED;
    }
}
```

- Toda factura creada por TakePOS es **F2** («Factura simplificada y facturas sin identificación del destinatario, art. 6.1.d RD 1619/2012»), con independencia del importe y del cliente seleccionado en el terminal.
- Todo abono creado por TakePOS (botón de devolución, que crea una `Facture` de tipo abono con `module_source = 'takepos'` y `fk_facture_source` apuntando al ticket original) es **R5** («Rectificativa en facturas simplificadas»).
- El mismo tratamiento reciben las facturas de terceros marcados con el campo adicional «Cliente de factura simplificada» (`verifactu_factura_simplificada`), aunque no vengan del TPV.

En el envío (`handleInvoiceCreationOrSubsanation()` en `lib/functions/functions.submission.php`), los abonos con `module_source == 'takepos'` se transmiten como `TYPE_CREDIT_NOTE_SIMPLIFIED` con tipo de rectificación por diferencias (`RECT_DIFFERENCES`), y para los tipos F2 y R5 no se añade el bloque de destinatario. Los demás detalles de las rectificativas están en [[Facturas rectificativas|Facturas-rectificativas]] y los tipos en [[Tipos de factura|Tipos-de-factura]].

Los valores fiscales por defecto (impuesto `01`, clave de régimen `01`, calificación `S1`) y las constantes `VERIFACTU_DEFAULT_*` se aplican igual que a cualquier otra factura; véase [[Desglose de impuestos|Desglose-de-impuestos]].

## Validación y envío inmediato

TakePOS valida la factura al cobrarla (`takepos/invoice.php?action=valid`). El trigger `billValidate()` del módulo aplica entonces estas reglas particulares:

1. **Envío directo siempre.** La condición es `module_source == 'takepos' || (module_source != 'takepos' && VERIFACTU_DIRECT_CALL_ON_VALIDATE)`: una factura de TakePOS se envía a la AEAT dentro de la propia validación aunque el envío directo esté desactivado para el resto de facturas. Véase [[Flujo de validación y envío|Flujo-de-validacion-y-envio]].
2. **Sin comprobaciones del tercero.** Las tres comprobaciones previas (NIF obligatorio para terceros españoles, NIF-IVA para terceros de otros países, dirección obligatoria) llevan la condición `$object->module_source != 'takepos'`, así que el cliente genérico del terminal puede carecer de NIF y dirección. El hook `dolGetButtonAction()` aplica la misma excepción al botón «Validar» de la ficha.
3. **Comprobaciones de la referencia.** Como para cualquier factura, la referencia definitiva no puede contener espacios, debe ser alfanumérica (se admiten `-`, `_` y `/`) y no superar 60 caracteres; si falla, la validación se detiene con los mensajes `VERIFACTU_INVOICE_REF_*`. Véase [[Fechas y numeración|Fechas-y-numeracion]].
4. **Sin conexión a Internet.** Si `checkInternetConnection()` devuelve `false`, la factura se valida igualmente y queda con el estado «Sin Conexión», `verifactu_error = NO_INTERNET_CONNECTION` e `Incidencia = S`; el ticket se imprime sin QR y el registro se remite después desde la cola de reintentos. Véase [[Reintentos y envíos pendientes|Reintentos-y-envios-pendientes]].
5. **Rechazo de la AEAT.** Si `execVERIFACTUCall()` devuelve `false`, el trigger revierte la factura a borrador mediante una conexión independiente a la base de datos (`fk_statut = 0`, `date_valid = NULL`, referencia `(PROV<id>)`) y muestra `VERIFACTU_ERROR_INVOICE_NOT_VALIDATED`. El tiempo de espera que pueda pedir la AEAT (`TiempoEsperaEnvio`) no se aplica en la validación, porque abortar aquí desharía una factura ya cobrada.
6. **Pendientes previos.** Antes de enviar, si hay conexión, el trigger llama a `VerifactuUtils::sendInvoicesWithErrors()` para remitir las facturas que quedaron pendientes por errores de conexión.

Las proformas quedan fuera de VeriFactu (`isVerifactuApplicableInvoice()`), pero TakePOS no genera proformas.

> **Importante:** el envío es síncrono. Entre que el cajero pulsa el modo de pago y aparece el ticket, el módulo firma el registro, lo encadena y espera la respuesta de la AEAT. Para que el cajero sepa qué ocurre, `js/verifactu.js.php` muestra una capa de espera (véase más abajo).

## El ticket de TakePOS (hook `TakeposReceipt`)

`takepos/receipt.php` del núcleo inicializa el contexto `takeposfrontend`, ejecuta el hook `TakeposReceipt` y, si algún módulo devuelve contenido, lo imprime y termina. `ActionsVerifactu::TakeposReceipt()` (`class/actions_verifactu.class.php`) devuelve **siempre** un ticket completo, con lo que el ticket del núcleo queda reemplazado en cuanto el módulo está activo, aunque la factura no esté enviada.

### Bloque del QR

Si la factura consta como enviada (`isFactureSendToVerifactu()`), el ticket empieza con:

```html
<div class="verifactu-qr-header center">
  <div class="verifactu-qr-text">QR tributario:</div>   <!-- solo si VERIFACTU_QR_SHOW_TEXT_TPV -->
  <img src="data:image/png;base64,..." class="verifactu-qr-code" style="width:120px; height:120px;" />
  <div class="verifactu-qr-text">VeriFactu</div>        <!-- solo si VERIFACTU_QR_SHOW_TEXT_TPV -->
</div>
```

El bloque lleva borde discontinuo y fondo gris claro (`.verifactu-qr-header {margin: 10px 0; padding: 8px; border: 1px dashed #ccc; background: #f9f9f9;}`) y los textos van en negrita a 0,7 em. La imagen se pide con `getQrImage($object, 120)`. Cualquier excepción al generar el QR se silencia para no impedir la impresión. Véase [[Código QR|Codigo-QR]].

### Contenido del ticket

El resto reproduce el ticket estándar de TakePOS y respeta sus constantes:

| Elemento | Constantes / comportamiento |
|---|---|
| Nombre de la empresa | `$mysoc->name`, en grande y negrita |
| Cabecera libre | `TAKEPOS_HEADER` y `TAKEPOS_HEADER<terminal>`, con sustituciones (`getCommonSubstitutionArray()` + `complete_substitutions_array()`) |
| Fecha, nombre del ticket y referencia | `TAKEPOS_RECEIPT_NAME`; en borradores la referencia `(PROV-POS<terminal>-<lugar>)` se muestra como «Terminal <n> Lugar <m>» |
| Cliente | `TAKEPOS_SHOW_CUSTOMER`, salvo que sea el cliente genérico `CASHDESK_ID_THIRDPARTY<terminal>` |
| Fecha de impresión | `TAKEPOS_SHOW_DATE_OF_PRINING` |
| Líneas | Etiqueta, cantidad, precio y total con IVA; columna sin IVA con `TAKEPOS_SHOW_HT_RECEIPT`; modo `action=without_details` agrupa todo en una línea; `gift=1` oculta los importes |
| Totales | Sin IVA, IVA (agrupado por tipo con `TAKEPOS_TICKET_VAT_GROUPPED`), impuestos locales 1 y 2 si procede, total con IVA; conversión a la moneda del cliente si `takeposcustomercurrency` difiere |
| Pagos | `TAKEPOS_PRINT_PAYMENT_METHOD`: lista los pagos (`llx_paiement_facture`, `llx_paiement`, `llx_c_paiement`) ordenados por fecha; en un pago en efectivo (`LIQ`) con cambio, muestra el importe entregado (pago + `pos_change`) y una línea «Cambio» con la vuelta |
| Pie libre | `TAKEPOS_FOOTER<terminal>` y `TAKEPOS_FOOTER`, con sustituciones |
| Impresión automática | `window.print()` salvo en el ticket de muestra (sin `id`) |

### Cobros y cambio

El cobro lo registra TakePOS; el módulo solo lo refleja en el ticket. La consulta de pagos selecciona `p.pos_change`, `p.datep`, `pf.amount`, `pf.multicurrency_amount` y el código del modo de pago, y ajusta los importes cuando la factura está en otra moneda (`multicurrency_tx != 1`).

## Capa de espera al cobrar (`js/verifactu.js.php`)

El fichero se carga en todas las páginas (`module_parts['js']`). En la ventana de cobro de TakePOS (`takepos/pay.php`), que define la función JavaScript `Validate(payment)` para los botones de modo de pago, el script:

1. Sondea cada 100 ms, durante 5 segundos, hasta encontrar `window.Validate`.
2. La envuelve: al pulsar un modo de pago muestra una capa a pantalla completa (fondo oscuro con desenfoque, icono, animación giratoria y los textos `Sending to Verifactu...` y `Please wait while your invoice is being processed`, escritos en inglés en el propio script) y a continuación ejecuta la función original.
3. Si la función original lanza un error, oculta la capa a los 2 segundos y relanza el error.

La capa vive en la ventana emergente de cobro, así que desaparece cuando TakePOS la cierra tras registrar el pago. Los mensajes `[Verifactu] Función Validate() detectada, interceptando...` en la consola del navegador confirman que la intercepción está activa. Véase [[Panel principal|Panel-principal]] para el resto de funciones de este fichero.

## Módulo POS externo (`views/pos.facture.php`)

Para instalaciones con el módulo POS externo (el que usa las tablas `llx_pos_facture`, las clases `Cash` y `Place` y, opcionalmente, los módulos `rewards` y `discounts`), el hook `updateSession()` (contexto `main`, ejecutado por `main.inc.php` en cada petición) detecta la URL del ticket de ese módulo:

```php
if (strpos($_SERVER['REQUEST_URI'], 'pos/frontend/tpl/facture.tpl.php') !== false) {
    header("Location: " . dol_buildpath('/verifactu/views/pos.facture.php', 1) . '?' . http_build_query($_GET));
    exit();
}
```

`views/pos.facture.php` carga `pos/class/cash.class.php`, `pos/class/place.class.php` y `pos/backend/lib/pos.lib.php`, lee la factura por `id` y pinta un ticket propio:

- QR en la esquina superior derecha (`.qr-verifactu-container` con `position: absolute; top: 10px; right: 10px`), con `getQrImage($object, 100)` y los textos «QR tributario:» y «VeriFactu» si `VERIFACTU_QR_SHOW_TEXT_TPV` está activa, solo cuando la factura consta como enviada.
- Logotipo (`POS_TICKET_LOGO`), datos de la empresa, vendedor (usuario que validó), cliente, lugar (`llx_pos_facture.fk_place`).
- Líneas con precio unitario (con IVA si `POS_TICKET_TTC`), referencia si `POS_PRINT_MODE == 1`, promociones del módulo `discounts`, desglose de IVA por tipo, impuestos locales.
- Pagos por modo (`Cash::select_Paymentname()`), descuentos aplicados, puntos del módulo `rewards`, cambio a devolver (`CustomerRet`) o pendiente (`CustomerDeb`) a partir de `customer_pay`.
- Mensaje predefinido `POS_PREDEF_MSG`, referencia y fecha de creación al pie; impresión automática al cargar y cierre de la ventana si `POS_CLOSE_WIN`.

Esta vista solo funciona con ese módulo instalado: sin sus clases, los `dol_include_once()` fallan. En una instalación que use únicamente TakePOS la redirección nunca se activa.

## Otros ajustes que fuerza el módulo

`updateSession()` también fija en cada petición `SOCIETE_IDPROF1_INVOICE_MANDATORY = false` para que Dolibarr no exija NIF al facturar (el trigger ya hace sus propias comprobaciones), e impide borrar desde las páginas de administración los campos adicionales cuyo nombre empieza por `verifactu_`. Véase [[Campos adicionales|Campos-adicionales]].

## Requisitos de configuración del TPV

- Módulo TakePOS activo y terminal configurado, con su cliente genérico (`CASHDESK_ID_THIRDPARTY<terminal>`) y las cuentas bancarias por modo de pago que exige el núcleo (`CASHDESK_ID_BANKACCOUNT_CASH<terminal>`, `CASHDESK_ID_BANKACCOUNT_CHEQUE<terminal>`, etc.); sin cuenta, TakePOS rechaza el cobro antes de llegar al módulo.
- Ajustes básicos de VeriFactu completos: razón social, NIF válido (el QR falla con un NIF incorrecto) y certificado. Véase [[Configuración|Configuracion]] y [[Certificados digitales|Certificados-digitales]].
- Conexión con la AEAT operativa en el momento de la venta; en caso contrario las facturas quedan «Sin Conexión» y se reenvían después (trabajo programado `sendInvoicesWithErrors`, cada 2 horas, o al validar la siguiente factura).
- `VERIFACTU_QR_SHOW_TEXT_TPV` activa (valor por defecto) para que el ticket lleve los textos obligatorios junto al QR.
- El módulo debe estar activo antes de crear los tickets: el tipo F2 y el estado inicial se fijan en `billCreate()`, es decir, al crear la factura, no al validarla.

## Limitaciones

- **Todo es F2.** No se comprueba ningún límite de importe ni se tiene en cuenta el cliente elegido en el terminal: una venta a una empresa identificada hecha desde TakePOS se transmite como factura simplificada sin destinatario. Si necesitas una factura completa, emítela desde el módulo de facturas.
- **Referencia tras un rechazo.** Cuando la AEAT rechaza el registro, la factura vuelve a borrador con la referencia `(PROV<id>)`, no con la referencia `(PROV-POS<terminal>-<lugar>)` que TakePOS usa para localizar el ticket abierto de cada lugar.
- **El ticket se reemplaza por completo.** Personalizaciones aplicadas al ticket del núcleo no se conservan; si otro módulo también implementa `TakeposReceipt`, el núcleo imprime la salida de ambos, una tras otra.
- **Imagen incrustada.** El QR va en el HTML como URI de datos en base64; el propio `receipt.php` del núcleo advierte de que ese fichero se envía a la aplicación de impresión de TakePOS y debe mantenerse básico.
- **Tamaños fijos.** El QR del ticket mide 120 px (TakePOS) o 100 px (POS externo) y no depende de `VERIFACTU_QR_SIZE`. `views/pos.facture.php` lee `VERIFACTU_QR_TEXT_SIZE` pero no la aplica.
- **Textos de la capa de espera** en inglés y sin traducción.
- **Sin QR en tickets sin envío.** Con estado «Sin Conexión» o tras un rechazo, el ticket impreso en ese momento no lleva QR; una vez enviada la factura, cualquier impresión posterior del ticket (`takepos/receipt.php?facid=<id>`) sí lo incluye, porque el hook consulta el estado en cada llamada.

## Véase también

- [[Código QR|Codigo-QR]]
- [[Tipos de factura|Tipos-de-factura]]
- [[Facturas rectificativas|Facturas-rectificativas]]
- [[Flujo de validación y envío|Flujo-de-validacion-y-envio]]
- [[Reintentos y envíos pendientes|Reintentos-y-envios-pendientes]]
- [[Fechas y numeración|Fechas-y-numeracion]]
- [[Configuración|Configuracion]]
- [[Campos adicionales|Campos-adicionales]]
- [[Panel principal|Panel-principal]]
- [[Resolución de problemas|Resolucion-de-problemas]]
