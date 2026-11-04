# Sustituciones ODT y plantillas

Dolibarr permite generar facturas a partir de plantillas de LibreOffice (ODT/ODS) en las que las etiquetas `{clave}` se sustituyen por datos del documento. El módulo aporta a ese mecanismo tres imágenes del código QR de cotejo, listas para insertar en la plantilla, y una etiqueta de texto con el nombre de la pestaña VeriFactu. Esta página lista las etiquetas exactas, explica cómo se generan las imágenes, cómo insertarlas en una plantilla ODT y qué limitaciones tiene el sistema.

## Por qué hace falta un mecanismo propio para ODT

El QR del PDF se añade a posteriori con TCPDI desde el hook `afterPDFCreation` ([[Plantilla PDF verifactu|Plantilla-PDF-verifactu]]). El generador ODT del núcleo (`core/modules/facture/doc/doc_generic_invoice_odt.modules.php`) no dispara ese hook, sino `afterODTCreation`, tanto si entrega el ODT como si lo convierte a PDF con `MAIN_ODT_AS_PDF`. Por eso el módulo entrega el QR como imagen a través del hook `ODTSubstitution`, que sí ejecuta el generador ODT.

## Dónde interviene el módulo

| Pieza | Fichero | Activación |
|---|---|---|
| Función de sustitución de texto | `core/substitutions/functions_verifactu.lib.php` | `'substitutions' => 1` en `module_parts` del descriptor |
| Hook `ODTSubstitution` | `ActionsVerifactu::ODTSubstitution()` en `class/actions_verifactu.class.php` | Contexto `odtgeneration` declarado en `module_parts['hooks']` |

## Etiquetas disponibles

### Texto

| Etiqueta en la plantilla | Contenido | Origen |
|---|---|---|
| `{FACTUREVERIFACTUTAB}` | Traducción de `FACTURE_VERIFACTU_TAB` («VeriFactu» en es_ES) | `verifactu_completesubstitutionarray()`; solo cuando el objeto es una `Facture` |

La función es mínima:

```php
function verifactu_completesubstitutionarray(&$substitutionarray, $langs, $object, $parameters = null)
{
    if ($object instanceof Facture) {
        $substitutionarray['FACTUREVERIFACTUTAB'] = $langs->trans("FACTURE_VERIFACTU_TAB");
    }
}
```

Su finalidad principal no son las plantillas: el descriptor del módulo declara la pestaña de la factura con el título `SUBSTITUTION_FACTUREVERIFACTUTAB`, y Dolibarr resuelve ese prefijo llamando a las funciones de sustitución de los módulos. Como `complete_substitutions_array()` también se ejecuta al generar documentos ODT, la clave queda disponible en las plantillas, aunque su utilidad allí es limitada.

### Imágenes del QR

| Etiqueta en la plantilla | Tamaño nominal | Píxeles del QR (96 ppp) | Contenido |
|---|---|---|---|
| `{verifactuqrcode30_logo}` | 30 mm | 113 px | Texto «QR tributario:», QR y texto «VeriFactu», sobre fondo blanco |
| `{verifactuqrcode35_logo}` | 35 mm | 132 px | Igual, tamaño recomendado por la AEAT |
| `{verifactuqrcode40_logo}` | 40 mm | 151 px | Igual, tamaño máximo |

Las tres etiquetas existen siempre que se genera un documento: si la factura consta como enviada, contienen la ruta de un fichero PNG temporal; si no, contienen una cadena vacía.

> **Importante:** el sufijo `_logo` no es decorativo. El generador ODT del núcleo trata como imagen únicamente las claves cuyo nombre termina en `logo` (`preg_match('/logo$/', $key)`); el resto las inserta como texto.

## Cómo se generan las imágenes

`ODTSubstitution()` se ejecuta en las dos llamadas al hook que hace el generador ODT (una antes de abrir la plantilla y otra con el array completo de sustituciones). Para una `Facture` que cumpla `isFactureSendToVerifactu()`:

1. Genera el QR base con `getQrBase64($factura, 300, 0)` ([[Código QR|Codigo-QR]]), lo decodifica y lo guarda en `sys_get_temp_dir() . '/qr_verifactu_base.png'`.
2. Carga `core/lib/images.lib.php` del núcleo y calcula la fuente GD para los textos a partir de `VERIFACTU_QR_TEXT_SIZE`:

   | `VERIFACTU_QR_TEXT_SIZE` | Fuente GD (`imagestring()`) |
   |---|---|
   | 8 o menos (valor por defecto: 8) | 3 |
   | 9 a 11 | 4 |
   | 12 o más | 5 |

3. Para cada tamaño (30, 35 y 40 mm):
   - Calcula los píxeles: `round(mm × 96 / 25,4)`.
   - Redimensiona el QR base con `dol_imageResizeOrCrop($original, 0, $px, $px, 0, 0, $destino, 100)` a `qr_verifactu_<mm>mm.png`; si la función devuelve un error, usa el QR base sin redimensionar.
   - Crea con GD una imagen nueva en color verdadero, fondo blanco, cuyo ancho es el mayor entre el QR y los dos textos, y cuya altura suma texto superior, margen, QR, margen y texto inferior. El margen entre texto y QR es `max(2, 2 % de los píxeles del QR)`.
   - Dibuja centrados el texto superior (`VerifactuQRTributario`, «QR tributario:»), el QR y el texto inferior (`VerifactuQRLegend`, «VeriFactu»), en negro.
   - Guarda el resultado en `qr_verifactu_<mm>mm_final.png` y asigna esa ruta a `$parameters['substitutionarray']['verifactuqrcode<mm>_logo']`.
   - Si la composición falla, asigna la ruta del QR base sin textos y anota `Error creating QR with text for <mm>mm` en el registro.
4. Si la factura no está enviada (o el objeto no es una `Facture`), asigna `''` a las tres claves.

Después, el generador del núcleo recorre el array: para cada clave terminada en `logo` comprueba `file_exists()`; si el fichero existe llama a `$odfHandler->setImage($clave, $ruta)`, y si no, sustituye la etiqueta por el texto `ErrorFileNotFound`.

### Cómo inserta la imagen odtphp

`Odf::setImage()` (`includes/odtphp/odf.php`) mide el PNG con `getimagesize()`, convierte los píxeles a centímetros con la constante `PIXEL_TO_CM = 0.026458333` (equivalente a 96 ppp) y sustituye la etiqueta `{clave}` por un marco `draw:frame` anclado como carácter (`text:anchor-type="aschar"`) que referencia la imagen copiada dentro del documento. Por tanto:

- El QR de 35 mm se inserta con 132 px × 0,026458333 ≈ 3,49 cm de ancho, más el alto de los dos textos.
- La imagen ocupa el lugar exacto de la etiqueta y fluye con el texto, así que conviene colocarla en un párrafo o celda propios, con la alineación deseada.

## Insertar el QR en una plantilla ODT

1. Abre la plantilla de factura en LibreOffice Writer (o crea una a partir de la de ejemplo de Dolibarr, `install/doctemplates/invoices/template_invoice.odt`).
2. Escribe la etiqueta como texto normal donde deba aparecer el QR, por ejemplo en una celda de la tabla de cabecera:

   ```
   {verifactuqrcode35_logo}
   ```

   No uses el cuadro de diálogo de insertar imagen ni pongas la etiqueta dentro de un campo: debe ser texto plano y completo, sin formato que parta la cadena.
3. Guarda la plantilla en una de las carpetas que lista la constante `FACTURE_ADDON_PDF_ODT_PATH` (se edita en Inicio > Configuración > Módulos/Aplicaciones > Facturas y abonos > Configuración, tabla «Plantillas de documentos», fila «ODT/ODS templates») y activa esa fila si no lo está.
4. Genera el documento desde una factura ya enviada a la AEAT: la etiqueta se sustituye por la imagen con los textos incrustados.

Ejemplo de cabecera de plantilla que combina etiquetas del núcleo con la del módulo:

```
Factura {object_ref}                 {verifactuqrcode35_logo}
Fecha: {object_date}
CSV AEAT: {object_options_verifactu_csv_factura}
```

### Etiquetas del núcleo con datos VeriFactu

El núcleo expone los campos adicionales del objeto como `{object_options_<nombre>}` (método `fill_substitutionarray_with_extrafields()` de `CommonDocGenerator`; en los campos de tipo lista sustituye el código por su etiqueta y formatea las fechas). Esto no depende del módulo, pero permite imprimir en la plantilla los datos fiscales que el módulo guarda en la factura, por ejemplo:

| Etiqueta | Campo |
|---|---|
| `{object_options_verifactu_csv_factura}` | CSV devuelto por la AEAT |
| `{object_options_verifactu_id_factura}` | Identificador de la factura en VeriFactu |
| `{object_options_verifactu_fecha_factura}` | Fecha de emisión registrada |
| `{object_options_verifactu_huella}` | Huella SHA-256 del registro |
| `{object_options_verifactu_fecha_hora_generacion}` | Fecha y hora de generación de la huella |
| `{object_options_verifactu_factura_tipo}` | Tipo de factura (F1, F2, R1, R5…) |
| `{object_options_verifactu_impuesto}`, `{object_options_verifactu_clave_regimen}`, `{object_options_verifactu_calificacion_operacion}`, `{object_options_verifactu_operacion_exenta}` | Datos fiscales del desglose |

Evita `{object_options_verifactu_estado}`: ese campo almacena un fragmento HTML (el badge de estado), que en el ODT aparecería como texto con etiquetas. La relación completa de campos está en [[Campos adicionales|Campos-adicionales]].

## Limitaciones

- **`ErrorFileNotFound` en facturas no enviadas.** Como las claves se rellenan con `''` cuando la factura no consta como enviada, el generador del núcleo imprime el texto `ErrorFileNotFound` en lugar de la imagen. Si generas ODT de borradores o de facturas pendientes, ten en cuenta ese texto; una vez enviada, vuelve a generar el documento.
- **Solo tres tamaños.** No hay etiqueta para otros tamaños ni para el QR sin textos; los textos van incrustados en el PNG y no se pueden desactivar para ODT.
- **Fuentes de mapa de bits.** Los textos se dibujan con las fuentes internas de GD (`imagestring()`), no con la tipografía del documento; el tamaño depende de la tabla de `VERIFACTU_QR_TEXT_SIZE` y no escala con la imagen.
- **Sin etiqueta para la URL ni para el CSV desde el hook.** El hook solo publica las tres claves de imagen; la URL de cotejo no se ofrece como texto. El CSV se puede imprimir con la etiqueta del núcleo indicada arriba.
- **Ficheros temporales de nombre fijo.** Las imágenes se escriben en el directorio temporal del sistema con nombres fijos (`qr_verifactu_base.png`, `qr_verifactu_35mm.png`, `qr_verifactu_35mm_final.png`, etc.), que se sobrescriben en cada generación.
- **Excepciones al generar el QR base.** La llamada a `getQrBase64()` no está dentro del bloque `try` del hook; si falla la generación del QR (por ejemplo, NIF del emisor inválido), la excepción interrumpe la generación del documento.
- **Requiere GD.** Además de la biblioteca de QR, el hook usa `imagecreatefrompng()`, `imagecreatetruecolor()`, `imagestring()`, `imagecopy()` e `imagepng()`. Véase [[Requisitos|Requisitos]].
- **Solo facturas de cliente.** El hook comprueba `instanceof Facture`; en generadores ODT de otros objetos (pedidos, presupuestos) las claves quedan vacías, y usar allí la etiqueta produce `ErrorFileNotFound`.
- **El entorno se decide al generar.** Como en el resto de salidas, la URL del QR usa el entorno vigente (`getEnvironment()`), no el guardado en la factura.

## Depuración

Si la etiqueta no se sustituye como esperas, revisa el registro de Dolibarr ([[Registro y depuración|Registro-y-depuracion]]):

| Mensaje | Origen | Significado |
|---|---|---|
| `Error generating base QR code: ...` | Hook del módulo | No se pudo escribir el PNG base en el directorio temporal |
| `Error creating QR with text for <mm>mm: ...` | Hook del módulo | Falló la composición con GD; se usa el QR sin textos |
| Texto `ErrorFileNotFound` en el documento | Generador ODT del núcleo | La clave `_logo` no apuntaba a un fichero existente (factura no enviada o fichero temporal no creado) |
| `OdfException` anotada con nivel `LOG_INFO` | Generador ODT del núcleo | La etiqueta no existe en la plantilla o la imagen no es válida (`Invalid image`); el núcleo continúa con el resto de claves |

Comprobaciones rápidas:

- La factura debe tener CSV y estado «Enviada»; sin eso las claves llegan vacías.
- El directorio que devuelve `sys_get_temp_dir()` debe ser escribible por el usuario del servidor web.
- La etiqueta debe estar escrita de una sola pieza en el ODT; LibreOffice puede partir el texto en varios tramos si se aplican formatos distintos a parte de la etiqueta.

## Véase también

- [[Código QR|Codigo-QR]]
- [[Plantilla PDF verifactu|Plantilla-PDF-verifactu]]
- [[Registro y depuración|Registro-y-depuracion]]
- [[Campos adicionales|Campos-adicionales]]
- [[Pestaña VeriFactu de la factura|Pestana-VeriFactu-de-la-factura]]
- [[Configuración|Configuracion]]
- [[Requisitos|Requisitos]]
- [[Arquitectura del módulo|Arquitectura-del-modulo]]
