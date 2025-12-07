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
 * \file    core/triggers/interface_900_modVerifactu_BillRestrictions.class.php
 * \ingroup verifactu
 * \brief   Restrictions for invoices sent to VeriFactu.
 *
 * Implements restrictions for invoices already sent to AEAT:
 * - Blocks modification of validated and sent invoices
 * - Blocks deletion of invoices with VeriFactu record
 * - Blocks invalidation of successfully sent invoices
 *
 * Priority 900 to execute before the main trigger (999).
 */

require_once DOL_DOCUMENT_ROOT . '/core/triggers/dolibarrtriggers.class.php';


/**
 *  Class of triggers for Verifactu module
 */
class InterfaceBillRestrictions extends DolibarrTriggers
{
	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;

		$this->name = preg_replace('/^Interface/i', '', get_class($this));
		$this->family = "verifactu";
		$this->description = "Verifactu triggers.";
		// 'development', 'experimental', 'dolibarr' or version
		$this->version = '1.0';
		$this->picto = 'verifactu.png@verifactu';
	}

	/**
	 * Trigger name
	 *
	 * @return string Name of trigger file
	 */
	public function getName()
	{
		return $this->name;
	}

	/**
	 * Trigger description
	 *
	 * @return string Description of trigger file
	 */
	public function getDesc()
	{
		return $this->description;
	}


	/**
	 * Function called when a Dolibarrr business event is done.
	 * All functions "runTrigger" are triggered if file
	 * is inside directory core/triggers
	 *
	 * @param string 		$action 	Event action code
	 * @param CommonObject 	$object 	Object
	 * @param User 			$user 		Object user
	 * @param Translate 	$langs 		Object langs
	 * @param Conf 			$conf 		Object conf
	 * @return int              		<0 if KO, 0 if no triggered ran, >0 if OK
	 */
	public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
	{
		if (empty($conf->verifactu) || empty($conf->verifactu->enabled)) {
			return 0; // If module is not enabled, we do nothing
		}

		// Put here code you want to execute when a Dolibarr business events occurs.
		// Data and type of action are stored into $object and $action

		// You can isolate code for each action in a separate method: this method should be named like the trigger in camelCase.
		// For example : COMPANY_CREATE => public function companyCreate($action, $object, User $user, Translate $langs, Conf $conf)
		$methodName = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', strtolower($action)))));
		$callback = array($this, $methodName);
		if (is_callable($callback)) {
			dol_syslog(
				"Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
			);

			return call_user_func($callback, $action, $object, $user, $langs, $conf);
		};

		$langs->load('verifactu@verifactu');

		return 0;
	}

	public function billDelete($action, $object, User $user, Translate $langs, Conf $conf)
	{
		if (strpos($object->ref, 'PROV') === false) {
			setEventMessage($langs->trans('VERIFACTU_INVOICE_CAN_NOT_BE_MODIFIED', $object->ref), 'errors');
			return -1;
		}
	}
	public function lineBillInsert($action, $object, User $user, Translate $langs, Conf $conf)
	{
		$object->origin_id = $object->fk_facture;
		$object->origin = 'facture';
		$object->fetch_origin();
		$factureRef = (intval(DOL_VERSION) >= 19 ? $object->origin_object->ref : $object->facture->ref);
		if (strpos($factureRef, 'PROV') === false) {
			setEventMessage($langs->trans('VERIFACTU_INVOICE_CAN_NOT_BE_MODIFIED', $factureRef), 'errors');
			return -1;
		}
	}
	public function lineBillUpdate($action, $object, User $user, Translate $langs, Conf $conf)
	{
		$object->origin_id = $object->fk_facture;
		$object->origin = 'facture';
		$object->fetch_origin();
		$factureRef = (intval(DOL_VERSION) >= 19 ? $object->origin_object->ref : $object->facture->ref);
		if (strpos($factureRef, 'PROV') === false) {
			setEventMessage($langs->trans('VERIFACTU_INVOICE_CAN_NOT_BE_MODIFIED', $factureRef), 'errors');
			return -1;
		}
	}
	public function lineBillDelete($action, $object, User $user, Translate $langs, Conf $conf)
	{
		$object->origin_id = $object->fk_facture;
		$object->origin = 'facture';
		$object->fetch_origin();
		$factureRef = (intval(DOL_VERSION) >= 19 ? $object->origin_object->ref : $object->facture->ref);
		if (strpos($factureRef, 'PROV') === false) {
			setEventMessage($langs->trans('VERIFACTU_INVOICE_CAN_NOT_BE_MODIFIED', $factureRef), 'errors');
			return -1;
		}
	}
	public function billUnvalidate($action, $object, User $user, Translate $langs, Conf $conf)
	{
		if (strpos($object->ref, 'PROV') === false) {
			setEventMessage($langs->trans('VERIFACTU_INVOICE_CAN_NOT_BE_MODIFIED', $object->ref), 'errors');
			return -1;
		}
	}
}
