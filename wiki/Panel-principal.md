# Panel principal

La entrada «Verifactu» del menú superior abre `verifactuindex.php`, un panel de solo lectura que resume el estado de las facturas de la entidad activa frente a VeriFactu: gráficos por mes y por estado, últimas facturas con actividad, facturas con error y facturas validadas que aún no tienen estado VeriFactu. Esta página describe cada bloque, las consultas SQL que hay detrás, los filtros por entidad y los enlaces a los listados, además de la corrección de la versión 2.2.1 y del papel de los ficheros `css/verifactu.css.php` y `js/verifactu.js.php` en la interfaz.

## Acceso y menú

El descriptor del módulo (`core/modules/modVerifactu.class.php`) declara el menú superior `verifactu` (título «Verifactu», URL `/verifactu/verifactuindex.php`) y estas entradas del menú lateral, todas condicionadas a `$user->rights->verifactu->manage || $user->admin` y reservadas a usuarios internos:

| Entrada | Clave de idioma | Página |
|---|---|---|
| Declaración responsable | `VERIFACTU_DECLARATION_MENU` | `views/declaration.php` |
| Facturas emitidas | `VERIFACTU_SEND_INVOICES_MENU` | `views/list.facture.php` |
| Consulta AEAT | `VERIFACTU_SEND_INVOICES_QUERY_MENU` | `views/query.facture.php` |
| Documentación | `VERIFACTU_DOCUMENTATION_MENU` | `views/documentation.php` |
| Ayuda | `VERIFACTU_HELP_MENU` | `views/faq.php` |
| Configuración | `VERIFACTU_SETUP` | `admin/setup.php` |

Véase [[Permisos y menús|Permisos-y-menus]]. El título de la página es «Área VeriFactu» (`VerifactuArea`) con el icono `bill`, y carga los ficheros de idioma `verifactu@verifactu`, `bills` y `companies`.

## Consultas (solo lectura)

Todas las consultas unen `llx_facture` (`f`) con `llx_facture_extrafields` (`fe`) por `f.rowid = fe.fk_object` y filtran por **`f.entity = $conf->entity`**: el panel muestra únicamente la entidad activa, en línea con el aislamiento entre entidades introducido en la 2.0.0 ([[Multiempresa|Multiempresa]]). Ninguna consulta escribe en la base de datos.

| Nº | Variable | Consulta | Uso en la vista |
|---|---|---|---|
| 1 | `$invoicesByStatus` | `SELECT fe.verifactu_estado, COUNT(*) ... WHERE fe.verifactu_estado IS NOT NULL AND fe.verifactu_estado != '' GROUP BY fe.verifactu_estado ORDER BY total DESC` | Gráfico «Distribución por estado» |
| 2 | `$totalErrors` | `SELECT COUNT(*) ... WHERE fe.verifactu_error IS NOT NULL AND fe.verifactu_error != '' AND f.fk_statut > 0` | Contador del bloque «Con errores» |
| 3 | `$invoicesByMonth` | `SELECT f.datef ... WHERE fe.verifactu_estado no vacío AND f.datef >= primer día del año AND f.datef <= último día del año` | Gráfico «Por mes» |
| 4 | `$lastSentInvoices` | `SELECT f.rowid, f.ref, f.datef, f.total_ttc, fe.verifactu_estado, fe.verifactu_ultimafecha_modificacion, s.rowid, s.nom ... LEFT JOIN llx_societe s ... WHERE fe.verifactu_estado no vacío ORDER BY fe.verifactu_ultimafecha_modificacion DESC LIMIT 5` | Tabla «Últimas enviadas» |
| 5 | `$errorInvoices` | Igual que la 2, con datos de factura y tercero, `ORDER BY f.datef DESC LIMIT 5` | Tabla «Con errores» |
| 6 | `$pendingInvoices` | `... LEFT JOIN llx_facture_extrafields ... WHERE f.fk_statut = 1 AND (fe.verifactu_estado IS NULL OR fe.verifactu_estado = '') ORDER BY f.datef DESC LIMIT 5` | Tabla «Pendientes de envío» |
| 7 | `$totalPending` | `SELECT COUNT(*)` con los mismos criterios que la 6 | Contador y enlace «Más…» |

Dos precisiones sobre el criterio «estado no vacío»:

- El campo `verifactu_estado` guarda el badge HTML del estado ([[Campos adicionales|Campos-adicionales]]). Al activar el módulo se asigna el badge «No Enviada» a todas las facturas de la entidad que no tuvieran estado, y `billCreate()` lo asigna a cada factura nueva. Por tanto las consultas 1, 3 y 4 abarcan todas las facturas con algún estado VeriFactu registrado, incluidas las «No Enviada»; el bloque «Pendientes de envío» solo recoge facturas validadas cuya fila de campos adicionales carece de estado.
- El gráfico mensual resuelve el mes en PHP: recupera `f.datef`, lo convierte con `$db->jdate()` y cuenta con `date('n')`. Hasta la 2.1.0 usaba `MONTH()` y `YEAR()` en SQL, funciones que Dolibarr no traduce y que dejaban el gráfico vacío en PostgreSQL.

La página incluye la comprobación estándar de usuario externo (`$user->socid`), pero no aplica el tercero como filtro a las consultas; el acceso queda limitado por el permiso del menú.

## Bloques del panel

La vista usa la rejilla estándar de Dolibarr: un tercio a la izquierda para los gráficos y dos tercios a la derecha para las tablas.

### Por mes (columna izquierda)

- Título: «Por mes» (`VERIFACTU_STAT_BY_MONTH`) seguido del año en curso.
- Gráfico de barras `DolGraph` de 380 × 180 px, identificador `verifactu_by_month`, leyenda `NbOfInvoices` del núcleo, mínimo 0 y máximo redondeado con `GetCeilMaxValue()`.
- Doce barras con las abreviaturas de mes escritas en el código (`Ene`, `Feb`, `Mar`, `Abr`, `May`, `Jun`, `Jul`, `Ago`, `Sep`, `Oct`, `Nov`, `Dic`), sin traducción.
- Datos: número de facturas con estado VeriFactu por mes de `datef` del año en curso (consulta 3).

### Distribución por estado (columna izquierda)

- Solo se muestra si la consulta 1 devuelve filas. Título `VERIFACTU_STAT_STATUS_DISTRIBUTION`.
- Gráfico de barras de 380 × 150 px, identificador `verifactu_by_status`.
- Como el estado se guarda como HTML, la etiqueta de cada barra se extrae del badge: primero el valor del atributo `attr-status`, si no el texto entre `>` y `<`, si no `strip_tags()`, y `Desconocido` si queda vacío. Los recuentos de badges distintos con la misma etiqueta se suman.

### Últimas enviadas (columna derecha)

- Título `VERIFACTU_LAST_SENT_INVOICES` con un badge que indica cuántas filas se muestran (máximo 5).
- Columnas: `Ref` (enlace a la factura mediante `getNomUrl()`, al que el hook `getNomUrl()` del módulo añade el icono y el estado almacenado, seguido de un badge de estado propio), `Company` (enlace al tercero o `-`), `AmountTTC` y `DateModification` (`verifactu_ultimafecha_modificacion` con hora).
- El color del badge se decide buscando palabras en el estado almacenado: `error` o `incorrecto` → `badge-status8` (rojo), `anulada` → `badge-status6` (naranja), `enviada` o `correcto` → `badge-status4` (verde); por defecto `badge-status4`. El texto se intenta traducir con `VERIFACTU_STATUS_<estado en mayúsculas>` y, si no existe la clave, se muestra el estado almacenado.
- Sin filas, muestra `NoRecordFound`.

### Con errores (columna derecha)

- Solo aparece si `$totalErrors > 0`. Título `VERIFACTU_INVOICES_WITH_ERRORS` con el total en un badge rojo.
- Columnas: `Ref`, `Company`, `AmountTTC` y `Error`; el mensaje de `verifactu_error` se recorta a 40 caracteres con `dol_trunc()` y el texto completo va en el atributo `title` de la celda.
- Muestra como máximo las 5 facturas con error más recientes por fecha de factura; el resto se consulta desde el listado «Facturas emitidas» (`views/list.facture.php`). El significado de cada error está en [[Respuestas de la AEAT|Respuestas-de-la-AEAT]] y [[Códigos de error|Codigos-de-error]].

### Pendientes de envío (columna derecha)

- Solo aparece si `$totalPending > 0`. Título `VERIFACTU_PENDING_INVOICES` con el total en un badge `badge-status1`.
- Columnas: `Ref`, `Company`, `AmountTTC` y `Date` (fecha de factura).
- Si hay más de 5, una fila final enlaza a `views/list.facture.php` con el texto «Más… (n)», donde `n` es el número de facturas no mostradas. Véase [[Reintentos y envíos pendientes|Reintentos-y-envios-pendientes]].

### Lo que el panel no muestra

El fichero de idioma contiene las claves `VERIFACTU_STAT_TOTAL_SENT`, `VERIFACTU_STAT_SENT_OK`, `VERIFACTU_STAT_WITH_ERRORS` y `VERIFACTU_STAT_BY_YEAR`, y el código calcula `$totalSent` (suma de la consulta 1), pero la versión 2.2.2 no pinta indicadores numéricos con esos títulos ni un gráfico anual.

## La corrección de la versión 2.2.1

En las versiones 2.1.0 y 2.2.0 abrir el panel terminaba con un error 500 y el registro de PHP mostraba `Call to undefined function dol_get_first_day()`. El gráfico mensual se había reescrito en la 2.1.0 para funcionar en PostgreSQL, sustituyendo `MONTH()` y `YEAR()` por `dol_get_first_day()` y `dol_get_last_day()`, pero esas funciones viven en `core/lib/date.lib.php`, que Dolibarr no carga en todas las páginas, y `verifactuindex.php` no lo incluía. La configuración del módulo seguía abriéndose, así que el fallo se percibía como «el módulo ha dejado de funcionar», aunque la validación, el envío y la pestaña VeriFactu no se veían afectados.

La 2.2.1 añade la inclusión junto al resto de dependencias:

```php
require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';
```

y la prueba `tests/DashboardDependenciesTest.php` comprueba que la inclusión precede a la primera llamada y que ningún fichero del módulo usa una función de `date.lib.php` sin cargar la biblioteca. Véase [[Historial de versiones|Historial-de-versiones]] y [[Suite de pruebas|Suite-de-pruebas]].

> **Aviso:** si tras actualizar a la 2.1.0 o la 2.2.0 el panel devuelve un error 500, actualiza a la 2.2.1; no hace falta tocar la configuración. Véase [[Actualización|Actualizacion]].

## Hoja de estilos: `css/verifactu.css.php`

El descriptor declara el fichero en `module_parts['css']`, de modo que Dolibarr lo enlaza en todas las páginas. Es un PHP que emite `Content-type: text/css`, se sirve sin exigir inicio de sesión (`NOLOGIN`) y se cachea 3 horas (`Cache-Control: max-age=10800`); tras actualizar el módulo puede ser necesario recargar con Ctrl+F5. Sus reglas:

| Selector | Efecto |
|---|---|
| `div.mainmenu.verifactu::before` | Icono del menú superior: carácter `\f570` de «Font Awesome 5 Free» (fa-file-invoice), peso 900 |
| `div.mainmenu.verifactu` | `filter: saturate(1)` |
| `tr[id^="extrarow-facture-verifactu_"] td.valuefield` (y sus `span` y `a`) | Los valores de los campos adicionales VeriFactu en la ficha de la factura se muestran como texto normal (peso normal, color `#333`, sin subrayado) en lugar de como enlaces |
| `tr[id^="extrarow-facture-verifactu_"] td.valuefield .badge` | Los badges conservan su estilo (peso 600, texto blanco) |

## JavaScript: `js/verifactu.js.php`

Declarado en `module_parts['js']`, se carga también en todas las páginas, con caché de 1 hora, y tiene dos cometidos:

### Desplegable de estado en los listados de facturas

Al cargar la página busca el campo de búsqueda del campo adicional de estado, `input[name="search_options_verifactu_estado"]` (presente tanto en el listado estándar de facturas como en `views/list.facture.php`), y lo sustituye por un `<select>` con el mismo `name` e `id`, clase `minwidth150`, inicializado con Select2. Las opciones son:

| Valor | Texto |
|---|---|
| `''` | Todos (`VERIFACTU_ALL_STATUS`) |
| `No Enviada` | `VERIFACTU_STATUS_NOT_SEND` |
| `Enviada` | `VERIFACTU_STATUS_SEND` |
| `Error` | `VERIFACTU_STATUS_ERROR` |

Los valores son las traducciones del idioma activo porque la búsqueda se hace por texto sobre el badge HTML almacenado; se preselecciona la opción que coincide con el valor actual del filtro. El script escribe el valor actual en la consola del navegador. En `views/list.facture.php`, la plantilla `core/tpl/extrafields_list_array_fields.tpl.php` añade además las columnas de todos los campos adicionales `verifactu_*` al listado, dejando visibles por defecto solo `verifactu_estado` y `verifactu_csv_factura`.

### Capa de espera en TakePOS

El resto del fichero intercepta la función `Validate()` de la ventana de cobro de TakePOS para mostrar una capa de espera mientras se envía la factura; está descrito en [[TakePOS|TakePOS]].

## Véase también

- [[Permisos y menús|Permisos-y-menus]]
- [[Multiempresa|Multiempresa]]
- [[Campos adicionales|Campos-adicionales]]
- [[Reintentos y envíos pendientes|Reintentos-y-envios-pendientes]]
- [[Respuestas de la AEAT|Respuestas-de-la-AEAT]]
- [[Consulta a la AEAT|Consulta-a-la-AEAT]]
- [[TakePOS|TakePOS]]
- [[Historial de versiones|Historial-de-versiones]]
- [[Suite de pruebas|Suite-de-pruebas]]
- [[Arquitectura del módulo|Arquitectura-del-modulo]]
