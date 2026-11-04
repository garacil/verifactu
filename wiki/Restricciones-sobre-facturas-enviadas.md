# Restricciones sobre facturas enviadas

Un registro de facturación transmitido a la AEAT identifica a la factura por emisor, número y fecha, y forma parte de una cadena de huellas. Por eso el módulo impide que una factura con referencia definitiva se modifique, se borre o vuelva a borrador, protege la identificación fiscal de los terceros que ya tienen facturas registradas y evita que se eliminen los campos adicionales de los que depende. Las restricciones se aplican en varias capas: el trigger `interface_900_modVerifactu_BillRestrictions.class.php`, los *hooks* de `class/actions_verifactu.class.php`, el evento `COMPANY_MODIFY` del trigger 999 y varias constantes del núcleo que el módulo activa al instalarse.

## Qué se considera «factura enviada»

El criterio del trigger 900 es la **referencia**: una factura cuya referencia no contiene `PROV` está validada y, por tanto, protegida. No comprueba si tiene huella o CSV. En la práctica esto significa que la protección alcanza a toda factura validada, incluidas las que están «Pendiente» o «Sin Conexión» a la espera de transmitirse, lo cual es coherente: su registro existe y se enviará con la referencia y la fecha actuales.

La protección del tercero, en cambio, sí exige que existan facturas con `verifactu_csv_factura` y `verifactu_huella`, es decir, registros aceptados por la AEAT.

## Trigger 900: eventos bloqueados

El trigger tiene prioridad 900 para ejecutarse **antes** que el principal (999). Cuando el evento afecta a una factura con referencia definitiva, muestra el mensaje `VERIFACTU_INVOICE_CAN_NOT_BE_MODIFIED` con la referencia y devuelve `-1`, lo que hace que el núcleo revierta la operación.

| Evento | Método | Qué operación bloquea |
|---|---|---|
| `BILL_DELETE` | `billDelete()` | Borrar la factura |
| `BILL_UNVALIDATE` | `billUnvalidate()` | Devolver la factura a borrador |
| `LINEBILL_INSERT` | `lineBillInsert()` | Añadir una línea |
| `LINEBILL_UPDATE` | `lineBillUpdate()` | Modificar una línea |
| `LINEBILL_DELETE` | `lineBillDelete()` | Eliminar una línea |

Para los eventos de línea, el trigger carga la factura origen (`fetch_origin()`) y lee su referencia de `origin_object->ref` en Dolibarr 19 o superior, o de `facture->ref` en versiones anteriores.

El texto previsto para el mensaje está en `langs/es_ES/verifactu.lang`: «La factura %s no puede modificarse (restricción VeriFactu)».

> **Nota:** hasta la 2.2.1 el fichero de idioma definía ese texto bajo la clave `VERIFACTU_INVOICE_CANNOT_BE_MODIFIED`, mientras que el trigger y el hook de botones solicitan `VERIFACTU_INVOICE_CAN_NOT_BE_MODIFIED`, y el aviso aparecía como clave sin traducir (el bloqueo se aplicaba igualmente). Desde la 2.2.2 las dos grafías están definidas en los cinco idiomas.

No existe un método para `BILL_MODIFY`: la edición de cabecera de una factura validada la restringe el propio núcleo de Dolibarr, y los botones de la ficha se deshabilitan como se describe a continuación.

## Botones de la ficha de factura

El hook `dolGetButtonAction()` (contexto `invoicecard`) actúa sobre los botones de acción:

| Factura | Botón | Resultado |
|---|---|---|
| Referencia definitiva | **Modificar** (`action=modif`) | Se sustituye por un `<span class="butActionRefused">` con el mensaje de restricción como *tooltip* y la URL pasa a `action=view` |
| Referencia definitiva | **Eliminar** (`action=delete`) | Igual que el anterior |
| Referencia provisional | **Validar** (`action=valid`) | Se deshabilita si el tercero no cumple los requisitos (CIF/NIF, CIF intracomunitario o dirección; véase [[Flujo de validación y envío|Flujo-de-validacion-y-envio]]) |

## Constantes del núcleo que activa el módulo

Al activar el módulo, `core/modules/modVerifactu.class.php` crea estas constantes en la entidad:

| Constante | Valor | Efecto en Dolibarr |
|---|---|---|
| `INVOICE_DISALLOW_REOPEN` | `1` | La ficha de factura no permite reabrir facturas cerradas (`$usercanreopen = false`) |
| `INVOICE_CAN_NEVER_BE_REMOVED` | `1` | `CommonInvoice::is_erasable()` devuelve 0: ninguna factura validada se puede borrar, con independencia del trigger |
| `INVOICE_CHECK_POSTERIOR_DATE` | `1` | Se rechaza validar una factura con fecha anterior a la última validada del mismo tipo (véase [[Fechas y numeración|Fechas-y-numeracion]]) |
| `BLOCKEDLOG_DISABLE_NOT_ALLOWED_FOR_COUNTRY` | `ES` | El módulo de registro inalterable (*BlockedLog*) no se puede desactivar para España |
| `SOCIETE_IDPROF1_INVOICE_MANDATORY` | `0` | Véase la sección sobre `updateSession()` |

Estas constantes permanecen en la configuración aunque el módulo se desactive; `remove()` no las elimina.

## `COMPANY_MODIFY`: protección de la identificación fiscal del tercero

El método `companyModify()` del trigger 999 se ejecuta al guardar cambios en un tercero. Cuenta las facturas de ese tercero en la entidad activa que tienen CSV y huella:

```sql
SELECT COUNT(*) AS count
  FROM llx_facture f
  INNER JOIN llx_facture_extrafields fe ON f.rowid = fe.fk_object
 WHERE fk_soc = {id del tercero}
   AND fe.verifactu_csv_factura IS NOT NULL
   AND fe.verifactu_huella IS NOT NULL
   AND f.entity = {entidad activa}
```

Si el recuento es mayor que cero:

| Tercero | Cambio detectado | Acción |
|---|---|---|
| País `ES` | `idprof1` (CIF/NIF) cambia a otro valor, o se vacía | Se restaura el valor anterior con `setValueFrom('siren', $oldCif)` y se avisa con `SOCIETE_HAVE_INVOICES_NOT_IDPROF1_MODIFIED` |
| Otro país | `tva_intra` cambia a otro valor, o se vacía | Se restaura con `setValueFrom('tva_intra', $oldCifIntra)` y se avisa con `SOCIETE_HAVE_INVOICES_NOT_TVAINTRA_MODIFIED` |

El trigger **no devuelve error**: el resto de cambios del tercero (nombre, dirección, contacto…) se guardan con normalidad; solo el identificador fiscal vuelve a su valor anterior, y el aviso lo indica. El valor anterior se toma de `$object->oldcopy`, que la ficha del tercero rellena antes de guardar.

Los textos previstos en el fichero de idioma son «**CIF/NIF** no actualizado. **%s** tiene **%s** facturas en VeriFactu.» y «**CIF Intra** no actualizado. **%s** tiene **%s** facturas en VeriFactu.» (claves `THIRD_PARTY_HAS_INVOICES_NIF_NOT_MODIFIED` y `THIRD_PARTY_HAS_INVOICES_VAT_INTRA_NOT_MODIFIED`; el código solicita las claves `SOCIETE_HAVE_INVOICES_NOT_*`, que desde la 2.2.2 son alias con el mismo texto; antes se mostraban sin traducir).

Además, en las fichas de tercero (`thirdpartycard`, `thirdpartycomm`) el hook `doActions()` sustituye las etiquetas de los campos «CIF/NIF» y «CIF intra» por `ProfId1ESVerifactu` y `VATIntraVerifactu`, que incluyen una ayuda contextual; la del CIF/NIF advierte: «No modificable tras envío a VeriFactu».

La identificación del **emisor** (NIF del obligado tributario, `VERIFACTU_HOLDER_NIF`) tiene una protección análoga en la pantalla de configuración: desde la 2.0.0 queda fijada en cuanto la entidad tiene su primera huella (`VERIFACTU_TAX_IDENTITY_LOCKED`); el certificado sí puede sustituirse. Véase [[Configuración|Configuracion]].

## `updateSession()`: campos adicionales y obligatoriedad del CIF

El hook `updateSession()` se ejecuta en cada petición y realiza tres ajustes:

1. **`SOCIETE_IDPROF1_INVOICE_MANDATORY` a `false` en memoria.** Dolibarr ofrece esta opción para exigir el CIF/NIF del tercero al validar cualquier factura; el módulo la anula porque sus propias comprobaciones ya cubren el caso y contemplan excepciones (TakePOS y terceros con factura simplificada) que la opción del núcleo no admite.
2. **Protección de los campos adicionales `verifactu_*`.** En las páginas de administración de campos adicionales de terceros (`societe/admin/societe_extrafields.php`) y de facturas de cliente (`compta/facture/admin/facture_cust_extrafields.php`, `compta/facture/admin/invoice_cust_extrafields.php`), si la acción es `delete` y el nombre del atributo empieza por `verifactu_`, se muestra `VERIFACTU_CANNOT_DELETE_EXTRA_FIELD` («No se puede eliminar este campo. Los campos 'verifactu_' son esenciales para el módulo.») y se redirige a la misma página sin borrar. Véase [[Campos adicionales|Campos-adicionales]].
3. **Redirección de la plantilla de ticket del TPV 2byte** hacia `views/pos.facture.php` del módulo, para incluir el código QR (véase [[TakePOS|TakePOS]]).

## Otras salvaguardas relacionadas

- **Facturas anuladas en VeriFactu:** la pestaña VeriFactu y el listado impiden enviar de nuevo una factura cuyo estado contiene «Anulada» (`VERIFACTU_ERROR_FACTURA_YA_ANULADA`), y en el listado sus casillas aparecen deshabilitadas.
- **Facturas ya enviadas correctamente:** en la pestaña VeriFactu se deshabilitan «Enviar Alta» y «Enviar Modificación» con el aviso «Factura ya enviada correctamente. Solo puede enviar una Baja para anularla.»; en el envío masivo se omiten en silencio.
- **Proformas:** quedan fuera de todo el flujo, así que no reciben ninguna de estas restricciones específicas; siguen las reglas normales de Dolibarr.
- **Copia de facturas:** `billCreate()` vacía todos los campos `verifactu_*` de la factura nueva, de modo que una factura clonada no hereda huella, CSV ni estado.

## Cómo proceder legítimamente

| Necesidad | Vía correcta | Dónde |
|---|---|---|
| Corregir importes, líneas o destinatario de una factura ya registrada | Emitir una **factura rectificativa**: un abono (rectificación por diferencias, `R1` tipo `I`) o una factura de sustitución (`R1` tipo `S`, que envía el bloque `FacturasRectificadas`) | Creación de un abono o de una factura de sustitución desde Dolibarr; el módulo asigna el tipo VeriFactu al crearla. Véase [[Facturas rectificativas|Facturas-rectificativas]] |
| Dejar sin efecto una factura registrada | Enviar una **anulación** (`Baja`) con el tipo adecuado (`normal`, `rechazo_previo`, `sin_registro_previo`) | Pestaña VeriFactu o listado Facturas emitidas; véase [[Anulación de facturas|Anulacion-de-facturas]] |
| Reenviar el registro de una factura que la AEAT rechazó o que quedó parcial | **Enviar Modificación** (subsanación, `Mod`), disponible cuando la factura no consta como enviada correctamente | Pestaña VeriFactu; véase [[Respuestas de la AEAT|Respuestas-de-la-AEAT]] |
| Cambiar el CIF/NIF de un cliente con facturas registradas | El identificador de las facturas ya enviadas no puede cambiar; la rectificación o anulación del registro se decide con el asesor fiscal. Para facturas futuras, la vía habitual es un tercero nuevo con la identificación correcta | Ficha del tercero |
| Corregir un borrador | Sin restricciones: mientras la referencia sea `PROV` la factura se edita, se borra y se refecha con normalidad | Ficha de la factura |

> **Importante:** el módulo describe lo que bloquea y las vías que ofrece; qué tipo de rectificativa o de anulación corresponde a cada situación es una cuestión fiscal que conviene consultar con el asesor.

## Véase también

- [[Flujo de validación y envío|Flujo-de-validacion-y-envio]]
- [[Facturas rectificativas|Facturas-rectificativas]]
- [[Anulación de facturas|Anulacion-de-facturas]]
- [[Encadenamiento y huella|Encadenamiento-y-huella]]
- [[Campos adicionales|Campos-adicionales]]
- [[Pestaña VeriFactu de la factura|Pestana-VeriFactu-de-la-factura]]
- [[Fechas y numeración|Fechas-y-numeracion]]
- [[Configuración|Configuracion]]
- [[Permisos y menús|Permisos-y-menus]]
- [[Preguntas frecuentes|Preguntas-frecuentes]]
