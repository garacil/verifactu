<?php

/**
 * Regression test for issue #53: validated, unpaid invoices listed as "Draft".
 *
 * views/list.facture.php builds a Facture object per row to print the Dolibarr
 * status with getLibStatut(). It only filled the deprecated property statut.
 * Up to Dolibarr 19 getLibStatut() reads $this->statut, but from Dolibarr 20 on
 * it reads $this->status (verified on 22.0 and 24.0 commoninvoice.class.php),
 * so recent versions saw a null status. LibStatut() first branches on $paye and
 * then takes a null status as draft: paid invoices showed "Paid" and every
 * other one "Draft", which is exactly what was reported on Dolibarr 24.0.1.
 *
 * The list now fills both properties, as compta/facture/list.php does.
 *
 * Run with: php tests/ListStatusColumnTest.php
 */

error_reporting(E_ALL);

$passed = 0;
$failed = 0;
$total = 0;

function assert_test(bool $condition, string $message, &$passed, &$failed, &$total): void
{
    $total++;
    if ($condition) {
        $passed++;
        echo "  PASS: $message\n";
    } else {
        $failed++;
        echo "  FAIL: $message\n";
    }
}

$moduleRoot = dirname(__DIR__);
$source = file_get_contents($moduleRoot . '/views/list.facture.php');

echo "=== Invoice list status column (issue #53) ===\n\n";

// ---------------------------------------------------------------------------
// Source: both properties are assigned, before the status is printed
// ---------------------------------------------------------------------------
echo "views/list.facture.php\n";

$deprecatedPosition = strpos($source, '$facturestatic->statut = $obj->fk_statut;');
$currentPosition = strpos($source, '$facturestatic->status = $obj->fk_statut;');
$printPosition = strpos($source, '$facturestatic->getLibStatut(5');

assert_test($deprecatedPosition !== false, 'the deprecated property statut is still filled, for Dolibarr 19 and older', $passed, $failed, $total);
assert_test($currentPosition !== false, 'the property status is filled, which Dolibarr 20 and later read in getLibStatut()', $passed, $failed, $total);
assert_test($printPosition !== false, 'the Dolibarr status column is printed with getLibStatut(5, ...)', $passed, $failed, $total);
assert_test(
    $currentPosition !== false && $printPosition !== false && $currentPosition < $printPosition,
    'status is assigned before getLibStatut() is called',
    $passed,
    $failed,
    $total
);
assert_test(
    strpos($source, '$facturestatic->statut == Facture::STATUS_CLOSED') === false,
    'the discount_vat check no longer relies on the deprecated property',
    $passed,
    $failed,
    $total
);

// ---------------------------------------------------------------------------
// Behaviour: the module's own assignments feed both getLibStatut() variants
// ---------------------------------------------------------------------------
echo "\nLibStatut() as implemented by Dolibarr 19 and by Dolibarr 22/24\n";

/**
 * Reduced LibStatut() with the branches that matter here, taken from
 * commoninvoice.class.php: $paye first, then status 0 is draft, 1 unpaid.
 */
function reducedLibStatut($paye, $status)
{
    if (!$paye) {
        if ($status == 0) {
            return 'Draft';
        }
        if ($status == 1) {
            return 'NotPaid';
        }
        return 'Other';
    }
    return 'Paid';
}

class FactureDolibarr19
{
    public $statut;
    public $status;
    public $paye;
    public $close_code;
    public function getLibStatut()
    {
        return reducedLibStatut($this->paye, $this->statut);
    }
}

class FactureDolibarr22
{
    public $statut;
    public $status;
    public $paye;
    public $close_code;
    public function getLibStatut()
    {
        return reducedLibStatut($this->paye, $this->status);
    }
}

// Take the assignment lines as they stand in the list, so the test follows the
// file instead of a copy of it.
preg_match_all('/^\s*\$facturestatic->(?:statut|status|close_code|paye) = \$obj->[a-z_]+;.*$/m', $source, $assignments);
$assignmentCode = implode("\n", array_map('trim', $assignments[0]));
assert_test(substr_count($assignmentCode, '$obj->fk_statut') === 2, 'fk_statut is copied into the two status properties', $passed, $failed, $total);

$obj = (object) array('fk_statut' => 1, 'paye' => 0, 'close_code' => null);

$facturestatic = new FactureDolibarr19();
eval($assignmentCode);
assert_test($facturestatic->getLibStatut() === 'NotPaid', 'Dolibarr 19 shows a validated unpaid invoice as not paid', $passed, $failed, $total);

$facturestatic = new FactureDolibarr22();
eval($assignmentCode);
assert_test($facturestatic->getLibStatut() === 'NotPaid', 'Dolibarr 22/24 shows a validated unpaid invoice as not paid, not as draft', $passed, $failed, $total);

$obj->fk_statut = 0;
$facturestatic = new FactureDolibarr22();
eval($assignmentCode);
assert_test($facturestatic->getLibStatut() === 'Draft', 'a real draft is still shown as draft', $passed, $failed, $total);

$obj->fk_statut = 2;
$obj->paye = 1;
$facturestatic = new FactureDolibarr22();
eval($assignmentCode);
assert_test($facturestatic->getLibStatut() === 'Paid', 'a paid invoice is still shown as paid', $passed, $failed, $total);

echo "\n=== Results ===\n";
echo "Total:  $total\n";
echo "Passed: $passed\n";
echo "Failed: $failed\n";

exit($failed > 0 ? 1 : 0);
