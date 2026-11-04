# Biblioteca newfenix

`lib/newfenix/` contiene la biblioteca **OpenAEAT Billing**, la capa del módulo que construye los registros de facturación VeriFactu, calcula la huella, comprueba los límites del esquema oficial, genera el QR y se comunica por SOAP con la AEAT. Es un paquete PHP independiente de Dolibarr: ninguna de sus clases usa `$conf`, `$db`, `dol_syslog()` ni ninguna otra función del núcleo, de modo que se puede probar y usar de forma autónoma. Esta página documenta su espacio de nombres real y los alias con los que la nombra el resto del módulo, cada clase con sus métodos públicos, las constantes de tipos, la configuración, las excepciones y las dependencias.

## Qué es y dónde está

| Dato | Valor |
|---|---|
| Ruta | `lib/newfenix/` |
| Nombre del paquete (`composer.json`) | `openaeat/billing`, tipo `library` |
| Espacio de nombres | `OpenAEAT\Billing` (PSR-4 sobre `src/`) |
| Licencia | GPL-3.0-or-later |
| Autor | Germán Luis Aracil Boned |
| Origen | Creada al reorganizar el módulo original (README, «Historia del proyecto»: «Se creó una nueva librería interna (OpenAEAT\Billing)») |

```text
lib/newfenix/
├── autoload.php        Carga las nueve clases y registra los alias heredados
├── composer.json       Metadatos, requisitos y scripts (phpunit, phpstan, phpcs)
├── phpunit.xml         Configuración PHPUnit (tests/Unit y tests/Integration, no distribuidos)
├── src/
│   ├── BillingException.php
│   ├── Cancellation.php
│   ├── Config.php
│   ├── Invoice.php
│   ├── Manager.php
│   ├── QRGenerator.php
│   ├── Query.php
│   ├── SchemaManager.php
│   └── SoapClient.php
└── vendor/             chillerlan/php-qrcode 5.0.4, chillerlan/php-settings-container 2.1.6
```

Todos los ficheros de `src/` declaran `declare(strict_types=1)`. El módulo carga la biblioteca desde `lib/verifactu.lib.php` con dos inclusiones: `lib/newfenix/autoload.php` (clases propias) y `lib/newfenix/vendor/autoload.php` (dependencias del QR).

## Espacio de nombres y alias

El espacio de nombres real es `OpenAEAT\Billing`. El código del módulo, sin embargo, sigue nombrando las clases por el espacio de nombres histórico `Sietekas\Verifactu`, que `autoload.php` mantiene mediante `class_alias()`:

| Clase real | Alias heredado | Quién usa el alias |
|---|---|---|
| `OpenAEAT\Billing\Invoice` | `Sietekas\Verifactu\VerifactuInvoice` | `functions.submission.php`, triggers, `actions_verifactu.class.php` |
| `OpenAEAT\Billing\Cancellation` | `Sietekas\Verifactu\VerifactuInvoiceCancel` | `functions.cancellation.php` |
| `OpenAEAT\Billing\Query` | `Sietekas\Verifactu\VerifactuInvoiceQuery` | `functions.query.php`, `views/query.facture.php`, `views/tabVERIFACTU.facture.php` |
| `OpenAEAT\Billing\Manager` | `Sietekas\Verifactu\VerifactuManager` | `functions.submission.php`, `functions.query.php` |
| `OpenAEAT\Billing\QRGenerator` | `Sietekas\Verifactu\QRGenerator` | `functions.qr.php` |
| `OpenAEAT\Billing\Config` | *(sin alias)* | Uso interno y tests |
| `OpenAEAT\Billing\SoapClient` | *(sin alias)* | Uso interno (`Manager`) |
| `OpenAEAT\Billing\SchemaManager` | *(sin alias)* | `Manager`, `admin/setup.php`, tests |
| `OpenAEAT\Billing\BillingException` | *(sin alias)* | `SoapClient` |

`autoload.php` hace tres cosas, en este orden:

1. **Carga anticipada.** `require_once` de los nueve ficheros de `src/`, para que las clases existan antes de que cualquier otro autoloader intente resolverlas.
2. **Alias.** Cinco `class_alias()` de `OpenAEAT\Billing\*` a `Sietekas\Verifactu\*`. Un `instanceof`, una constante (`VerifactuInvoice::TYPE_SIMPLIFIED`) o un método estático funcionan igual con cualquiera de los dos nombres.
3. **Autoloader antepuesto.** `spl_autoload_register()` con `prepend = true`: devuelve `true` sin cargar nada para los cinco nombres heredados (impide que un autoloader de Composer cargue definiciones antiguas en conflicto) y resuelve por PSR-4 cualquier otra clase de `OpenAEAT\Billing\` que no se hubiera cargado.

> **Nota:** el nombre heredado se conserva también en `vendor/composer/installed.php`, donde el paquete raíz figura como `sietekas/verifactu`; `composer.json` declara `openaeat/billing`. Es el rastro de la migración de nombre y no afecta al funcionamiento.

Los tests del módulo (`tests/SchemaManagerTest.php`, `tests/IssueFixesTest.php`, `tests/InvoiceValidationTest.php`, `tests/PriorRejectionTest.php`, `tests/MultiEntityAndCancellationTest.php`) cargan `src/*.php` directamente y usan los nombres reales; `tests/IssueFixesTest.php` define un sustituto mínimo de `Sietekas\Verifactu\VerifactuInvoice` cuando el alias no está cargado. Ver [[Suite de pruebas|Suite-de-pruebas]].

## Clases y responsabilidades

| Clase | Responsabilidad | Lanza |
|---|---|---|
| `Config` | Entorno (pruebas/producción), tipo de certificado, URL de los servicios, credenciales, ruta del WSDL local | — |
| `Manager` | Orquestador: crea el transporte, completa el registro con emisor y sistema informático, envía altas, anulaciones y consultas, guarda la última huella y el último payload | Lo que propaguen `Invoice`/`SoapClient` |
| `Invoice` | Registro de alta (`RegistroAlta`): identificación, tipo, destinatarios, desglose, encadenamiento, huella, validación | `\InvalidArgumentException` |
| `Cancellation` | Registro de anulación (`RegistroAnulacion`) con sus tres variantes | `\InvalidArgumentException` |
| `Query` | Petición `ConsultaFactuSistemaFacturacion` con sus filtros | `\InvalidArgumentException` |
| `QRGenerator` | URL de verificación y render del QR (PNG, SVG, base64) | `\InvalidArgumentException`, `\RuntimeException` |
| `SchemaManager` | Descarga y caché local de WSDL/XSD | — (devuelve arrays de resultado) |
| `SoapClient` | Envoltorio de `\SoapClient` de PHP: opciones, contexto TLS, trazas | `BillingException` |
| `BillingException` | Excepción con categoría, contexto y factorías | — |

## Config

Gestiona la configuración de conexión. Un `Manager` crea su propia instancia en `provisionTransport()`; el módulo no la instancia directamente.

### Constantes

| Constante | Valor | Significado |
|---|---|---|
| `ENV_TEST` | `test` | Entorno de pruebas (por defecto) |
| `ENV_PRODUCTION` | `production` | Entorno de producción |
| `CERT_STANDARD`, `CERT_NORMAL` | `standard`, `normal` | Certificado de persona física o representante: servicios `www1`/`prewww1` |
| `CERT_SEAL`, `CERT_SELLO` | `seal`, `sello` | Certificado de sello: servicios `www10`/`prewww10` |

### Puntos de acceso que resuelve

| Método | Pruebas | Producción |
|---|---|---|
| `getVerifactuEndpoint()` (certificado normal) | `https://prewww1.aeat.es/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSOAP` | `https://www1.agenciatributaria.gob.es/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSOAP` |
| `getVerifactuEndpoint()` (certificado de sello) | `https://prewww10.aeat.es/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSOAP` | `https://www10.agenciatributaria.gob.es/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSOAP` |
| `getRequirementEndpoint()` | mismo host, ruta `/wlpl/TIKE-CONT/ws/SistemaFacturacion/RequerimientoSOAP` | ídem |
| `getQrValidationUrl()` | `https://prewww2.aeat.es/wlpl/TIKE-CONT/ValidarQR` | `https://www2.agenciatributaria.gob.es/wlpl/TIKE-CONT/ValidarQR` |
| `getQrNonVerifactuUrl()` | `https://prewww2.aeat.es/wlpl/TIKE-CONT/ValidarQRNoVerifactu` | `https://www2.agenciatributaria.gob.es/wlpl/TIKE-CONT/ValidarQRNoVerifactu` |

### Métodos

| Método | Descripción |
|---|---|
| `__construct($runtime = 'test', $authMode = 'standard')` | Valores iniciales; el servicio por defecto es `verifactu` |
| `setEnvironment()`, `setService()`, `setCertType()` | Cambian entorno (`test`/`production`), servicio (`verifactu`/`requirement`) y tipo de certificado |
| `setCertificate($filePath, $passphrase)` | Ruta del PEM y contraseña de la clave |
| `setCertOptions(array $options)` | Guarda el array completo de opciones y toma de él `local_cert` y `passphrase` si existen |
| `setSystemIdentity($devTaxId, $name, $version, $identifier)` / `getSystemIdentity()` | Identidad del software (no la lee `Manager`, que usa su propio array de metadatos) |
| `setLocalWsdlPath()` / `getLocalWsdlPath()` | Ruta del WSDL local cacheado (`null` si no hay) |
| `getEnvironment()`, `getCertificateType()`, `getCertificatePath()`, `getCertificatePassword()`, `getCertOptions()`, `getService()` | Lectura de los ajustes |
| `isProduction()`, `isTestEnvironment()`, `isAutomatedAuth()` | Consultas booleanas |
| `getSettings()`, `getCredentialInfo()` | Volcado de ajustes; `getCredentialInfo()` no revela la contraseña |
| `forTesting($authMode = 'sello')`, `forProduction($authMode = 'sello')` | Factorías estáticas |
| `reset()` | Vuelve a los valores por defecto |

## Manager

Es la fachada que usa el módulo (`new VerifactuManager(...)` en `functions.submission.php` y `functions.query.php`).

### Constructor y valores por defecto

```php
new Manager(
    string $taxId,                  // NIF del obligado tributario
    string $legalName,              // razón social del obligado
    string $environment = 'test',   // 'test' | 'production'
    string $service = 'verifactu',  // 'verifactu' | 'requirement'
    array  $systemInfo = [],        // bloque SistemaInformatico completo
    string $authMode = 'sello'      // 'normal' | 'sello' (también 'standard' | 'seal')
);
```

El módulo lo construye con `getEnvironment()`, `'verifactu'`, `getSystemConfig()` y `VERIFACTU_CERT_TYPE` (por defecto `normal`). Las factorías `Manager::forTesting()` y `Manager::forProduction()` fijan el modo `sello`.

### Métodos

| Método | Descripción |
|---|---|
| `sendInvoice(Invoice $document, array $credentials)` | Completa emisor y sistema informático si faltan, crea el transporte, obtiene `getData(true)`, anota huella, sello de tiempo y payload, y llama a `RegFactuSistemaFacturacion` |
| `sendCancellation(Cancellation $document, array $credentials)` | Igual para una anulación (mismo servicio SOAP). No actualiza la pista de auditoría |
| `queryInvoice(Query $request, array $credentials = [])` | Llama a `ConsultaFactuSistemaFacturacion` |
| `submitInvoice()`, `submitCancellation()`, `query()` | Variantes sin credenciales explícitas |
| `setSchemasDir(string $dir)` | Carpeta de la caché de esquemas; activa el uso del WSDL local |
| `getLastGeneratedHash()` / `getLastFingerprint()` | Huella del último alta enviado |
| `getLastGeneratedTimestamp()` / `getLastTimestamp()` | `FechaHoraHusoGenRegistro` del último alta |
| `getLastSendInvoiceData()` / `getLastResponse()` | Payload del último alta (el registro enviado, no la respuesta de la AEAT, pese al nombre del alias) |
| `getDebugInfo($formatted = true)` | Trazas SOAP del transporte |
| `getSoapClient()`, `resetTransport()`, `clearAuditTrail()` | Acceso y reinicio del transporte y de la auditoría |
| `createInvoice()`, `createCancellation()`, `createQuery()` | Factorías con el emisor ya informado |
| `getObligatedTaxId()`, `getObligatedName()`, `getSystemMetadata()`, `setSystemMetadata()`, `getEnvironment()` | Lectura y ajuste de metadatos |

### Qué hace al enviar

`provisionTransport($credentials)` se ejecuta una sola vez por instancia: crea un `Config` con entorno, servicio y tipo de certificado, le pasa las credenciales con `setCertOptions()`, y si se ha llamado a `setSchemasDir()` crea un `SchemaManager`, descarga los esquemas si faltan y fija `setLocalWsdlPath()` cuando están completos. Después construye el `SoapClient`. Las credenciales que recibe el módulo son el array que devuelve `getCertificateOptions()` (`local_cert`, `passphrase`, `trace`, `exceptions`, `cache_wsdl`, `stream_context`), que acaba fusionado con las opciones del cliente SOAP; ver [[Conexión SOAP y entornos|Conexion-SOAP-y-entornos]].

`prepareInvoiceDocument()` rellena `IDEmisorFactura`/`NombreRazonEmisor` con los datos del obligado si el registro no los trae, y `SistemaInformatico` con `$systemInfo` si no está vacío. `recordAuditInfo()` guarda `Huella`, `FechaHoraHusoGenRegistro` y el payload completo; de ahí los lee el módulo para escribir `verifactu_huella`, `verifactu_fecha_hora_generacion` y `verifactu_payload`.

## Invoice

Construye el bloque `RegistroAlta` y lo envuelve, en `getData(true)`, con la `Cabecera` (`IDVersion`, `ObligadoEmision` y, si se fijó, `RemisionVoluntaria.Incidencia`).

### Constantes de tipos

| Grupo | Constantes |
|---|---|
| Tipo de factura (L2) | `TYPE_STANDARD` F1, `TYPE_SIMPLIFIED` F2, `TYPE_SUBSTITUTION` F3, `TYPE_CREDIT_NOTE_LEGAL` R1, `TYPE_CREDIT_NOTE_80_3` R2, `TYPE_CREDIT_NOTE_80_4` R3, `TYPE_CREDIT_NOTE_OTHER` R4, `TYPE_CREDIT_NOTE_SIMPLIFIED` R5 |
| Calificación (L9) | `QUAL_TAXABLE` S1, `QUAL_TAXABLE_REVERSE` S2, `QUAL_NOT_SUBJECT` N1, `QUAL_NOT_SUBJECT_LOCATION` N2 |
| Exención (L10) | `EXEMPT_ARTICLE_20` E1, `EXEMPT_ARTICLE_21` E2, `EXEMPT_ARTICLE_22` E3, `EXEMPT_ARTICLE_23_24` E4, `EXEMPT_ARTICLE_25` E5, `EXEMPT_OTHER` E6 |
| Impuesto (L1) | `TAX_VAT` 01, `TAX_IPSI` 02, `TAX_IGIC` 03, `TAX_OTHER` 05 |
| Identificación extranjera | `ID_TYPE_EU_VAT` 02, `ID_TYPE_PASSPORT` 03, `ID_TYPE_OFFICIAL_DOC` 04, `ID_TYPE_RESIDENCE_CERT` 05, `ID_TYPE_OTHER_DOC` 06, `ID_TYPE_NOT_REGISTERED` 07 |
| Tipo de rectificativa | `RECT_SUBSTITUTION` S, `RECT_DIFFERENCES` I |
| Clave de régimen (L8) | `REGIME_GENERAL` 01, `REGIME_EXPORT` 02, `REGIME_USED_GOODS` 03, `REGIME_INVESTMENT_GOLD` 04, `REGIME_TRAVEL_AGENCIES` 05, `REGIME_VAT_GROUP` 06, `REGIME_CASH_ACCOUNTING` 07, `REGIME_IGIC_IPSI` 08, `REGIME_TRAVEL_INTERMEDIARIES` 09, `REGIME_THIRD_PARTY_COLLECTIONS` 10, `REGIME_BUSINESS_RENTAL` 11, `REGIME_CONSTRUCTION_CERT` 14, `REGIME_SUCCESSIVE_TRACT` 15, `REGIME_OSS_IOSS` 17, `REGIME_EQUIVALENCE_SURCHARGE` 18, `REGIME_AGRICULTURE` 19, `REGIME_SIMPLIFIED` 20 |

Internamente fija `IDVersion` = `1.0` y `TipoHuella` = `01` (SHA-256).

### Construcción y factorías

| Método | Resultado |
|---|---|
| `new Invoice($serial, $issueDate, $issuerTaxId, $issuerName)` | Registro F1 con `IDFactura`, `NombreRazonEmisor`, `FechaHoraHusoGenRegistro` (fecha actual del servidor) y `TipoHuella`. La fecha va en formato `DD-MM-AAAA` |
| `Invoice::createSubsanacion($serial, $date, $taxId, $name, ?bool $priorRejection = false)` | Marca `Subsanacion = S`; con `true` añade `RechazoPrevio = S` (la AEAT rechazó el registro antes) y con `null`, `RechazoPrevio = X` (nunca llegó a la AEAT). Semántica corregida en la 2.0.0 (PR #38) |
| `Invoice::createRectificativa(..., $creditType = 'R1')` | Fija el tipo rectificativo |
| `Invoice::createSimplificada(...)` | Tipo F2 |
| `Invoice::getTiposFactura()` | Tabla código → descripción (en inglés) |

### Métodos principales

| Grupo | Métodos |
|---|---|
| Identificación | `setIssuer($taxId, $name)`, `setInvoiceId($serial, $date)`, `setType($type)`, `setDescription($text)` (`DescripcionOperacion`), `setExternalReference($ref)` (`RefExterna`), `setRectificationType('S'\|'I')` |
| Cabecera | `setIncidence('S'\|'N')` → `RemisionVoluntaria.Incidencia`; `getIncidence()` |
| Destinatarios | `addRecipient($nif, $name)`, `addForeignRecipient($name, $idType, $idNumber, $countryCode)` (con país vacío o `ES` delega en `addRecipient()`), `setRecipients(array)`, `getRecipients()` |
| Desglose | `addDesglose($qualification, $baseAmount, $exemptionCause, $taxSystem, $regime, $rate, $taxAmount, $costBase, $surchargeRate, $surchargeAmount)`, `addTaxLine()`, `addDesgloseIVA()`, `addDesgloseIVAConRecargo()`, `addDesgloseExento()`, `addDesgloseNoSujeto()`, `addDesgloseIGIC()`, `addDesgloseIPSI()`, `addDesgloseOtros()`, `setDesglose(array)` |
| Encadenamiento | `setAsFirstInChain()` → `Encadenamiento.PrimerRegistro = S`; `setChainLink($prevTaxId, $prevSerial, $prevDate, $prevHash)` → `Encadenamiento.RegistroAnterior` (la huella se normaliza a mayúsculas; vacía lanza excepción) |
| Marcas | `setAsCorrection(bool)` (`Subsanacion`), `setAsPreviousRejection(?bool)` (`RechazoPrevio` S/X, implica `Subsanacion`) |
| Sistema informático | `setSystemInfo(array)` (bloque completo, el que usa el módulo), `setSystemIdentity($devTaxId, $name, $version, $id)` (bloque estándar con `NumeroInstalacion = '1'`) |
| Rectificadas | `addRectifiedInvoice($taxId, $serial, $date, $baseRect, $taxRect, $surchargeRect)` → `FacturasRectificadas` e `ImporteRectificacion` acumulados; `getRectifiedInvoices()`, `getRectificationAmounts()`, `hasRectifiedInvoices()`, `getRectificationType()` |
| Tiempo y huella | `setGenerationTimestamp()`, `setTimestampForHash()`, `setFingerprintType()`, `generateHash()`, `generateFingerprint($tz = 'Europe/Madrid')` (renueva el sello de tiempo y calcula la huella) |
| Salida | `validate()`, `getData($forRegistration = true)`, `calculateTotals()`, `toJSON()`, `setInformacionAdicional(array)` |
| Lectura | `getInvoiceNumber()`, `getInvoiceDate()`, `getIssuerNif()`, `getIssuerName()`, `getInvoiceType()`, `getTotalAmount()`, `getTotalTax()`, `getFingerprint()`, `getDescription()`, `getGenerationTimestamp()` |

`addDesglose()` agrupa las líneas por tipo impositivo, recargo y clave de régimen, acumula bases y cuotas redondeando a dos decimales y recalcula `CuotaTotal` (cuota más recargo) e `ImporteTotal` (base más cuota total). Ignora las líneas con base 0 y tipo 0 o nulo. Ver [[Desglose de impuestos|Desglose-de-impuestos]].

### Huella

`generateHash()` calcula `CuotaTotal`/`ImporteTotal`, toma la huella del `RegistroAnterior` (cadena vacía si es primer registro) y aplica SHA-256 en mayúsculas a esta cadena:

```text
IDEmisorFactura=…&NumSerieFactura=…&FechaExpedicionFactura=…&TipoFactura=…&CuotaTotal=…&ImporteTotal=…&Huella=…&FechaHoraHusoGenRegistro=…
```

`getData()` la calcula si no existe todavía. Ver [[Encadenamiento y huella|Encadenamiento-y-huella]].

### validate()

`getData()` y `toJSON()` llaman siempre a `validate()`, que lanza `\InvalidArgumentException` si falla alguna de estas comprobaciones (los límites proceden de `SuministroInformacion.xsd`; incorporadas en la 2.0.0, PR #40):

- Campos obligatorios: `IDVersion`, `IDFactura` (con `NumSerieFactura`, `FechaExpedicionFactura`, `IDEmisorFactura`), `NombreRazonEmisor`, `TipoFactura`, `DescripcionOperacion`, `FechaHoraHusoGenRegistro`, `TipoHuella`.
- `TipoFactura` es uno de los ocho tipos admitidos.
- `NombreRazonEmisor` ≤ 120 caracteres; `DescripcionOperacion` ≤ 500.
- F2 y R5 no llevan destinatarios; el resto lleva al menos uno; nunca más de 1000.
- Entre 1 y 12 líneas de desglose.
- Hay `PrimerRegistro` o `RegistroAnterior`; la huella anterior, y la propia si ya existe, son 64 caracteres hexadecimales en mayúsculas.

`tests/InvoiceValidationTest.php` recorre estos casos. Ver [[Esquemas WSDL y XSD|Esquemas-WSDL-y-XSD]].

## Cancellation

Construye el bloque `RegistroAnulacion`: `IDVersion`, `IDFactura` (`IDEmisorFacturaAnulada`, `NumSerieFacturaAnulada`, `FechaExpedicionFacturaAnulada`), `FechaHoraHusoGenRegistro` (zona `Europe/Madrid`) y `TipoHuella` = `01`.

| Método | Descripción |
|---|---|
| `new Cancellation($invoiceNumberToCancel, $invoiceDateToCancel, $issuerNIFToCancel, $issuerNIF, $issuerName)` | Factura a anular y obligado que remite la anulación |
| `Cancellation::createNormal(...)` | Anulación de un registro aceptado previamente |
| `Cancellation::createForPreviousRejection(...)` | Añade `RechazoPrevio = S` |
| `Cancellation::createWithoutPreviousRecord(...)` | Añade `SinRegistroPrevio = S` (el registro nunca llegó a la AEAT) |
| `setAsPreviousRejection(bool)` / `setWithoutPreviousRecord(bool)` | Marcas mutuamente excluyentes: activar una desactiva la otra |
| `setInvoice($nif, $number, $date)` | Cambia la factura anulada (NIF en mayúsculas) |
| `setChainLink($previousNif, $previousNumber, $previousDate, $previousFingerprint)` / `setAsFirstInChain()` | Encadenamiento. Desde la 2.2.0 el módulo marca la anulación como primer registro cuando la cadena está vacía (PR #39) |
| `setSystemInfo(array)` / `setSystemIdentity(...)` | Bloque `SistemaInformatico` |
| `setAsCorrection(bool)` | `Subsanacion` |
| `setGenerationTimestamp()`, `setFingerprintType()` | Ajustes del registro |
| `generateHash()` / `generateFingerprint($tz)` | Huella SHA-256 en mayúsculas de `IDEmisorFacturaAnulada=…&NumSerieFacturaAnulada=…&FechaExpedicionFacturaAnulada=…&Huella=…&FechaHoraHusoGenRegistro=…` |
| `validate()` | Exige los campos obligatorios y un encadenamiento (anterior o primer registro) |
| `getData($forRegistration = true)` | `Cabecera` + `RegistroFactura.RegistroAnulacion`, calculando la huella si falta |
| `getFingerprint()`, `getGenerationTimestamp()`, `getInvoiceNumber()`, `getInvoiceDate()`, `getEmitterTaxId()`, `getEmitterName()`, `getOperationalFlags()`, `isPreviousRejection()`, `isWithoutPriorRecord()`, `getRawPayload()`, `reset()` | Lectura y reinicio |

El módulo elige la factoría en `createCancellationByType()` según el tipo `normal`, `rechazo_previo` o `sin_registro_previo` que llega del formulario. Ver [[Anulación de facturas|Anulacion-de-facturas]].

## Query

Construye la petición `ConsultaFactuSistemaFacturacion` con `Cabecera` (`IDVersion`, `ObligadoEmision`) y `FiltroConsulta`.

| Método | Descripción |
|---|---|
| `new Query($invoiceSerial = null, $invoiceDate = null, $obligatedTaxId = '', $obligatedName = '', $counterpartyTaxId = null, $counterpartyName = null)` | La fecha, si se indica, debe estar en `d-m-Y` |
| `setIssuer($taxId, $name)`, `setInvoice($serial, $date)` | Obligado y factura objetivo |
| `setFiscalPeriod($year, $month)` | `PeriodoImputacion` (mes con dos dígitos) |
| `setCounterparty($taxId, $name = null)` | `Contraparte` |
| `setDateRange($start, $end)` | `FechaExpedicionFactura.RangoFechaExpedicion` |
| `setSpecificInvoice($serial, $date, $issuerTaxId = null)` | `IDFactura` con `NumSerieFacturaEmisor`, `FechaExpedicionFacturaEmisor` y `NIF` |
| `setFingerprint($hash)` | Filtro por `Huella` |
| `addCustomFilters(array)`, `setAdditionalOptions(array)`, `clearFilters()` | Filtros y parámetros libres |
| `getData()` | Deduce `PeriodoImputacion` de la fecha de la factura objetivo si no se fijó, aplica los filtros del constructor si no se sobrescribieron y devuelve el array completo |
| `getFilters()`, `getInvoiceNumber()`, `getInvoiceDate()`, `getIssuerNif()`, `getIssuerName()`, `getRecipientNif()`, `getRecipientName()`, `getAdditionalOptions()` | Lectura |
| `Query::forPeriod()`, `Query::forInvoice()`, `Query::forCounterparty()` | Factorías |

`functions.query.php` combina `setDateRange()` (día de la factura y el siguiente) con `setSpecificInvoice()` para localizar una factura concreta, y aplica filtros adicionales en PHP sobre la respuesta. Ver [[Consulta a la AEAT|Consulta-a-la-AEAT]].

## QRGenerator

Genera la URL de cotejo del QR tributario y la imagen. Implementa, según su cabecera, el documento de especificaciones técnicas de la AEAT v0.4.7 (17/10/2024).

| Método | Descripción |
|---|---|
| `new QRGenerator(?Config $config = null)` | Sin configuración, o con una que no sea de producción, usa las URL de pruebas |
| `createVerifactuUrl($taxId, $serialNumber, $date, $total, ?bool $useTestEnv = null)` | URL `ValidarQR` con `nif`, `numserie`, `fecha` e `importe` (dos decimales, codificación RFC 3986) |
| `createStandardUrl(...)` | URL `ValidarQRNoVerifactu` |
| `appendJsonFormat($url)` | Añade `formato=json` (no para QR impresos) |
| `renderPng($content, $pixels = 300)`, `renderSvg()`, `renderBase64($content, $pixels, $withPrefix = true)`, `saveToDisk($content, $path, $pixels)` | Render con chillerlan/php-qrcode: versión automática, corrección de errores M, escala `max(1, píxeles / 25)` |
| `invoiceQrPng()`, `invoiceQrBase64()`, `invoiceQrSvg()` | URL y render en una llamada |
| Estáticos: `generateVerifiableUrl($nif, $num, $date, $amount, $isTest = false)`, `generateNonVerifiableUrl()`, `addJsonFormat()`, `generateQRImage()`, `generateBase64QR()`, `saveQRImage()`, `getQRConfiguration()`, `getExamples()`, `generateQRWithInfo()` | Compatibilidad con el código anterior; el parámetro `$margin` se acepta pero no se usa |
| `DIMENSIONS`, `ERROR_CORRECTION` | Tamaño físico 30–40 mm (recomendado 35), margen mínimo 2 mm (recomendado 6), nivel M |

Antes de construir la URL valida los datos: NIF de 9 caracteres (letra de control de DNI y NIE; formato básico de CIF), número de serie de 1 a 60 caracteres ASCII imprimibles, fecha `DD-MM-AAAA` real y parte entera del importe de hasta 12 dígitos. El módulo llama a `generateVerifiableUrl()` y `generateBase64QR()` desde `functions.qr.php`, con `getVerifactuImporteTotal()` como importe y `getEnvironment() === 'test'` como indicador de entorno. En la 2.0.0 se corrigió el tipo del parámetro de salida (`string`, no `int`) que rompía el render con PHP 8 (issue #32). Ver [[Código QR|Codigo-QR]].

## SchemaManager y SoapClient

`SchemaManager` descarga y cachea los siete ficheros WSDL/XSD y reescribe la referencia al esquema del W3C; `SoapClient` construye el `\SoapClient` de PHP con el WSDL local o remoto, el contexto TLS y las trazas, y traduce cualquier `\SoapFault` en `BillingException`. Ambas tienen página propia: [[Esquemas WSDL y XSD|Esquemas-WSDL-y-XSD]] y [[Conexión SOAP y entornos|Conexion-SOAP-y-entornos]].

## BillingException

Extiende `\Exception` y añade categoría, contexto y sello de tiempo ISO 8601.

| Categoría | Constante |
|---|---|
| `validation` | `CATEGORY_VALIDATION` |
| `communication` | `CATEGORY_COMMUNICATION` |
| `authentication` | `CATEGORY_AUTHENTICATION` |
| `processing` | `CATEGORY_PROCESSING` (por defecto) |
| `configuration` | `CATEGORY_CONFIGURATION` |

Factorías estáticas:

| Factoría | Código | Mensaje | Categoría |
|---|---|---|---|
| `invalidTaxId($taxId)` | 400 | `Invalid tax ID: …` | validation |
| `invalidDate($date)` | 400 | `Invalid date format: …` | validation |
| `missingRequiredField($field)` | 400 | `Missing required field: …` | validation |
| `invalidInvoiceData($reason, $data)` | 400 | `Invalid invoice data: …` | validation |
| `soapError($message, $previous)` | 0 | `SOAP error: …` | communication |
| `serviceUnavailable($endpoint)` | 503 | `Service unavailable: …` | communication |
| `authenticationFailed($reason, $details)` | 401 | `Authentication failed: …` | authentication |
| `certificateError($message, $certPath)` | 403 | `Certificate error: …` | authentication |
| `configurationError($message, $info)` | 0 | `Configuration error: …` | configuration |
| `hashGenerationFailed($reason)` | 0 | `Hash generation failed: …` | processing |
| `chainLinkageError($message)` | 0 | `Chain linkage error: …` | processing |
| `processingFailed($message, $context, $previous)` | 0 | el mensaje tal cual | processing |

Métodos de instancia: `getContext()`, `getContextValue($key, $default)`, `hasContextKey()`, `addContext()`, `getCategory()`, `getTimestamp()`, `isValidationError()`, `isCommunicationError()`, `toArray()`, `getErrorDetails()`.

En la práctica, la única que se lanza dentro de la biblioteca es la de `SoapClient::executeOperation()`, que envuelve el `\SoapFault` así: mensaje `AEAT communication failed: <mensaje del fault>`, código `(int) $fault->getCode()` y el fault como excepción previa; conserva la categoría por defecto (`processing`). Las factorías están disponibles para quien use la biblioteca por su cuenta. El resto de errores de la biblioteca son `\InvalidArgumentException` (datos de registro, consulta o QR) y `\RuntimeException` (render del QR). El módulo captura `\Exception` en `execVERIFACTUCall()` y guarda el mensaje en `verifactu_error` con el prefijo `Exception:`.

## Cómo la usa el módulo

| Función del módulo | Clases y métodos de la biblioteca |
|---|---|
| `handleInvoiceCreationOrSubsanation()` (`functions.submission.php`) | `new VerifactuInvoice()` o `VerifactuInvoice::createSubsanacion()`; `setSystemInfo()`, `setDescription()`, `setType()`, `setIncidence()`, `setExternalReference()`, `setRectificationType()`, `addRectifiedInvoice()`, `addRecipient()`/`addForeignRecipient()`, `addDesglose()`, `setChainLink()`/`setAsFirstInChain()`; `VerifactuManager::sendInvoice()`, `getLastGeneratedHash()`, `getLastGeneratedTimestamp()`, `getLastSendInvoiceData()`, `getEnvironment()` |
| `handleInvoiceCancellation()` (`functions.cancellation.php`) | `VerifactuInvoiceCancel::createNormal()`/`createForPreviousRejection()`/`createWithoutPreviousRecord()`; `setChainLink()`/`setAsFirstInChain()`; `VerifactuManager::sendCancellation()` |
| `execVERIFACTUQuery()` y `preCheckInvoiceInAEAT()` (`functions.query.php`) | `new VerifactuInvoiceQuery()`, `setFiscalPeriod()`, `setDateRange()`, `setSpecificInvoice()`, `addCustomFilters()`; `VerifactuManager::queryInvoice()` |
| `getQrImage()`, `getQrBase64()`, `getQRUrl()` (`functions.qr.php`) | `QRGenerator::generateVerifiableUrl()`, `QRGenerator::generateBase64QR()` |
| `admin/setup.php` | `OpenAEAT\Billing\SchemaManager`: `schemasExist()`, `getDownloadMetadata()`, `downloadSchemas(true)` |

## Ejemplo mínimo de uso autónomo

El código permite usar la biblioteca fuera de Dolibarr: basta con el autoload propio (y el de `vendor/` si se generan QR), un certificado en PEM y una carpeta donde cachear los esquemas. El ejemplo envía un alta al entorno de pruebas con certificado de persona física o representante (`normal`); para un certificado de sello, `Manager::forTesting()` ya fija `sello`.

```php
<?php
require_once '/ruta/a/verifactu/lib/newfenix/autoload.php';

use OpenAEAT\Billing\Invoice;
use OpenAEAT\Billing\Manager;

// Bloque SistemaInformatico tal como lo espera la AEAT (mismas claves que getSystemConfig())
$sistemaInformatico = [
    'NombreRazon'                  => 'Razón social del obligado',
    'NIF'                          => 'B00000000',
    'NombreSistemaInformatico'     => 'Dolibarr Verifactu Module', // máximo 30 caracteres
    'IdSistemaInformatico'         => 'DV',                        // máximo 2 caracteres
    'Version'                      => '2.2.4',                     // máximo 50 caracteres
    'NumeroInstalacion'            => 'instalacion-de-prueba',     // máximo 100 caracteres
    'TipoUsoPosibleSoloVerifactu'  => 'S',
    'TipoUsoPosibleMultiOT'        => 'N',
    'IndicadorMultiplesOT'         => 'N',
];

$manager = new Manager('B00000000', 'Razón social del obligado', 'test', 'verifactu', $sistemaInformatico, 'normal');
$manager->setSchemasDir('/ruta/escribible/schemas');

$registro = new Invoice('FA2026-0001', '19-09-2026', 'B00000000', 'Razón social del obligado');
$registro->setType(Invoice::TYPE_STANDARD)
    ->setDescription('Invoice FA2026-0001')
    ->setIncidence('N')
    ->addRecipient('12345678Z', 'Cliente de prueba')
    ->addDesgloseIVA(100.00, 21.0)
    ->setAsFirstInChain(); // o ->setChainLink($nif, $numeroAnterior, $fechaAnterior, $huellaAnterior)

$respuesta = $manager->sendInvoice($registro, [
    'local_cert' => '/ruta/certificado.pem',   // certificado y clave privada en un solo PEM
    'passphrase' => 'contraseña-de-la-clave',
]);

echo $respuesta->EstadoEnvio, PHP_EOL;                 // Correcto | ParcialmenteCorrecto | Incorrecto
echo $manager->getLastGeneratedHash(), PHP_EOL;        // huella: es el RegistroAnterior del siguiente envío
echo $manager->getLastGeneratedTimestamp(), PHP_EOL;   // FechaHoraHusoGenRegistro
```

> **Importante:** la biblioteca no persiste nada. Quien la use por su cuenta debe guardar la huella y el sello de tiempo de cada registro para encadenar el siguiente, serializar los envíos concurrentes y respetar el `TiempoEsperaEnvio` de cada respuesta, que es lo que el módulo hace en `functions.hash.php` y `functions.response.php`. El array de credenciales se fusiona con las opciones de `\SoapClient`, por lo que admite cualquier opción de ese constructor.

## Dependencias y requisitos

Según `composer.json`:

| Requisito | Valor |
|---|---|
| `php` | `>=8.1` (declarado en la biblioteca; el módulo declara PHP 7.4 en su descriptor y el README documenta comprobaciones con PHP 7.4) |
| Extensiones | `ext-soap`, `ext-mbstring`, `ext-json`, `ext-openssl` |
| Sugeridas | `ext-gd` y `chillerlan/php-qrcode ^5.0` para el QR |
| Desarrollo | `phpunit/phpunit ^10.0`, `phpstan/phpstan ^1.10`, `squizlabs/php_codesniffer ^3.7`; scripts `test`, `test-coverage`, `phpstan`, `cs-check`, `cs-fix` (PSR-12) |

La carpeta `vendor/` distribuida contiene `chillerlan/php-qrcode` **5.0.4** (generador de QR, licencias MIT y Apache-2.0; requiere `ext-mbstring`) y su dependencia `chillerlan/php-settings-container` **2.1.6**. Es la única dependencia de terceros del módulo y solo la usa `QRGenerator`. `phpunit.xml` apunta a `tests/Unit` y `tests/Integration`, carpetas que no se distribuyen; las pruebas del módulo son los scripts de `tests/` en la raíz. Ver [[Requisitos|Requisitos]].

## Véase también

- [[Arquitectura del módulo|Arquitectura-del-modulo]] — dónde encaja la biblioteca en el conjunto.
- [[Conexión SOAP y entornos|Conexion-SOAP-y-entornos]] — `SoapClient`, certificados y URL por entorno.
- [[Esquemas WSDL y XSD|Esquemas-WSDL-y-XSD]] — `SchemaManager` y la validación local del registro.
- [[Encadenamiento y huella|Encadenamiento-y-huella]] — cómo usa el módulo `setChainLink()` y la huella.
- [[Tipos de factura|Tipos-de-factura]], [[Desglose de impuestos|Desglose-de-impuestos]], [[Anulación de facturas|Anulacion-de-facturas]], [[Consulta a la AEAT|Consulta-a-la-AEAT]], [[Código QR|Codigo-QR]] — uso de cada clase desde el módulo.
- [[Suite de pruebas|Suite-de-pruebas]] — tests que ejercitan la biblioteca.
- [[Glosario|Glosario]] — términos de los registros (`RegistroAlta`, `Huella`, `RechazoPrevio`…).
