# Pestaña VeriFactu de la factura

Cada factura de cliente tiene una pestaña «VeriFactu» (`views/tabVERIFACTU.facture.php`) que concentra todo lo que el módulo sabe de ella: el estado del envío, la huella, el CSV, el tipo de factura, los parámetros fiscales, la última respuesta de la AEAT y el entorno usado. Desde la misma pestaña se lanzan las operaciones manuales (alta, modificación, baja y verificación de estado) y se consulta en vivo lo que la AEAT tiene registrado, junto al registro que se le envió. Esta página describe bloque por bloque qué se muestra, qué botones hay, en qué condiciones se activan y qué mensajes pueden aparecer.

## Acceso y permisos

La pestaña se declara en `core/modules/modVerifactu.class.php`:

```php
$this->tabs[] = array('data' => 'invoice:+factureVERIFACTU_TAB:SUBSTITUTION_FACTUREVERIFACTUTAB:verifactu@verifactu:($user->rights->verifactu->manage || $user->admin):/verifactu/views/tabVERIFACTU.facture.php?id=__ID__');
```

El título «VeriFactu» sale de la clave `FACTURE_VERIFACTU_TAB` a través de la función de sustitución `verifactu_completesubstitutionarray()` (`core/substitutions/functions_verifactu.lib.php`). La pestaña solo se muestra a usuarios con el permiso «Gestionar módulo VeriFactu» (`verifactu->manage`) o administradores; véase [[Permisos y menús|Permisos-y-menus]].

La propia página vuelve a comprobarlo y además:

- deniega el acceso con el mensaje `SOAP_EXTENSION_NOT_INSTALLED` si PHP no tiene la extensión SOAP;
- aplica `restrictedArea($user, 'facture', $id)`, de modo que rigen también los permisos de facturas y la restricción por tercero de los usuarios externos;
- acepta la factura por `id` (o `facid`, por compatibilidad) o por `ref`, y la carga con el indicador `INVOICE_USE_SITUATION` para que las facturas de situación se resuelvan correctamente.

## Estructura de la página

1. **Cabecera de la factura** (`dol_banner_tab`): referencia, referencia de cliente, tercero, proyecto si el módulo está activo, y una tabla con los campos adicionales VeriFactu.
2. **Bloque «Acciones VeriFactu»** (`VERIFACTU_ACTIONS_SECTION_TITLE`): botones de envío y verificación, o el motivo por el que no se puede enviar.
3. **Bloque «Datos en la AEAT»** (`VERIFACTU_AEAT_DATA_SECTION_TITLE`): resultado de consultar la factura en la AEAT y el registro enviado.

## Campos mostrados en la cabecera

La pestaña toma la lista de campos adicionales de la factura, descarta los que no contienen `verifactu_` en su clave y los dos separadores (`separador` en la clave), e incluye la plantilla estándar `core/tpl/extrafields_view.tpl.php` de Dolibarr. La plantilla respeta la visibilidad declarada para cada campo, por lo que se ven todos salvo `verifactu_payload` (visibilidad 0). Los detalles de definición de cada campo están en [[Campos adicionales|Campos-adicionales]].

| Campo | Etiqueta | Qué muestra | Editable en la ficha |
|---|---|---|---|
| `verifactu_estado` | Estado | Badge de color con el estado VeriFactu (No Enviada, Enviada, Parcial, Error, Pendiente, Sin Conexión, Enviada (Duplicada), Duplicada (Importes diferentes), anulada). Es un campo `varchar`, así que el HTML del badge se pinta tal cual | No |
| `verifactu_error` | Error | Último error: texto de la AEAT, excepción o marcador interno (`NO_INTERNET_CONNECTION`, `SERVICE_UNAVAILABLE`, `PENDING_SUBMISSION`) | No |
| `verifactu_csv_factura` | Código CSV | CSV devuelto por la AEAT en el último envío aceptado (alta, modificación o baja) | No |
| `verifactu_id_factura` | ID en VeriFactu | JSON con `IDEmisorFactura`, `NumSerieFactura` y `FechaExpedicionFactura` tal como los devolvió la AEAT | No |
| `verifactu_fecha_factura` | Fecha de emisión | Fecha de expedición confirmada por la AEAT | No |
| `verifactu_ultima_salida` | Última respuesta | Resumen `Status: ... \| CSV: ... \| Sent: ... \| Record: ...` en las altas; JSON completo en las bajas; textos de verificación en la comprobación previa y en «Verificar Estado» | No |
| `verifactu_ultimafecha_modificacion` | Última modificación | Fecha y hora de la última escritura del módulo | No |
| `verifactu_huella` | Huella digital | SHA-256 del registro de alta (64 caracteres hexadecimales en mayúsculas) | No |
| `verifactu_fecha_hora_generacion` | Fecha/hora de huella | `FechaHoraHusoGenRegistro` en ISO 8601 con zona horaria | No |
| `verifactu_entorno` | Entorno (pruebas/producción) | `test` o `production` según el entorno del envío | No |
| `verifactu_modo` | Modo de operación | Badge con `VERIFACTU_MODE` (`verifactu`) | No |
| `verifactu_factura_tipo` | Tipo de factura | F1, F2, F3 o R1 a R5; véase [[Tipos de factura|Tipos-de-factura]] | Sí (icono de lápiz) |
| `verifactu_impuesto` | Impuesto | 01 IVA, 02 IPSI, 03 IGIC, 05 Otros | Sí |
| `verifactu_clave_regimen` | Clave Régimen | Clave L8 | Sí |
| `verifactu_calificacion_operacion` | Calificación Operación | S1, S2, N1, N2 o automático | Sí |
| `verifactu_operacion_exenta` | Operación Exenta | E1 a E6 o automático | Sí |
| `verifactu_incidencia` | Incidencia | N o S | Sí |

Los cinco campos fiscales y el tipo se declaran con visibilidad 4 (3 en Dolibarr anterior a la 12) y como siempre editables, por lo que la plantilla ofrece el lápiz de edición aunque la factura esté validada. Cambiarlos después de un envío aceptado no provoca ningún reenvío: el registro ya transmitido no cambia hasta que se pida una modificación. Véase [[Desglose de impuestos|Desglose-de-impuestos]].

## Bloque «Acciones VeriFactu»

### Cómo se evalúa el estado

Antes de pintar los botones, la pestaña calcula a partir de `verifactu_estado` y `verifactu_csv_factura`:

| Variable | Condición |
|---|---|
| `$isAlreadySentCorrectly` | Hay CSV y el estado contiene `VERIFACTU_STATUS_SEND`, `badge-status4` o `Correcto` |
| `$hasError` | El estado contiene `VERIFACTU_STATUS_DUPLICATE_DIFF`, `badge-status1` o `badge-status8` |
| `$canSend` | La factura está validada (`statut > 0`), su referencia no contiene `PROV` y el estado no contiene `Anulada` ni `VERIFACTU_STATUS_ANULADA` |
| `$canSendAlta`, `$canSendMod` | Falsos si `$isAlreadySentCorrectly` |
| `$canSendBaja` | Siempre verdadero mientras `$canSend` lo sea |

### Botones

| Botón | Valor `EXECUTEVERIFACTU` | Se muestra activo cuando | Qué hace |
|---|---|---|---|
| Enviar Alta | `Alta` | `$canSend` y no está enviada correctamente | Comprobación previa en la AEAT y envío del `RegistroAlta`; véase [[Flujo de validación y envío|Flujo-de-validacion-y-envio]] |
| Enviar Modificación | `Mod` | `$canSend` y no está enviada correctamente | Envía un `RegistroAlta` de la misma factura con `Subsanacion` = `S` (`Invoice::createSubsanacion()`), sin `RechazoPrevio`, con el desglose actual y encadenado al último registro |
| Enviar Baja | `Baja` | `$canSend` | Envía un `RegistroAnulacion`; véase [[Anulación de facturas|Anulacion-de-facturas]] |
| Verificar Estado en AEAT | `VerificarEstado` | `$canSend` y (`$hasError` o hay algún estado guardado) | Consulta la factura en la AEAT y reconcilia el estado; véase [[Consulta a la AEAT|Consulta-a-la-AEAT]] |

Cuando la factura ya está enviada correctamente, encima de los botones aparece el aviso «Factura ya enviada correctamente. Solo puede enviar una Baja para anularla.» (`VERIFACTU_INVOICE_ALREADY_SENT_CORRECTLY`) y «Enviar Alta» y «Enviar Modificación» se muestran deshabilitados con el tooltip «Factura ya enviada a VeriFactu» (`VERIFACTU_ALREADY_SENT_TOOLTIP`).

> **Nota:** con la factura ya aceptada, «Enviar Modificación» queda deshabilitado en la pestaña. La subsanación solo puede lanzarse desde aquí mientras el estado no sea el de enviada correctamente (por ejemplo, tras un rechazo), o desde el listado del módulo, que no aplica esa restricción.

Cuando no se puede enviar, el bloque muestra el aviso «No se puede enviar a VeriFactu» (`VERIFACTU_CANNOT_SEND_TITLE`) seguido de los motivos, y los tres botones de envío deshabilitados:

| Motivo | Clave |
|---|---|
| La factura debe estar validada para enviar a VeriFactu | `VERIFACTU_VALIDATION_INVOICE_NOT_VALIDATED` |
| No se pueden enviar facturas provisionales (con "PROV" en referencia) | `VERIFACTU_VALIDATION_INVOICE_PROVISIONAL` |
| La factura ya está anulada en VeriFactu | `VERIFACTU_ERROR_FACTURA_YA_ANULADA` |

### Diálogo de confirmación

Los tres envíos pasan por un diálogo `Form::formconfirm` con el título «Confirmar envío» y el texto «¿Enviar las facturas seleccionadas a VeriFactu?» (`CONFIRM_SEND_TO_AEAT_TITLE`, `CONFIRM_SEND_TO_AEAT_MESSAGE`). Para la baja se añade el desplegable `tipo_anulacion` (`normal`, `rechazo_previo`, `sin_registro_previo`, con `normal` preseleccionado) y un recuadro con la descripción de cada tipo. Al confirmar, la pestaña ejecuta:

```php
execVERIFACTUCall($object, $actionVERIFACTU, false);
```

El tercer parámetro (`$enforceWaitTime = false`) indica que una petición explícita del usuario no se pospone por el `TiempoEsperaEnvio` que la AEAT haya pedido; ese tiempo sí lo respeta la cola de reintentos (véase [[Reintentos y envíos pendientes|Reintentos-y-envios-pendientes]]). Cualquier excepción se captura y se muestra como mensaje de error; después la factura se recarga para reflejar los nuevos valores.

«Verificar Estado en AEAT» no pide confirmación: se ejecuta en cuanto llega `EXECUTEVERIFACTU=VerificarEstado` y muestra el mensaje devuelto como éxito o aviso.

### Mensajes que pueden aparecer

| Situación | Mensaje |
|---|---|
| Alta aceptada | `Invoice <ref> sent successfully to VeriFactu` (texto fijo en inglés) |
| Alta aceptada parcialmente | `Invoice <ref> sent to VeriFactu with partial success (some records may have warnings)` |
| Ya existía en la AEAT con los mismos importes | «La factura %s ya existe en AEAT. No se realizó envío adicional.» (`VERIFACTU_PRECHECK_SKIP_SEND`) |
| Existe con importes distintos | «La factura %s existe en AEAT con importes diferentes: Dolibarr=%s, AEAT=%s. Revise los datos.» (`VERIFACTU_PRECHECK_AMOUNTS_DIFFER`) |
| Error 3000 con importes iguales | «La factura %s ya existe en AEAT con los mismos importes. Marcada como enviada.» |
| Error 3000 con importes distintos | «La factura %s existe en AEAT con importes diferentes…» y «ACCIÓN REQUERIDA: Si Dolibarr es correcto, cree factura rectificativa. Si AEAT es correcto, ajuste Dolibarr.» |
| Rechazo de la AEAT | `Error in VeriFactu submission - Status: <estado> - Code: <código> - <descripción>` |
| Excepción (certificado, esquema, validación local…) | «Error VeriFactu: %s» (`VERIFACTU_EXCEPTION`) |
| Servicio no disponible | «Factura %s validada localmente. No se pudo enviar a VeriFactu. Marcada como "Pendiente" para reintento. Error: %s» |
| Otra validación encadenando | «Se está encadenando otra factura en este momento y ha vencido el tiempo de espera…» (`VERIFACTU_CHAIN_LOCK_BUSY`) |
| Verificación: no existe | «Factura %s no existe en AEAT. Errores anteriores limpiados. Puede enviarla.» |
| Verificación: coincide | «Factura %s verificada. Estado: Enviada. Importe: %s» |
| Verificación: difiere | «Factura %s en AEAT con importes diferentes: Dolibarr=%s, AEAT=%s (diferencia: %s). Requiere revisión.» |

El detalle completo de cada respuesta queda en `verifactu_error` y `verifactu_ultima_salida`; véanse [[Respuestas de la AEAT|Respuestas-de-la-AEAT]] y [[Códigos de error|Codigos-de-error]].

## Bloque «Datos en la AEAT»

Este bloque solo tiene contenido cuando `verifactu_id_factura` está informado, es decir, cuando la AEAT ha devuelto alguna vez la identificación de la factura. En ese caso, **en cada carga de la pestaña** se hace una consulta SOAP en vivo: se decodifica el JSON del campo (número, fecha y NIF emisor), se construye una consulta por rango de fechas [fecha, fecha + 1 día] con `setSpecificInvoice()` y se llama a `execVERIFACTUQuery()`. Según el resultado:

- **`ConDatos`**: se muestra el número de registros devueltos, la referencia y la fecha, y dos paneles en formato `<pre>` generados con `dumper()` (`lib/dumper.php`): a la izquierda «Payload Sent to AEAT», con el contenido de `verifactu_payload` (el array `RegFactuSistemaFacturacion` que se envió, guardado en JSON), y a la derecha «AEAT Query Response», con el primer registro devuelto por la AEAT (`RegistroRespuestaConsultaFactuSistemaFacturacion[0]`).
- **`SinDatos`**: aviso de que la factura no consta en los registros de la AEAT o los criterios no coinciden.
- **Otro caso**: error de consulta con indicación de revisar la configuración.

Si el JSON del campo no contiene número o fecha válidos se muestra un error de datos incompletos; si el campo no es JSON, se intenta una consulta de respaldo usando su valor como número y la fecha de la factura.

Este panel es el único sitio de la interfaz donde se ve el registro transmitido. Ten en cuenta que:

- lo que se muestra es la estructura de datos en JSON, no el XML SOAP; el campo se etiqueta «Datos XML» pero su contenido es `json_encode()` del array enviado;
- no hay botón de descarga del registro ni del XML; las trazas SOAP de petición y respuesta solo se obtienen con la depuración descrita en [[Registro y depuración|Registro-y-depuracion]];
- cada apertura de la pestaña supone una llamada a la AEAT con el certificado configurado.

## Lo que no está en la pestaña

- **El código QR.** Se muestra en la ficha principal de la factura, no en la pestaña: los hooks `moreHtmlStatus()` y `formDolBanner()` de `class/actions_verifactu.class.php` insertan el QR con los textos «QR tributario:» y «VeriFactu» cuando `isInvoiceSentToVerifactu()` es cierto (hay CSV y el badge de estado lleva `attr-status="Enviada"`). También va al PDF y al ticket de TakePOS. Véase [[Código QR|Codigo-QR]].
- **La edición de la factura.** Una factura sin `PROV` no puede modificarse ni borrarse; el módulo sustituye los botones «Modificar» y «Eliminar» de la ficha por versiones deshabilitadas con el mensaje `VERIFACTU_INVOICE_CANNOT_BE_MODIFIED`. Véase [[Restricciones sobre facturas enviadas|Restricciones-sobre-facturas-enviadas]].
- **El envío masivo.** Las mismas acciones (alta, modificación y baja) existen para varias facturas a la vez en el listado del módulo (`views/list.facture.php`, menú «Facturas emitidas»). Véase [[Validación masiva|Validacion-masiva]].

## Flujos habituales desde la pestaña

| Necesidad | Pasos |
|---|---|
| Reenviar tras un error | Corrige la causa (datos del tercero, campos fiscales, certificado) y pulsa «Enviar Alta». La comprobación previa evita el duplicado si el alta anterior sí llegó a registrarse |
| Estado desactualizado en Dolibarr | Pulsa «Verificar Estado en AEAT»: si el registro existe con los mismos importes, el estado pasa a «Enviada» y se recupera la huella; si no existe, se limpian las marcas para poder reenviar |
| Corregir un registro aceptado | Emite una rectificativa (véase [[Facturas rectificativas|Facturas-rectificativas]]) o, si el tipo de corrección lo admite, lanza una subsanación desde el listado |
| Dejar sin efecto el registro | «Enviar Baja» con el tipo de anulación adecuado |
| Cotejar lo enviado con lo registrado | Compara los dos paneles del bloque «Datos en la AEAT» |

## Véase también

- [[Campos adicionales|Campos-adicionales]]
- [[Flujo de validación y envío|Flujo-de-validacion-y-envio]]
- [[Anulación de facturas|Anulacion-de-facturas]]
- [[Consulta a la AEAT|Consulta-a-la-AEAT]]
- [[Respuestas de la AEAT|Respuestas-de-la-AEAT]]
- [[Reintentos y envíos pendientes|Reintentos-y-envios-pendientes]]
- [[Restricciones sobre facturas enviadas|Restricciones-sobre-facturas-enviadas]]
- [[Código QR|Codigo-QR]]
- [[Permisos y menús|Permisos-y-menus]]
- [[Registro y depuración|Registro-y-depuracion]]
