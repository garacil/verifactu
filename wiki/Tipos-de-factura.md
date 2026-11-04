# Tipos de factura

VeriFactu clasifica cada registro de facturación con un código de tipo (lista L2 de la AEAT: F1, F2, F3 y R1 a R5). El módulo decide ese código a partir del tipo de factura de Dolibarr y de su origen (facturación normal, TakePOS o cliente marcado como consumidor final), lo guarda en el campo adicional `verifactu_factura_tipo` en el momento de crear la factura y lo transmite dentro del elemento `TipoFactura` del `RegistroAlta`. Esta página describe la correspondencia completa, los casos especiales (simplificadas, proformas, anticipos, facturas de situación) y qué parte del valor guardado se transmite realmente.

## Códigos VeriFactu y constantes de la biblioteca

La biblioteca interna (`lib/newfenix/src/Invoice.php`, clase `OpenAEAT\Billing\Invoice`, accesible también con el alias `Sietekas\Verifactu\VerifactuInvoice`) define una constante por cada tipo. Las descripciones son las del fichero `lib/verifactu-types.array.php` (`$invoiceTypes`), que es el que alimenta el desplegable del campo adicional.

| Código | Constante en `Invoice.php` | Descripción (`$invoiceTypes`) |
|---|---|---|
| F1 | `TYPE_STANDARD` | (F1) Factura (art. 6 o 7.2 o 7.3 del RD 1619/2012) |
| F2 | `TYPE_SIMPLIFIED` | (F2) Factura Simplificada y Facturas sin identificación del destinatario art. 6.1.d) RD 1619/2012 |
| F3 | `TYPE_SUBSTITUTION` | (F3) Factura emitida en sustitución de facturas simplificadas facturadas y declaradas |
| R1 | `TYPE_CREDIT_NOTE_LEGAL` | (R1) Factura Rectificativa (Error fundado en derecho y Art. 80 Uno Dos y Seis LIVA) |
| R2 | `TYPE_CREDIT_NOTE_80_3` | (R2) Factura Rectificativa (Art. 80.3) |
| R3 | `TYPE_CREDIT_NOTE_80_4` | (R3) Factura Rectificativa (Art. 80.4) |
| R4 | `TYPE_CREDIT_NOTE_OTHER` | (R4) Factura Rectificativa (Resto) |
| R5 | `TYPE_CREDIT_NOTE_SIMPLIFIED` | (R5) Factura Rectificativa en facturas simplificadas |

`Invoice::validate()` rechaza cualquier otro valor con la excepción `Invalid invoice type` antes de llamar al servicio web, de modo que un tipo desconocido nunca llega a la AEAT.

## Correspondencia entre tipos de Dolibarr y tipos VeriFactu

Dolibarr define seis tipos de factura de cliente en `Facture` (y en `CommonInvoice`): `TYPE_STANDARD` (0), `TYPE_REPLACEMENT` (1), `TYPE_CREDIT_NOTE` (2), `TYPE_DEPOSIT` (3), `TYPE_PROFORMA` (4) y `TYPE_SITUATION` (5). La asignación se hace en dos momentos distintos y conviene distinguirlos:

1. **Al crear la factura** (evento `BILL_CREATE`, método `billCreate()` de `core/triggers/interface_999_modVerifactu_VerifactuTriggers.class.php`) se rellena `verifactu_factura_tipo`. Es el valor que se ve en la pestaña VeriFactu y en el listado.
2. **Al enviar** (`handleInvoiceCreationOrSubsanation()` en `lib/functions/functions.submission.php`) se parte de ese campo (`setType($facture->array_options['options_verifactu_factura_tipo'] ?? TYPE_STANDARD)`) y, para abonos y facturas de sustitución, se sobrescribe con el tipo rectificativo que corresponde y se añade `TipoRectificativa`.

| Tipo Dolibarr | Valor | Se guarda en `verifactu_factura_tipo` | Se transmite en `TipoFactura` |
|---|---|---|---|
| `TYPE_STANDARD` | 0 | F1 | El valor del campo (F1 salvo cambio manual) |
| `TYPE_REPLACEMENT` | 1 | R1 | R1 con `TipoRectificativa` = S (sustitución), siempre |
| `TYPE_CREDIT_NOTE` | 2 | R1 (R5 si procede de TakePOS) | R1 con `TipoRectificativa` = I (diferencias); R5 + I si `module_source` es `takepos` |
| `TYPE_DEPOSIT` | 3 | F1 | El valor del campo |
| `TYPE_PROFORMA` | 4 | Vacío | No se transmite nada |
| `TYPE_SITUATION` | 5 | F1 | El valor del campo |

La rama de origen simplificado tiene prioridad sobre la tabla anterior: si la factura viene de TakePOS (`module_source == 'takepos'`), es una instancia de `Facturesim` (módulo TPV de 2byte) o el tercero tiene activado el campo adicional `verifactu_factura_simplificada`, el trigger guarda F2, o R5 si además la factura es un abono (`TYPE_CREDIT_NOTE`).

> **Importante:** para abonos y facturas de sustitución el valor del campo `verifactu_factura_tipo` no manda: el envío fuerza R1 (o R5) y el `TipoRectificativa`. Para los demás tipos de Dolibarr, lo que hay en el campo es exactamente lo que se transmite, así que un cambio manual a F2 o F3 sí tiene efecto. Los detalles de cada rectificativa están en [[Facturas rectificativas|Facturas-rectificativas]].

### Qué más se fija en el envío

Además del tipo, `handleInvoiceCreationOrSubsanation()` rellena en el mismo punto:

- `DescripcionOperacion`: el texto `Invoice <ref>` seguido de ` Customer Ref: <ref_client>` si la factura tiene referencia de cliente. `Invoice::validate()` limita este campo a 500 caracteres.
- `RefExterna`: el `rowid` de la factura en Dolibarr.
- `Incidencia` (cabecera `RemisionVoluntaria`): el valor del campo `verifactu_incidencia`, `N` por defecto.

## Factura simplificada (F2)

### Cuándo se asigna

`billCreate()` guarda F2 en cualquiera de estos tres casos:

| Caso | Condición comprobada en el trigger |
|---|---|
| Ticket de TakePOS | `$object->module_source == 'takepos'` |
| Ticket del TPV de 2byte | `$object instanceof Facturesim` |
| Cliente de factura simplificada | `$object->thirdparty->array_options['options_verifactu_factura_simplificada'] == 1` |

El tercer caso permite emitir simplificadas desde la facturación normal de Dolibarr: basta con marcar el campo adicional del tercero (véase [[Campos adicionales|Campos-adicionales]]). Si una de estas facturas es un abono, el tipo guardado es R5.

### Consecuencias en el registro

- **Sin destinatario.** En `handleInvoiceCreationOrSubsanation()` el bloque `Destinatarios` solo se construye cuando el tipo guardado no es F2 ni R5. `Invoice::validate()` lo refuerza: una F2 o R5 con destinatarios lanza `Simplified invoices cannot have recipients`, y cualquier otro tipo sin destinatario lanza `Must have at least one recipient`.
- **Validación de datos del cliente relajada.** Las comprobaciones previas a validar (NIF obligatorio para España, NIF-IVA obligatorio fuera de España y dirección obligatoria, en `billValidate()` del trigger y en `dolGetButtonAction()` de `class/actions_verifactu.class.php`) se omiten cuando la factura procede de TakePOS o el tercero está marcado como cliente de factura simplificada.
- **Envío inmediato en TakePOS.** En `billValidate()` la condición `module_source == 'takepos'` fuerza el envío directo al validar, con independencia de `VERIFACTU_DIRECT_CALL_ON_VALIDATE`. Véase [[TakePOS|TakePOS]].
- **PDF.** El hook `beforePDFCreation()` de `class/actions_verifactu.class.php` sustituye el título del documento (`PdfInvoiceTitle`) por «Factura Simplificada» (clave `verifactu_FACTURA_Simplificada`) cuando `verifactu_factura_tipo` es F2, y el hook `pdf_build_address()` reemplaza el bloque de dirección del destinatario por «Cliente de factura simplificada» (clave `verifactu_SIMPLIFIED_INVOICE_CUSTOMER`). Ambas claves están definidas desde la 2.2.2; antes el PDF mostraba las claves en bruto. Véase [[Plantilla PDF verifactu|Plantilla-PDF-verifactu]].

> **Nota:** la ayuda integrada del módulo (`VERIFACTU_FAQ_F2_DESC`) recuerda que la simplificada está pensada para ventas al consumidor final con un máximo de 400 € IVA incluido. El módulo no comprueba ese importe: la decisión de emitir F2 depende únicamente del origen de la factura o de la marca del tercero.

## Factura en sustitución de simplificadas (F3)

El código F3 está disponible en el desplegable de `verifactu_factura_tipo`, pero el módulo nunca lo asigna automáticamente. Si se elige a mano en una factura estándar, se transmite `TipoFactura` = F3 tal cual; la biblioteca no dispone de un método para construir el bloque de facturas sustituidas, así que el registro sale sin él.

## Facturas proforma: excluidas

Una proforma no es una factura expedida legalmente y no debe generar registro de facturación (Art. 6 del RD 1007/2023). Desde la versión 2.0.0 el módulo las excluye de todo el flujo:

- `billCreate()` deja `verifactu_factura_tipo` vacío para `TYPE_PROFORMA`.
- `isVerifactuApplicableInvoice()` (`lib/functions/functions.compatibility.php`) devuelve `false` cuando `$invoice->type` coincide con `TYPE_PROFORMA`. Lee la constante de la propia clase del objeto y solo recurre al literal `4` como último recurso, para no confundirla nunca con `TYPE_CREDIT_NOTE` (2). Un objeto sin propiedad `type` se considera dentro del ámbito para no descartar una factura real en silencio.
- `billValidate()` llama a esa función antes de cualquier procesamiento VeriFactu y sale con `0`: la proforma se valida con normalidad en Dolibarr, sin registro ni envío.
- `execVERIFACTUCall()` la vuelve a llamar, con lo que quedan cubiertas todas las vías de envío: validación, pestaña VeriFactu, listado del módulo, envío masivo y cola de reintentos.

Los tests `tests/IssueFixesTest.php` comprueban que los tipos 0, 1, 2, 3 y 5 se incluyen, que el 4 se excluye y que el trigger y la función de envío llaman a `isVerifactuApplicableInvoice()`.

## Anticipos (`TYPE_DEPOSIT`)

Las facturas de anticipo se guardan y se transmiten como F1. El módulo no aplica ninguna lógica especial ni las relaciona con la factura final: cada una es un registro de alta independiente en la cadena.

## Facturas de situación (`TYPE_SITUATION`)

Las facturas de situación también se guardan y se transmiten como F1. La pestaña VeriFactu carga la factura con el indicador `INVOICE_USE_SITUATION` para que Dolibarr resuelva correctamente el ciclo de situación, pero el registro que se envía a la AEAT es el de una factura ordinaria con su propio desglose.

## Facturas rectificativas R1 a R5

| Código | Cómo se obtiene desde Dolibarr |
|---|---|
| R1 | Automáticamente: abono (`TYPE_CREDIT_NOTE`) o factura de sustitución (`TYPE_REPLACEMENT`) |
| R5 | Automáticamente: abono creado desde TakePOS |
| R2, R3, R4 | Se pueden seleccionar en el desplegable, pero el módulo no genera para ellos `TipoRectificativa` ni el bloque `FacturasRectificadas`, y en abonos y sustituciones el valor se sobrescribe por R1/R5 |

Hasta la versión 2.0.0 el trigger guardaba R2 para abonos y sustituciones mientras el envío transmitía R1, de modo que la pestaña mostraba un tipo que nunca era el enviado. Desde la 2.0.0 ambos puntos guardan y envían R1 (issue #30, punto 5). Véase [[Facturas rectificativas|Facturas-rectificativas]].

## Dónde se ve el tipo

- En la pestaña VeriFactu de la factura, dentro del bloque de campos fiscales (`verifactu_factura_tipo`, etiqueta «Tipo de factura»). Véase [[Pestaña VeriFactu de la factura|Pestana-VeriFactu-de-la-factura]].
- En el listado del módulo, como columna opcional del mismo campo.
- En el registro transmitido, que queda guardado en JSON en el campo `verifactu_payload` (`RegistroFactura.RegistroAlta.TipoFactura`).

## Las demás listas de códigos de `lib/verifactu-types.array.php`

El mismo fichero define el resto de tablas de la AEAT que usan los campos adicionales fiscales. Se documentan con detalle en [[Desglose de impuestos|Desglose-de-impuestos]]:

| Variable | Lista AEAT | Contenido |
|---|---|---|
| `$taxTypes` (alias `$tipoImpuestos`) | L1 | 01 IVA, 02 IPSI, 03 IGIC, 05 Otros |
| `$invoiceTypes` (alias `$tipoFacturas`) | L2 | Tipos de factura de esta página |
| `$regimeKeys` (alias `$claveRegimen`) | L8 | Claves de régimen 01 a 20 |
| `$operationQualifications` (alias `$calificacionOperacion`) | L9 | Automático, S1, S2, N1, N2 |
| `$exemptOperations` (alias `$operacionExenta`) | L10 | Automático, E1 a E6 |
| `$identificationTypes` (alias `$tiposIdentificacion`) | L7 | 02 a 07 para destinatarios extranjeros |
| `$verifactuIncidence` (alias `$incidenciaVeriFactu`) | — | N sin incidencia, S con incidencia técnica |

Los alias en español se mantienen por compatibilidad con código anterior y son referencias a los mismos arrays.

## Ejemplo de registro por tipo

Fragmento del `RegistroAlta` que genera el módulo para un abono ordinario (R1 por diferencias):

```xml
<RegistroAlta>
  <IDVersion>1.0</IDVersion>
  <IDFactura>
    <IDEmisorFactura>B12345678</IDEmisorFactura>
    <NumSerieFactura>AV2603-0007</NumSerieFactura>
    <FechaExpedicionFactura>12-03-2026</FechaExpedicionFactura>
  </IDFactura>
  <NombreRazonEmisor>Mi Empresa SL</NombreRazonEmisor>
  <TipoFactura>R1</TipoFactura>
  <TipoRectificativa>I</TipoRectificativa>
  <DescripcionOperacion>Invoice AV2603-0007</DescripcionOperacion>
  ...
</RegistroAlta>
```

Y el de una simplificada de TakePOS, sin bloque `Destinatarios`:

```xml
<RegistroAlta>
  ...
  <TipoFactura>F2</TipoFactura>
  <DescripcionOperacion>Invoice TC2603-0151</DescripcionOperacion>
  <Desglose>...</Desglose>
  ...
</RegistroAlta>
```

## Véase también

- [[Facturas rectificativas|Facturas-rectificativas]]
- [[Desglose de impuestos|Desglose-de-impuestos]]
- [[Campos adicionales|Campos-adicionales]]
- [[TakePOS|TakePOS]]
- [[Plantilla PDF verifactu|Plantilla-PDF-verifactu]]
- [[Flujo de validación y envío|Flujo-de-validacion-y-envio]]
- [[Marco normativo|Marco-normativo]]
