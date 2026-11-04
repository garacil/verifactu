# Desglose de impuestos

El bloque `Desglose` del registro de alta describe, por cada combinación de tipo impositivo, recargo y clave de régimen, la base imponible, la cuota repercutida y, si procede, el recargo de equivalencia. El módulo lo construye a partir de las líneas de la factura de Dolibarr y de cuatro campos adicionales fiscales (impuesto, clave de régimen, calificación de la operación y exención) que el usuario es responsable de configurar. De ese desglose salen `CuotaTotal` e `ImporteTotal`, que entran en la huella. Esta página describe paso a paso cómo se decide cada elemento, cómo se agrupan y redondean los importes, cómo se identifica al destinatario y qué ocurre con el IRPF, el recargo de equivalencia y las operaciones exentas o no sujetas.

## Los cuatro parámetros fiscales

`getVerifactuParams()` (`lib/functions/functions.configuration.php`) resuelve cuatro valores. Desde la **2.2.4** los busca, por este orden, en los campos adicionales de la factura, en los del cliente y en los valores por defecto del módulo, y se queda con el primero que esté puesto (`getVerifactuConfiguredValue()`). Un valor vacío o `'0'` se considera no configurado.

| Campo adicional | Lista AEAT | Clave devuelta | Elemento del desglose |
|---|---|---|---|
| `verifactu_impuesto` | L1 | `tipoImpuesto` (`taxType`) | `Impuesto` |
| `verifactu_clave_regimen` | L8 | `claveRegimen` (`regimeKey`) | `ClaveRegimen` |
| `verifactu_calificacion_operacion` | L9 | `calificacionOperacion` (`operationQualification`) | `CalificacionOperacion` |
| `verifactu_operacion_exenta` | L10 | `operacionExenta` (`exemptOperation`) | `OperacionExenta` |

El módulo no infiere estos valores del país del cliente ni del tipo de IVA: la ayuda integrada lo dice expresamente («Este módulo NO incluye inferencia automática de parámetros fiscales», clave `VERIFACTU_FAQ_IMPORTANT_CONFIG_DESC`). Lo que hace es rellenarlos al crear la factura siguiendo este orden de prioridad, de menor a mayor (`billCreate()` en `core/triggers/interface_999_modVerifactu_VerifactuTriggers.class.php`):

1. **Valores fijos**: impuesto `01` (`TAX_VAT`), clave de régimen `01` (`REGIME_GENERAL`), calificación `S1` (`QUAL_TAXABLE`) y exención `0` (ninguna), salvo que el formulario de creación haya enviado otro valor.
2. **Constantes globales** `VERIFACTU_DEFAULT_TAX_TYPE`, `VERIFACTU_DEFAULT_TAX_REGIME`, `VERIFACTU_DEFAULT_OPERATION_QUALIFICATION` y `VERIFACTU_DEFAULT_EXEMPT_OPERATION`, si no están vacías. Véase [[Configuración|Configuracion]].
3. **Campos del tercero** `verifactu_impuesto`, `verifactu_clave_regimen`, `verifactu_calificacion_operacion` y `verifactu_operacion_exenta`, si no están vacíos; en ese caso se muestra un aviso indicando que se ha aplicado la configuración del cliente.

Después pueden editarse en la pestaña VeriFactu de la factura hasta el envío. Véanse [[Campos adicionales|Campos-adicionales]] y [[Pestaña VeriFactu de la factura|Pestana-VeriFactu-de-la-factura]].

Ese relleno lo hace el disparador de **creación**, así que una factura creada antes de instalar el módulo no tiene ninguno de los cuatro valores. Hasta la 2.2.3 solo se leía la factura, de modo que esas facturas salían con el desglose incompleto y la AEAT las rechazaba con el código **4102** señalando `BaseImponibleOimporteNoSujeto`, que no era el campo que faltaba (véase más abajo). Leyendo también el cliente y los valores por defecto, lo que el administrador tenga configurado sirve igualmente para las facturas antiguas. Lo reportó **[@miguelpicher](https://github.com/miguelpicher)** en la [issue #54](https://github.com/garacil/verifactu/issues/54).

### Validación previa al envío

Antes de recorrer las líneas, `handleInvoiceCreationOrSubsanation()` (`lib/functions/functions.submission.php`) lanza una excepción, y por tanto aborta el envío, en estos casos:

| Situación | Mensaje de la excepción |
|---|---|
| Impuesto vacío | `ERROR: Tax Type not configured. Must be manually configured in the VeriFactu tab of the invoice after consulting with your tax advisor.` |
| Impuesto 01 (IVA) o 03 (IGIC) sin clave de régimen | `ERROR: Regime Key not configured. For VAT and IGIC it is mandatory. Must be manually configured after consulting with your tax advisor.` |
| Impuesto 02 (IPSI) o 05 (Otros) con clave de régimen | `ERROR: Regime Key must be empty for IPSI and Other taxes according to AEAT specifications. Consult with your tax advisor.` |

La excepción se guarda en `verifactu_error` con el prefijo `Exception:` y el estado pasa a «Error»; si el envío se hizo al validar, la factura vuelve a borrador.

### La calificación y la exención son una elección, no dos campos

`SuministroInformacion.xsd` coloca `CalificacionOperacion` y `OperacionExenta` en un `xs:choice` situado justo antes de `BaseImponibleOimporteNoSujeto`: en cada línea del desglose viaja **una de las dos, nunca las dos ni ninguna**. Si falta la elección, el validador de la AEAT se queda sin esa rama y señala el siguiente campo obligatorio de la secuencia, que es `BaseImponibleOimporteNoSujeto` aunque ese sí se haya informado.

Desde la **2.2.4**:

- Si no hay ni calificación ni exención, se transmite **S1** (sujeta y no exenta), que es con la que nace toda factura creada por el módulo, y se avisa en pantalla (`VERIFACTU_QUALIFICATION_DEFAULTED`) y en el registro, porque es una decisión fiscal y conviene revisarla.
- Si están configuradas las dos, viaja la exención, que es la más específica, y queda anotado en el registro.
- `Invoice::createTaxGroup()` no escribe una calificación vacía y `Invoice::validate()` rechaza una línea sin ninguno de los dos términos, o con ambos, antes de salir a la AEAT.

## Tablas de códigos

Los textos son los de `lib/verifactu-types.array.php`, que son los que aparecen en los desplegables.

### L1: impuesto (`$taxTypes`)

| Código | Constante | Descripción |
|---|---|---|
| 01 | `TAX_VAT` | (01) Impuesto sobre el Valor Añadido (IVA) |
| 02 | `TAX_IPSI` | (02) Impuesto sobre la Producción. Los Servicios y la Importación (IPSI) de Ceuta y Melilla |
| 03 | `TAX_IGIC` | (03) Impuesto General Indirecto Canario (IGIC) |
| 05 | `TAX_OTHER` | (05) Otros |

### L8: clave de régimen (`$regimeKeys`)

| Código | Constante | Descripción |
|---|---|---|
| 01 | `REGIME_GENERAL` | (01) Operación de régimen general |
| 02 | `REGIME_EXPORT` | (02) Exportación |
| 03 | `REGIME_USED_GOODS` | (03) Operaciones a las que se aplique el régimen especial de bienes usados. Objetos de arte.Antigüedades y objetos de colección |
| 04 | `REGIME_INVESTMENT_GOLD` | (04) Régimen especial del oro de inversión |
| 05 | `REGIME_TRAVEL_AGENCIES` | (05) Régimen especial de las agencias de viajes |
| 06 | `REGIME_VAT_GROUP` | (06) Régimen especial grupo de entidades (IVA: Nivel Avanzado / IGIC: Nivel Avanzado) |
| 07 | `REGIME_CASH_ACCOUNTING` | (07) Régimen especial del criterio de caja |
| 08 | `REGIME_IGIC_IPSI` | (08) Operaciones sujetas (IVA: al IPSI/IGIC / IGIC-IPSI: al IPSI/IVA) |
| 09 | `REGIME_TRAVEL_INTERMEDIARIES` | (09) Facturación de las prestaciones de servicios de agencias de viaje que actúan como mediadoras en nombre y por cuenta ajena (D.A.4ª RD1619/2012) |
| 10 | `REGIME_THIRD_PARTY_COLLECTIONS` | (10) Cobros por cuenta de terceros de honorarios profesionales o de derechos derivados de la propiedad industrial. De autor u otros por cuenta de sus socios asociados o colegiados efectuados por sociedades. Asociaciones. Colegios profesionales u otras entidades que realicen estas funciones de cobro |
| 11 | `REGIME_BUSINESS_RENTAL` | (11) Operaciones de arrendamiento de local de negocio |
| 14 | `REGIME_CONSTRUCTION_CERT` | (14) Factura con IVA/IGIC pendiente de devengo en certificaciones de obra cuyo destinatario sea una Administración Pública |
| 15 | `REGIME_SUCCESSIVE_TRACT` | (15) Factura con IVA/IGIC pendiente de devengo en operaciones de tracto sucesivo |
| 17 | `REGIME_OSS_IOSS` | (17) IVA: Operación acogida a alguno de los regímenes previstos en el Capítulo XI del Título IX (OSS e IOSS) / IGIC: Régimen especial de comerciante minorista |
| 18 | `REGIME_EQUIVALENCE_SURCHARGE` | (18) IVA: Recargo de equivalencia / IGIC: Régimen especial del pequeño empresario o profesional |
| 19 | `REGIME_AGRICULTURE` | (19) IVA: Operaciones de actividades incluidas en el Régimen Especial de Agricultura. Ganadería y Pesca (REAGYP) / IGIC: Operaciones interiores exentas por aplicación artículo 25 Ley 19/1994 |
| 20 | `REGIME_SIMPLIFIED` | (20) Régimen simplificado (solo IVA) |

### L9: calificación de la operación (`$operationQualifications`)

| Código | Constante | Descripción |
|---|---|---|
| (vacío) | — | Automático (recomendado) |
| S1 | `QUAL_TAXABLE` | (S1) Operación Sujeta y No exenta - Sin inversión del sujeto pasivo |
| S2 | `QUAL_TAXABLE_REVERSE` | (S2) Operación Sujeta y No exenta - Con inversión del sujeto pasivo |
| N1 | `QUAL_NOT_SUBJECT` | (N1) Operación No Sujeta artículo 7. 14. otros |
| N2 | `QUAL_NOT_SUBJECT_LOCATION` | (N2) Operación No Sujeta por Reglas de localización |

> **Nota:** la opción «Automático (recomendado)» deja el campo vacío. En ese caso `getVerifactuParams()` devuelve `null` y el desglose se transmite sin `CalificacionOperacion`; no existe ninguna lógica que la calcule. Al crear la factura el trigger pone S1 por defecto, así que el valor vacío solo aparece si se borra a mano o si la constante global o el tercero lo dejan así.

### L10: operación exenta (`$exemptOperations`)

| Código | Constante | Descripción |
|---|---|---|
| (vacío) | — | Automático (recomendado) |
| E1 | `EXEMPT_ARTICLE_20` | (E1) Exenta por el artículo 20 |
| E2 | `EXEMPT_ARTICLE_21` | (E2) Exenta por el artículo 21 |
| E3 | `EXEMPT_ARTICLE_22` | (E3) Exenta por el artículo 22 |
| E4 | `EXEMPT_ARTICLE_23_24` | (E4) Exenta por los artículos 23 y 24 |
| E5 | `EXEMPT_ARTICLE_25` | (E5) Exenta por el artículo 25 |
| E6 | `EXEMPT_OTHER` | (E6) Exenta por otros |

## Cómo se recorre cada línea

Con los cuatro parámetros base en la mano, el bucle de `handleInvoiceCreationOrSubsanation()` procesa cada línea de la factura (`$facture->lines`):

1. **Tipo, base y cuota**: `tva_tx`, `total_ht` y `total_tva` de la línea (con respaldo en `total` y `tva` para versiones antiguas de Dolibarr).
2. **Abonos**: si `$facture->type == Facture::TYPE_CREDIT_NOTE`, base, cuota y recargo se pasan por `abs()`, porque la AEAT no admite negativos. Véase [[Facturas rectificativas|Facturas-rectificativas]].
3. **Recargo de equivalencia**: se leen `localtax1_tx` y `total_localtax1`. Hay recargo cuando el tipo es mayor que cero y el importe no es cero. Si lo hay y el impuesto es IVA (`01`), la clave de régimen de esa línea pasa a ser `18` (`REGIME_EQUIVALENCE_SURCHARGE`), sea cual sea la configurada.
4. **IVA al 0 % sin exención**: si `tva_tx` es 0 y no hay código de exención, el módulo solo deja un aviso en el registro de Dolibarr (`Line with 0% VAT without specific exemption - may require manual review`) y transmite la línea como sujeta con `TipoImpositivo` 0 y `CuotaRepercutida` 0.
5. **Operaciones no sujetas (N1, N2)**: si la calificación es `QUAL_NOT_SUBJECT` o `QUAL_NOT_SUBJECT_LOCATION`, se llama a `addDesglose()` solo con la calificación, la base, el impuesto y la clave de régimen: sin exención, sin tipo impositivo ni cuota. La base va a `BaseImponibleOimporteNoSujeto`.
6. **Con recargo**: `addDesglose()` con calificación, base, exención, impuesto, régimen, tipo, cuota, `null` como base a coste, tipo de recargo e importe de recargo.
7. **Resto**: `addDesglose()` con calificación, base, exención, impuesto, régimen, tipo y cuota.

> **Aviso:** el módulo asume la convención española de Dolibarr para los impuestos locales: el impuesto local 1 (`localtax1`) es el recargo de equivalencia y el impuesto local 2 (`localtax2`) es la retención de IRPF, que Dolibarr guarda con signo negativo. No consulta el país de la empresa ni el tipo de impuesto local configurado en el diccionario de IVA. Una instalación que use los impuestos locales con otro significado transmitirá un desglose incorrecto.

## Agrupación y redondeo en `Invoice::addDesglose()`

`addDesglose()` (`lib/newfenix/src/Invoice.php`) no añade una entrada por línea sino por grupo:

- Descarta la llamada si la base es 0 y el tipo es 0 o `null` (líneas sin importe).
- La clave del grupo es `round($rate, 1) . '_' . round($surchargeRate, 1) . '_' . ($regime ?? 'NONE')`: mismo tipo (a un decimal), mismo recargo y misma clave de régimen se acumulan en la misma entrada de `DetalleDesglose`. La calificación y la exención **no** forman parte de la clave: el grupo toma las de la primera línea que lo crea.
- Al crear el grupo (`createTaxGroup()`) se fijan `CalificacionOperacion`, `Impuesto`, `ClaveRegimen` (si no está vacía) y:
  - si hay exención: `OperacionExenta` con el código, y no se incluyen `TipoImpositivo` ni `CuotaRepercutida`;
  - si no la hay: `TipoImpositivo` (redondeado a 2 decimales), `CuotaRepercutida`, y `TipoRecargoEquivalencia` y `CuotaRecargoEquivalencia` cuando se pasó recargo.
- `aggregateTaxGroup()` suma base, cuota y recargo al grupo, redondeando cuota y recargo a 2 decimales en cada suma; `rebuildTaxEntries()` vuelve a redondear base, cuota y recargo a 2 decimales al generar `DetalleDesglose`.
- `Invoice::validate()` exige al menos una entrada y como máximo 12 (`An invoice cannot have more than 12 tax breakdown details`), límite tomado del esquema `SuministroInformacion.xsd`.

Ejemplo: una factura con dos líneas al 21 % (100,00 y 50,00 €), una al 10 % (30,00 €) y otra al 21 % con recargo del 5,2 % (200,00 €), todas con régimen general, produce tres entradas:

```xml
<Desglose>
  <DetalleDesglose>
    <Impuesto>01</Impuesto>
    <ClaveRegimen>01</ClaveRegimen>
    <CalificacionOperacion>S1</CalificacionOperacion>
    <TipoImpositivo>21</TipoImpositivo>
    <BaseImponibleOimporteNoSujeto>150.00</BaseImponibleOimporteNoSujeto>
    <CuotaRepercutida>31.50</CuotaRepercutida>
  </DetalleDesglose>
  <DetalleDesglose>
    <Impuesto>01</Impuesto>
    <ClaveRegimen>01</ClaveRegimen>
    <CalificacionOperacion>S1</CalificacionOperacion>
    <TipoImpositivo>10</TipoImpositivo>
    <BaseImponibleOimporteNoSujeto>30.00</BaseImponibleOimporteNoSujeto>
    <CuotaRepercutida>3.00</CuotaRepercutida>
  </DetalleDesglose>
  <DetalleDesglose>
    <Impuesto>01</Impuesto>
    <ClaveRegimen>18</ClaveRegimen>
    <CalificacionOperacion>S1</CalificacionOperacion>
    <TipoImpositivo>21</TipoImpositivo>
    <BaseImponibleOimporteNoSujeto>200.00</BaseImponibleOimporteNoSujeto>
    <CuotaRepercutida>42.00</CuotaRepercutida>
    <TipoRecargoEquivalencia>5.2</TipoRecargoEquivalencia>
    <CuotaRecargoEquivalencia>10.40</CuotaRecargoEquivalencia>
  </DetalleDesglose>
</Desglose>
```

El orden real de los elementos en el XML lo impone el WSDL al serializar; el ejemplo solo ilustra el contenido.

## `CuotaTotal` e `ImporteTotal`

`Invoice::computeTotals()` recalcula ambos totales cada vez que cambia el desglose:

- `CuotaTotal` = suma de `CuotaRepercutida` + suma de `CuotaRecargoEquivalencia` de todas las entradas.
- `ImporteTotal` = suma de `BaseImponibleOimporteNoSujeto` + `CuotaTotal`.

Se formatean con dos decimales (`number_format(..., 2, '.', '')`) y forman parte de la cadena de la huella (`IDEmisorFactura=...&NumSerieFactura=...&FechaExpedicionFactura=...&TipoFactura=...&CuotaTotal=...&ImporteTotal=...&Huella=...&FechaHoraHusoGenRegistro=...`). Véase [[Encadenamiento y huella|Encadenamiento-y-huella]].

Como los totales se derivan de los grupos redondeados y no de `total_ttc` de la factura, puede haber una diferencia de un céntimo respecto a Dolibarr cuando las líneas se agrupan; las comparaciones con la AEAT usan una tolerancia de 0,01 (`compararImportesFactura()`).

## Retención de IRPF

La retención (impuesto local 2 en la configuración española, negativa) no aparece en el desglose: el bucle de líneas nunca lee `localtax2_tx` ni `total_localtax2`, de modo que `ImporteTotal` transmitido es base + cuota + recargo sin deducir el IRPF, que es lo que exige la AEAT.

Para el resto de usos, el módulo centraliza el cálculo en `getVerifactuImporteTotal()` (`lib/functions/functions.compatibility.php`):

```php
return $totalTtc + abs($localtax2);   // total_ttc + |total_localtax2|
```

Se utiliza en la URL del código QR (`getQRUrl()`, `getQrImage()`, `getQrBase64()`) y en todas las comparaciones de importes con la AEAT (comprobación previa, duplicados con error 3000 y verificación de estado). Sin retención devuelve `total_ttc` sin cambios; en abonos devuelve el valor negativo de Dolibarr: las comparaciones con la AEAT le aplican `abs()` y la URL del QR lo recibe tal cual. Este comportamiento se introdujo en la versión 1.0.4 (README, «Facturas con Retención IRPF»).

## Multidivisa

El módulo trabaja siempre con los importes en la moneda de la empresa: lee `total_ht`, `total_tva` y `total_localtax1` de las líneas y `total_ttc` y `total_localtax2` de la factura, y no consulta ningún campo `multicurrency_*`. Una factura en otra divisa se transmite con sus importes convertidos a la moneda de la sociedad tal como los guarda Dolibarr.

## Destinatarios

`handleInvoiceCreationOrSubsanation()` añade el bloque `Destinatarios` salvo que el tipo guardado sea F2 o R5 (simplificadas). La lógica depende del país del tercero:

| País del tercero (`country_code`) | Identificación transmitida | Método de `Invoice.php` |
|---|---|---|
| `ES` o vacío | `NIF` = `idprof1`, `NombreRazon` = nombre. Si `idprof1` está vacío no se añade destinatario | `addRecipient()` |
| Cualquier otro | Bloque `IDOtro` con `CodigoPais` (país en mayúsculas), `IDType` y `ID` = `tva_intra`. Si `tva_intra` está vacío no se añade destinatario | `addForeignRecipient()` |

El `IDType` sale del campo adicional del tercero `verifactu_tipo_identificacion`; si está vacío se usa `02` (`ID_TYPE_EU_VAT`). `addForeignRecipient()` vuelve a `addRecipient()` (bloque `NIF`) si el país recibido es `ES` o está vacío.

| Código L7 | Constante | Descripción (`$identificationTypes`) |
|---|---|---|
| 02 | `ID_TYPE_EU_VAT` | (02) NIF-IVA |
| 03 | `ID_TYPE_PASSPORT` | (03) Pasaporte |
| 04 | `ID_TYPE_OFFICIAL_DOC` | (04) Documento oficial de identificación expedido por el país o territorio de residencia |
| 05 | `ID_TYPE_RESIDENCE_CERT` | (05) Certificado de residencia |
| 06 | `ID_TYPE_OTHER_DOC` | (06) Otro documento probatorio |
| 07 | `ID_TYPE_NOT_REGISTERED` | (07) No censado |

Puntos que conviene tener presentes:

- El módulo no distingue entre clientes intracomunitarios y extracomunitarios: para todo país distinto de España usa `IDOtro` con el tipo configurado en el tercero. Para un cliente de fuera de la Unión hay que elegir en el tercero el tipo de documento adecuado (03 a 07); la ayuda integrada pone como ejemplo el Reino Unido, que dejó de ser intracomunitario en 2021.
- Un tercero español sin NIF, o extranjero sin NIF-IVA, produce un registro sin destinatario que `Invoice::validate()` rechaza con `Must have at least one recipient`. Para evitar llegar ahí, `billValidate()` y el hook `dolGetButtonAction()` impiden validar la factura con los mensajes `VERIFACTU_INVOICE_CAN_NOT_BE_VALID_CIF_REQUIRED`, `..._TVA_INTRA_REQUIRED` y `..._ADDRESS_REQUIRED`, salvo en TakePOS o clientes de factura simplificada.
- El NIF y el NIF-IVA de un tercero con facturas ya enviadas no se pueden cambiar: el trigger `companyModify()` revierte el cambio. Véase [[Restricciones sobre facturas enviadas|Restricciones-sobre-facturas-enviadas]].
- `Invoice::validate()` admite hasta 1.000 destinatarios, aunque el módulo siempre añade uno.

## Dónde revisar el desglose transmitido

El registro completo enviado se guarda en JSON en el campo `verifactu_payload` y se muestra en la pestaña VeriFactu (bloque «Datos en la AEAT», panel «Payload Sent to AEAT») junto con lo que la AEAT tiene registrado. La vista de consulta del módulo también reconstruye por cada factura la base, la cuota por tipo, el recargo y el total a partir del `Desglose` devuelto por la AEAT. Véanse [[Pestaña VeriFactu de la factura|Pestana-VeriFactu-de-la-factura]] y [[Consulta a la AEAT|Consulta-a-la-AEAT]].

## Véase también

- [[Tipos de factura|Tipos-de-factura]]
- [[Facturas rectificativas|Facturas-rectificativas]]
- [[Campos adicionales|Campos-adicionales]]
- [[Configuración|Configuracion]]
- [[Encadenamiento y huella|Encadenamiento-y-huella]]
- [[Código QR|Codigo-QR]]
- [[Consulta a la AEAT|Consulta-a-la-AEAT]]
- [[Códigos de error|Codigos-de-error]]
- [[Glosario|Glosario]]
