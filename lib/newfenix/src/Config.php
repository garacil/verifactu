<?php

/**
 * OpenAEAT Billing Library - Config Class
 *
 * This class manages runtime configuration for AEAT VeriFactu connections.
 * Handles environment selection, certificate authentication, service endpoints,
 * and billing system identity configuration.
 *
 * @package    OpenAEAT\Billing
 * @author     German Luis Aracil Boned <garacilb@gmail.com>
 * @copyright  2025 German Luis Aracil Boned
 * @license    GPL-3.0-or-later
 */

declare(strict_types=1);

namespace OpenAEAT\Billing;

/**
 * AEAT Connection Configuration Manager
 */
class Config
{
    /**
     * Runtime environment identifiers
     */
    public const ENV_TEST = 'test';
    public const ENV_PRODUCTION = 'production';

    /**
     * Certificate authentication modes
     */
    public const CERT_STANDARD = 'standard';
    public const CERT_SEAL = 'seal';
    public const CERT_NORMAL = 'normal';
    public const CERT_SELLO = 'sello';

    /**
     * Service endpoint bases by environment and auth mode
     */
    private const ENDPOINT_REGISTRY = [
        'sandbox' => [
            'personal' => 'https://prewww1.aeat.es',
            'automated' => 'https://prewww10.aeat.es',
        ],
        'live' => [
            'personal' => 'https://www1.agenciatributaria.gob.es',
            'automated' => 'https://www10.agenciatributaria.gob.es',
        ],
    ];

    /**
     * Service path definitions
     */
    private const SERVICE_PATHS = [
        'billing' => '/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSOAP',
        'compliance' => '/wlpl/TIKE-CONT/ws/SistemaFacturacion/RequerimientoSOAP',
    ];

    /**
     * QR validation service hosts
     */
    private const VALIDATION_HOSTS = [
        'sandbox' => 'https://prewww2.aeat.es',
        'live' => 'https://www2.agenciatributaria.gob.es',
    ];

    /**
     * QR service paths
     */
    private const QR_PATHS = [
        'verifactu' => '/wlpl/TIKE-CONT/ValidarQR',
        'standard' => '/wlpl/TIKE-CONT/ValidarQRNoVerifactu',
    ];

    /**
     * Auth mode mapping to endpoint categories
     */
    private const AUTH_MAPPING = [
        self::CERT_STANDARD => 'personal',
        self::CERT_NORMAL => 'personal',
        self::CERT_SEAL => 'automated',
        self::CERT_SELLO => 'automated',
    ];

    /**
     * Environment mapping to registry keys
     */
    private const ENV_MAPPING = [
        self::ENV_TEST => 'sandbox',
        self::ENV_PRODUCTION => 'live',
    ];

    /**
     * Service type mapping
     */
    private const SERVICE_MAPPING = [
        'verifactu' => 'billing',
        'requirement' => 'compliance',
    ];

    /**
     * Current runtime settings
     */
    private array $settings = [
        'runtime' => self::ENV_TEST,
        'authMode' => self::CERT_STANDARD,
        'serviceType' => 'verifactu',
    ];

    /**
     * Certificate credentials
     */
    private array $credentials = [
        'path' => '',
        'secret' => '',
        'options' => [],
    ];

    /**
     * Software identity metadata
     */
    private ?array $softwareIdentity = null;

    /**
     * Initializes configuration with default or specified values.
     *
     * @param string $runtime Runtime environment
     * @param string $authMode Certificate authentication mode
     */
    public function __construct(
        string $runtime = self::ENV_TEST,
        string $authMode = self::CERT_STANDARD
    ) {
        $this->settings['runtime'] = $runtime;
        $this->settings['authMode'] = $authMode;
    }

    /**
     * Resolves the base host for current configuration.
     *
     * @return string Base URL
     */
    private function resolveBaseHost(): string
    {
        $envKey = self::ENV_MAPPING[$this->settings['runtime']] ?? 'sandbox';
        $authKey = self::AUTH_MAPPING[$this->settings['authMode']] ?? 'personal';
        return self::ENDPOINT_REGISTRY[$envKey][$authKey];
    }

    /**
     * Resolves the validation host for current environment.
     *
     * @return string Validation host URL
     */
    private function resolveValidationHost(): string
    {
        $envKey = self::ENV_MAPPING[$this->settings['runtime']] ?? 'sandbox';
        return self::VALIDATION_HOSTS[$envKey];
    }

    /**
     * Configures certificate file credentials.
     *
     * @param string $filePath Certificate file path
     * @param string $passphrase Certificate password
     * @return self
     */
    public function setCertificate(string $filePath, string $passphrase): self
    {
        $this->credentials['path'] = $filePath;
        $this->credentials['secret'] = $passphrase;
        return $this;
    }

    /**
     * Sets the runtime environment.
     *
     * @param string $runtime Environment identifier
     * @return self
     */
    public function setEnvironment(string $runtime): self
    {
        $this->settings['runtime'] = $runtime;
        return $this;
    }

    /**
     * Sets the service type.
     *
     * @param string $serviceType Service identifier
     * @return self
     */
    public function setService(string $serviceType): self
    {
        $this->settings['serviceType'] = $serviceType;
        return $this;
    }

    /**
     * Sets the certificate authentication mode.
     *
     * @param string $authMode Authentication mode
     * @return self
     */
    public function setCertType(string $authMode): self
    {
        $this->settings['authMode'] = $authMode;
        return $this;
    }

    /**
     * Configures certificate options from array.
     *
     * @param array $options Certificate options
     * @return self
     */
    public function setCertOptions(array $options): self
    {
        $this->credentials['options'] = $options;

        if (isset($options['local_cert'])) {
            $this->credentials['path'] = $options['local_cert'];
        }
        if (isset($options['passphrase'])) {
            $this->credentials['secret'] = $options['passphrase'];
        }

        return $this;
    }

    /**
     * Configures billing software identity.
     *
     * @param string $devTaxId Developer tax identification
     * @param string $name Software name
     * @param string $version Software version
     * @param string $identifier Software identifier
     * @return self
     */
    public function setSystemIdentity(
        string $devTaxId,
        string $name,
        string $version,
        string $identifier
    ): self {
        $this->softwareIdentity = [
            'developerNif' => $devTaxId,
            'softwareName' => $name,
            'softwareVersion' => $version,
            'softwareId' => $identifier,
        ];
        return $this;
    }

    /**
     * Returns the current runtime environment.
     *
     * @return string Environment identifier
     */
    public function getEnvironment(): string
    {
        return $this->settings['runtime'];
    }

    /**
     * Returns the current certificate authentication mode.
     *
     * @return string Auth mode identifier
     */
    public function getCertificateType(): string
    {
        return $this->settings['authMode'];
    }

    /**
     * Returns the certificate file path.
     *
     * @return string File path
     */
    public function getCertificatePath(): string
    {
        return $this->credentials['path'];
    }

    /**
     * Returns the certificate password.
     *
     * @return string Password
     */
    public function getCertificatePassword(): string
    {
        return $this->credentials['secret'];
    }

    /**
     * Returns the certificate options array.
     *
     * @return array Options
     */
    public function getCertOptions(): array
    {
        return $this->credentials['options'];
    }

    /**
     * Returns the current service type.
     *
     * @return string Service identifier
     */
    public function getService(): string
    {
        return $this->settings['serviceType'];
    }

    /**
     * Returns the software identity configuration.
     *
     * @return array|null Identity data
     */
    public function getSystemIdentity(): ?array
    {
        return $this->softwareIdentity;
    }

    /**
     * Constructs the VeriFactu billing service endpoint.
     *
     * @return string Full endpoint URL
     */
    public function getVerifactuEndpoint(): string
    {
        return $this->resolveBaseHost() . self::SERVICE_PATHS['billing'];
    }

    /**
     * Constructs the requirement/compliance service endpoint.
     *
     * @return string Full endpoint URL
     */
    public function getRequirementEndpoint(): string
    {
        return $this->resolveBaseHost() . self::SERVICE_PATHS['compliance'];
    }

    /**
     * Constructs the QR validation URL for VeriFactu invoices.
     *
     * @return string Full validation URL
     */
    public function getQrValidationUrl(): string
    {
        return $this->resolveValidationHost() . self::QR_PATHS['verifactu'];
    }

    /**
     * Constructs the QR validation URL for non-VeriFactu invoices.
     *
     * @return string Full validation URL
     */
    public function getQrNonVerifactuUrl(): string
    {
        return $this->resolveValidationHost() . self::QR_PATHS['standard'];
    }

    /**
     * Checks if running in production environment.
     *
     * @return bool True if production
     */
    public function isProduction(): bool
    {
        return $this->settings['runtime'] === self::ENV_PRODUCTION;
    }

    /**
     * Checks if running in test/sandbox environment.
     *
     * @return bool True if test
     */
    public function isTestEnvironment(): bool
    {
        return $this->settings['runtime'] === self::ENV_TEST;
    }

    /**
     * Checks if using automated (seal) certificate.
     *
     * @return bool True if automated
     */
    public function isAutomatedAuth(): bool
    {
        return in_array(
            $this->settings['authMode'],
            [self::CERT_SEAL, self::CERT_SELLO],
            true
        );
    }

    /**
     * Returns all current settings.
     *
     * @return array Settings array
     */
    public function getSettings(): array
    {
        return $this->settings;
    }

    /**
     * Returns all credential information (excluding secret).
     *
     * @return array Credential info
     */
    public function getCredentialInfo(): array
    {
        return [
            'path' => $this->credentials['path'],
            'hasSecret' => !empty($this->credentials['secret']),
            'optionCount' => count($this->credentials['options']),
        ];
    }

    /**
     * Creates a test environment configuration.
     *
     * @param string $authMode Authentication mode
     * @return self Configured instance
     */
    public static function forTesting(string $authMode = self::CERT_SELLO): self
    {
        return new self(self::ENV_TEST, $authMode);
    }

    /**
     * Creates a production environment configuration.
     *
     * @param string $authMode Authentication mode
     * @return self Configured instance
     */
    public static function forProduction(string $authMode = self::CERT_SELLO): self
    {
        return new self(self::ENV_PRODUCTION, $authMode);
    }

    /**
     * Resets configuration to defaults.
     *
     * @return self
     */
    public function reset(): self
    {
        $this->settings = [
            'runtime' => self::ENV_TEST,
            'authMode' => self::CERT_STANDARD,
            'serviceType' => 'verifactu',
        ];
        $this->credentials = [
            'path' => '',
            'secret' => '',
            'options' => [],
        ];
        $this->softwareIdentity = null;
        return $this;
    }
}
