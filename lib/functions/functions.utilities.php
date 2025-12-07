<?php
/* Copyright (C) 2025 Germán Luis Aracil Boned <garacilb@gmail.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    verifactu/lib/functions/functions.utilities.php
 * \ingroup verifactu
 * \brief   General utility functions for the VeriFactu module
 */

/**
 * Formats an XML string to be human-readable
 *
 * @param string $xmlStr XML string to format
 * @return string|null Formatted XML or null if invalid
 */
function prettyXML($xmlStr)
{
	if (empty($xmlStr)) {
		return null;
	}
	if (substr(trim($xmlStr), 0, strlen('<?xml')) !== '<?xml') {
		return null;
	}
	$dom = new DOMDocument("1.0");
	$dom->preserveWhiteSpace = false;
	$dom->formatOutput = true;
	$dom->loadXML($xmlStr);
	return $dom->saveXML();
}


/**
 * Checks if there is an Internet connection using multiple methods and services.
 *
 * Methods implemented:
 * 1. HTTP requests to reliable public services
 * 2. DNS lookups
 * 3. Socket connections as fallback
 * 4. Ping to public IPs (if available)
 *
 * @param int $timeout Timeout in seconds per method
 * @return bool True if Internet connection is available
 */
function checkInternetConnection(int $timeout = 3): bool
{
	// Method 1: HTTP requests to reliable public services
	$httpServices = [
		'https://www.google.com/generate_204',  // Google no-content endpoint
		'https://httpbin.org/status/200',       // HTTPBin status endpoint
		'https://1.1.1.1/',                    // Cloudflare
		'https://www.microsoft.com/favicon.ico', // Microsoft
		'http://detectportal.firefox.com/success.txt' // Firefox captive portal detection
	];
	foreach ($httpServices as $url) {
		if (checkHttpConnection($url, $timeout)) {
			return true;
		}
	}

	// Method 2: DNS lookups
	$domains = [
		'google.com',
		'cloudflare.com',
		'microsoft.com',
		'github.com',
		'stackoverflow.com'
	];

	foreach ($domains as $domain) {
		if (checkDnsResolution($domain, $timeout)) {
			return true;
		}
	}

	// Method 3: Socket connections (fallback)
	$socketHosts = [
		["8.8.8.8", 53],        // Google DNS
		["1.1.1.1", 53],        // Cloudflare DNS
		["9.9.9.9", 53],        // Quad9 DNS
		["208.67.222.222", 53], // OpenDNS
		["8.8.4.4", 53],        // Google DNS secondary
	];

	foreach ($socketHosts as [$host, $port]) {
		$connected = @fsockopen($host, $port, $errno, $errstr, $timeout);
		if ($connected) {
			fclose($connected);
			return true;
		}
	}

	// Method 4: Ping as last resort (only on compatible systems)
	if (function_exists('exec') && !isWindowsWithoutPing()) {
		$pingHosts = ['8.8.8.8', '1.1.1.1', '9.9.9.9'];
		foreach ($pingHosts as $host) {
			if (checkPingConnection($host, $timeout)) {
				return true;
			}
		}
	}

	return false; // All methods failed
}

/**
 * Checks HTTP connection to a specific URL
 *
 * @param string $url URL to check
 * @param int $timeout Timeout in seconds
 * @return bool True if connection successful
 */
function checkHttpConnection(string $url, int $timeout): bool
{
	try {
		// Use cURL if available
		if (function_exists('curl_init')) {
			$ch = curl_init();
			curl_setopt_array($ch, [
				CURLOPT_URL => $url,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_TIMEOUT => $timeout,
				CURLOPT_CONNECTTIMEOUT => $timeout,
				CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; Internet-Check/1.0)',
				CURLOPT_SSL_VERIFYPEER => false,
				CURLOPT_SSL_VERIFYHOST => false,
				CURLOPT_NOBODY => true, // HEAD request only
				CURLOPT_HEADER => false
			]);

			$result = curl_exec($ch);
			$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			curl_close($ch);

			// Valid HTTP codes indicating connectivity
			return $result !== false && ($httpCode >= 200 && $httpCode < 400);
		}

		// Fallback: file_get_contents with context
		$context = stream_context_create([
			'http' => [
				'method' => 'HEAD',
				'timeout' => $timeout,
				'user_agent' => 'Mozilla/5.0 (compatible; Internet-Check/1.0)',
				'ignore_errors' => true
			]
		]);

		$result = @file_get_contents($url, false, $context);
		return $result !== false;
	} catch (Exception $e) {
		return false;
	}
}

/**
 * Checks DNS resolution for a domain
 *
 * @param string $domain Domain to resolve
 * @param int $timeout Timeout in seconds
 * @return bool True if resolution successful
 */
function checkDnsResolution(string $domain, int $timeout): bool
{
	try {
		// Set DNS timeout
		$originalTimeout = ini_get('default_socket_timeout');
		ini_set('default_socket_timeout', $timeout);

		$result = @gethostbyname($domain);

		// Restore original timeout
		ini_set('default_socket_timeout', $originalTimeout);

		// If gethostbyname cannot resolve, it returns the same domain
		return $result !== $domain && filter_var($result, FILTER_VALIDATE_IP);
	} catch (Exception $e) {
		return false;
	}
}

/**
 * Checks connection using ping
 *
 * @param string $host Host to ping
 * @param int $timeout Timeout in seconds
 * @return bool True if ping successful
 */
function checkPingConnection(string $host, int $timeout): bool
{
	try {
		$isWindows = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');

		if ($isWindows) {
			$cmd = "ping -n 1 -w " . ($timeout * 1000) . " $host";
		} else {
			$cmd = "ping -c 1 -W $timeout $host";
		}

		$output = [];
		$returnCode = 0;
		@exec($cmd . ' 2>&1', $output, $returnCode);

		return $returnCode === 0;
	} catch (Exception $e) {
		return false;
	}
}

/**
 * Detects if running on Windows without ping command available
 *
 * @return bool True if Windows without ping
 */
function isWindowsWithoutPing(): bool
{
	if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
		return false;
	}

	// Check if ping is available on Windows
	$output = [];
	$returnCode = 0;
	@exec('ping 2>&1', $output, $returnCode);

	// If ping is not available, usually returns error code
	return empty($output) || $returnCode !== 0;
}
