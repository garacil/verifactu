<?php

/**
 * Regression test for the certificate expiration shown on the setup page.
 *
 * The page read the configured file with openssl_x509_read(), which cannot
 * open an encrypted .pfx/.p12 container, so it always reported "No se pudo
 * leer el certificado" until the first submission converted it to .pem. The
 * read-only view also printed the certificate password in clear.
 *
 * Certificates are generated during the run with PHP's OpenSSL bindings.
 */

if (!defined('LOG_ERR')) {
	define('LOG_ERR', 3);
}
if (!defined('LOG_INFO')) {
	define('LOG_INFO', 6);
}
if (!defined('LOG_DEBUG')) {
	define('LOG_DEBUG', 7);
}
if (!function_exists('dol_syslog')) {
	function dol_syslog($message, $level = 0)
	{
	}
}

require_once __DIR__ . '/../lib/functions/functions.certificates.php';

$total = 0;
$passed = 0;
function check($condition, $label)
{
	global $total, $passed;
	$total++;
	if ($condition) {
		$passed++;
		echo "  PASS: " . $label . "\n";
		return;
	}
	echo "  FAIL: " . $label . "\n";
}

echo "Certificate expiration on the setup page\n";

$tempDir = sys_get_temp_dir() . '/verifactu_cert_expiration_' . getmypid();
mkdir($tempDir, 0700, true);

$key = openssl_pkey_new(array('private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA));
$csr = openssl_csr_new(array('commonName' => 'VeriFactu Test'), $key, array('digest_alg' => 'sha256'));
$cert = openssl_csr_sign($csr, null, $key, 30, array('digest_alg' => 'sha256'));
if (!$cert) {
	echo "  SKIP: PHP OpenSSL could not generate the test certificate\n";
	exit(0);
}
$expected = openssl_x509_parse($cert)['validTo_time_t'];

$p12 = $tempDir . '/cert.p12';
openssl_pkcs12_export_to_file($cert, $p12, $key, 'secret');
$pem = $tempDir . '/cert.pem';
openssl_x509_export($cert, $pemCert);
openssl_pkey_export($key, $pemKey);
file_put_contents($pem, $pemCert . $pemKey);

check(@openssl_x509_read(file_get_contents($p12)) === false, 'openssl_x509_read() cannot open a .p12, the cause of the bug');

$result = getCertificateValidTo($p12, 'secret');
check($result['error'] === '' && $result['validTo'] === $expected, '.p12 with its password: expiration is read');

$result = getCertificateValidTo($p12, 'wrong');
check($result['error'] === 'VERIFACTU_CERT_ERROR_WRONG_PASSWORD' && $result['validTo'] === 0, '.p12 with a wrong password: says the password is wrong');

$result = getCertificateValidTo($pem, '');
check($result['error'] === '' && $result['validTo'] === $expected, '.pem: expiration is read without a password');

file_put_contents($tempDir . '/broken.pem', 'not a certificate');
$result = getCertificateValidTo($tempDir . '/broken.pem', '');
check($result['error'] === 'CertificateUnreadable' && $result['validTo'] === 0, 'unreadable file: reported as unreadable');

$setup = file_get_contents(__DIR__ . '/../admin/setup.php');
check(strpos($setup, 'getCertificateValidTo($certPath') !== false && strpos($setup, 'openssl_x509_read') === false, 'the setup page reads the expiration through getCertificateValidTo()');
check(preg_match("/newItem\('VERIFACTU_CERTIFICATE_KEY'\);.*?fieldOutputOverride = '\*{8}'/s", $setup) === 1, 'the read-only view masks the certificate password');

foreach (glob($tempDir . '/*') as $file) {
	unlink($file);
}
rmdir($tempDir);

echo "\nTotal: " . $total . " | Passed: " . $passed . " | Failed: " . ($total - $passed) . "\n";
exit($total === $passed ? 0 : 1);
