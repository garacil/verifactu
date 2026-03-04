<?php

/**
 * OpenAEAT Billing Library - SchemaManager Class
 *
 * Downloads and caches WSDL/XSD schemas locally to avoid runtime
 * dependencies on external servers (AEAT, W3C). This prevents
 * rate-limiting errors when processing many invoices in sequence.
 *
 * @package    OpenAEAT\Billing
 * @author     German Luis Aracil Boned <garacilb@gmail.com>
 * @copyright  2025 German Luis Aracil Boned
 * @license    GPL-3.0-or-later
 */

declare(strict_types=1);

namespace OpenAEAT\Billing;

class SchemaManager
{
    /**
     * Base URL for AEAT schema files
     */
    private const AEAT_BASE_URL = 'https://prewww2.aeat.es/static_files/common/internet/dep/aplicaciones/es/aeat/tikeV1.0/cont/ws/';

    /**
     * Remote URL for W3C XML Digital Signature schema
     */
    private const XMLDSIG_REMOTE_URL = 'http://www.w3.org/TR/xmldsig-core/xmldsig-core-schema.xsd';

    /**
     * WSDL filename
     */
    private const WSDL_FILENAME = 'SistemaFacturacion.wsdl';

    /**
     * Schema files to download from AEAT (relative to base URL)
     */
    private const AEAT_SCHEMAS = [
        'SistemaFacturacion.wsdl',
        'SuministroInformacion.xsd',
        'SuministroLR.xsd',
        'ConsultaLR.xsd',
        'RespuestaConsultaLR.xsd',
        'RespuestaSuministro.xsd',
    ];

    /**
     * Local directory for cached schemas
     */
    private string $schemasDir;

    /**
     * @param string $schemasDir Absolute path to schema storage directory
     */
    public function __construct(string $schemasDir)
    {
        $this->schemasDir = rtrim($schemasDir, '/');
    }

    /**
     * Returns the path to the local WSDL file.
     *
     * @return string Absolute path to local WSDL
     */
    public function getLocalWsdlPath(): string
    {
        return $this->schemasDir . '/' . self::WSDL_FILENAME;
    }

    /**
     * Checks if all required schema files exist locally.
     *
     * @return bool True if all schemas are present
     */
    public function schemasExist(): bool
    {
        foreach (self::AEAT_SCHEMAS as $filename) {
            if (!file_exists($this->schemasDir . '/' . $filename)) {
                return false;
            }
        }
        if (!file_exists($this->schemasDir . '/xmldsig-core-schema.xsd')) {
            return false;
        }
        return true;
    }

    /**
     * Downloads all schema files and rewrites schemaLocation references
     * to point to local files.
     *
     * @param bool $force Re-download even if files exist
     * @return array Result with 'success' bool, 'count' int, 'error' string
     */
    public function downloadSchemas(bool $force = false): array
    {
        if (!$force && $this->schemasExist()) {
            return ['success' => true, 'count' => 0, 'error' => ''];
        }

        // Create directory if needed
        if (!is_dir($this->schemasDir)) {
            if (!@mkdir($this->schemasDir, 0755, true)) {
                return ['success' => false, 'count' => 0, 'error' => 'Cannot create directory: ' . $this->schemasDir];
            }
        }

        $downloaded = 0;
        $streamContext = stream_context_create([
            'http' => [
                'timeout' => 30,
                'user_agent' => 'Dolibarr-VeriFactu/1.0',
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        // Download AEAT schemas
        foreach (self::AEAT_SCHEMAS as $filename) {
            $url = self::AEAT_BASE_URL . $filename;
            $content = @file_get_contents($url, false, $streamContext);
            if ($content === false) {
                return [
                    'success' => false,
                    'count' => $downloaded,
                    'error' => 'Failed to download: ' . $url,
                ];
            }

            $localPath = $this->schemasDir . '/' . $filename;
            if (file_put_contents($localPath, $content) === false) {
                return [
                    'success' => false,
                    'count' => $downloaded,
                    'error' => 'Failed to write: ' . $localPath,
                ];
            }
            $downloaded++;
        }

        // Download W3C xmldsig schema
        $xmldsigContent = @file_get_contents(self::XMLDSIG_REMOTE_URL, false, $streamContext);
        if ($xmldsigContent === false) {
            return [
                'success' => false,
                'count' => $downloaded,
                'error' => 'Failed to download: ' . self::XMLDSIG_REMOTE_URL,
            ];
        }

        $xmldsigPath = $this->schemasDir . '/xmldsig-core-schema.xsd';
        if (file_put_contents($xmldsigPath, $xmldsigContent) === false) {
            return [
                'success' => false,
                'count' => $downloaded,
                'error' => 'Failed to write: ' . $xmldsigPath,
            ];
        }
        $downloaded++;

        // Rewrite schemaLocation in SuministroInformacion.xsd to use local xmldsig
        $this->rewriteSchemaLocations();

        // Record download timestamp
        file_put_contents(
            $this->schemasDir . '/schemas.meta.json',
            json_encode([
                'downloaded_at' => date('Y-m-d H:i:s'),
                'timestamp' => time(),
                'files' => $downloaded,
            ])
        );

        return ['success' => true, 'count' => $downloaded, 'error' => ''];
    }

    /**
     * Rewrites remote schemaLocation URLs in downloaded files to point
     * to local files in the same directory.
     */
    private function rewriteSchemaLocations(): void
    {
        // SuministroInformacion.xsd: change W3C xmldsig URL to local file
        $file = $this->schemasDir . '/SuministroInformacion.xsd';
        if (file_exists($file)) {
            $content = file_get_contents($file);
            $content = str_replace(
                'schemaLocation="http://www.w3.org/TR/xmldsig-core/xmldsig-core-schema.xsd"',
                'schemaLocation="xmldsig-core-schema.xsd"',
                $content
            );
            file_put_contents($file, $content);
        }
    }

    /**
     * Returns metadata about the last download, or null if not available.
     *
     * @return array|null Metadata with 'downloaded_at', 'timestamp', 'files'
     */
    public function getDownloadMetadata(): ?array
    {
        $metaPath = $this->schemasDir . '/schemas.meta.json';
        if (!file_exists($metaPath)) {
            return null;
        }
        $content = file_get_contents($metaPath);
        $data = json_decode($content, true);
        return is_array($data) ? $data : null;
    }

    /**
     * Removes all cached schema files.
     */
    public function clearSchemas(): void
    {
        $files = array_merge(
            self::AEAT_SCHEMAS,
            ['xmldsig-core-schema.xsd', 'schemas.meta.json']
        );
        foreach ($files as $filename) {
            $path = $this->schemasDir . '/' . $filename;
            if (file_exists($path)) {
                @unlink($path);
            }
        }
    }

    /**
     * Returns the schemas directory path.
     *
     * @return string Directory path
     */
    public function getSchemasDir(): string
    {
        return $this->schemasDir;
    }
}
