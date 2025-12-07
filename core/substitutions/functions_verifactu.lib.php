<?php

function verifactu_completesubstitutionarray(&$substitutionarray, $langs, $object, $parameters = null)
{

	global $conf, $db;
	if ($object instanceof Facture) {
		$substitutionarray['FACTUREVERIFACTUTAB'] = $langs->trans("FACTURE_VERIFACTU_TAB");
	}
}
