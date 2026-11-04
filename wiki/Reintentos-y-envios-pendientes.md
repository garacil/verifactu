# Reintentos y envíos pendientes

Una factura validada puede quedarse sin transmitir a la AEAT por tres motivos: no había conexión a Internet, el servicio respondió «no disponible» (HTTP 503) o el envío directo en la validación estaba desactivado. En los tres casos el registro de facturación existe y está pendiente de remitirse, con la marca de incidencia que exige la normativa, y la clase `VerifactuUtils` (`class/verifactu.utils.php`) se encarga de reenviarlo: desde una tarea programada, al validar la siguiente factura o cuando se fuerza manualmente.

## Cómo llega una factura a la cola

| Origen | Estado (`verifactu_estado`) | `verifactu_error` | `verifactu_incidencia` | Dónde se marca |
|---|---|---|---|---|
| Sin conexión al validar | «Sin Conexión» (`badge-status1`) | `NO_INTERNET_CONNECTION` | `S` | `billValidate()` (trigger 999) |
| Servicio no disponible (503) durante el envío | «Pendiente» (`badge-status1`) | `SERVICE_UNAVAILABLE` | `S` | `handleInvoiceCreationOrSubsanation()` |
| Envío directo desactivado (`VERIFACTU_DIRECT_CALL_ON_VALIDATE` = 0, factura no TakePOS) | «Pendiente» (`badge-status1`) | `PENDING_SUBMISSION` | `S` | `billValidate()` (trigger 999), desde la 2.1.0 |

El caso 503 se detecta en la excepción del transporte: código `503` o mensaje que contiene `service unavailable`, `Service Unavailable` o `HTTP 503`. En los tres casos la validación **se completa** (la función devuelve `true`) y la factura queda con referencia definitiva; lo único pendiente es la transmisión. Los avisos que se muestran son `VERIFACTU_HAS_NO_INTERNET_CONNECTION`, `VERIFACTU_SERVICE_UNAVAILABLE_INFO` («Factura %s validada localmente. No se pudo enviar a VeriFactu. Marcada como "Pendiente" para reintento. Error: %s») y `VERIFACTU_PENDING_SUBMISSION_INFO`.

> **Nota:** una factura rechazada por la AEAT (estado «Error») **no** entra en la cola: en la validación se devuelve a borrador y, si el rechazo llegó desde otra vía, hay que corregir la causa y reenviar a mano. Tampoco entra una «Duplicada (Importes diferentes)», que requiere una decisión (véase [[Respuestas de la AEAT|Respuestas-de-la-AEAT]]).

## Qué facturas selecciona la cola

`VerifactuUtils::getInvoicesWithConnectionErrors()` ejecuta:

```sql
SELECT f.rowid, f.ref, f.datef, f.entity
  FROM llx_facture f
  INNER JOIN llx_facture_extrafields fe ON f.rowid = fe.fk_object
 WHERE f.entity = {entidad activa}
   AND f.fk_statut > 0
   AND f.ref NOT LIKE '%PROV%'
   AND (fe.verifactu_error LIKE '%NO_INTERNET_CONNECTION%'
        OR fe.verifactu_error LIKE '%SERVICE_UNAVAILABLE%'
        OR fe.verifactu_error LIKE '%PENDING_SUBMISSION%')
   AND (fe.verifactu_huella IS NULL OR fe.verifactu_huella = '')
 ORDER BY f.datef ASC, f.rowid ASC
```

| Criterio | Motivo |
|---|---|
| `f.entity = $conf->entity` | Cada entidad tiene su configuración, su certificado y su cadena; los reintentos no cruzan entidades (véase [[Multiempresa|Multiempresa]]) |
| `fk_statut > 0` | Solo facturas validadas |
| `ref NOT LIKE '%PROV%'` | Nunca se envía una referencia provisional |
| Alguno de los tres marcadores en `verifactu_error` | Son los únicos estados que la cola sabe resolver |
| Sin huella | Una factura con huella ya está en la cadena |
| Orden por fecha y `rowid` ascendentes | Las facturas se encadenan de la más antigua a la más reciente |

## Cuándo se procesa la cola

1. **Tarea programada.** El descriptor del módulo (`core/modules/modVerifactu.class.php`) registra en el módulo *Tareas programadas* de Dolibarr la entrada siguiente:

   | Propiedad | Valor |
   |---|---|
   | Etiqueta | `sendInvoicesWithErrorsCronName`: «VeriFactu - Reenvío de facturas con errores de conexión» |
   | Tipo | `method` |
   | Clase / objeto / método | `/verifactu/class/verifactu.utils.php` / `VerifactuUtils` / `sendInvoicesWithErrors` |
   | Frecuencia | `2` × `3600` s, es decir, **cada 2 horas** |
   | Estado inicial | Activa (`status = 1`) |
   | Condición | `$conf->verifactu->enabled` |
   | Prioridad | 50 |

   La tarea se crea en la entidad en la que se activa el módulo y se ejecuta con la configuración de esa entidad.

2. **Antes de validar otra factura.** `billValidate()` comprueba la conexión y, si la hay, llama a `sendInvoicesWithErrors()` antes de construir el registro de la factura que se está validando. Así las pendientes se encadenan primero y la nueva factura queda detrás. Si se procesó alguna, se muestra `"%d pending invoices processed automatically"` (y ` (%d with errors)` si hubo fallos).

3. **Manualmente**, como se describe más abajo.

### Activar la tarea programada en Dolibarr

La tarea solo se ejecuta si el módulo **Tareas programadas** (cron) de Dolibarr está activo y existe un disparador externo que lo invoque periódicamente. Las dos formas que ofrece el núcleo son:

- Por línea de órdenes, desde el `crontab` del servidor:

  ```bash
  */15 * * * * /usr/bin/php /ruta/a/dolibarr/scripts/cron/cron_run_jobs.php CLAVE_DE_SEGURIDAD usuario_dolibarr
  ```

- Por URL, útil en alojamientos sin acceso a `cron`:

  ```bash
  */15 * * * * curl -s "https://tu-dolibarr/public/cron/cron_run_jobs_by_url.php?securitykey=CLAVE_DE_SEGURIDAD&userlogin=usuario_dolibarr" > /dev/null
  ```

La clave de seguridad y el usuario se definen en la configuración del módulo Tareas programadas. La entrada de VeriFactu aparece en **Inicio > Herramientas de administración > Tareas programadas**, donde se puede ejecutar a mano, cambiar la frecuencia o desactivar. La propia tarea vuelve a comprobar la conexión antes de enviar, de modo que ejecutarla con más frecuencia que la del planificador de Dolibarr no tiene coste apreciable cuando no hay pendientes.

## Cómo reenvía cada factura

`sendInvoicesWithErrors()`:

1. Carga el idioma del módulo y vacía `output`, `error` y `result`.
2. Obtiene la lista de pendientes. Si está vacía, `output = "No pending invoices found with connection errors"` y devuelve `0`.
3. Comprueba la conexión con `checkInternetConnection()`. Si no la hay, `error = "No internet connection available"` y devuelve `-1`.
4. Para cada factura: vuelve a comprobar la conexión (si se ha perdido, la salta y la cuenta como `skipped`), llama a `processInvoiceWithConnectionError()` y espera medio segundo (`usleep(500000)`) antes de la siguiente.
5. Rellena `output` con una línea por factura («Processed successfully», «Processing error» o «Exception - …»), un resumen `Task completed - Processed: n, Errors: n, Skipped: n` y `result` con las claves `processed`, `errors`, `skipped` y `total_found`.
6. Devuelve `-1` si hubo algún error y `0` en caso contrario. El módulo Tareas programadas registra `output` y marca la ejecución como fallida cuando el retorno es negativo.

`processInvoiceWithConnectionError()`:

1. Carga la factura y sus campos adicionales.
2. **Conserva la fecha de expedición original** (lo anota en el registro con `dol_print_date($facture->date, 'day')`); solo la fecha de envío es posterior.
3. Si `verifactu_incidencia` no es `S`, la pone a `S` y la guarda (compatibilidad con facturas marcadas por versiones anteriores).
4. Llama a `execVERIFACTUCall($facture, 'Alta', true)`: el **tiempo de espera de la AEAT sí se respeta** aquí (véase más abajo). La consulta previa a la AEAT, el bloqueo de la cadena y el encadenamiento son los habituales (véase [[Flujo de validación y envío|Flujo-de-validacion-y-envio]]).
5. Si el resultado es `true` (o un array con `success`), llama a `clearConnectionError()` y devuelve `1`; si no, devuelve `0`.

`clearConnectionError()` elimina de `verifactu_error` las cadenas `NO_INTERNET_CONNECTION`, `SERVICE_UNAVAILABLE` y `PENDING_SUBMISSION` con una sentencia `UPDATE` directa sobre `llx_facture_extrafields`, dejando `NULL` si no queda nada. **`verifactu_incidencia` se mantiene en `S`**: documenta que hubo una incidencia en la remisión original, tal como el registro transmitido declaró en `RemisionVoluntaria.Incidencia`.

## Tiempo de espera de la AEAT

Cada respuesta del servicio incluye `TiempoEsperaEnvio`, los segundos que la AEAT pide esperar antes del siguiente envío. `registerAEATWaitTime()` (`lib/functions/functions.response.php`) guarda el instante hasta el que hay que esperar en la constante `VERIFACTU_NEXT_SEND_AT` de la entidad, y `getAEATWaitTimeRemaining()` devuelve los segundos que faltan (nunca negativos).

| Vía | ¿Respeta la espera? | Motivo |
|---|---|---|
| Cola de reintentos (`VerifactuUtils`) | **Sí** (`$enforceWaitTime = true`) | Posponer un reintento es inocuo y evita que el bucle sature el servicio |
| Validación (trigger `BILL_VALIDATE`) | No | Abortar devolvería la factura a borrador; una ventana de espera no debe deshacer una factura emitida |
| Pestaña VeriFactu y envío masivo del listado | No | Son acciones explícitas del usuario |

Cuando la espera bloquea un reintento, `execVERIFACTUCall()` devuelve `false` tras avisar con `VERIFACTU_AEAT_WAIT_REQUIRED` («La AEAT requiere esperar %s segundos antes del siguiente envío VeriFactu.»); la factura sigue en la cola y se reintentará en la siguiente pasada. Este control se incorporó en la 2.0.0 (PR #37) y lo cubre `tests/AEATWaitTimeTest.php`.

## Cómo detecta la conexión

`checkInternetConnection()` (`lib/functions/functions.utilities.php`) prueba, con 3 segundos de tiempo de espera por intento y en este orden, hasta que uno responde:

1. Peticiones HTTP `HEAD` (cURL, o `file_get_contents` si no hay cURL) a `https://www.google.com/generate_204`, `https://httpbin.org/status/200`, `https://1.1.1.1/`, `https://www.microsoft.com/favicon.ico` y `http://detectportal.firefox.com/success.txt`; vale cualquier código 2xx o 3xx.
2. Resolución DNS de `google.com`, `cloudflare.com`, `microsoft.com`, `github.com` y `stackoverflow.com`.
3. Conexión de *socket* al puerto 53 de `8.8.8.8`, `1.1.1.1`, `9.9.9.9`, `208.67.222.222` y `8.8.4.4`.
4. `ping` a `8.8.8.8`, `1.1.1.1` y `9.9.9.9`, solo si `exec()` está disponible.

Si todo falla, devuelve `false`. En servidores con salida a Internet restringida a los dominios de la AEAT, esta función puede dar negativo aunque el servicio sea alcanzable; en ese caso la validación marca «Sin Conexión» y el reintento automático no se ejecuta hasta que alguna de las pruebas responda.

## Forzar un reenvío manual

- **Desde la pestaña VeriFactu de la factura** (`views/tabVERIFACTU.facture.php`): pulsa **Enviar Alta**. El botón está disponible si la factura está validada, su referencia no contiene `PROV`, no está anulada en VeriFactu y no consta ya como enviada correctamente (`VERIFACTU_INVOICE_ALREADY_SENT_CORRECTLY`: «Factura ya enviada correctamente. Solo puede enviar una Baja para anularla.»). Se llama a `execVERIFACTUCall($object, 'Alta', false)`, sin respetar la espera de la AEAT. Véase [[Pestaña VeriFactu de la factura|Pestana-VeriFactu-de-la-factura]].
- **Desde el listado Facturas emitidas** (`views/list.facture.php`): selecciona las facturas y pulsa **Enviar Alta**; las ya enviadas se omiten. Véase [[Validación masiva|Validacion-masiva]].
- **Ejecutando la tarea programada** desde Inicio > Herramientas de administración > Tareas programadas.
- **Verificar Estado en AEAT** (`verificarEstadoFacturaAEAT()`): si la factura no existe en la AEAT, limpia todos los campos VeriFactu de la factura (`VERIFACTU_VERIFY_NOT_FOUND_CLEARED`: «Factura %s no existe en AEAT. Errores anteriores limpiados. Puede enviarla.»); si existe con los mismos importes, la marca como «Enviada» y adopta la huella de la AEAT. Véase [[Consulta a la AEAT|Consulta-a-la-AEAT]].

La consulta previa de `execVERIFACTUCall()` protege cualquier reenvío frente a duplicados: si la factura ya consta en la AEAT con los mismos importes, se marca como enviada sin transmitirla de nuevo (`VERIFACTU_PRECHECK_SKIP_SEND`). El texto de ayuda del módulo lo resume así (`VERIFACTU_FAQ_RESEND_DESC`): «1) Corrija la configuración del cliente. 2) Abra la factura. 3) Vaya a la pestaña VeriFactu. 4) Pulse "Enviar".»

## Seguimiento y registro

- Panel principal: el contador «Pendientes de envío» (`VERIFACTU_PENDING_INVOICES`); véase [[Panel principal|Panel-principal]].
- Registro de Dolibarr: todas las entradas de la cola llevan el prefijo `VerifactuUtils::sendInvoicesWithErrors` o `VerifactuUtils::processInvoiceWithConnectionError`; la consulta de pendientes se escribe con nivel `LOG_DEBUG`. Véase [[Registro y depuración|Registro-y-depuracion]].
- Historial de la tarea: la salida (`output`) de cada ejecución queda en el módulo Tareas programadas.

## Véase también

- [[Flujo de validación y envío|Flujo-de-validacion-y-envio]]
- [[Respuestas de la AEAT|Respuestas-de-la-AEAT]]
- [[Encadenamiento y huella|Encadenamiento-y-huella]]
- [[Fechas y numeración|Fechas-y-numeracion]]
- [[Pestaña VeriFactu de la factura|Pestana-VeriFactu-de-la-factura]]
- [[Consulta a la AEAT|Consulta-a-la-AEAT]]
- [[Configuración|Configuracion]]
- [[Resolución de problemas|Resolucion-de-problemas]]
- [[Registro y depuración|Registro-y-depuracion]]
