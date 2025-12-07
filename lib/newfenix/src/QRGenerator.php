<?php

/**
 * OpenAEAT Billing Library - QR Code Generator for Spanish Tax Agency
 *
 * Generates QR codes for invoice verification as required by AEAT VeriFactu system.
 * Implements AEAT technical specifications document v0.4.7 (17/10/2024).
 *
 * @package    OpenAEAT\Billing
 * @author     German Luis Aracil Boned <garacilb@gmail.com>
 * @copyright  2025 German Luis Aracil Boned
 * @license    GPL-3.0-or-later
 * @link       https://www.agenciatributaria.es/
 */

declare(strict_types=1);

namespace OpenAEAT\Billing;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/**
 * QR Code generator for VeriFactu invoice verification.
 *
 * This class handles the generation of QR codes that allow customers
 * to verify invoices against the Spanish Tax Agency (AEAT) systems.
 */
class QRGenerator
{
    /**
     * AEAT endpoint configuration
     */
    private const ENDPOINTS = [
        'test' => [
            'verifactu' => 'https://prewww2.aeat.es/wlpl/TIKE-CONT/ValidarQR',
            'standard' => 'https://prewww2.aeat.es/wlpl/TIKE-CONT/ValidarQRNoVerifactu',
        ],
        'production' => [
            'verifactu' => 'https://www2.agenciatributaria.gob.es/wlpl/TIKE-CONT/ValidarQR',
            'standard' => 'https://www2.agenciatributaria.gob.es/wlpl/TIKE-CONT/ValidarQRNoVerifactu',
        ],
    ];

    /**
     * Physical QR code dimensions per AEAT specifications (in millimeters)
     */
    public const DIMENSIONS = [
        'size_min' => 30,
        'size_max' => 40,
        'size_recommended' => 35,
        'margin_min' => 2,
        'margin_recommended' => 6,
    ];

    /**
     * Error correction level: M = 15% recovery capacity
     */
    public const ERROR_CORRECTION = 'M';

    /**
     * NIF validation letter sequence
     */
    private const NIF_LETTERS = 'TRWAGMYFPDXBNJZSQVHLCKE';

    /**
     * NIE prefix to number mapping
     */
    private const NIE_PREFIXES = ['X' => '0', 'Y' => '1', 'Z' => '2'];

    /**
     * CIF valid first characters
     */
    private const CIF_LETTERS = 'ABCDEFGHJNPQRSUVW';

    /** @var Config|null Configuration instance */
    private ?Config $config;

    /** @var bool Whether to use test environment */
    private bool $testMode;

    /**
     * Constructor.
     *
     * @param Config|null $config Configuration for environment detection
     */
    public function __construct(?Config $config = null)
    {
        $this->config = $config;
        $this->testMode = $config === null || !$config->isProduction();
    }

    // =========================================================================
    // PUBLIC API - URL Generation
    // =========================================================================

    /**
     * Creates a verification URL for VeriFactu-submitted invoices.
     *
     * @param string $taxId Tax identification number (NIF/CIF)
     * @param string $serialNumber Invoice serial + number (max 60 chars)
     * @param string $date Issue date (DD-MM-YYYY format)
     * @param float $total Invoice total amount
     * @param bool|null $useTestEnv Override environment (null = auto-detect)
     * @return string Complete verification URL
     * @throws \InvalidArgumentException On validation failure
     */
    public function createVerifactuUrl(
        string $taxId,
        string $serialNumber,
        string $date,
        float $total,
        ?bool $useTestEnv = null
    ): string {
        $this->checkInputs($taxId, $serialNumber, $date, $total);
        $env = $useTestEnv ?? $this->testMode;
        $endpoint = self::ENDPOINTS[$env ? 'test' : 'production']['verifactu'];
        return $this->assembleUrl($endpoint, $taxId, $serialNumber, $date, $total);
    }

    /**
     * Creates a verification URL for non-VeriFactu invoices.
     *
     * @param string $taxId Tax identification number
     * @param string $serialNumber Invoice serial + number
     * @param string $date Issue date (DD-MM-YYYY)
     * @param float $total Invoice total
     * @param bool|null $useTestEnv Override environment
     * @return string Complete verification URL
     * @throws \InvalidArgumentException On validation failure
     */
    public function createStandardUrl(
        string $taxId,
        string $serialNumber,
        string $date,
        float $total,
        ?bool $useTestEnv = null
    ): string {
        $this->checkInputs($taxId, $serialNumber, $date, $total);
        $env = $useTestEnv ?? $this->testMode;
        $endpoint = self::ENDPOINTS[$env ? 'test' : 'production']['standard'];
        return $this->assembleUrl($endpoint, $taxId, $serialNumber, $date, $total);
    }

    /**
     * Appends JSON response format parameter to URL.
     *
     * Note: This should NOT be used for printed QR codes.
     *
     * @param string $url Base verification URL
     * @return string URL with formato=json appended
     */
    public function appendJsonFormat(string $url): string
    {
        $glue = (strpos($url, '?') === false) ? '?' : '&';
        return $url . $glue . 'formato=json';
    }

    // =========================================================================
    // PUBLIC API - QR Image Generation
    // =========================================================================

    /**
     * Renders QR code as PNG binary.
     *
     * @param string $content Data to encode
     * @param int $pixels Image dimension in pixels
     * @return string PNG binary data
     * @throws \RuntimeException On generation failure
     */
    public function renderPng(string $content, int $pixels = 300): string
    {
        return $this->renderQrCode($content, $pixels, QRCode::OUTPUT_IMAGE_PNG);
    }

    /**
     * Renders QR code as SVG markup.
     *
     * @param string $content Data to encode
     * @param int $pixels Base size for scaling
     * @return string SVG XML markup
     * @throws \RuntimeException On generation failure
     */
    public function renderSvg(string $content, int $pixels = 300): string
    {
        return $this->renderQrCode($content, $pixels, QRCode::OUTPUT_MARKUP_SVG);
    }

    /**
     * Renders QR code as Base64-encoded data URI.
     *
     * @param string $content Data to encode
     * @param int $pixels Image dimension
     * @param bool $withPrefix Include data:image/png;base64, prefix
     * @return string Base64 string
     * @throws \RuntimeException On generation failure
     */
    public function renderBase64(string $content, int $pixels = 300, bool $withPrefix = true): string
    {
        $binary = $this->renderPng($content, $pixels);
        $encoded = base64_encode($binary);
        return $withPrefix ? 'data:image/png;base64,' . $encoded : $encoded;
    }

    /**
     * Saves QR code image to filesystem.
     *
     * @param string $content Data to encode
     * @param string $path Destination file path
     * @param int $pixels Image dimension
     * @return bool True on success
     * @throws \RuntimeException On save failure
     */
    public function saveToDisk(string $content, string $path, int $pixels = 300): bool
    {
        $data = $this->renderPng($content, $pixels);
        $written = file_put_contents($path, $data);
        if ($written === false) {
            throw new \RuntimeException("Failed to write QR image to: {$path}");
        }
        return true;
    }

    // =========================================================================
    // PUBLIC API - Convenience Methods
    // =========================================================================

    /**
     * Generates complete QR PNG for a VeriFactu invoice.
     *
     * @param string $taxId Issuer tax ID
     * @param string $serialNumber Invoice number
     * @param string $date Issue date
     * @param float $total Amount
     * @param int $pixels Image size
     * @return string PNG binary
     */
    public function invoiceQrPng(
        string $taxId,
        string $serialNumber,
        string $date,
        float $total,
        int $pixels = 300
    ): string {
        $url = $this->createVerifactuUrl($taxId, $serialNumber, $date, $total);
        return $this->renderPng($url, $pixels);
    }

    /**
     * Generates complete QR Base64 for a VeriFactu invoice.
     *
     * @param string $taxId Issuer tax ID
     * @param string $serialNumber Invoice number
     * @param string $date Issue date
     * @param float $total Amount
     * @param int $pixels Image size
     * @return string Base64 data URI
     */
    public function invoiceQrBase64(
        string $taxId,
        string $serialNumber,
        string $date,
        float $total,
        int $pixels = 300
    ): string {
        $url = $this->createVerifactuUrl($taxId, $serialNumber, $date, $total);
        return $this->renderBase64($url, $pixels);
    }

    /**
     * Generates complete QR SVG for a VeriFactu invoice.
     *
     * @param string $taxId Issuer tax ID
     * @param string $serialNumber Invoice number
     * @param string $date Issue date
     * @param float $total Amount
     * @param int $pixels Base size
     * @return string SVG markup
     */
    public function invoiceQrSvg(
        string $taxId,
        string $serialNumber,
        string $date,
        float $total,
        int $pixels = 300
    ): string {
        $url = $this->createVerifactuUrl($taxId, $serialNumber, $date, $total);
        return $this->renderSvg($url, $pixels);
    }

    // =========================================================================
    // PUBLIC API - Static Factory Methods (Backwards Compatibility)
    // =========================================================================

    /**
     * Creates VeriFactu verification URL (static version).
     *
     * @param string $nif Tax ID
     * @param string $invoiceNumber Serial number
     * @param string $invoiceDate Date DD-MM-YYYY
     * @param float $amount Total
     * @param bool $isTest Use test environment
     * @return string Verification URL
     */
    public static function generateVerifiableUrl(
        string $nif,
        string $invoiceNumber,
        string $invoiceDate,
        float $amount,
        bool $isTest = false
    ): string {
        $instance = new self();
        return $instance->createVerifactuUrl($nif, $invoiceNumber, $invoiceDate, $amount, $isTest);
    }

    /**
     * Creates non-VeriFactu verification URL (static version).
     *
     * @param string $nif Tax ID
     * @param string $invoiceNumber Serial number
     * @param string $invoiceDate Date DD-MM-YYYY
     * @param float $amount Total
     * @param bool $isTest Use test environment
     * @return string Verification URL
     */
    public static function generateNonVerifiableUrl(
        string $nif,
        string $invoiceNumber,
        string $invoiceDate,
        float $amount,
        bool $isTest = false
    ): string {
        $instance = new self();
        return $instance->createStandardUrl($nif, $invoiceNumber, $invoiceDate, $amount, $isTest);
    }

    /**
     * Adds JSON format parameter (static version).
     *
     * @param string $baseUrl URL to modify
     * @return string Modified URL
     */
    public static function addJsonFormat(string $baseUrl): string
    {
        return (new self())->appendJsonFormat($baseUrl);
    }

    /**
     * Generates QR PNG image (static version).
     *
     * @param string $url Content to encode
     * @param int $size Pixel dimension
     * @param int $margin Unused, kept for compatibility
     * @return string PNG binary
     */
    public static function generateQRImage(string $url, int $size = 300, int $margin = 4): string
    {
        return (new self())->renderPng($url, $size);
    }

    /**
     * Generates QR Base64 (static version).
     *
     * @param string $url Content to encode
     * @param int $size Pixel dimension
     * @param int $margin Unused
     * @param bool $includeDataUri Include prefix
     * @return string Base64 string
     */
    public static function generateBase64QR(
        string $url,
        int $size = 300,
        int $margin = 4,
        bool $includeDataUri = true
    ): string {
        return (new self())->renderBase64($url, $size, $includeDataUri);
    }

    /**
     * Saves QR to file (static version).
     *
     * @param string $url Content
     * @param string $filename Path
     * @param int $size Pixels
     * @param int $margin Unused
     * @return bool Success
     */
    public static function saveQRImage(string $url, string $filename, int $size = 300, int $margin = 4): bool
    {
        return (new self())->saveToDisk($url, $filename, $size);
    }

    /**
     * Gets QR configuration with AEAT recommendations.
     *
     * @param string $url Data to encode
     * @param int $size Pixel size
     * @param int $margin Margin modules
     * @return array Configuration array
     */
    public static function getQRConfiguration(string $url, int $size = 300, int $margin = 4): array
    {
        return [
            'data' => $url,
            'size' => $size,
            'margin' => $margin,
            'error_correction_level' => self::ERROR_CORRECTION,
            'encoding' => 'UTF-8',
            'recommendations' => [
                'physical_size_mm' => [
                    'min' => self::DIMENSIONS['size_min'] . 'x' . self::DIMENSIONS['size_min'],
                    'max' => self::DIMENSIONS['size_max'] . 'x' . self::DIMENSIONS['size_max'],
                    'recommended' => self::DIMENSIONS['size_recommended'] . 'x' . self::DIMENSIONS['size_recommended'],
                ],
                'margin_mm' => [
                    'min' => self::DIMENSIONS['margin_min'],
                    'recommended' => self::DIMENSIONS['margin_recommended'],
                ],
            ],
        ];
    }

    /**
     * Gets example URLs for documentation/testing.
     *
     * @return array Example URLs
     */
    public static function getExamples(): array
    {
        $testNif = '89890001K';
        $testSerial = '12345678-G33';
        $testDate = '01-09-2024';
        $testAmount = 241.4;

        return [
            'verifiable_test' => self::generateVerifiableUrl($testNif, $testSerial, $testDate, $testAmount, true),
            'verifiable_production' => self::generateVerifiableUrl($testNif, $testSerial, $testDate, $testAmount, false),
            'non_verifiable_test' => self::generateNonVerifiableUrl($testNif, $testSerial, $testDate, $testAmount, true),
            'non_verifiable_production' => self::generateNonVerifiableUrl($testNif, $testSerial, $testDate, $testAmount, false),
        ];
    }

    /**
     * Generates QR with metadata (static version).
     *
     * @param string $url Content
     * @param array $options Options
     * @return array Data and metadata
     */
    public static function generateQRWithInfo(string $url, array $options = []): array
    {
        $size = $options['size'] ?? 300;
        $t0 = microtime(true);
        $png = self::generateQRImage($url, $size);
        $elapsed = microtime(true) - $t0;

        return [
            'url' => $url,
            'image_data' => $png,
            'base64' => base64_encode($png),
            'data_uri' => 'data:image/png;base64,' . base64_encode($png),
            'size_pixels' => $size,
            'margin_pixels' => $options['margin'] ?? 4,
            'file_size_bytes' => strlen($png),
            'generation_time_ms' => round($elapsed * 1000, 2),
            'url_length' => strlen($url),
        ];
    }

    // =========================================================================
    // PRIVATE - URL Assembly
    // =========================================================================

    /**
     * Builds the complete URL with query parameters.
     *
     * @param string $endpoint Base URL
     * @param string $taxId NIF
     * @param string $serial Invoice number
     * @param string $date Date
     * @param float $amount Amount
     * @return string Complete URL
     */
    private function assembleUrl(
        string $endpoint,
        string $taxId,
        string $serial,
        string $date,
        float $amount
    ): string {
        $query = http_build_query([
            'nif' => $taxId,
            'numserie' => $serial,
            'fecha' => $date,
            'importe' => $this->formatMoney($amount),
        ], '', '&', PHP_QUERY_RFC3986);

        return $endpoint . '?' . $query;
    }

    /**
     * Formats monetary amount with 2 decimal places.
     *
     * @param float $value Amount
     * @return string Formatted (e.g., "123.45")
     */
    private function formatMoney(float $value): string
    {
        return number_format($value, 2, '.', '');
    }

    // =========================================================================
    // PRIVATE - Input Validation
    // =========================================================================

    /**
     * Validates all input parameters.
     *
     * @param string $taxId Tax ID
     * @param string $serial Serial number
     * @param string $date Date
     * @param float $amount Amount
     * @throws \InvalidArgumentException On failure
     */
    private function checkInputs(string $taxId, string $serial, string $date, float $amount): void
    {
        $this->checkTaxId($taxId);
        $this->checkSerialNumber($serial);
        $this->checkDate($date);
        $this->checkAmount($amount);
    }

    /**
     * Validates Spanish tax identification number.
     *
     * @param string $id Tax ID to validate
     * @throws \InvalidArgumentException If invalid
     */
    private function checkTaxId(string $id): void
    {
        $id = strtoupper(trim($id));

        if (strlen($id) !== 9) {
            throw new \InvalidArgumentException("Tax ID must be 9 characters: {$id}");
        }

        // Check DNI format: 8 digits + letter
        if (preg_match('/^\d{8}[A-Z]$/', $id)) {
            $num = (int) substr($id, 0, 8);
            $letter = $id[8];
            if ($letter !== self::NIF_LETTERS[$num % 23]) {
                throw new \InvalidArgumentException("Invalid DNI control letter: {$id}");
            }
            return;
        }

        // Check NIE format: X/Y/Z + 7 digits + letter
        if (preg_match('/^[XYZ]\d{7}[A-Z]$/', $id)) {
            $prefix = self::NIE_PREFIXES[$id[0]];
            $num = (int) ($prefix . substr($id, 1, 7));
            $letter = $id[8];
            if ($letter !== self::NIF_LETTERS[$num % 23]) {
                throw new \InvalidArgumentException("Invalid NIE control letter: {$id}");
            }
            return;
        }

        // Check CIF format: letter + 7 digits + control
        if (preg_match('/^[' . self::CIF_LETTERS . ']\d{7}[\dA-J]$/', $id)) {
            return; // Basic CIF format check
        }

        throw new \InvalidArgumentException("Unrecognized tax ID format: {$id}");
    }

    /**
     * Validates invoice serial number.
     *
     * @param string $serial Serial to validate
     * @throws \InvalidArgumentException If invalid
     */
    private function checkSerialNumber(string $serial): void
    {
        $len = strlen($serial);

        if ($len === 0 || $len > 60) {
            throw new \InvalidArgumentException("Serial number must be 1-60 characters, got {$len}");
        }

        // Must be printable ASCII only (0x20-0x7E)
        if (!preg_match('/^[\x20-\x7E]+$/', $serial)) {
            throw new \InvalidArgumentException("Serial number contains invalid characters");
        }
    }

    /**
     * Validates date in DD-MM-YYYY format.
     *
     * @param string $date Date to validate
     * @throws \InvalidArgumentException If invalid
     */
    private function checkDate(string $date): void
    {
        if (!preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $date, $m)) {
            throw new \InvalidArgumentException("Date must be DD-MM-YYYY format: {$date}");
        }

        if (!checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
            throw new \InvalidArgumentException("Invalid calendar date: {$date}");
        }
    }

    /**
     * Validates monetary amount.
     *
     * @param float $amount Amount to validate
     * @throws \InvalidArgumentException If invalid
     */
    private function checkAmount(float $amount): void
    {
        $formatted = $this->formatMoney($amount);
        $parts = explode('.', $formatted);

        if (strlen($parts[0]) > 12) {
            throw new \InvalidArgumentException("Amount integer part exceeds 12 digits");
        }
    }

    // =========================================================================
    // PRIVATE - QR Rendering
    // =========================================================================

    /**
     * Renders QR code using chillerlan/php-qrcode library.
     *
     * @param string $content Data to encode
     * @param int $pixels Output size
     * @param int $outputType QRCode output type constant
     * @return string Rendered output (binary or markup)
     * @throws \RuntimeException On failure
     */
    private function renderQrCode(string $content, int $pixels, int $outputType): string
    {
        try {
            $scale = max(1, (int) ($pixels / 25));

            $opts = new QROptions([
                'version' => QRCode::VERSION_AUTO,
                'outputType' => $outputType,
                'eccLevel' => QRCode::ECC_M,
                'scale' => $scale,
                'imageBase64' => false,
                'imageTransparent' => false,
            ]);

            $qr = new QRCode($opts);
            return $qr->render($content);
        } catch (\Throwable $e) {
            throw new \RuntimeException('QR generation failed: ' . $e->getMessage(), 0, $e);
        }
    }
}
