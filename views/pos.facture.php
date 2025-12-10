<?php
/* Copyright (C) 2025 Alberto SuperAdmin <aluquerivasdev@gmail.com>
 * Copyright (C) 2025 Germán Luis Aracil Boned <garacilb@gmail.com>
 *
 * Based on original code from verifactu module by Alberto SuperAdmin (easysoft.es)
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

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"] . "/main.inc.php";
}
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--;
	$j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1)) . "/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1)) . "/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1))) . "/main.inc.php")) {
	$res = @include dirname(substr($tmp, 0, ($i + 1))) . "/main.inc.php";
}
// Try main.inc.php using relative path
if (!$res && file_exists("../main.inc.php")) {
	$res = @include "../main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res && file_exists("../../../../main.inc.php")) {
	$res = @include "../../../../main.inc.php";
}
//try level 5 path

if (!$res) {
	die("Include of main fails");
}              // For "custom" directory

require_once(DOL_DOCUMENT_ROOT . "/compta/facture/class/facture.class.php");
require_once(DOL_DOCUMENT_ROOT . "/core/lib/company.lib.php");
require_once DOL_DOCUMENT_ROOT . '/core/class/discount.class.php';
dol_include_once('/pos/class/cash.class.php');
dol_include_once('/pos/class/place.class.php');
dol_include_once('/pos/backend/lib/pos.lib.php');

global $langs, $db, $mysoc, $conf;

$langs->load("main");
$langs->load("pos@pos");
$langs->load("rewards@rewards");
$langs->load("bills");
header("Content-type: text/html; charset=" . $conf->file->character_set_client);
$id = GETPOST('id');
// Variables

$object = new Facture($db);
$result = $object->fetch($id, $ref);
?>
<html>

<head>
	<title>Print facture</title>

	<style type="text/css">
		body {
			font-size: 14px;
			position: relative;
			font-family: monospace, courier, arial, helvetica, system;
			margin: 35px;
		}

		.entete {
			/* 		position: relative; */
		}

		.adresse {
			/* 			float: left; */
			font-size: 12px;
		}

		.date_heure {
			float: right;
			font-size: 12px;
			width: 100%;
			text-align: center;
		}

		.infos {
			position: relative;
			font-size: 14px;
		}


		.liste_articles {
			width: 100%;
			border-bottom: 1px solid #000;
			text-align: center;
			font-size: 12px;
		}

		.liste_articles tr.titres th {
			border-bottom: 1px solid #000;
			font-size: 13px;
		}

		.liste_articles td.total {
			text-align: right;
			font-size: 13px;
		}

		.total_tot {
			font-size: 15px;
			font-weight: bold;
			text-align: right;
		}

		.totaux {
			margin-top: 20px;
			width: 40%;
			float: right;
			text-align: right;
			font-size: 14px;
		}

		.totpay {
			margin-left: 50%;
			width: 30%;
			float: right;
			text-align: right;
			font-size: 14px;
		}

		.note {
			float: right;
			font-size: 12px;
			width: 100%;
			text-align: center;
		}

		.lien {
			position: absolute;
			top: 0;
			left: 0;
			display: none;
			font-size: 14px;
		}

		.qr-verifactu-container {
			position: absolute;
			top: 10px;
			right: 10px;
			text-align: center;
			font-size: 9px;
			width: auto;
		}

		.qr-verifactu-container div {
			margin: 2px 0;
		}

		.qr-verifactu-text {
			font-weight: bold;
			font-size: 8px;
		}

		@media print {

			.lien {
				display: none;
			}

			@page {

				margin: 0;

			}

		}
	</style>

</head>

<body onload="window.print()" onafterprint="<?php echo ($conf->global->POS_CLOSE_WIN ? 'window.close()' : ''); ?>">

	<div class="entete">
		<?php
		// VeriFactu QR (if invoice is sent) - TOP RIGHT
		try {
			dol_include_once('/verifactu/lib/verifactu.lib.php');
			if ($object instanceof Facture && $object->id > 0) {
				if (function_exists('isFactureSendToVerifactu') && isFactureSendToVerifactu($object)) {
					if (function_exists('getQrImage')) {
						$qrTextSize = !empty($conf->global->VERIFACTU_QR_TEXT_SIZE) ? intval($conf->global->VERIFACTU_QR_TEXT_SIZE) : 8;
						$qrShowText = !empty($conf->global->VERIFACTU_QR_SHOW_TEXT_TPV) ? true : false;

						echo '<div class="qr-verifactu-container">';
						if ($qrShowText) {
							echo '<div class="qr-verifactu-text">QR tributario:</div>';
						}
						echo '<div>' . getQrImage($object, 100) . '</div>';
						if ($qrShowText) {
							echo '<div class="qr-verifactu-text">VeriFactu</div>';
						}
						echo '</div>';
					}
				}
			}
		} catch (Exception $e) {
			// Don't break ticket if QR fails
		}
		?>
		<?php if (! empty($conf->global->POS_TICKET_LOGO)) { ?>
			<div class="logo">
				<?php
				print '<img src="' . DOL_URL_ROOT . get_mycompanylogo() . '">';
				?>
			</div>
		<?php } ?>
		<div class="infos">
			<p class="adresse"><?php echo $mysoc->name; ?><br>
				<?php echo $mysoc->idprof1; ?><br>
				<?php echo $mysoc->address; ?><br>
				<?php echo $mysoc->zip . ' ' . $mysoc->town; ?><br>
				<?php echo (!empty($mysoc->state_id) ? getState($mysoc->state_id) : ''); ?><br>
				<?php echo $mysoc->phone; ?><br><br>

				<?php



				$userstatic = new User($db);
				$userstatic->fetch($object->user_valid);
				print $langs->trans("VendorPOS") . ': ' . $userstatic->firstname . ' ' . $userstatic->lastname . '<br><br>';

				$client = new Societe($db);
				$client->fetch($object->socid);
				print $client->name . '<br>';
				print $client->idprof1 . '<br>';
				print $client->address . '<br>';
				print $client->zip . ' ' . $client->town . '<br>';
				print $client->state . '</p>';

				$sql = "SELECT fk_place,fk_cash FROM " . MAIN_DB_PREFIX . "pos_facture WHERE fk_facture =" . $object->id;
				$result = $db->query($sql);

				if ($result) {
					$objp = $db->fetch_object($result);
					if ($objp->fk_place > 0) {
						$place = new Place($db);
						$place->fetch($objp->fk_place);
						print $langs->trans("Place") . ': ' . $place->name . '</p>';
					}
				}

				?>
		</div>
	</div>

	<?php
	if ($result) {
		if (! empty($object->lines)) {
			$onediscount = false;
			foreach ($object->lines as $line) {
				if ($line->remise_percent)
					$onediscount = true;
			}
		}
	}

	?>
	<div class="infos"><?php print $object->note_private ?></div>
	<table class="liste_articles">
		<tr class="titres">
			<th><?php print $langs->trans("Label"); ?></th>
			<th><?php print $langs->trans("Qty") . "/" . $langs->trans("Price"); ?></th><?php if ($onediscount) print '<th>' . $langs->trans("DiscountLineal") . '</th>'; ?><th><?php print $langs->trans("Total"); ?></th>
		</tr>

		<?php

		if ($result) {
			//$object->getLinesArray();
			if (! empty($object->lines)) {
				//$subtotal=0;
				$promos = 0;

				if (!empty($conf->global->MAIN_MULTILANGS)) {
					$outputlangs = new Translate("", $conf);
					$outputlangs->setDefaultLang($userstatic->lang);
				}

				foreach ($object->lines as $line) {

					if ($conf->discounts->enabled) {
						dol_include_once('/discounts/class/discount_doc.class.php');
						$langs->load("discounts@discounts");
						$dis_doc = new Discounts_doc($db);
						$res = $dis_doc->fetch(3, $line->rowid);
						if ($res > 0) {
							$are_promo = true;
						} else {
							$are_promo = false;
						}
					}


					if (empty($line->product_label))
						$line->product_label = $line->desc;

					$label = $line->product_label;

					if (! empty($conf->global->MAIN_MULTILANGS)) {
						$prodser = new Product($db);
						if ($line->fk_product) {
							$prodser->fetch($line->fk_product);

							if (!empty($conf->global->MAIN_MULTILANGS)) {
								$label = $prodser->label;
							}
						}
					}
					$labeltxt = $label;
					$batch = str_replace($line->product_desc, '', $line->desc);
					$label = $label . $batch;
					$batch = str_replace('<br>', '', $batch);

					if ($conf->global->POS_PRINT_MODE == 1) {
						$label .= '&nbsp;&nbsp;&nbsp;<b>Ref: </b>' . $line->ref;
					}

					if (preg_match('/\(CREDIT_NOTE\)/', $line->product_label)) $line->product_label = preg_replace('/\(CREDIT_NOTE\)/', $langs->trans("CreditNote"), $line->product_label);
					if (preg_match('/\(DEPOSIT\)/', $line->product_label)) $line->product_label = preg_replace('/\(DEPOSIT\)/', $langs->trans("Deposit"), $line->product_label);

					if ($are_promo) {
						echo ('<tr><td align="left">' . $label . '</td><td align="left">' . $line->qty . " * " . price(price2num($conf->global->POS_TICKET_TTC ? $dis_doc->ori_subprice * (1 + $line->tva_tx / 100) : $dis_doc->ori_subprice), 0, '', 1, -1, $conf->global->MAIN_MAX_DECIMALS_TOT) . '</td>' . ($onediscount ? '<td align="right">' . $line->remise_percent . '%</td>' : '') . '<td class="total">' . price(price2num($conf->global->POS_TICKET_TTC ? $dis_doc->ori_totalht * (1 + $line->tva_tx / 100) : $dis_doc->ori_totalht), 0, '', 1, -1, $conf->global->MAIN_MAX_DECIMALS_TOT) . '</td></tr>');
						echo ('<tr><td align="left">' . $dis_doc->descr . '</td><td align="left"></td>' . ($onediscount ? '<td align="right"></td>' : '') . '<td class="total">-' . price(price2num($conf->global->POS_TICKET_TTC ? $dis_doc->ori_totalht * (1 + $line->tva_tx / 100) - $line->total_ttc : $dis_doc->ori_totalht - (isset($line->total_ht) ? $line->total_ht : (isset($line->total) ? $line->total : 0))), 0, '', 1, -1, $conf->global->MAIN_MAX_DECIMALS_TOT) . '</td></tr>');
						$linepromo = $conf->global->POS_TICKET_TTC ? $dis_doc->ori_totalht * (1 + $line->tva_tx / 100) - $line->total_ttc : $dis_doc->ori_totalht - (isset($line->total_ht) ? $line->total_ht : (isset($line->total) ? $line->total : 0));
						$promos += $linepromo;
					} else {
						echo ('<tr><td align="left">' . $label . '</td><td align="left">' . $line->qty . " * " . price(price2num($conf->global->POS_TICKET_TTC ? $line->subprice * (1 + $line->tva_tx / 100) : $line->subprice), 0, '', 1, -1, $conf->global->MAIN_MAX_DECIMALS_TOT) . '</td>' . ($onediscount ? '<td align="right">' . $line->remise_percent . '%</td>' : '') . '<td class="total">' . price(price2num($conf->global->POS_TICKET_TTC ? $line->total_ttc : (isset($line->total_ht) ? $line->total_ht : (isset($line->total) ? $line->total : 0))), 0, '', 1, -1, $conf->global->MAIN_MAX_DECIMALS_TOT) . '</td></tr>');
					}
					$subtotal[$line->tva_tx] += (isset($line->total_ht) ? $line->total_ht : (isset($line->total) ? $line->total : 0));
					$subtotaltva[$line->tva_tx] += (isset($line->total_tva) ? $line->total_tva : (isset($line->tva) ? $line->tva : 0));
					if (!empty($line->total_localtax1)) {
						$localtax1 = $line->localtax1_tx;
					}
					if (!empty($line->total_localtax2)) {
						$localtax2 = $line->localtax2_tx;
					}
				}
			} else {
				echo ('<p>' . $langs->trans("ErrNoArticles") . '</p>' . "\n");
			}
		}
		?>
	</table>
	<?php if ($promos > 0) { ?>
		<div class="total_tot"><?php echo $langs->trans("InPromo") . '   -' . price(price2num($promos)) . ' ' . $langs->trans(currency_name($conf->currency)); ?></div>
	<?php } ?>
	<div class="total_tot"><?php echo $langs->trans("TotalTTC") . '   ' . price(price2num($object->total_ttc)) . ' ' . $langs->trans(currency_name($conf->currency)); ?></div>
	<table class="totaux">
		<?php

		echo '<tr><th nowrap="nowrap" style="width:50%;">' . $langs->trans("TotalHT") . '</th><th nowrap="nowrap" style="width:25%;">' . $langs->trans("VAT") . '</th><th nowrap="nowrap" style="width:25%;">' . $langs->trans("TotalVAT") . '</th></tr>';
		if (! empty($subtotal)) {
			foreach ($subtotal as $totkey => $totval) {
				echo '<tr><td nowrap="nowrap" style="text-align:left;">' . price(price2num($subtotal[$totkey])) . '</td><td nowrap="nowrap">' . price(price2num($totkey)) . '%</td><td nowrap="nowrap">' . price(price2num($subtotaltva[$totkey])) . '</td></tr>';
			}
		}

		echo '<tr><td nowrap="nowrap" style="border-top: 1px dashed #000000;text-align:left;">' . price(price2num(isset($object->total_ht) ? $object->total_ht : (isset($object->total) ? $object->total : 0))) . '</td><td style="border-top: 1px dashed #000000;">--</td><td nowrap="nowrap" style="border-top: 1px dashed #000000;">' . price(price2num(isset($object->total_tva) ? $object->total_tva : (isset($object->tva) ? $object->tva : 0))) . "</td></tr>";

		if ($object->total_localtax1 != 0) {
			echo '<tr><td></td><th nowrap="nowrap">' . $langs->transcountrynoentities("TotalLT1", $mysoc->country_code) . ' ' . price(price2num($localtax1)) . '%</th><td nowrap="nowrap">' . price(price2num($object->total_localtax1)) . "</td></tr>";
		}
		if ($object->total_localtax2 != 0) {
			echo '<tr><td></td><th nowrap="nowrap">' . $langs->transcountrynoentities("TotalLT2", $mysoc->country_code) . ' ' . price(price2num($localtax2)) . '%</th><td nowrap="nowrap">' . price(price2num($object->total_localtax2)) . "</td></tr>";
		}


		?>
	</table>

	<table class="totpay">
		<?php
		echo '<tr><td></td></tr>';
		echo '<tr><td></td></tr>';

		$terminal = new Cash($db);
		$sql = 'SELECT fk_cash, customer_pay FROM ' . MAIN_DB_PREFIX . 'pos_facture WHERE fk_facture = ' . $object->id;
		$resql = $db->query($sql);
		$obj = $db->fetch_object($resql);
		$customer_pay = $obj->customer_pay;
		$terminal->fetch($obj->fk_cash);

		if (! empty($conf->rewards->enabled)) {
			dol_include_once('/rewards/class/rewards.class.php');
			$rewards = new Rewards($db);
			$points = $rewards->getInvoicePoints($object->id);
		}
		if ($object->type == 0) {
			$pay = $object->getSommePaiement();
			$coupon = $object->getSumCreditNotesUsed();
			$coupon += $object->getSumDepositsUsed();
			$pay += $coupon;

			if (! empty($conf->rewards->enabled)) {
				$usepoints = abs($rewards->getInvoicePoints($object->id, 1));
				$moneypoints = abs($usepoints * $conf->global->REWARDS_DISCOUNT); //falta fer algo per aci
				if ($customer_pay > $pay - $moneypoints)
					$pay = $customer_pay;
				else
					$pay = $pay - $moneypoints;
			} else {
				if ($customer_pay > $pay)
					$pay = $customer_pay;
			}
		}
		if ($object->type == 2) {
			$customer_pay = $customer_pay * -1;
			$pay = $object->getSommePaiement();

			if (! empty($conf->rewards->enabled)) {
				$usepoints = abs($rewards->getInvoicePoints($object->id, 0));
				$moneypoints = -1 * ($usepoints * $conf->global->REWARDS_DISCOUNT); //falta fer algo per aci
				if ($customer_pay > $pay - $moneypoints)
					$pay = $customer_pay;
				else
					$pay = $pay - $moneypoints;
			} else {
				if ($customer_pay > $pay)
					$pay = $customer_pay;
			}
		}
		$diff_payment = $object->total_ttc - $moneypoints - $pay;
		$listofpayments = $object->getListOfPayments();
		foreach ($listofpayments as $paym) {
			if ($paym['type'] != 'PNT') {
				if ($paym['type'] != 'LIQ') {
					echo '<tr><th nowrap="nowrap">' . $terminal->select_Paymentname(dol_getIdFromCode($db, $paym['type'], 'c_paiement')) . '</th><td nowrap="nowrap">' . price(price2num($paym['amount'])) . " " . $langs->trans(currency_name($conf->currency)) . "</td></tr>";
				} else {
					echo '<tr><th nowrap="nowrap">' . $terminal->select_Paymentname(dol_getIdFromCode($db, $paym['type'], 'c_paiement')) . '</th><td nowrap="nowrap">' . price(price2num($paym['amount'] - (($object->type > 1 ? $diff_payment * -1 : $diff_payment) < 0 ? $diff_payment : 0))) . " " . $langs->trans(currency_name($conf->currency)) . "</td></tr>";
				}
			}
		}
		if ($coupon > 0) {
			echo '<tr><th nowrap="nowrap">' . $langs->trans("Discount") . '</th><td nowrap="nowrap">' . price(price2num($coupon)) . " " . $langs->trans(currency_name($conf->currency)) . "</td></tr>";
		}
		if (! empty($conf->rewards->enabled)) {
			if ($moneypoints != 0) {
				echo '<tr><th nowrap="nowrap">' . $usepoints . " " . $langs->trans("Points") . '</th><td nowrap="nowrap">' . price(price2num($moneypoints)) . " " . $langs->trans(currency_name($conf->currency)) . "</td></tr>";
			}
		}
		$discount = new DiscountAbsolute($db);
		$result = $discount->fetch(0, $object->id);
		if ($result > 0) {
			echo '<tr><th nowrap="nowrap"></th><td nowrap="nowrap">' . $langs->trans("ReductionConvert") . '</td></tr>';
		} else {
			echo '<tr><th nowrap="nowrap">' . (($object->type > 1 ? $diff_payment * -1 : $diff_payment) < 0 ? $langs->trans("CustomerRet") : $langs->trans("CustomerDeb")) . '</th><td nowrap="nowrap">' . price(abs(price2num($diff_payment))) . " " . $langs->trans(currency_name($conf->currency)) . "</td></tr>";
		}
		if ($points != 0 && ! empty($conf->rewards->enabled)) {
			echo '<tr><th nowrap="nowrap">' . $langs->trans("TotalPointsInvoice") . '</th><td nowrap="nowrap">' . price(price2num($points)) . " " . $langs->trans('Points') . "</td></tr>";
			$total_points = $rewards->getCustomerPoints($object->socid);
			echo '<tr><th nowrap="nowrap">' . $langs->trans("DispoPoints") . '</th><td nowrap="nowrap">' . price(price2num($total_points)) . " " . $langs->trans('Points') . "</td></tr>";
		}
		?>
	</table>

	<div class="note">
		<p><?php print $conf->global->POS_PREDEF_MSG; ?> </p>
	</div>

	<div><?php $now = dol_now();
			print '<p class="date_heure" align="right">' . $object->ref . " " . dol_print_date($object->date_creation, 'dayhour') . '</p>'; ?></div>



</body>
