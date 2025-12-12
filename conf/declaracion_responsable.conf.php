<?php
/**
 * Fichero de configuración para la Declaración Responsable VeriFactu
 *
 * Este fichero contiene todos los parámetros requeridos por la normativa española
 * para la declaración responsable de sistemas informáticos de facturación.
 *
 * Marco Legal:
 * - Real Decreto 1007/2023, de 5 de diciembre (Reglamento VeriFactu)
 * - Real Decreto 254/2025 (Modificaciones y plazos)
 * - Orden HAC/1177/2024, de 17 de octubre (Especificaciones técnicas)
 * - Artículo 29.2.j) de la Ley 58/2003, General Tributaria
 *
 * @package    verifactu
 * @subpackage conf
 * @author     Germán Luis Aracil Boned <garacilb@gmail.com>
 * @copyright  2025 Germán Luis Aracil Boned
 * @license    GPL-3.0-or-later
 * @version    1.0.0
 * @since      2025-12-12
 *
 * @see https://www.boe.es/diario_boe/txt.php?id=BOE-A-2024-22138
 * @see https://sede.agenciatributaria.gob.es/Sede/iva/sistemas-informaticos-facturacion-verifactu/
 */

if (!defined('VERIFACTU_DECLARACION_RESPONSABLE')) {
    define('VERIFACTU_DECLARACION_RESPONSABLE', true);
}

/**
 * =============================================================================
 * SECCIÓN 1: DATOS DEL PRODUCTOR DEL SISTEMA INFORMÁTICO
 * =============================================================================
 * Según Artículo 15 de la Orden HAC/1177/2024
 *
 * El productor es la persona física o jurídica que desarrolla, fabrica o
 * comercializa el sistema informático de facturación.
 */
$declaracionResponsable['productor'] = array(

    /**
     * Razón social o nombre completo del productor
     * @required Obligatorio según Art. 15.1.a) Orden HAC/1177/2024
     */
    'razon_social' => 'Germán Luis Aracil Boned',

    /**
     * NIF español del productor
     * Si no dispone de NIF español, utilizar 'nif_extranjero'
     * @required Obligatorio según Art. 15.1.b) Orden HAC/1177/2024
     */
    'nif' => '',  // Completar con NIF válido

    /**
     * Identificación fiscal extranjera (si aplica)
     * Solo si el productor no dispone de NIF español
     */
    'nif_extranjero' => array(
        'tipo_identificacion' => '',  // Tipo de documento (passport, VAT, etc.)
        'numero' => '',
        'pais_emision' => '',  // Código ISO 3166-1 alpha-2
    ),

    /**
     * Dirección postal completa del productor
     * @required Obligatorio según Art. 15.1.c) Orden HAC/1177/2024
     */
    'direccion' => array(
        'tipo_via' => 'Calle',
        'nombre_via' => '',
        'numero' => '',
        'piso' => '',
        'puerta' => '',
        'codigo_postal' => '',
        'localidad' => '',
        'provincia' => '',
        'pais' => 'España',
        'codigo_pais' => 'ES',
    ),

    /**
     * Datos de contacto del productor
     * @recommended Recomendado para comunicaciones de la AEAT
     */
    'contacto' => array(
        'email' => 'garacilb@gmail.com',
        'telefono' => '',
        'web' => 'https://github.com/garacil/verifactu',
    ),
);

/**
 * =============================================================================
 * SECCIÓN 2: DATOS DEL SISTEMA INFORMÁTICO
 * =============================================================================
 * Según Artículo 15 de la Orden HAC/1177/2024
 */
$declaracionResponsable['sistema'] = array(

    /**
     * Nombre comercial del sistema informático
     * Denominación genérica para su distribución/comercialización
     * @required Obligatorio según Art. 15.2.a) Orden HAC/1177/2024
     */
    'nombre' => 'VeriFactu para Dolibarr ERP/CRM',

    /**
     * IdSistemaInformatico - Código identificador único
     * Asignado por el productor para identificar unívocamente el sistema
     * @required Obligatorio según Art. 15.2.b) Orden HAC/1177/2024
     * @format Alfanumérico, máximo 30 caracteres
     */
    'id_sistema_informatico' => 'VERIFACTU-DOLIBARR-OSS',

    /**
     * Versión del sistema informático
     * Identificador completo de la versión concreta
     * @required Obligatorio según Art. 15.2.c) Orden HAC/1177/2024
     */
    'version' => '1.0.2',

    /**
     * Fecha de la versión
     */
    'fecha_version' => '2025-07-16',

    /**
     * Número de instalación/instancia (generado automáticamente)
     * Identificador único de cada instalación
     */
    'numero_instalacion' => '',  // Se genera automáticamente con hash

    /**
     * Tipo de licencia del software
     */
    'tipo_licencia' => 'GPL-3.0-or-later',

    /**
     * URL del repositorio del código fuente
     */
    'repositorio' => 'https://github.com/garacil/verifactu',
);

/**
 * =============================================================================
 * SECCIÓN 3: COMPONENTES DEL SISTEMA
 * =============================================================================
 * Descripción del hardware y software que compone el sistema
 * Según Art. 15.2.d) Orden HAC/1177/2024
 */
$declaracionResponsable['componentes'] = array(

    /**
     * Componentes de software
     */
    'software' => array(
        array(
            'nombre' => 'Dolibarr ERP/CRM',
            'descripcion' => 'Sistema ERP/CRM de código abierto',
            'version_minima' => '10.0',
            'tipo' => 'plataforma_base',
        ),
        array(
            'nombre' => 'PHP',
            'descripcion' => 'Lenguaje de programación del servidor',
            'version_minima' => '7.4',
            'tipo' => 'runtime',
        ),
        array(
            'nombre' => 'OpenSSL',
            'descripcion' => 'Biblioteca criptográfica para firma digital',
            'version_minima' => '1.1.0',
            'tipo' => 'dependencia',
        ),
        array(
            'nombre' => 'SOAP Extension',
            'descripcion' => 'Extensión PHP para comunicación con servicios web AEAT',
            'version_minima' => '',
            'tipo' => 'dependencia',
        ),
        array(
            'nombre' => 'chillerlan/php-qrcode',
            'descripcion' => 'Biblioteca para generación de códigos QR',
            'version_minima' => '4.0',
            'tipo' => 'dependencia',
        ),
    ),

    /**
     * Requisitos de hardware (mínimos)
     */
    'hardware' => array(
        'arquitectura' => 'x86_64 / ARM64',
        'memoria_minima' => '512 MB',
        'almacenamiento_minimo' => '100 MB',
        'descripcion' => 'Servidor web compatible con PHP 7.4+',
    ),

    /**
     * Funcionalidades del sistema
     * Según especificaciones técnicas de VeriFactu
     */
    'funcionalidades' => array(
        'generacion_registros' => true,
        'encadenamiento_hash' => true,
        'firma_electronica' => true,
        'generacion_qr' => true,
        'envio_aeat' => true,
        'consulta_aeat' => true,
        'conservacion_registros' => true,
        'exportacion_datos' => true,
    ),
);

/**
 * =============================================================================
 * SECCIÓN 4: ESPECIFICACIONES TÉCNICAS VERIFACTU
 * =============================================================================
 * Según Orden HAC/1177/2024, Anexos I y II
 */
$declaracionResponsable['especificaciones_tecnicas'] = array(

    /**
     * Modalidad de funcionamiento
     * @required Obligatorio según Art. 15.2.e) Orden HAC/1177/2024
     *
     * Valores posibles:
     * - 'verifactu': Sistema VERI*FACTU (envío inmediato a AEAT)
     * - 'no_verifactu': Sistema NO VERI*FACTU (sin envío inmediato)
     */
    'modalidad_funcionamiento' => 'verifactu',

    /**
     * ¿Funciona exclusivamente como VERI*FACTU?
     * Si es true, el sistema solo opera en modo VERI*FACTU
     * Si es false, puede operar en ambos modos
     */
    'exclusivo_verifactu' => false,

    /**
     * Sistema multi-usuario
     * Indica si permite varios obligados tributarios en la misma instalación
     * @required Obligatorio según Art. 15.2.f) Orden HAC/1177/2024
     */
    'multiusuario' => true,

    /**
     * Multiempresa
     * Indica si soporta múltiples empresas/entidades
     */
    'multiempresa' => true,

    /**
     * Tipo de firma electrónica utilizada
     * @required Obligatorio según Art. 15.2.g) Orden HAC/1177/2024
     *
     * Según Art. 14 Orden HAC/1177/2024:
     * - XAdES Enveloped con certificado cualificado
     */
    'tipo_firma' => array(
        'formato' => 'XAdES',
        'tipo' => 'Enveloped',
        'nivel' => 'B-Level',
        'certificado' => 'Certificado cualificado de firma electrónica',
        'algoritmo_firma' => 'RSA-SHA256',
        'algoritmo_hash' => 'SHA-256',
    ),

    /**
     * Algoritmo de huella (hash) para encadenamiento
     * Según Art. 14 Orden HAC/1177/2024
     */
    'algoritmo_huella' => 'SHA-256',

    /**
     * Codificación de caracteres
     */
    'codificacion' => 'UTF-8',

    /**
     * Versión del esquema XML de VeriFactu
     */
    'version_esquema_xml' => '1.0',

    /**
     * URLs de los servicios web de la AEAT
     */
    'endpoints_aeat' => array(
        'produccion' => 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroLR.wsdl',
        'pruebas' => 'https://www7.aeat.es/wlpl/TIKE-CONT/ws/SuministroLR.wsdl',
    ),
);

/**
 * =============================================================================
 * SECCIÓN 5: INTEGRIDAD DEL SISTEMA (HASH DEL MÓDULO)
 * =============================================================================
 * Huella digital para verificar la integridad del sistema
 */
$declaracionResponsable['integridad'] = array(

    /**
     * Algoritmo utilizado para el cálculo del hash
     */
    'algoritmo' => 'SHA-256',

    /**
     * Hash del módulo (se calcula dinámicamente)
     * Este valor se genera automáticamente al cargar el fichero
     */
    'hash_modulo' => '',  // Se calcula en tiempo de ejecución

    /**
     * Fecha del último cálculo de hash
     */
    'fecha_calculo' => '',

    /**
     * Ficheros incluidos en el cálculo del hash
     * Lista de ficheros críticos cuya integridad se verifica
     */
    'ficheros_verificados' => array(
        'core/modules/modVerifactu.class.php',
        'class/verifactu.class.php',
        'class/verifactu.utils.php',
        'lib/verifactu.lib.php',
        'lib/verifactu-types.array.php',
        'core/triggers/interface_99_modVerifactu_VerifactuTriggers.class.php',
    ),
);

/**
 * =============================================================================
 * SECCIÓN 6: DECLARACIÓN DE CUMPLIMIENTO NORMATIVO
 * =============================================================================
 * Según Art. 15.3 Orden HAC/1177/2024
 */
$declaracionResponsable['cumplimiento'] = array(

    /**
     * Declaración responsable de cumplimiento
     * Según artículo 29.2.j) de la Ley 58/2003, General Tributaria
     */
    'declaracion' => 'El productor del sistema informático de facturación declara, '
        . 'bajo su responsabilidad, que el presente sistema cumple con los requisitos '
        . 'establecidos en el Real Decreto 1007/2023, de 5 de diciembre, por el que se '
        . 'aprueba el Reglamento que establece los requisitos que deben adoptar los '
        . 'sistemas y programas informáticos o electrónicos que soporten los procesos '
        . 'de facturación de empresarios y profesionales, y la estandarización de '
        . 'formatos de los registros de facturación, y en la Orden HAC/1177/2024, '
        . 'de 17 de octubre, que desarrolla las especificaciones técnicas.',

    /**
     * Requisitos técnicos cumplidos
     * Según Art. 8 del RD 1007/2023
     */
    'requisitos_cumplidos' => array(
        'integridad' => true,          // Art. 8.1.a) Integridad
        'conservacion' => true,        // Art. 8.1.b) Conservación
        'accesibilidad' => true,       // Art. 8.1.c) Accesibilidad
        'legibilidad' => true,         // Art. 8.1.d) Legibilidad
        'trazabilidad' => true,        // Art. 8.1.e) Trazabilidad
        'inalterabilidad' => true,     // Art. 8.1.f) Inalterabilidad
    ),

    /**
     * Referencia normativa principal
     */
    'referencia_legal' => array(
        'ley' => 'Ley 58/2003, de 17 de diciembre, General Tributaria',
        'articulo' => '29.2.j)',
        'real_decreto' => 'Real Decreto 1007/2023, de 5 de diciembre',
        'orden_ministerial' => 'Orden HAC/1177/2024, de 17 de octubre',
    ),
);

/**
 * =============================================================================
 * SECCIÓN 7: FECHA Y LUGAR DE LA DECLARACIÓN
 * =============================================================================
 * Según Art. 15.4 Orden HAC/1177/2024
 */
$declaracionResponsable['suscripcion'] = array(

    /**
     * Fecha de suscripción de la declaración responsable
     * Formato: YYYY-MM-DD (ISO 8601)
     * @required Obligatorio
     */
    'fecha' => '2025-12-12',

    /**
     * Lugar de suscripción
     * @required Obligatorio (localidad y país como mínimo)
     */
    'lugar' => array(
        'localidad' => '',
        'provincia' => '',
        'pais' => 'España',
        'codigo_pais' => 'ES',
    ),

    /**
     * Persona que suscribe la declaración
     */
    'firmante' => array(
        'nombre' => 'Germán Luis Aracil Boned',
        'cargo' => 'Desarrollador / Productor',
        'en_representacion_de' => '',  // Si actúa en representación de una entidad
    ),
);

/**
 * =============================================================================
 * SECCIÓN 8: METADATOS DE LA CONFIGURACIÓN
 * =============================================================================
 */
$declaracionResponsable['metadata'] = array(

    /**
     * Versión del fichero de configuración
     */
    'version_config' => '1.0.0',

    /**
     * Fecha de creación
     */
    'fecha_creacion' => '2025-12-12',

    /**
     * Última modificación
     */
    'ultima_modificacion' => '2025-12-12',

    /**
     * Autor de la configuración
     */
    'autor' => 'Germán Luis Aracil Boned',

    /**
     * Notas de la versión
     */
    'notas' => 'Versión inicial del fichero de configuración para declaración responsable.',
);

/**
 * =============================================================================
 * FUNCIONES AUXILIARES
 * =============================================================================
 */

/**
 * Calcula el hash de integridad del módulo VeriFactu
 *
 * @param string $basePath Ruta base del módulo
 * @return string Hash SHA-256 del módulo
 */
function calcularHashModuloVerifactu($basePath = '')
{
    global $declaracionResponsable;

    if (empty($basePath)) {
        $basePath = dirname(__DIR__);
    }

    $contenidoTotal = '';

    foreach ($declaracionResponsable['integridad']['ficheros_verificados'] as $fichero) {
        $rutaCompleta = $basePath . '/' . $fichero;
        if (file_exists($rutaCompleta)) {
            $contenidoTotal .= file_get_contents($rutaCompleta);
        }
    }

    $hash = hash('sha256', $contenidoTotal);

    // Actualizar valores en la configuración
    $declaracionResponsable['integridad']['hash_modulo'] = $hash;
    $declaracionResponsable['integridad']['fecha_calculo'] = date('Y-m-d H:i:s');

    return $hash;
}

/**
 * Genera el identificador único de instalación
 *
 * @return string Identificador único
 */
function generarIdInstalacion()
{
    global $declaracionResponsable, $conf;

    $datos = array(
        $declaracionResponsable['sistema']['id_sistema_informatico'],
        $declaracionResponsable['sistema']['version'],
        isset($conf->global->MAIN_INFO_SOCIETE_NOM) ? $conf->global->MAIN_INFO_SOCIETE_NOM : '',
        isset($conf->global->MAIN_INFO_SIREN) ? $conf->global->MAIN_INFO_SIREN : '',
        php_uname('n'),
    );

    return substr(hash('sha256', implode('|', $datos)), 0, 32);
}

/**
 * Obtiene la configuración completa de la declaración responsable
 *
 * @param bool $calcularHash Si es true, recalcula el hash del módulo
 * @return array Configuración completa
 */
function obtenerDeclaracionResponsable($calcularHash = true)
{
    global $declaracionResponsable;

    if ($calcularHash) {
        calcularHashModuloVerifactu();
    }

    $declaracionResponsable['sistema']['numero_instalacion'] = generarIdInstalacion();

    return $declaracionResponsable;
}

/**
 * Valida que la configuración de la declaración responsable esté completa
 *
 * @return array Array con errores encontrados (vacío si todo es correcto)
 */
function validarDeclaracionResponsable()
{
    global $declaracionResponsable;

    $errores = array();

    // Validar datos del productor
    if (empty($declaracionResponsable['productor']['razon_social'])) {
        $errores[] = 'Falta la razón social del productor';
    }
    if (empty($declaracionResponsable['productor']['nif']) &&
        empty($declaracionResponsable['productor']['nif_extranjero']['numero'])) {
        $errores[] = 'Falta el NIF o identificación fiscal del productor';
    }
    if (empty($declaracionResponsable['productor']['direccion']['localidad'])) {
        $errores[] = 'Falta la localidad en la dirección del productor';
    }

    // Validar datos del sistema
    if (empty($declaracionResponsable['sistema']['nombre'])) {
        $errores[] = 'Falta el nombre del sistema informático';
    }
    if (empty($declaracionResponsable['sistema']['id_sistema_informatico'])) {
        $errores[] = 'Falta el IdSistemaInformatico';
    }
    if (empty($declaracionResponsable['sistema']['version'])) {
        $errores[] = 'Falta la versión del sistema';
    }

    // Validar suscripción
    if (empty($declaracionResponsable['suscripcion']['fecha'])) {
        $errores[] = 'Falta la fecha de suscripción';
    }
    if (empty($declaracionResponsable['suscripcion']['lugar']['localidad'])) {
        $errores[] = 'Falta la localidad de suscripción';
    }

    return $errores;
}

/**
 * Exporta la declaración responsable en formato JSON
 *
 * @return string JSON de la declaración
 */
function exportarDeclaracionJSON()
{
    $declaracion = obtenerDeclaracionResponsable(true);
    return json_encode($declaracion, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
