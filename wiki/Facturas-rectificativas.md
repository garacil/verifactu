# Facturas rectificativas

Una factura rectificativa corrige otra ya emitida. En Dolibarr se crea como factura de abono (`TYPE_CREDIT_NOTE`) o como factura de sustitución (`TYPE_REPLACEMENT`) vinculada a la original; el módulo la transmite a la AEAT como registro de alta de tipo R1 (R5 si es un abono de TakePOS), añade el elemento `TipoRectificativa` con el método de rectificación y, en las sustituciones, los bloques `FacturasRectificadas` e `ImporteRectificacion` con los datos de la factura corregida. Esta página explica qué genera el módulo en cada caso, cómo trata los signos de los importes y qué limitaciones tiene.

## Cómo se crea una rectificativa en Dolibarr

1. Crea una nueva factura de cliente y elige el tipo de abono (`TYPE_CREDIT_NOTE`, valor 2) o de sustitución (`TYPE_REPLACEMENT`, valor 1), indicando la factura que se corrige. Dolibarr guarda esa relación en `fk_facture_source`.
2. Al crearse, el trigger `billCreate()` (`core/triggers/interface_999_modVerifactu_VerifactuTriggers.class.php`) guarda R1 en el campo adicional `verifactu_factura_tipo` para ambos tipos, o R5 si el abono procede de TakePOS.
3. Al validarla se sigue el flujo habitual (véase [[Flujo de validación y envío|Flujo-de-validacion-y-envio]]): comprobación de datos del tercero, encadenamiento con el último registro y envío. La rectificativa es un registro más de la cadena, con su propia huella y su propio CSV.

La factura original no se toca: sigue con su estado VeriFactu, su huella y su CSV. Si lo que se necesita es que el registro original deje de existir en la AEAT, la operación es otra (véase [[Anulación de facturas|Anulacion-de-facturas]]).

## Dos métodos de rectificación

`handleInvoiceCreationOrSubsanation()` (`lib/functions/functions.submission.php`) decide el método a partir del tipo de Dolibarr, no del valor del campo `verifactu_factura_tipo`:

| Tipo Dolibarr | `TipoFactura` | `TipoRectificativa` | Constantes de `Invoice.php` | Bloques adicionales |
|---|---|---|---|---|
| `TYPE_REPLACEMENT` | R1 | S (sustitución) | `TYPE_CREDIT_NOTE_LEGAL`, `RECT_SUBSTITUTION` | `FacturasRectificadas` e `ImporteRectificacion` si hay `fk_facture_source` |
| `TYPE_CREDIT_NOTE` | R1 | I (diferencias) | `TYPE_CREDIT_NOTE_LEGAL`, `RECT_DIFFERENCES` | Ninguno |
| `TYPE_CREDIT_NOTE` con `module_source == 'takepos'` | R5 | I (diferencias) | `TYPE_CREDIT_NOTE_SIMPLIFIED`, `RECT_DIFFERENCES` | Ninguno; sin destinatario |

### R1 por sustitución (`TYPE_REPLACEMENT`)

La factura de sustitución contiene los importes correctos completos, en positivo, y sustituye a la original. El módulo:

1. Fija `TipoFactura` = R1 y `TipoRectificativa` = S.
2. Si `fk_facture_source` está informado, carga la factura original y llama a `Invoice::addRectifiedInvoice()` con:
   - `IDEmisorFactura`: el NIF del obligado tributario configurado (`VERIFACTU_HOLDER_NIF`), no el que tuviera la original.
   - `NumSerieFactura`: la referencia de la original.
   - `FechaExpedicionFactura`: la fecha de la original en formato `dd-mm-aaaa`.
   - `BaseRectificada`: `abs(getInvoiceTotalHT($original))`.
   - `CuotaRectificada`: `abs(getInvoiceTotalTVA($original))`.
   - `CuotaRecargoRectificado`: `abs($original->total_localtax1)` si es mayor que cero; en otro caso se pasa `null`.
3. El desglose se construye con las líneas de la propia factura de sustitución, con sus importes tal cual (positivos).

`getInvoiceTotalHT()` y `getInvoiceTotalTVA()` (`lib/functions/functions.compatibility.php`) leen `total_ht` y `total_tva` en Dolibarr 14 o superior y `total` y `tva` en versiones anteriores.

### R1 por diferencias (`TYPE_CREDIT_NOTE`)

El abono recoge solo la diferencia que se corrige. Dolibarr guarda sus importes en negativo; la AEAT exige importes positivos, así que en el bucle de líneas el módulo convierte con `abs()` la base, la cuota y el recargo de equivalencia de cada línea cuando `$facture->type == Facture::TYPE_CREDIT_NOTE`. El registro sale con `TipoFactura` = R1, `TipoRectificativa` = I y un desglose en positivo.

Para este método el módulo **no** añade `FacturasRectificadas` ni `ImporteRectificacion`, aunque el abono tenga `fk_facture_source`: esa rama solo existe para `TYPE_REPLACEMENT`. La relación con la factura corregida queda en Dolibarr, no en el registro transmitido.

### Abonos de TakePOS (R5)

Un abono generado desde TakePOS se guarda y se transmite como R5 con `TipoRectificativa` = I. Como cualquier simplificada, no lleva bloque `Destinatarios` (`Invoice::validate()` lo rechazaría) y sus importes también se convierten a positivo. Véase [[TakePOS|TakePOS]].

## El bloque `FacturasRectificadas` e `ImporteRectificacion`

`Invoice::addRectifiedInvoice()` (`lib/newfenix/src/Invoice.php`) acumula en el `payload`:

```php
$this->payload['FacturasRectificadas'][] = [
    'IDEmisorFactura'        => $taxId,
    'NumSerieFactura'        => $serial,
    'FechaExpedicionFactura' => $date,
];

$this->payload['ImporteRectificacion'] = [
    'BaseRectificada'        => ..., // suma de las bases rectificadas
    'CuotaRectificada'       => ..., // suma de las cuotas rectificadas
    'CuotaRecargoRectificado'=> ..., // suma de los recargos; 0.00 si no hay
];
```

Los tres importes se formatean siempre con dos decimales (`formatRectificationAmounts()`), y `CuotaRecargoRectificado` se incluye aunque valga `0.00`. El método admite varias llamadas (una por factura rectificada), pero el módulo solo lo invoca una vez, con la factura de `fk_facture_source`.

Ejemplo del fragmento transmitido para una sustitución de una factura de 1.000,00 € de base, 210,00 € de IVA y sin recargo (la estructura de elementos es la del esquema `SuministroInformacion.xsd`; la serialización la hace el cliente SOAP de PHP a partir del array anterior):

```xml
<TipoFactura>R1</TipoFactura>
<TipoRectificativa>S</TipoRectificativa>
<FacturasRectificadas>
  <IDFacturaRectificada>
    <IDEmisorFactura>B12345678</IDEmisorFactura>
    <NumSerieFactura>FA2603-0042</NumSerieFactura>
    <FechaExpedicionFactura>03-03-2026</FechaExpedicionFactura>
  </IDFacturaRectificada>
</FacturasRectificadas>
<ImporteRectificacion>
  <BaseRectificada>1000.00</BaseRectificada>
  <CuotaRectificada>210.00</CuotaRectificada>
  <CuotaRecargoRectificado>0.00</CuotaRecargoRectificado>
</ImporteRectificacion>
```

## Signos de los importes

| Documento | En Dolibarr | En el registro VeriFactu | Dónde se convierte |
|---|---|---|---|
| Abono (`TYPE_CREDIT_NOTE`) | Negativo (líneas y totales) | Positivo en `Desglose`, `CuotaTotal` e `ImporteTotal` | Bucle de líneas de `handleInvoiceCreationOrSubsanation()` (`$isCreditNote`) |
| Factura de sustitución | Positivo | Positivo | No hace falta |
| Importes rectificados de la original | Positivo | Positivo (`abs()` por seguridad) | `addRectifiedInvoice()` |

`CuotaTotal` e `ImporteTotal` se calculan a partir de los grupos del desglose ya convertidos (`Invoice::computeTotals()`), así que también salen en positivo y son los que entran en la huella. El mismo criterio se aplica al cotejar con la AEAT: `compararImportesFactura()` (`lib/functions/functions.query.php`) compara valores absolutos, de modo que un abono de -1,21 € en Dolibarr coincide con 1,21 € en la AEAT. El QR de un abono se genera con `getVerifactuImporteTotal()`, que devuelve el total con signo de Dolibarr; véase [[Código QR|Codigo-QR]].

## Qué pasa si la original no está en VeriFactu

El módulo no consulta a la AEAT antes de construir la rectificativa:

- En una sustitución, `FacturasRectificadas` se rellena con los datos que tiene Dolibarr de la original, exista o no su registro en la AEAT. Si la original nunca se envió (por ejemplo, es anterior a la puesta en marcha del módulo) el bloque se transmite igualmente y es la AEAT quien acepta o rechaza el registro; la respuesta se guarda en `verifactu_error` y `verifactu_ultima_salida` como en cualquier alta.
- Si la factura de sustitución no tiene `fk_facture_source`, no se añade ningún bloque y el registro sale solo con `TipoRectificativa` = S.
- En un abono no se referencia la original en ningún caso, así que el envío no depende de ella.

Lo que sí ocurre siempre es la comprobación previa de duplicados de la propia rectificativa (`preCheckInvoiceInAEAT()`), como en cualquier alta; véase [[Consulta a la AEAT|Consulta-a-la-AEAT]].

## Cambio de R2 a R1 en la versión 2.0.0

Hasta la 2.0.0, `billCreate()` guardaba R2 en `verifactu_factura_tipo` para abonos y facturas de sustitución, mientras que `handleInvoiceCreationOrSubsanation()` transmitía R1. La pestaña VeriFactu mostraba por tanto un tipo que nunca era el enviado. La revisión de conformidad de la issue #30 (punto 5) lo detectó y la 2.0.0 unificó ambos puntos en R1, que es lo que realmente se transmite y lo que corresponde a las rectificativas por error fundado en derecho y por los apartados Uno, Dos y Seis del artículo 80 de la LIVA, según la descripción de la lista L2. El test `tests/IssueFixesTest.php` comprueba que el trigger guarda `TYPE_CREDIT_NOTE_LEGAL` para ambos tipos y que esa constante vale `R1`.

Las facturas creadas antes de la 2.0.0 conservan el R2 guardado en el campo; el registro que se envió fue R1, y un reenvío o subsanación volvería a transmitir R1 porque la rama de rectificativas sobrescribe el valor del campo.

## Restricciones que afectan a las rectificativas

- La factura original validada no se puede modificar ni borrar: el trigger `interface_900_modVerifactu_BillRestrictions.class.php` bloquea la edición, inserción y borrado de líneas, la desvalidación y el borrado de cualquier factura cuya referencia no contenga `PROV`. Corregir es siempre emitir una rectificativa. Véase [[Restricciones sobre facturas enviadas|Restricciones-sobre-facturas-enviadas]].
- La fecha de la rectificativa está sujeta al orden cronológico que el módulo impone al validar. Véase [[Fechas y numeración|Fechas-y-numeracion]].
- El tercero de la rectificativa debe cumplir las mismas exigencias que en una factura ordinaria (NIF, NIF-IVA o dirección según el caso), salvo en TakePOS o clientes de factura simplificada.

## Limitaciones

- **No se pueden emitir R2, R3 ni R4 desde Dolibarr.** El desplegable de `verifactu_factura_tipo` los ofrece, pero para abonos y sustituciones el envío sobrescribe el valor con R1/R5, y para una factura estándar con R2 elegido a mano el módulo no genera `TipoRectificativa` ni `FacturasRectificadas`. La biblioteca dispone de `Invoice::createRectificativa()`, que acepta cualquier tipo R, pero el módulo no la utiliza.
- **Una sola factura rectificada por registro.** Solo se referencia `fk_facture_source`; no hay forma de rectificar varias facturas en un mismo registro.
- **Los abonos no llevan `FacturasRectificadas`.** La referencia a la original queda únicamente en Dolibarr.
- **El importe rectificado sale del total de la original**, no de las líneas que realmente se corrigen: en una sustitución `BaseRectificada` y `CuotaRectificada` son siempre los totales de la factura sustituida.
- **El emisor de la rectificada es el NIF configurado.** Si la original se emitió con otro NIF (cambio de titular), el bloque llevará el actual.

## Véase también

- [[Tipos de factura|Tipos-de-factura]]
- [[Desglose de impuestos|Desglose-de-impuestos]]
- [[Anulación de facturas|Anulacion-de-facturas]]
- [[Consulta a la AEAT|Consulta-a-la-AEAT]]
- [[Restricciones sobre facturas enviadas|Restricciones-sobre-facturas-enviadas]]
- [[Fechas y numeración|Fechas-y-numeracion]]
- [[Encadenamiento y huella|Encadenamiento-y-huella]]
- [[Marco normativo|Marco-normativo]]
