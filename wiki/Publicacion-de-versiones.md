# Publicación de versiones

Cómo se numera, declara y publica una versión del módulo VeriFactu para Dolibarr. Desde la 2.0.0 la etiqueta y la release de GitHub las crea un flujo de GitHub Actions (`.github/workflows/release.yml`) a partir de lo que ya está en `main`: comprueba que el número de versión coincide en los tres ficheros de los que depende el campo `Version` transmitido a la AEAT, ejecuta la suite de regresión y toma las notas del registro de cambios del README. Un segundo flujo (`limpiar-ramas.yml`) borra las ramas de trabajo ya integradas.

## Por qué el número de versión importa

El número de versión no es solo informativo: `getSystemConfig()` lo transmite a la AEAT en el bloque `SistemaInformatico` de cada registro de facturación (campo `Version`, Art. 15.2.c de la Orden HAC/1177/2024), y debe coincidir con el que figura en la declaración responsable (Art. 15.2). Entre diciembre de 2025 y marzo de 2026 los paquetes publicados como 1.0.4 y 1.1 seguían declarando `1.0.3` en `modVerifactu.class.php`: las etiquetas se movieron, pero el código no. El flujo de publicación existe para que eso no pueda repetirse.

## Dónde se declara la versión

Los tres ficheros que compara el flujo, con el patrón exacto que busca (`sed`, primera coincidencia):

| Fichero | Línea | Patrón que lee el flujo |
|---|---|---|
| `core/modules/modVerifactu.class.php` | `$this->verifactu_version = '2.2.2';` | `verifactu_version = '([0-9.]*)'` |
| `conf/declaracion_responsable.conf.php` | `'version' => '2.2.2',` en `$declaracionResponsable['sistema']` | `'version' => '([0-9.]*)',` |
| `lib/functions/functions.configuration.php` | `'version' => '2.2.2',` en los valores por defecto de `getDeclaredSystemIdentity()` | `'version' => '([0-9.]*)',` |

Además, aunque el flujo no los compare, deben actualizarse a la vez:

| Fichero | Campo | Formato |
|---|---|---|
| `core/modules/modVerifactu.class.php` | `$this->version = '2.2.2';` (la que muestra Dolibarr en la lista de módulos) | `X.Y.Z` |
| `core/modules/modVerifactu.class.php` | `$this->verifactu_version_date = '19/09/2026';` | `DD/MM/AAAA` |
| `conf/declaracion_responsable.conf.php` | `'fecha_version' => '2026-09-19',` | `AAAA-MM-DD` |
| `README.md` | `- **Versión**: 2.2.2` en «Información del Módulo» | |
| `README.md` | Entrada `### v2.2.2 (2026-09-19)` en «Registro de Cambios» | Encabezado exacto, con `v` delante y la fecha entre paréntesis |

> **Importante:** `getDeclaredSystemIdentity()` lee la versión de la declaración responsable y solo usa su valor por defecto si el fichero de declaración no es legible. Aun así, el flujo exige que los tres coincidan: el respaldo nunca debe quedar atrás.

## El flujo `release.yml` paso a paso

**Nombre:** «Publicar versión». **Permisos:** `contents: write`. **Disparadores:**

- `push` a `main`: si la versión que declara el módulo aún no tiene etiqueta, se publica. Así la etiqueta no puede quedar por detrás del número que se transmite a la AEAT.
- `workflow_dispatch` (ejecución manual) con un parámetro opcional `version` («Versión a publicar, sin prefijo (por ejemplo 2.0.0). Vacío: la que declare el módulo»).

Pasos, en orden:

1. **Descargar el repositorio** con `actions/checkout@v4` y `fetch-depth: 0` (hace falta el historial completo para consultar etiquetas).
2. **Determinar la versión y comprobar que está declarada igual en los tres sitios.** Extrae `modulo`, `declaracion` y `respaldo` con los patrones de la tabla anterior. Si se indicó `version` a mano, es la que se publica; si no, la del módulo. Si cualquiera de los tres valores difiere, el paso falla con:

   ```text
   ::error::La versión declarada (X) no coincide con la que se va a publicar (Y). Ese número se transmite a la AEAT en el campo Version, así que no se publica hasta que cuadre.
   ```

3. **Comprobar si la etiqueta ya existe** (`git rev-parse refs/tags/<version>`). Si existe, escribe «La etiqueta X ya existe; no hay nada que publicar.» y los pasos siguientes se saltan. Por eso un `push` a `main` que no cambie la versión (documentación, refactorizaciones) no publica nada.
4. **Pasar la suite de regresión.** Recorre `tests/*.php` ejecutando cada fichero con `php`; imprime `OK` o `FALLA` por fichero y, si alguno falla, aborta con `::error::N ficheros de prueba fallan; no se publica.` ([[Suite de pruebas|Suite-de-pruebas]]).
5. **Extraer las notas del registro de cambios.** Con `awk`, copia del `README.md` las líneas comprendidas entre la que empieza exactamente por `### vX.Y.Z ` (con espacio detrás del número) y el siguiente encabezado `### v`, a `NOTAS_VERSION.md`. Si el resultado está vacío, aborta con `::error::No hay entrada '### vX.Y.Z' en el registro de cambios del README.`
6. **Crear la etiqueta y publicar la versión.** Configura la identidad del bot de GitHub Actions, crea una etiqueta anotada `X.Y.Z` (sin prefijo `v`) con el mensaje «VeriFactu para Dolibarr X.Y.Z», la sube (`git push origin refs/tags/X.Y.Z`) y crea la release con `gh release create X.Y.Z --title "VeriFactu para Dolibarr X.Y.Z" --notes-file NOTAS_VERSION.md`.

Resultado: una release en `https://github.com/garacil/verifactu/releases/tag/X.Y.Z` cuyas notas son, literalmente, la entrada del README, y una etiqueta que apunta al commit de `main` que declaraba esa versión.

### Ejecución manual (`workflow_dispatch`)

Desde la pestaña *Actions* del repositorio, «Publicar versión» > *Run workflow*. Sirve para:

- **Republicar** una versión cuya publicación automática falló (por ejemplo, porque faltaba la entrada del README): tras corregir `main`, vuelve a lanzarlo sin parámetro.
- **Publicar una versión concreta**: indicando `version`. El flujo exige igualmente que los tres ficheros declaren ese número, así que solo tiene sentido para el commit actual de `main`.

Si la etiqueta ya existe, la ejecución manual tampoco hace nada: para rehacer una release habría que borrar antes la etiqueta y la release a mano, algo que no se ha hecho nunca en el proyecto y que conviene evitar con versiones ya instaladas por terceros.

### Qué puede fallar y cómo se ve

| Paso | Síntoma en Actions | Causa | Remedio |
|---|---|---|---|
| Versión | `La versión declarada (2.2.0) no coincide con la que se va a publicar (2.2.1)` | Uno de los tres ficheros no se actualizó | Corregir el fichero y hacer `push` |
| Etiqueta | `La etiqueta 2.2.1 ya existe; no hay nada que publicar.` | El número no cambió | Normal si el commit no era una versión nueva |
| Suite | `FALLA tests/…` y `N ficheros de prueba fallan; no se publica.` | Regresión o dependencia del ejecutor | Corregir el código o el test |
| Notas | `No hay entrada '### v2.2.1' en el registro de cambios del README.` | Falta la entrada o el encabezado no coincide (por ejemplo `### 2.2.1` sin `v`, o sin espacio tras el número) | Añadir o corregir la entrada |
| Publicación | Error de `git push` o `gh release create` | Permisos o etiqueta creada entre medias | Revisar el registro del paso |

## El flujo `limpiar-ramas.yml`

**Nombre:** «Limpiar ramas integradas». Se ejecuta en cada `push` a `main` y a mano. Recorre todas las ramas remotas y, para cada una que no esté en `RAMAS_PRESERVADAS` (`main master develop stable`), cuenta `git rev-list --count origin/main..origin/<rama>`: si es cero, la rama está completamente contenida en `main` y se borra (`git push origin --delete`); si no, se conserva y lo dice («Se conserva X: tiene N commits que no están en main»). Al final informa de cuántas ramas borró.

Consecuencias prácticas:

- Las ramas `work/*` desaparecen del remoto poco después de integrarse. Si necesitas volver a ellas, el historial sigue en `main`.
- Una rama con trabajo pendiente nunca se toca, aunque lleve meses.
- Desde la 2.2.0 la limpieza no se acota a un prefijo concreto: cualquier rama integrada, se llame como se llame, se borra.

## El flujo `publicar-wiki.yml`

Esta wiki es un repositorio git aparte (`verifactu.wiki.git`) al que no se llega con las credenciales de trabajo habituales, pero el token de Actions sí puede escribir en la wiki del propio repositorio. Las páginas viven por tanto en la carpeta `wiki/` del repositorio, y el flujo las copia allí:

- Se dispara con cualquier integración en `main` que toque `wiki/**`.
- Clona la wiki con `x-access-token`, copia los `.md` de `wiki/` encima y commitea **solo si hay cambios**, con el número de versión que declare el descriptor del módulo.
- El autor del commit es el del módulo, para que el historial de la wiki no se mezcle con el de los automatismos.

Para cambiar una página, edítala en `wiki/` y súbela con el resto del cambio: no hace falta tocar la wiki a mano. Editar una página desde la interfaz de GitHub también funciona, pero el siguiente cambio en `wiki/` la sobrescribirá.

## Criterios de numeración

El proyecto usa tres componentes `MAYOR.MENOR.PARCHE`. Del historial se deducen estos criterios:

| Componente | Cuándo | Ejemplos |
|---|---|---|
| **Mayor** | Cambia el comportamiento de forma visible o lo que se transmite a la AEAT, o hay que dejar atrás sin ambigüedad una numeración anterior | **2.0.0**: registros rechazados en local por los límites del XSD, consultas acotadas a la entidad, respuestas parciales con líneas rechazadas ya no se dan por enviadas, NIF fijado con la primera huella; y salto por encima de la etiqueta 1.1 ya publicada |
| **Menor** | Correcciones que cambian el comportamiento en instalaciones existentes o añaden funcionalidad, sin romper compatibilidad | **2.1.0**: cadena en PostgreSQL, bloqueo de la cadena, registro pendiente al validar con envío directo desactivado; **2.2.0**: anulación como primer registro, NIF único entre entidades, aviso de numeración compartida |
| **Parche** | Corrección acotada sin cambio de comportamiento para quien no sufría el fallo | **2.2.1**: `date.lib.php` en la página principal; **1.0.1** a **1.0.4** |

Regla práctica: si el cambio afecta a la huella, al encadenamiento, a los campos enviados, a qué facturas se envían o a qué respuestas se aceptan, no es un parche. Si además obliga a revisar registros ya remitidos (como la cadena en PostgreSQL), documenta en la entrada del README qué debe revisar quien actualiza.

## Lista de comprobación antes de publicar

1. La suite completa pasa en local (`for f in tests/*.php; do php "$f" >/dev/null 2>&1 || echo "FALLA $f"; done` sin salida).
2. Si la corrección tiene test nuevo, está en `tests/` y se menciona en el README.
3. Versión actualizada en `modVerifactu.class.php` (`version`, `verifactu_version`, `verifactu_version_date`), `conf/declaracion_responsable.conf.php` (`version`, `fecha_version`) y `functions.configuration.php` (valor por defecto de `getDeclaredSystemIdentity()`).
4. `README.md`: línea «Versión» de «Información del Módulo» y entrada `### vX.Y.Z (AAAA-MM-DD)` en «Registro de Cambios», con problema, causa, solución, ficheros modificados, tests y agradecimientos si procede. Las notas de la release serán exactamente ese texto: revisa el Markdown.
5. Textos nuevos en los cinco ficheros de `langs/`.
6. Si se añadieron ficheros críticos, `ficheros_verificados` de la declaración responsable los incluye (`tests/IssueFixesTest.php` comprueba que todos existen).
7. Commit con asunto `chore: publicar como X.Y.Z` (o el `fix:`/`feat:` correspondiente cuyo cuerpo indique que se publica como X.Y.Z), integrado en `main`.
8. Observar la ejecución de «Publicar versión» en *Actions* hasta que aparezca la release.
9. Instalar el paquete de la release en un Dolibarr limpio y comprobar que la lista de módulos muestra la versión nueva, que la declaración responsable muestra el mismo número y que un envío de prueba transmite ese `Version`.

## Véase también

- [[Historial de versiones|Historial-de-versiones]]
- [[Suite de pruebas|Suite-de-pruebas]]
- [[Desarrollo y contribución|Desarrollo-y-contribucion]]
- [[Declaración responsable|Declaracion-responsable]]
- [[Actualización|Actualizacion]]
- [[Integridad del módulo|Integridad-del-modulo]]
