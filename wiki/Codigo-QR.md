# Código QR

El módulo genera, para cada factura enviada correctamente a la AEAT, el código QR de cotejo que exige el RD 1007/2023 y que desarrolla la Orden HAC/1177/2024. El QR codifica una URL del servicio de validación de la AEAT con cuatro parámetros (NIF del emisor, número de serie, fecha e importe) y se muestra en la ficha de la factura, en el PDF, en el ticket del TPV y, opcionalmente, en plantillas ODT. Esta página describe qué contiene exactamente el QR, cuándo se genera, dónde aparece, qué opciones lo controlan y qué funciones del código lo producen.

## Qué contiene el QR

El contenido del QR es siempre una URL. La construye `QRGenerator::generateVerifiableUrl()` (`lib/newfenix/src/QRGenerator.php`) a partir de los datos que le pasa `getQRUrl()` (`lib/functions/functions.qr.php`):

```php
QRGenerator::generateVerifiableUrl(
    $conf->global->VERIFACTU_HOLDER_NIF,   // nif
    $facture->ref,                          // numserie
    date('d-m-Y', $facture->date),          // fecha
    getVerifactuImporteTotal($facture),     // importe
    (getEnvironment() === "test")           // entorno de pruebas sí/no
);
```

### Dirección base según el entorno

| Entorno | URL de cotejo (modalidad VeriFactu) |
|---|---|
| Pruebas | `https://prewww2.aeat.es/wlpl/TIKE-CONT/ValidarQR` |
| Producción | `https://www2.agenciatributaria.gob.es/wlpl/TIKE-CONT/ValidarQR` |

La biblioteca conoce también las direcciones `ValidarQRNoVerifactu` (para sistemas que no remiten registros, método `generateNonVerifiableUrl()`), pero el módulo no las utiliza: la única modalidad disponible en `VERIFACTU_MODE` es `verifactu`.

El entorno se decide en el momento de dibujar el QR mediante `getEnvironment()` (`lib/functions/functions.configuration.php`): devuelve `production` si está activa `VERIFACTU_FORCE_PRODUCTION_ENVIRONMENT`; si no, devuelve `test` hasta el 1 de enero de 2027 (o el 1 de julio de 2027 cuando `VERIFACTU_COMPANY_TYPE` vale `autonomo`), calculado en la zona horaria `Europe/Madrid`. Véase [[Conexión SOAP y entornos|Conexion-SOAP-y-entornos]].

> **Nota:** el QR no lee el campo adicional `verifactu_entorno` de la factura, sino el entorno vigente cuando se genera la imagen. Si cambias de entorno después de haber enviado facturas, el QR de esas facturas pasará a apuntar a la dirección del entorno nuevo.

### Parámetros de la URL

| Parámetro | Origen | Formato |
|---|---|---|
| `nif` | Constante `VERIFACTU_HOLDER_NIF` (ajustes del módulo) | 9 caracteres, en mayúsculas |
| `numserie` | `$facture->ref` (referencia de la factura) | 1 a 60 caracteres ASCII imprimibles |
| `fecha` | `date('d-m-Y', $facture->date)` | `DD-MM-AAAA` |
| `importe` | `getVerifactuImporteTotal($facture)` | Dos decimales con punto, sin separador de miles (`number_format($valor, 2, '.', '')`) |

La cadena de consulta se monta con `http_build_query()` y codificación RFC 3986, de modo que una barra en la referencia se transmite como `%2F` y un espacio como `%20`.

Ejemplo (valores de `QRGenerator::getExamples()`, entorno de pruebas):

```
https://prewww2.aeat.es/wlpl/TIKE-CONT/ValidarQR?nif=89890001K&numserie=12345678-G33&fecha=01-09-2024&importe=241.40
```

### El importe

`getVerifactuImporteTotal()` (`lib/functions/functions.compatibility.php`) devuelve `total_ttc + abs(total_localtax2)`. En Dolibarr la retención de IRPF se almacena como impuesto local 2 con signo negativo y ya está descontada del total; al sumar su valor absoluto se obtiene `Base + IVA + Recargo de equivalencia`, que es el `ImporteTotal` que espera la AEAT. Este cálculo se introdujo en la versión 1.0.4 (antes el QR de las facturas con IRPF llevaba un importe distinto del transmitido). La función no aplica valor absoluto al total, por lo que en un abono el importe del QR conserva el signo negativo del `total_ttc`.

### Comprobaciones previas

Antes de montar la URL, `QRGenerator::checkInputs()` valida los cuatro datos y lanza `InvalidArgumentException` si alguno no es correcto:

- NIF: exactamente 9 caracteres; si tiene formato DNI (8 dígitos y letra) o NIE (X/Y/Z, 7 dígitos y letra) se comprueba la letra de control con la secuencia `TRWAGMYFPDXBNJZSQVHLCKE`; si tiene formato CIF (letra de `ABCDEFGHJNPQRSUVW`, 7 dígitos y dígito o letra A–J de control) solo se comprueba el patrón.
- Número de serie: entre 1 y 60 caracteres, todos en el rango ASCII imprimible (0x20–0x7E).
- Fecha: patrón `DD-MM-AAAA` y fecha válida en el calendario (`checkdate()`).
- Importe: la parte entera no puede superar 12 dígitos.

Si el NIF configurado en `VERIFACTU_HOLDER_NIF` no supera estas comprobaciones, no se genera ningún QR (véase [[Problemas típicos|Codigo-QR#problemas-típicos]]).

## Cuándo se genera y cuándo no

Todos los puntos de salida del QR consultan primero `isInvoiceSentToVerifactu()` (alias histórico `isFactureSendToVerifactu()`, ambas en `lib/functions/functions.qr.php`). La función carga los campos adicionales de la factura y devuelve `true` solo si se cumplen las dos condiciones:

1. El campo `verifactu_csv_factura` (CSV devuelto por la AEAT) no está vacío.
2. El campo `verifactu_estado` contiene literalmente `attr-status="Enviada"`, donde `Enviada` es la traducción de `VERIFACTU_STATUS_SEND` en el idioma activo del usuario.

| Estado VeriFactu de la factura | ¿Se genera el QR? |
|---|---|
| No Enviada (borrador o validada sin remitir) | No |
| Pendiente / Sin Conexión (a la espera de reintento) | No |
| Error (rechazo de la AEAT) | No |
| Enviada, con CSV | Sí |
| Parcial, Enviada (Duplicada), Duplicada (Importes diferentes) | No: el texto de `attr-status` no es exactamente `Enviada` |
| Anulada (baja enviada a la AEAT) | No: la anulación sustituye el estado y el QR deja de generarse; un PDF ya generado conserva el QR que tuviera |

> **Nota:** la comparación se hace con la traducción del idioma activo en la sesión, mientras que el badge se guardó con la traducción vigente en el momento del envío. Si el usuario que consulta la factura trabaja en un idioma distinto del que se usó al enviarla, la condición no se cumple y el QR no se muestra. Los detalles del campo de estado están en [[Campos adicionales|Campos-adicionales]].

## Textos obligatorios

El módulo acompaña el QR de dos textos fijos, escritos tal cual en el código y en las claves de idioma `VerifactuQRTributario` y `VerifactuQRLegend`:

- Encima del QR: **`QR tributario:`** (los comentarios del código lo vinculan al Art. 21 de la Orden HAC/1177/2024).
- Debajo del QR: **`VeriFactu`** (vinculado al Art. 20.1.b de la misma Orden; el código señala que se ha elegido esta leyenda frente a «Factura verificable en la sede electrónica de la AEAT» por ser más compacta).

En la ficha de la factura los textos se muestran siempre. En el PDF dependen de `VERIFACTU_QR_SHOW_TEXT` y en los tickets de TPV de `VERIFACTU_QR_SHOW_TEXT_TPV` (ambas activas al instalar el módulo; el texto de ayuda de los ajustes las califica de obligatorias). En las imágenes para ODT los textos van incrustados en el propio PNG.

## Dónde aparece

### Ficha de la factura

`class/actions_verifactu.class.php` engancha dos puntos del encabezado de la ficha: `formDolBanner()` (zona de referencia que pinta `dol_banner_tab()`) y `moreHtmlStatus()` (zona de estado que pinta `Form::showrefnav()`). Ambos métodos generan el mismo bloque cuando la factura está enviada:

```html
<br><div class="center" style="margin: 5px 0;">
  <div style="font-weight: bold; font-size: 10px; margin-bottom: 2px;">QR tributario:</div>
  <img src="data:image/png;base64,..." class="verifactu-qr-code" style="width:150px; height:150px;" />
  <div style="font-weight: bold; font-size: 9px; margin-top: 2px;">VeriFactu</div>
</div>
```

La imagen se pide con `getQrImage($object, 150, 0)`. Cualquier excepción (NIF inválido, GD ausente, etc.) se captura y se anota en el registro de Dolibarr con el prefijo `ActionsVerifactu::moreHtmlStatus error:` o `ActionsVerifactu::formDolBanner error:`, sin romper la ficha. Véase [[Pestaña VeriFactu de la factura|Pestana-VeriFactu-de-la-factura]].

### PDF de la factura

El QR se inserta después de generar el PDF, con TCPDI y el generador de códigos 2D de TCPDF (`write2DBarcode()` con `QRCODE,M`), sea cual sea la plantilla utilizada. Tamaño, posición, textos y páginas se configuran con las constantes `VERIFACTU_QR_*`. Todo el detalle está en [[Plantilla PDF verifactu|Plantilla-PDF-verifactu]].

El hook `afterPDFCreation` se dispara cuando la plantilla ya ha cerrado y escrito el documento, así que el QR solo puede añadirse reimportando ese PDF. Desde la **2.2.4** ese paso está escalonado (`stampQROnPDF()`, `normalizePDF()` en `class/actions_verifactu.class.php`):

1. Se crea la instancia con `pdf_getInstance()` —que es quien carga TCPDI— y se comprueba **sobre ella** si admite `setSourceFile()` e `importPage()`. Antes se preguntaba por `class_exists('TCPDI')` antes de crearla, de modo que la comprobación podía contestar que no aunque la biblioteca estuviera disponible.
2. El resultado se escribe en un temporal y solo reemplaza al PDF de la factura si existe, empieza por `%PDF-` y no está truncado. Un fallo a mitad de escritura ya no se lleva por delante el documento bueno.
3. Si la importación falla —hay documentos que el parser de TCPDF no sabe leer y que terminan en `TCPDF_PARSER ERROR: decodeFilterFlateDecode: invalid code`—, se reintenta sobre una copia normalizada con Ghostscript o `qpdf`. Ninguna de las dos es dependencia del módulo: se usan solo en ese segundo intento, con la opción `VERIFACTU_PDF_NORMALIZER`, y donde `exec()` está deshabilitado el reintento no existe.
4. Si tampoco así se consigue, se avisa en pantalla (`VERIFACTU_PDF_WITHOUT_QR`) y en el registro, porque el QR es parte obligatoria de la factura (Art. 20.1 y 21 de la Orden HAC/1177/2024).

Todo el proceso captura `Throwable` y no solo `Exception`: en PHP 8 un método que no existe es un `Error`, y ese caso interrumpía la generación del documento.

El diagnóstico de este fallo, con la plantilla Crabe sobre Dolibarr 22.0.3 y TCPDF 6.7.5, es de **[@perey2002](https://github.com/perey2002)** (Sergio Pereiro) en la [issue #55](https://github.com/garacil/verifactu/issues/55).

### Ticket de TPV

- TakePOS (núcleo): el hook `TakeposReceipt()` reconstruye el ticket y coloca el QR en la cabecera, con `getQrImage($object, 120)`.
- Módulo POS externo (`pos/frontend/tpl/facture.tpl.php`): la petición se redirige a `views/pos.facture.php`, que sitúa el QR en la esquina superior derecha con `getQrImage($object, 100)`.

En ambos casos los textos se muestran si `VERIFACTU_QR_SHOW_TEXT_TPV` está activa. Véase [[TakePOS|TakePOS]].

### Plantillas ODT

El hook `ODTSubstitution()` pone a disposición de las plantillas ODT tres imágenes PNG (30, 35 y 40 mm) con los textos incrustados, bajo las claves `verifactuqrcode30_logo`, `verifactuqrcode35_logo` y `verifactuqrcode40_logo`. Véase [[Sustituciones ODT y plantillas|Sustituciones-ODT-y-plantillas]].

### Listados y enlaces

El hook `getNomUrl()` añade, junto a cualquier enlace a una factura, el icono del módulo (`img/object_verifactu.png`) y el badge de estado almacenado en `verifactu_estado`. No incluye el QR.

## Opciones de configuración

Las opciones se editan en el apartado «Código QR» de los ajustes del módulo (`admin/setup.php`), que solo se muestra una vez completados razón social, NIF y certificado. Las constantes se crean en `core/modules/modVerifactu.class.php` al activar el módulo con los valores de la columna «Al activar». Véase [[Configuración|Configuracion]].

| Constante | Etiqueta en ajustes | Valores | Al activar | Reserva en código | Dónde se aplica |
|---|---|---|---|---|---|
| `VERIFACTU_QR_SIZE` | Tamaño (mm) | Desplegable de 30 a 40 mm | `30` | 35 mm si está vacía | PDF |
| `VERIFACTU_QR_POSITION_X` | Posición horizontal | `left`, `center`, `right`, `custom` (también admite un número en mm) | `custom` | `center` | PDF |
| `VERIFACTU_QR_POSITION_X_CUSTOM` | Posición X personalizada (mm) | Desplegable de 0 a 210 mm | `70` | 50 mm si `custom` y vacía | PDF |
| `VERIFACTU_QR_POSITION_Y` | Posición vertical (mm) | Desplegable de 0 a 100 mm | `2` | 15 mm solo para valores negativos | PDF |
| `VERIFACTU_QR_SHOW_TEXT` | Mostrar texto | No / Sí | `1` | Desactivado si está vacía | PDF |
| `VERIFACTU_QR_SHOW_TEXT_TPV` | Mostrar texto en TPV | No / Sí | `1` | Desactivado si está vacía | Ticket TakePOS y POS externo |
| `VERIFACTU_QR_TEXT_SIZE` | Tamaño de texto | Campo libre (puntos) | `8` | 8 | PDF (tamaño de fuente) y ODT (fuente GD) |
| `VERIFACTU_QR_ALL_PAGES` | QR en todas las páginas | No / Sí | No se crea | Solo primera página | PDF |
| `VERIFACTU_PDF_NORMALIZER` | Herramienta para normalizar PDF | Vacío (busca `gs` o `qpdf`), ruta de un ejecutable, u `off` | Vacío | Búsqueda automática | PDF, solo si la reimportación falla (2.2.4) |

Observaciones que se desprenden del código:

- El QR de la ficha de la factura (150 px) y el de los tickets (120 y 100 px) tienen tamaño fijo; `VERIFACTU_QR_SIZE` solo afecta al PDF.
- No existe un interruptor para desactivar el QR en el PDF ni en la ficha: existe la clave de idioma `VERIFACTU_SHOW_QR_IN_PDF` («Mostrar QR en PDF»), pero ningún fichero del módulo la utiliza. Si la factura está enviada, el QR se añade.
- El texto de ayuda de `VERIFACTU_QR_SIZE` recuerda que la AEAT especifica 30–40 mm (recomendado 35); `QRGenerator::DIMENSIONS` recoge los mismos valores (`size_min` 30, `size_max` 40, `size_recommended` 35, `margin_min` 2, `margin_recommended` 6).
- `views/pos.facture.php` lee `VERIFACTU_QR_TEXT_SIZE` pero no la utiliza; el tamaño del texto del ticket está fijado en su hoja de estilos.

## Funciones del módulo (`lib/functions/functions.qr.php`)

Todas reciben un objeto `Facture` y se cargan a través de `lib/verifactu.lib.php`.

| Función | Devuelve |
|---|---|
| `getQRUrl(Facture $facture)` | La URL de cotejo (cadena). |
| `getQrBase64(Facture $facture, $size = 300, $margin = 0)` | La imagen PNG como URI de datos `data:image/png;base64,...`. |
| `getQrImage(Facture $facture, $size = 300, $margin = 0)` | Una etiqueta `<img>` lista para insertar en HTML. |
| `isInvoiceSentToVerifactu(Facture $facture)` | `true` si la factura consta como enviada con CSV. |
| `isFactureSendToVerifactu(Facture $facture)` | Alias de compatibilidad de la anterior. |

`getQrImage()` construye la etiqueta así:

```php
$style = 'width:' . $size . 'px; height:' . $size . 'px;';
if ($margin > 0) {
    $style .= ' margin:' . $margin . 'px;';
}
return '<img src="' . $image . '" id="' . newToken() . '" class="verifactu-qr-code" style="' . $style . '" />';
```

- `$size` es el tamaño en píxeles con el que se muestra la imagen; el PNG se genera con ese valor como referencia de escala y la etiqueta fuerza el ancho y alto por CSS.
- `$margin` se traduce en un margen CSS alrededor de la imagen; el mismo valor se pasa a `generateBase64QR()`, donde se ignora (el parámetro se mantiene solo por compatibilidad).
- El atributo `id` se rellena con el token de sesión (`newToken()`), y la clase `verifactu-qr-code` permite aplicar estilos propios.

## La clase `QRGenerator`

Vive en `lib/newfenix/src/QRGenerator.php`, dentro del espacio de nombres `OpenAEAT\Billing`; `lib/newfenix/autoload.php` registra el alias `Sietekas\Verifactu\QRGenerator`, que es el nombre que usa `functions.qr.php`. La cabecera del fichero indica que implementa el documento de especificaciones técnicas de la AEAT v0.4.7 (17/10/2024). Véase [[Biblioteca newfenix|Biblioteca-newfenix]].

### Generación de la imagen

El renderizado lo hace `renderQrCode()` con la biblioteca `chillerlan/php-qrcode` (versión 5.0.4, incluida en `lib/newfenix/vendor/`):

```php
$scale = max(1, (int) ($pixels / 25));
$opts = new QROptions([
    'version'          => QRCode::VERSION_AUTO,
    'outputType'       => $outputType,     // QRCode::OUTPUT_IMAGE_PNG u OUTPUT_MARKUP_SVG
    'eccLevel'         => QRCode::ECC_M,   // corrección de errores M (15 %)
    'scale'            => $scale,
    'imageBase64'      => false,
    'imageTransparent' => false,           // fondo blanco
]);
```

- La versión del QR se elige automáticamente según la longitud de la URL.
- El nivel de corrección es M, el mismo que usa `write2DBarcode()` en el PDF (`QRCODE,M`).
- El parámetro de píxeles se convierte en una escala (píxeles por módulo); el tamaño real del PNG depende de la versión del QR resultante.
- Cualquier fallo de la biblioteca se relanza como `RuntimeException` con el mensaje `QR generation failed: ...`.

### Métodos disponibles

| Método | Uso |
|---|---|
| `createVerifactuUrl()`, `createStandardUrl()` | URL de cotejo VeriFactu o no VeriFactu (instancia). |
| `generateVerifiableUrl()`, `generateNonVerifiableUrl()` | Versiones estáticas de las anteriores; el módulo usa la primera. |
| `appendJsonFormat()`, `addJsonFormat()` | Añaden `formato=json` a la URL; el propio código advierte de que no debe usarse en QR impresos. |
| `renderPng()`, `renderSvg()`, `renderBase64()`, `saveToDisk()` | Salida PNG binaria, SVG, URI de datos o fichero. |
| `generateQRImage()`, `generateBase64QR()`, `saveQRImage()` | Versiones estáticas; su parámetro `$margin` no se utiliza. |
| `invoiceQrPng()`, `invoiceQrBase64()`, `invoiceQrSvg()` | URL más imagen en una sola llamada. |
| `getQRConfiguration()` | Array con los parámetros de generación y las recomendaciones físicas de la AEAT. |
| `getExamples()` | Cuatro URL de ejemplo (verificable / no verificable, pruebas / producción). |
| `generateQRWithInfo()` | Imagen más metadatos (tamaño en bytes, tiempo de generación, longitud de la URL). |

## Requisitos

- Extensión PHP **GD**: la salida PNG de `chillerlan/php-qrcode` se apoya en GD, y el hook ODT usa además `imagecreatefrompng()`, `imagestring()` e `imagecopy()`. El README la lista entre los requisitos y `lib/newfenix/composer.json` la sugiere para la generación de QR. La prueba `tests/IssueFixesTest.php` omite los casos de renderizado si GD no está cargada.
- El QR del PDF no depende de GD ni de chillerlan: lo dibuja TCPDF a partir de la misma URL.

Véase [[Requisitos|Requisitos]].

## Problemas típicos

**El QR no aparece en la ficha ni en el PDF.** Comprueba que la factura tiene CSV y estado «Enviada» en la pestaña VeriFactu; con estado Error, Pendiente o Anulada el QR no se genera. Si el estado es correcto, revisa el idioma de la sesión (véase la nota de la sección anterior) y el registro de Dolibarr en busca de `moreHtmlStatus error` o `formDolBanner error`.

**`Tax ID must be 9 characters` o `Invalid DNI control letter` en el registro.** El NIF configurado en `VERIFACTU_HOLDER_NIF` no supera las comprobaciones de `checkTaxId()`. Corrígelo en los ajustes; ten en cuenta que, una vez existe la primera huella, el NIF queda bloqueado (véase [[Configuración|Configuracion]]).

**`QR generation failed: ...`.** La biblioteca de QR no ha podido generar la imagen; lo habitual es que falte la extensión GD.

**El PDF se generó antes del envío.** El módulo no regenera el PDF por su cuenta; si la factura se envió después de generar el documento (envío manual o cola de reintentos), vuelve a generar el PDF desde la ficha. Véase [[Plantilla PDF verifactu|Plantilla-PDF-verifactu]].

**El QR está en la ficha pero no en el PDF, y el registro dice `decodeFilterFlateDecode: invalid code`.** El parser de TCPDF no consigue reimportar el documento que ha escrito la plantilla. Corregido en la 2.2.4 (issue #55): se reintenta sobre una copia normalizada y, si no se consigue, se avisa en pantalla en lugar de entregar la factura sin QR. Véase la opción `VERIFACTU_PDF_NORMALIZER`.

**`TypeError` al abrir la ficha de la factura (versiones anteriores a 2.0.0).** `QRGenerator::renderQrCode()` declaraba el parámetro `$outputType` como `int`, pero las constantes `QRCode::OUTPUT_IMAGE_PNG` y `QRCode::OUTPUT_MARKUP_SVG` de chillerlan son cadenas. Con `declare(strict_types=1)`, PHP 8 rechazaba todas las llamadas y rompía la ficha de la factura y la pestaña VeriFactu (el PDF no se veía afectado porque usa `TCPDF::write2DBarcode()`). Se corrigió en la versión 2.0.0 (issue #32) declarando el parámetro como `string`; `tests/IssueFixesTest.php` comprueba el tipo por reflexión.

**Pantalla en blanco al ver facturas.** El README recomienda revisar el registro de errores de PHP y confirmar que todas las extensiones requeridas están instaladas. Véase [[Resolución de problemas|Resolucion-de-problemas]].

## Véase también

- [[Plantilla PDF verifactu|Plantilla-PDF-verifactu]]
- [[TakePOS|TakePOS]]
- [[Sustituciones ODT y plantillas|Sustituciones-ODT-y-plantillas]]
- [[Pestaña VeriFactu de la factura|Pestana-VeriFactu-de-la-factura]]
- [[Configuración|Configuracion]]
- [[Conexión SOAP y entornos|Conexion-SOAP-y-entornos]]
- [[Biblioteca newfenix|Biblioteca-newfenix]]
- [[Campos adicionales|Campos-adicionales]]
- [[Marco normativo|Marco-normativo]]
- [[Resolución de problemas|Resolucion-de-problemas]]
