<?php

/**
 * OpenAEAT Billing Library - Manager Class
 *
 * This class provides the main orchestration layer for AEAT VeriFactu operations.
 * It coordinates invoice submission, cancellation, and queries while managing
 * certificate authentication and system configuration.
 *
 * @package    OpenAEAT\Billing
 * @author     German Luis Aracil Boned <garacilb@gmail.com>
 * @copyright  2025 German Luis Aracil Boned
 * @license    GPL-3.0-or-later
 */

declare(strict_types=1);

namespace OpenAEAT\Billing;

/**
 * VeriFactu Operations Orchestrator
 */
class Manager
{
    /**
     * Default runtime environment
     */
    private const DEFAULT_RUNTIME = 'test';

    /**
     * Default service endpoint type
     */
    private const DEFAULT_SERVICE = 'verifactu';

    /**
     * Default certificate authentication mode
     */
    private const DEFAULT_AUTH_MODE = 'sello';

    /**
     * Obligated party information
     */
    private array $obligatedParty = [
        'taxId' => '',
        'legalName' => '',
    ];

    /**
     * Billing system metadata
     */
    private array $systemMetadata = [];

    /**
     * Runtime configuration
     */
    private array $runtimeConfig = [
        'environment' => self::DEFAULT_RUNTIME,
        'service' => self::DEFAULT_SERVICE,
        'authMode' => self::DEFAULT_AUTH_MODE,
    ];

    /**
     * Operation audit trail
     */
    private array $auditTrail = [
        'fingerprint' => null,
        'timestamp' => null,
        'payload' => null,
    ];

    /**
     * Transport layer instance
     */
    private ?SoapClient $transport = null;

    /**
     * Initializes the operations manager.
     *
     * @param string $taxId Obligated party tax identification
     * @param string $legalName Obligated party legal name
     * @param string $environment Runtime environment (test|production)
     * @param string $service Service type (verifactu|requirement)
     * @param array $systemInfo Billing system metadata
     * @param string $authMode Certificate authentication mode
     */
    public function __construct(
        string $taxId,
        string $legalName,
        string $environment = self::DEFAULT_RUNTIME,
        string $service = self::DEFAULT_SERVICE,
        array $systemInfo = [],
        string $authMode = self::DEFAULT_AUTH_MODE
    ) {
        $this->obligatedParty['taxId'] = $taxId;
        $this->obligatedParty['legalName'] = $legalName;
        $this->systemMetadata = $systemInfo;
        $this->runtimeConfig['environment'] = $environment;
        $this->runtimeConfig['service'] = $service;
        $this->runtimeConfig['authMode'] = $authMode;
    }

    /**
     * Provisions the transport layer with authentication.
     *
     * @param array $credentials Certificate credentials
     * @return SoapClient Configured transport
     */
    private function provisionTransport(array $credentials): SoapClient
    {
        if ($this->transport === null) {
            $settings = new Config();
            $settings->setEnvironment($this->runtimeConfig['environment']);
            $settings->setService($this->runtimeConfig['service']);
            $settings->setCertType($this->runtimeConfig['authMode']);
            $settings->setCertOptions($credentials);

            $this->transport = new SoapClient($settings);
        }
        return $this->transport;
    }

    /**
     * Prepares an invoice for transmission.
     *
     * @param Invoice $document Invoice document
     */
    private function prepareInvoiceDocument(Invoice $document): void
    {
        $currentData = $document->getData(false);
        if (empty($currentData['NombreRazonEmisor'] ?? '')) {
            $document->setIssuer(
                $this->obligatedParty['taxId'],
                $this->obligatedParty['legalName']
            );
        }

        if (!empty($this->systemMetadata)) {
            $document->setSystemInfo($this->systemMetadata);
        }
    }

    /**
     * Records operation audit information.
     *
     * @param array $data Transmitted data
     */
    private function recordAuditInfo(array $data): void
    {
        $registration = $data['RegistroFactura']['RegistroAlta'] ?? [];
        $this->auditTrail['fingerprint'] = $registration['Huella'] ?? null;
        $this->auditTrail['timestamp'] = $registration['FechaHoraHusoGenRegistro'] ?? null;
        $this->auditTrail['payload'] = $data;
    }

    /**
     * Transmits an invoice to AEAT.
     *
     * @param Invoice $document Invoice to transmit
     * @param array $credentials Authentication credentials
     * @return mixed AEAT response
     */
    public function sendInvoice(Invoice $document, array $credentials)
    {
        $this->prepareInvoiceDocument($document);
        $this->transport = $this->provisionTransport($credentials);

        $payload = $document->getData(true);
        $this->recordAuditInfo($payload);

        return $this->transport->sendInvoice([
            'RegFactuSistemaFacturacion' => $payload,
        ]);
    }

    /**
     * Transmits a cancellation to AEAT.
     *
     * @param Cancellation $document Cancellation document
     * @param array $credentials Authentication credentials
     * @return mixed AEAT response
     */
    public function sendCancellation(Cancellation $document, array $credentials)
    {
        if (!empty($this->systemMetadata)) {
            $document->setSystemInfo($this->systemMetadata);
        }

        $this->transport = $this->provisionTransport($credentials);
        $payload = $document->getData(true);

        return $this->transport->sendInvoice([
            'RegFactuSistemaFacturacion' => $payload,
        ]);
    }

    /**
     * Executes an invoice query against AEAT.
     *
     * @param Query $request Query request
     * @param array $credentials Authentication credentials
     * @return mixed AEAT response
     */
    public function queryInvoice(Query $request, array $credentials = [])
    {
        $this->transport = $this->provisionTransport($credentials);
        $payload = $request->getData();

        return $this->transport->sendInvoiceQuery($payload);
    }

    /**
     * Returns diagnostic information from the last operation.
     *
     * @param bool $formatted Format as HTML
     * @return string Diagnostic output
     */
    public function getDebugInfo(bool $formatted = true): string
    {
        if ($this->transport !== null) {
            return $this->transport->getDebugInfo($formatted);
        }
        return '';
    }

    /**
     * Returns the cryptographic fingerprint from the last invoice.
     *
     * @return string|null SHA-256 hash
     */
    public function getLastGeneratedHash(): ?string
    {
        return $this->auditTrail['fingerprint'];
    }

    /**
     * Returns the generation timestamp from the last invoice.
     *
     * @return string|null ISO 8601 timestamp
     */
    public function getLastGeneratedTimestamp(): ?string
    {
        return $this->auditTrail['timestamp'];
    }

    /**
     * Returns the current runtime environment.
     *
     * @return string|null Environment identifier
     */
    public function getEnvironment(): ?string
    {
        return $this->runtimeConfig['environment'];
    }

    /**
     * Returns the payload from the last invoice transmission.
     *
     * @return array|null Transmitted data
     */
    public function getLastSendInvoiceData(): ?array
    {
        return $this->auditTrail['payload'];
    }

    /**
     * Submits an invoice without explicit credentials.
     *
     * @param Invoice $document Invoice document
     * @return mixed AEAT response
     */
    public function submitInvoice(Invoice $document)
    {
        return $this->sendInvoice($document, []);
    }

    /**
     * Submits a cancellation without explicit credentials.
     *
     * @param Cancellation $document Cancellation document
     * @return mixed AEAT response
     */
    public function submitCancellation(Cancellation $document)
    {
        return $this->sendCancellation($document, []);
    }

    /**
     * Executes a query without explicit credentials.
     *
     * @param Query $request Query request
     * @return mixed AEAT response
     */
    public function query(Query $request)
    {
        return $this->queryInvoice($request, []);
    }

    /**
     * Returns the last fingerprint (alias).
     *
     * @return string|null SHA-256 hash
     */
    public function getLastFingerprint(): ?string
    {
        return $this->auditTrail['fingerprint'];
    }

    /**
     * Returns the last timestamp (alias).
     *
     * @return string|null ISO 8601 timestamp
     */
    public function getLastTimestamp(): ?string
    {
        return $this->auditTrail['timestamp'];
    }

    /**
     * Returns the last response payload (alias).
     *
     * @return array|null Transmitted data
     */
    public function getLastResponse(): ?array
    {
        return $this->auditTrail['payload'];
    }

    /**
     * Returns the underlying transport layer.
     *
     * @return SoapClient|null Transport instance
     */
    public function getSoapClient(): ?SoapClient
    {
        return $this->transport;
    }

    /**
     * Factory method for creating invoices.
     *
     * @return Invoice New invoice instance
     */
    public function createInvoice(): Invoice
    {
        return new Invoice(
            '',
            '',
            $this->obligatedParty['taxId'],
            $this->obligatedParty['legalName']
        );
    }

    /**
     * Factory method for creating cancellations.
     *
     * @return Cancellation New cancellation instance
     */
    public function createCancellation(): Cancellation
    {
        return new Cancellation(
            '',
            '',
            $this->obligatedParty['taxId'],
            $this->obligatedParty['taxId'],
            $this->obligatedParty['legalName']
        );
    }

    /**
     * Factory method for creating queries.
     *
     * @return Query New query instance
     */
    public function createQuery(): Query
    {
        return new Query(
            null,
            null,
            $this->obligatedParty['taxId'],
            $this->obligatedParty['legalName']
        );
    }

    /**
     * Returns the obligated party tax ID.
     *
     * @return string Tax identification
     */
    public function getObligatedTaxId(): string
    {
        return $this->obligatedParty['taxId'];
    }

    /**
     * Returns the obligated party legal name.
     *
     * @return string Legal name
     */
    public function getObligatedName(): string
    {
        return $this->obligatedParty['legalName'];
    }

    /**
     * Returns the billing system metadata.
     *
     * @return array System configuration
     */
    public function getSystemMetadata(): array
    {
        return $this->systemMetadata;
    }

    /**
     * Updates the billing system metadata.
     *
     * @param array $metadata New metadata
     * @return self
     */
    public function setSystemMetadata(array $metadata): self
    {
        $this->systemMetadata = $metadata;
        return $this;
    }

    /**
     * Resets the transport layer for new configuration.
     *
     * @return self
     */
    public function resetTransport(): self
    {
        $this->transport = null;
        return $this;
    }

    /**
     * Clears the audit trail.
     *
     * @return self
     */
    public function clearAuditTrail(): self
    {
        $this->auditTrail = [
            'fingerprint' => null,
            'timestamp' => null,
            'payload' => null,
        ];
        return $this;
    }

    /**
     * Creates a manager instance for testing environment.
     *
     * @param string $taxId Obligated party tax ID
     * @param string $name Obligated party name
     * @param array $systemInfo System metadata
     * @return self Configured instance
     */
    public static function forTesting(string $taxId, string $name, array $systemInfo = []): self
    {
        return new self($taxId, $name, 'test', 'verifactu', $systemInfo, 'sello');
    }

    /**
     * Creates a manager instance for production environment.
     *
     * @param string $taxId Obligated party tax ID
     * @param string $name Obligated party name
     * @param array $systemInfo System metadata
     * @return self Configured instance
     */
    public static function forProduction(string $taxId, string $name, array $systemInfo = []): self
    {
        return new self($taxId, $name, 'production', 'verifactu', $systemInfo, 'sello');
    }
}
