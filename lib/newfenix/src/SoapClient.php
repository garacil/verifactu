<?php

/**
 * OpenAEAT Billing Library - SoapClient Class
 *
 * This class handles all SOAP communication with the Spanish Tax Agency (AEAT)
 * VeriFactu services. Provides a clean, modular approach to SOAP operations
 * with comprehensive debugging capabilities.
 *
 * @package    OpenAEAT\Billing
 * @author     German Luis Aracil Boned <garacilb@gmail.com>
 * @copyright  2025 German Luis Aracil Boned
 * @license    GPL-3.0-or-later
 */

declare(strict_types=1);

namespace OpenAEAT\Billing;

/**
 * AEAT SOAP Transport Layer
 */
class SoapClient
{
    /**
     * Service operation types
     */
    public const OP_REGISTRATION = 'RegFactuSistemaFacturacion';
    public const OP_QUERY = 'ConsultaFactuSistemaFacturacion';

    /**
     * WSDL definition location
     */
    private const SCHEMA_LOCATION = 'https://prewww2.aeat.es/static_files/common/internet/dep/aplicaciones/es/aeat/tikeV1.0/cont/ws/SistemaFacturacion.wsdl';

    /**
     * SOAP transport configuration
     */
    private const TRANSPORT_SETTINGS = [
        'version' => SOAP_1_1,
        'encoding' => 'UTF-8',
        'trace_enabled' => true,
        'exception_handling' => true,
        'cache_policy' => WSDL_CACHE_NONE,
    ];

    /**
     * Configuration instance
     */
    private Config $settings;

    /**
     * Trace buffer for debugging
     */
    private array $traceBuffer = [
        'outgoing_headers' => null,
        'outgoing_body' => null,
        'incoming_headers' => null,
        'incoming_body' => null,
    ];

    /**
     * Creates a new SOAP transport instance.
     *
     * @param Config $settings Configuration for the transport
     */
    public function __construct(Config $settings)
    {
        $this->settings = $settings;
    }

    /**
     * Transmits an invoice registration to AEAT.
     *
     * @param array $payload Invoice data structure
     * @return mixed AEAT response object
     * @throws BillingException On SOAP communication failure
     */
    public function sendInvoice(array $payload)
    {
        $unwrapped = $this->extractPayload($payload, self::OP_REGISTRATION);
        return $this->executeOperation(
            self::OP_REGISTRATION,
            $unwrapped,
            $this->settings->getVerifactuEndpoint()
        );
    }

    /**
     * Transmits a cancellation request to AEAT.
     * Cancellations use the same registration operation as invoices.
     *
     * @param array $payload Cancellation data structure
     * @return mixed AEAT response object
     * @throws BillingException On SOAP communication failure
     */
    public function sendCancellation(array $payload)
    {
        return $this->sendInvoice($payload);
    }

    /**
     * Executes an invoice query against AEAT.
     *
     * @param array $payload Query parameters
     * @return mixed AEAT response object
     * @throws BillingException On SOAP communication failure
     */
    public function sendQuery(array $payload)
    {
        $unwrapped = $this->extractPayload($payload, self::OP_QUERY);
        return $this->executeOperation(
            self::OP_QUERY,
            $unwrapped,
            $this->settings->getVerifactuEndpoint()
        );
    }

    /**
     * Executes an invoice query (compatibility method).
     *
     * @param array $payload Query parameters
     * @return mixed AEAT response object
     */
    public function sendInvoiceQuery(array $payload)
    {
        return $this->sendQuery($payload);
    }

    /**
     * Transmits a requirement response to AEAT.
     *
     * @param array $payload Requirement response data
     * @return mixed AEAT response object
     * @throws BillingException On SOAP communication failure
     */
    public function sendRequirement(array $payload)
    {
        $unwrapped = $this->extractPayload($payload, self::OP_REGISTRATION);
        return $this->executeOperation(
            self::OP_REGISTRATION,
            $unwrapped,
            $this->settings->getRequirementEndpoint()
        );
    }

    /**
     * Extracts payload from wrapper if present.
     *
     * @param array $data Possibly wrapped data
     * @param string $wrapperKey Expected wrapper key
     * @return array Unwrapped data
     */
    private function extractPayload(array $data, string $wrapperKey): array
    {
        return $data[$wrapperKey] ?? $data;
    }

    /**
     * Executes a SOAP operation against AEAT.
     *
     * @param string $operation Operation name
     * @param array $data Operation parameters
     * @param string $targetEndpoint Target service URL
     * @return mixed AEAT response
     * @throws BillingException On failure
     */
    private function executeOperation(string $operation, array $data, string $targetEndpoint)
    {
        $transport = $this->buildTransport($targetEndpoint);

        try {
            $result = $transport->__soapCall($operation, [$data]);
            $this->captureTrace($transport);
            return $result;
        } catch (\SoapFault $fault) {
            $this->captureTrace($transport);
            throw new BillingException(
                sprintf('AEAT communication failed: %s', $fault->getMessage()),
                (int) $fault->getCode(),
                $fault
            );
        }
    }

    /**
     * Constructs the PHP SOAP client with appropriate settings.
     *
     * @param string $endpoint Target service endpoint
     * @return \SoapClient Configured PHP SOAP client
     */
    private function buildTransport(string $endpoint): \SoapClient
    {
        $options = $this->assembleTransportOptions();
        $client = new \SoapClient(self::SCHEMA_LOCATION, $options);
        $client->__setLocation($endpoint);
        return $client;
    }

    /**
     * Assembles transport options for the SOAP client.
     *
     * @return array Complete options array
     */
    private function assembleTransportOptions(): array
    {
        $base = [
            'soap_version' => self::TRANSPORT_SETTINGS['version'],
            'encoding' => self::TRANSPORT_SETTINGS['encoding'],
            'trace' => self::TRANSPORT_SETTINGS['trace_enabled'],
            'exceptions' => self::TRANSPORT_SETTINGS['exception_handling'],
            'cache_wsdl' => self::TRANSPORT_SETTINGS['cache_policy'],
            'stream_context' => $this->createSecurityContext(),
        ];

        $certSettings = $this->settings->getCertOptions();
        if (!empty($certSettings)) {
            $base = array_merge($base, $certSettings);
        }

        return $base;
    }

    /**
     * Creates SSL/TLS stream context for secure communication.
     *
     * @return resource Stream context
     */
    private function createSecurityContext()
    {
        $sslConfig = [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ];

        $certPath = $this->settings->getCertificatePath();
        if (!empty($certPath)) {
            $sslConfig['local_cert'] = $certPath;
        }

        $certPass = $this->settings->getCertificatePassword();
        if (!empty($certPass)) {
            $sslConfig['passphrase'] = $certPass;
        }

        $additionalOptions = $this->settings->getCertOptions();
        $sslConfig = array_merge($sslConfig, $additionalOptions);

        return stream_context_create(['ssl' => $sslConfig]);
    }

    /**
     * Captures trace information from a SOAP call.
     *
     * @param \SoapClient $transport The transport instance
     */
    private function captureTrace(\SoapClient $transport): void
    {
        $this->traceBuffer = [
            'outgoing_headers' => $transport->__getLastRequestHeaders(),
            'outgoing_body' => $transport->__getLastRequest(),
            'incoming_headers' => $transport->__getLastResponseHeaders(),
            'incoming_body' => $transport->__getLastResponse(),
        ];
    }

    /**
     * Retrieves the last outgoing request body.
     *
     * @return string|null XML request body
     */
    public function getLastRequest(): ?string
    {
        return $this->traceBuffer['outgoing_body'];
    }

    /**
     * Retrieves the last incoming response body.
     *
     * @return string|null XML response body
     */
    public function getLastResponse(): ?string
    {
        return $this->traceBuffer['incoming_body'];
    }

    /**
     * Retrieves the last outgoing request headers.
     *
     * @return string|null HTTP headers
     */
    public function getLastRequestHeaders(): ?string
    {
        return $this->traceBuffer['outgoing_headers'];
    }

    /**
     * Retrieves the last incoming response headers.
     *
     * @return string|null HTTP headers
     */
    public function getLastResponseHeaders(): ?string
    {
        return $this->traceBuffer['incoming_headers'];
    }

    /**
     * Generates formatted debug output.
     *
     * @param bool $asHtml Format as HTML (true) or plain text (false)
     * @return string Formatted debug information
     */
    public function getDebugInfo(bool $asHtml = true): string
    {
        $divider = $asHtml ? '<br>' : PHP_EOL;

        $sections = [
            ['title' => 'OUTGOING HEADERS', 'content' => $this->traceBuffer['outgoing_headers']],
            ['title' => 'OUTGOING BODY', 'content' => $this->traceBuffer['outgoing_body']],
            ['title' => 'INCOMING HEADERS', 'content' => $this->traceBuffer['incoming_headers']],
            ['title' => 'INCOMING BODY', 'content' => $this->traceBuffer['incoming_body']],
        ];

        $output = [];
        foreach ($sections as $section) {
            $output[] = sprintf('=== %s ===', $section['title']);
            $output[] = $section['content'] ?? 'N/A';
            $output[] = '';
        }

        $formatted = implode($divider, $output);

        if ($asHtml) {
            return sprintf('<pre>%s</pre>', htmlspecialchars($formatted));
        }

        return $formatted;
    }

    /**
     * Clears the trace buffer.
     */
    public function clearTrace(): void
    {
        $this->traceBuffer = [
            'outgoing_headers' => null,
            'outgoing_body' => null,
            'incoming_headers' => null,
            'incoming_body' => null,
        ];
    }

    /**
     * Returns the current configuration.
     *
     * @return Config Current settings
     */
    public function getConfig(): Config
    {
        return $this->settings;
    }
}
