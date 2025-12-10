<?php
/* Copyright (C) 2004-2018  Laurent Destailleur     <eldy@users.sourceforge.net>
 * Copyright (C) 2018-2019  Nicolas ZABOURI         <info@inovea-conseil.com>
 * Copyright (C) 2019-2020  Frédéric France         <frederic.france@netlogic.fr>
 * Copyright (C) 2025 Alberto SuperAdmin <aluquerivasdev@gmail.com>
 * Copyright (C) 2025 Germán Luis Aracil Boned <garacilb@gmail.com>
 *
 * Based on original code from verifactu module by Alberto SuperAdmin (easysoft.es)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * 	\defgroup   verifactu     Module Verifactu
 *  \brief      Verifactu module descriptor.
 *
 *  \file       htdocs/verifactu/core/modules/modVerifactu.class.php
 *  \ingroup    verifactu
 *  \brief      Description and activation file for module Verifactu
 */
include_once DOL_DOCUMENT_ROOT . '/core/modules/DolibarrModules.class.php';



/**
 *  Description and activation class for module Verifactu
 */
class modVerifactu extends DolibarrModules
{
	/**
	 * Constructor. Define names, constants, directories, boxes, permissions
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $langs, $conf;
		$this->db = $db;

		// Id for module (must be unique).
		// Use here a free id (See in Home -> System information -> Dolibarr for list of used modules id).
		$this->numero = 350100;

		// Key text used to identify module (for permissions, menus, etc...)
		$this->rights_class = 'verifactu';

		// Family can be 'base' (core modules),'crm','financial','hr','projects','products','ecm','technic' (transverse modules),'interface' (link with external tools),'other','...'
		// It is used to group modules by family in module setup page
		$this->family = "financial";

		// Module position in the family on 2 digits ('01', '10', '20', ...)
		$this->module_position = '90';

		// Gives the possibility for the module, to provide his own family info and position of this family (Overwrite $this->family and $this->module_position. Avoid this)
		//$this->familyinfo = array('myownfamily' => array('position' => '01', 'label' => $langs->trans("MyOwnFamily")));
		// Module label (no space allowed), used if translation string 'ModuleVerifactuName' not found (Verifactu is name of module).
		$this->name = preg_replace('/^mod/i', '', get_class($this));

		// Module description, used if translation string 'ModuleVerifactuDesc' not found (Verifactu is name of module).
		$this->description = "VerifactuDescription";
		// Used only if file README.md and README-LL.md not found.
		$this->descriptionlong = "VerifactuDescription";

		// Author
		$this->editor_name = 'Germán Luis Aracil Boned <garacilb@gmail.com>';
		$this->editor_url = '';

		// Possible values for version are: 'development', 'experimental', 'dolibarr', 'dolibarr_deprecated' or a version string like 'x.y.z'
		$this->version = '1.0.1';
		$this->verifactu_version = '1.0.1';
		$this->verifactu_version_date = '16/07/2025';
		// Url to the file with your last numberversion of this module
		$this->url_last_version = '';

		// Key used in llx_const table to save module status enabled/disabled (where VERIFACTU is value of property name of module in uppercase)
		$this->const_name = 'MAIN_MODULE_' . strtoupper($this->name);

		// Name of image file used for this module.
		// If file is in theme/yourtheme/img directory under name object_pictovalue.png, use this->picto='pictovalue'
		// If file is in module/img directory under name object_pictovalue.png, use this->picto='pictovalue@module'
		// To use a supported fa-xxx css style of font awesome, use this->picto='xxx'
		$this->picto = 'bill';

		// Define some features supported by module (triggers, login, substitutions, menus, css, etc...)
		$this->module_parts = array(
			// Set this to 1 if module has its own trigger directory (core/triggers)
			'triggers' => 1,
			// Set this to 1 if module has its own login method file (core/login)
			'login' => 0,
			// Set this to 1 if module has its own substitution function file (core/substitutions)
			'substitutions' => 1,
			// Set this to 1 if module has its own menus handler directory (core/menus)
			'menus' => 0,
			// Set this to 1 if module overwrite template dir (core/tpl)
			'tpl' => 0,
			// Set this to 1 if module has its own barcode directory (core/modules/barcode)
			'barcode' => 0,
			// Set this to 1 if module has its own models directory (core/modules/xxx)
			'models' => 1,
			// Set this to 1 if module has its own printing directory (core/modules/printing)
			'printing' => 0,
			// Set this to 1 if module has its own theme directory (theme)
			'theme' => 0,
			// Set this to relative path of css file if module has its own css file
			'css' => array(
				'/verifactu/css/verifactu.css.php',
			),
			// Set this to relative path of js file if module must load a js on all pages
			'js' => array(
				'/verifactu/js/verifactu.js.php',
			),
			// Set here all hooks context managed by module. To find available hook context, make a "grep -r '>initHooks(' *" on source code. You can also set hook context to 'all'
			'hooks' => array(
				'main',
				'invoicecard',
				'pdfgeneration',
				'takeposfrontend',
				'odtgeneration'
				//   'data' => array(
				//       'hookcontext1',
				//       'hookcontext2',
				//   ),
				//   'entity' => '0',
			),
			// Set this to 1 if features of module are opened to external users
			'moduleforexternal' => 0,
		);

		// Data directories to create when module is enabled.
		// Example: this->dirs = array("/verifactu/temp","/verifactu/subdir");
		$this->dirs = array("/verifactu/temp", "/verifactu/certificates");

		// Config pages. Put here list of php page, stored into verifactu/admin directory, to use to setup module.
		$this->config_page_url = array("setup.php@verifactu");

		// Dependencies
		// A condition to hide module
		$this->hidden = false;
		// List of module class names as string that must be enabled if this module is enabled. Example: array('always1'=>'modModuleToEnable1','always2'=>'modModuleToEnable2', 'FR1'=>'modModuleToEnableFR'...)
		$this->depends = array(
			'1' => 'modFacture',
			'2' => 'modBlockedLog',
			'3' => 'modApi',
		);
		$this->requiredby = array(); // List of module class names as string to disable if this one is disabled. Example: array('modModuleToDisable1', ...)
		$this->conflictwith = array(); // List of module class names as string this module is in conflict with. Example: array('modModuleToDisable1', ...)

		// The language file dedicated to your module
		$this->langfiles = array("verifactu@verifactu");

		// Prerequisites
		$this->phpmin = array(7, 4); // Minimum version of PHP required by module
		$this->need_dolibarr_version = array(10, -3); // Minimum version of Dolibarr required by module

		// Messages at activation
		$this->warnings_activation = array(); // Warning to show when we activate module. array('always'='text') or array('FR'='textfr','MX'='textmx'...)
		$this->warnings_activation_ext = array(); // Warning to show when we activate an external module. array('always'='text') or array('FR'='textfr','MX'='textmx'...)
		//$this->automatic_activation = array('FR'=>'VerifactuWasAutomaticallyActivatedBecauseOfYourCountryChoice');
		//$this->always_enabled = true;								// If true, can't be disabled

		// Constants
		// List of particular constants to add when module is enabled (key, 'chaine', value, desc, visible, 'current' or 'allentities', deleteonunactive)
		// Example: $this->const=array(1 => array('VERIFACTU_MYNEWCONST1', 'chaine', 'myvalue', 'This is a constant to add', 1),
		//                             2 => array('VERIFACTU_MYNEWCONST2', 'chaine', 'myvalue', 'This is another constant to add', 0, 'current', 1)
		// );
		$this->const = array(
			1 => array('INVOICE_DISALLOW_REOPEN', 'chaine', '1', $langs->trans('INVOICE_DISALLOW_REOPEN'), 1),
			2 => array('INVOICE_CAN_NEVER_BE_REMOVED', 'chaine', '1', $langs->trans('INVOICE_CAN_NEVER_BE_REMOVED'), 1),
			3 => array('BLOCKEDLOG_DISABLE_NOT_ALLOWED_FOR_COUNTRY', 'chaine', 'ES', 'Allow modBlockedLog on SPAIN', 1),
			4 => array('VERIFACTU_DIRECT_CALL_ON_VALIDATE', 'chaine', '1', $langs->trans('VERIFACTU_DIRECT_CALL_ON_VALIDATE'), 1),
			5 => array('VERIFACTU_QR_SIZE', 'chaine', '30', $langs->trans('VERIFACTU_QR_SIZE'), 1),
			6 => array('VERIFACTU_QR_POSITION_X', 'chaine', 'custom', $langs->trans('VERIFACTU_QR_POSITION_X'), 1),
			7 => array('VERIFACTU_QR_POSITION_X_CUSTOM', 'chaine', '70', $langs->trans('VERIFACTU_QR_POSITION_X_CUSTOM'), 1),
			8 => array('VERIFACTU_QR_POSITION_Y', 'chaine', '2', $langs->trans('VERIFACTU_QR_POSITION_Y'), 1),
			9 => array('VERIFACTU_QR_SHOW_TEXT', 'chaine', '1', $langs->trans('VERIFACTU_QR_SHOW_TEXT'), 1),
			10 => array('VERIFACTU_QR_TEXT_SIZE', 'chaine', '8', $langs->trans('VERIFACTU_QR_TEXT_SIZE'), 1),
			11 => array('VERIFACTU_MODE', 'chaine', 'verifactu', $langs->trans('VERIFACTU_MODE'), 1),
			//Hacer obligatorio CIF para crear tercero y factura
			12 => array('SOCIETE_IDPROF1_INVOICE_MANDATORY', 'chaine', '0', $langs->trans('SOCIETE_IDPROF1_INVOICE_MANDATORY'), 1),
			13 => array('INVOICE_CHECK_POSTERIOR_DATE', 'chaine', '1', $langs->trans('INVOICE_CHECK_POSTERIOR_DATE'), 1),
			//Tipos por defecto
			14 => array('VERIFACTU_DEFAULT_TAX_TYPE', 'chaine', '', $langs->trans('VERIFACTU_DEFAULT_TAX_TYPE'), 1),
			15 => array('VERIFACTU_DEFAULT_TAX_REGIME', 'chaine', '', $langs->trans('VERIFACTU_DEFAULT_TAX_REGIME'), 1),
			16 => array('VERIFACTU_DEFAULT_OPERATION_QUALIFICATION', 'chaine', '', $langs->trans('VERIFACTU_DEFAULT_OPERATION_QUALIFICATION'), 1),
			17 => array('VERIFACTU_DEFAULT_EXEMPT_OPERATION', 'chaine', '', $langs->trans('VERIFACTU_DEFAULT_EXEMPT_OPERATION'), 1),
			18 => array('VERIFACTU_CERT_TYPE', 'chaine', 'normal', $langs->trans('VERIFACTU_CERT_TYPE'), 1),
			19 => array('FAC_FORCE_DATE_VALIDATION', 'chaine', '1', $langs->trans('FAC_FORCE_DATE_VALIDATION'), 1),
			20 => array('VERIFACTU_COMPANY_TYPE', 'chaine', 'sociedad', $langs->trans('VERIFACTU_COMPANY_TYPE'), 1),
			21 => array('VERIFACTU_QR_SHOW_TEXT_TPV', 'chaine', '1', $langs->trans('VERIFACTU_QR_SHOW_TEXT_TPV'), 1),
		);

		// Some keys to add into the overwriting translation tables
		/*$this->overwrite_translation = array(
			'en_US:ParentCompany'=>'Parent company or reseller',
			'fr_FR:ParentCompany'=>'Maison mère ou revendeur'
		)*/

		if (!isset($conf->verifactu) || !isset($conf->verifactu->enabled)) {
			$conf->verifactu = new stdClass();
			$conf->verifactu->enabled = 0;
		}

		// Array to add new pages in new tabs
		$this->tabs = array();
		$this->tabs[] = array('data' => 'invoice:+factureVERIFACTU_TAB:SUBSTITUTION_FACTUREVERIFACTUTAB:verifactu@verifactu:($user->rights->verifactu->manage || $user->admin):/verifactu/views/tabVERIFACTU.facture.php?id=__ID__');

		// Example:
		// $this->tabs[] = array('data'=>'objecttype:+tabname1:Title1:mylangfile@verifactu:$user->rights->verifactu->read:/verifactu/mynewtab1.php?id=__ID__');  					// To add a new tab identified by code tabname1
		// $this->tabs[] = array('data'=>'objecttype:+tabname2:SUBSTITUTION_Title2:mylangfile@verifactu:$user->rights->othermodule->read:/verifactu/mynewtab2.php?id=__ID__',  	// To add another new tab identified by code tabname2. Label will be result of calling all substitution functions on 'Title2' key.
		// $this->tabs[] = array('data'=>'objecttype:-tabname:NU:conditiontoremove');                                                     										// To remove an existing tab identified by code tabname
		//
		// Where objecttype can be
		// 'categories_x'	  to add a tab in category view (replace 'x' by type of category (0=product, 1=supplier, 2=customer, 3=member)
		// 'contact'          to add a tab in contact view
		// 'contract'         to add a tab in contract view
		// 'group'            to add a tab in group view
		// 'intervention'     to add a tab in intervention view
		// 'invoice'          to add a tab in customer invoice view
		// 'invoice_supplier' to add a tab in supplier invoice view
		// 'member'           to add a tab in fundation member view
		// 'opensurveypoll'	  to add a tab in opensurvey poll view
		// 'order'            to add a tab in customer order view
		// 'order_supplier'   to add a tab in supplier order view
		// 'payment'		  to add a tab in payment view
		// 'payment_supplier' to add a tab in supplier payment view
		// 'product'          to add a tab in product view
		// 'propal'           to add a tab in propal view
		// 'project'          to add a tab in project view
		// 'stock'            to add a tab in stock view
		// 'thirdparty'       to add a tab in third party view
		// 'user'             to add a tab in user view

		// Dictionaries
		$this->dictionaries = array();
		/* Example:
		$this->dictionaries=array(
			'langs'=>'verifactu@verifactu',
			// List of tables we want to see into dictonnary editor
			'tabname'=>array("table1", "table2", "table3"),
			// Label of tables
			'tablib'=>array("Table1", "Table2", "Table3"),
			// Request to select fields
			'tabsql'=>array('SELECT f.rowid as rowid, f.code, f.label, f.active FROM '.MAIN_DB_PREFIX.'table1 as f', 'SELECT f.rowid as rowid, f.code, f.label, f.active FROM '.MAIN_DB_PREFIX.'table2 as f', 'SELECT f.rowid as rowid, f.code, f.label, f.active FROM '.MAIN_DB_PREFIX.'table3 as f'),
			// Sort order
			'tabsqlsort'=>array("label ASC", "label ASC", "label ASC"),
			// List of fields (result of select to show dictionary)
			'tabfield'=>array("code,label", "code,label", "code,label"),
			// List of fields (list of fields to edit a record)
			'tabfieldvalue'=>array("code,label", "code,label", "code,label"),
			// List of fields (list of fields for insert)
			'tabfieldinsert'=>array("code,label", "code,label", "code,label"),
			// Name of columns with primary key (try to always name it 'rowid')
			'tabrowid'=>array("rowid", "rowid", "rowid"),
			// Condition to show each dictionary
			'tabcond'=>array($conf->verifactu->enabled, $conf->verifactu->enabled, $conf->verifactu->enabled),
			// Tooltip for every fields of dictionaries: DO NOT PUT AN EMPTY ARRAY
			'tabhelp'=>array(array('code'=>$langs->trans('CodeTooltipHelp'), 'field2' => 'field2tooltip'), array('code'=>$langs->trans('CodeTooltipHelp'), 'field2' => 'field2tooltip'), ...),
		);
		*/

		// Boxes/Widgets
		// Add here list of php file(s) stored in verifactu/core/boxes that contains a class to show a widget.
		$this->boxes = array(
			//  0 => array(
			//      'file' => 'verifactuwidget1.php@verifactu',
			//      'note' => 'Widget provided by Verifactu',
			//      'enabledbydefaulton' => 'Home',
			//  ),
			//  ...
		);

		// Cronjobs (List of cron jobs entries to add when module is enabled)
		// unit_frequency must be 60 for minute, 3600 for hour, 86400 for day, 604800 for week
		$this->cronjobs = array(
			0 => array(
				'label' => 'sendInvoicesWithErrorsCronName',
				'jobtype' => 'method',
				'class' => '/verifactu/class/verifactu.utils.php',
				'objectname' => 'VerifactuUtils',
				'method' => 'sendInvoicesWithErrors',
				'parameters' => '',
				'comment' => 'sendInvoicesWithErrorsCronName',
				'frequency' => 2,
				'unitfrequency' => 3600,
				'status' => 1,
				'test' => '$conf->verifactu->enabled',
				'priority' => 50,
			),
		);
		// Example: $this->cronjobs=array(
		//    0=>array('label'=>'My label', 'jobtype'=>'method', 'class'=>'/dir/class/file.class.php', 'objectname'=>'MyClass', 'method'=>'myMethod', 'parameters'=>'param1, param2', 'comment'=>'Comment', 'frequency'=>2, 'unitfrequency'=>3600, 'status'=>0, 'test'=>'$conf->verifactu->enabled', 'priority'=>50),
		//    1=>array('label'=>'My label', 'jobtype'=>'command', 'command'=>'', 'parameters'=>'param1, param2', 'comment'=>'Comment', 'frequency'=>1, 'unitfrequency'=>3600*24, 'status'=>0, 'test'=>'$conf->verifactu->enabled', 'priority'=>50)
		// );

		// Permissions provided by this module
		$this->rights = array();
		$r = 0;
		$this->rights[$r][0] = $this->numero . sprintf("%02d", $r + 1); // Permission id (must not be already used)
		$this->rights[$r][1] = 'PERMISSIONS_MANAGE_VERIFACTU'; // Permission label
		$this->rights[$r][4] = 'manage';

		// Main menu entries to add
		$this->menu = array();
		$r = 0;
		// Add here entries to declare new menus
		/* BEGIN MODULEBUILDER TOPMENU */
		$this->menu[$r++] = array(
			'fk_menu' => '', // '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type' => 'top', // This is a Top menu entry
			'titre' => 'Verifactu',
			/* 			'prefix' => img_picto('', $this->picto, 'class="paddingright pictofixedwidth valignmiddle"'),
 */
			'mainmenu' => 'verifactu',
			'leftmenu' => '',
			'url' => '/verifactu/verifactuindex.php',
			'langs' => 'verifactu@verifactu', // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position' => 1000 + $r,
			'enabled' => '$conf->verifactu->enabled', // Define condition to show or hide menu entry. Use '$conf->verifactu->enabled' if entry must be visible if module is enabled.
			'perms' => '$user->rights->verifactu->manage || $user->admin', // Use 'perms'=>'$user->rights->verifactu->verifactu->read' if you want your menu with a permission rules
			'target' => '',
			'user' => 0, // 0=Menu for internal users, 1=external users, 2=both
		);

		// Declaración responsable
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=verifactu',
			'type' => 'left',
			'titre' => 'VERIFACTU_DECLARATION_MENU',
			'mainmenu' => 'verifactu',
			'leftmenu' => 'verifactu_declaration',
			'url' => '/verifactu/views/declaration.php',
			'langs' => 'verifactu@verifactu',
			'position' => 1000 + $r,
			'enabled' => '$conf->verifactu->enabled',
			'perms' => '$user->rights->verifactu->manage || $user->admin',
			'target' => '',
			'user' => 0,
		);

		// Issued invoices
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=verifactu',
			'type' => 'left',
			'titre' => 'VERIFACTU_SEND_INVOICES_MENU',
			'mainmenu' => 'verifactu',
			'leftmenu' => 'verifactu_send_invoices',
			'url' => '/verifactu/views/list.facture.php',
			'langs' => 'verifactu@verifactu',
			'position' => 1000 + $r,
			'enabled' => '$conf->verifactu->enabled',
			'perms' => '$user->rights->verifactu->manage || $user->admin',
			'target' => '',
			'user' => 0,
		);

		// AEAT Query
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=verifactu',
			'type' => 'left',
			'titre' => 'VERIFACTU_SEND_INVOICES_QUERY_MENU',
			'mainmenu' => 'verifactu',
			'leftmenu' => 'verifactu_query',
			'url' => '/verifactu/views/query.facture.php',
			'langs' => 'verifactu@verifactu',
			'position' => 1000 + $r,
			'enabled' => '$conf->verifactu->enabled',
			'perms' => '$user->rights->verifactu->manage || $user->admin',
			'target' => '',
			'user' => 0,
		);

		// Documentation
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=verifactu',
			'type' => 'left',
			'titre' => 'VERIFACTU_DOCUMENTATION_MENU',
			'mainmenu' => 'verifactu',
			'leftmenu' => 'verifactu_documentation',
			'url' => '/verifactu/views/documentation.php',
			'langs' => 'verifactu@verifactu',
			'position' => 1000 + $r,
			'enabled' => '$conf->verifactu->enabled',
			'perms' => '$user->rights->verifactu->manage || $user->admin',
			'target' => '',
			'user' => 0,
		);

		// Ayuda / FAQ
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=verifactu',
			'type' => 'left',
			'titre' => 'VERIFACTU_HELP_MENU',
			'mainmenu' => 'verifactu',
			'leftmenu' => 'verifactu_help',
			'url' => '/verifactu/views/faq.php',
			'langs' => 'verifactu@verifactu',
			'position' => 1000 + $r,
			'enabled' => '$conf->verifactu->enabled',
			'perms' => '$user->rights->verifactu->manage || $user->admin',
			'target' => '',
			'user' => 0,
		);


		/* END MODULEBUILDER TOPMENU */
		/* BEGIN MODULEBUILDER LEFTMENU VERIFACTU
		$this->menu[$r++]=array(
			'fk_menu'=>'fk_mainmenu=verifactu',      // '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type'=>'left',                          // This is a Left menu entry
			'titre'=>'Verifactu',
			'prefix' => img_picto('', $this->picto, 'class="paddingright pictofixedwidth valignmiddle"'),
			'mainmenu'=>'verifactu',
			'leftmenu'=>'verifactu',
			'url'=>'/verifactu/verifactuindex.php',
			'langs'=>'verifactu@verifactu',	        // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position'=>1000+$r,
			'enabled'=>'$conf->verifactu->enabled',  // Define condition to show or hide menu entry. Use '$conf->verifactu->enabled' if entry must be visible if module is enabled.
			'perms'=>'$user->rights->verifactu->verifactu->read',			                // Use 'perms'=>'$user->rights->verifactu->level1->level2' if you want your menu with a permission rules
			'target'=>'',
			'user'=>2,				                // 0=Menu for internal users, 1=external users, 2=both
		);
		$this->menu[$r++]=array(
			'fk_menu'=>'fk_mainmenu=verifactu,fk_leftmenu=verifactu',	    // '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type'=>'left',			                // This is a Left menu entry
			'titre'=>'List_Verifactu',
			'mainmenu'=>'verifactu',
			'leftmenu'=>'verifactu_verifactu_list',
			'url'=>'/verifactu/verifactu_list.php',
			'langs'=>'verifactu@verifactu',	        // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position'=>1000+$r,
			'enabled'=>'$conf->verifactu->enabled',  // Define condition to show or hide menu entry. Use '$conf->verifactu->enabled' if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
			'perms'=>'$user->rights->verifactu->verifactu->read',			                // Use 'perms'=>'$user->rights->verifactu->level1->level2' if you want your menu with a permission rules
			'target'=>'',
			'user'=>2,				                // 0=Menu for internal users, 1=external users, 2=both
		);
		$this->menu[$r++]=array(
			'fk_menu'=>'fk_mainmenu=verifactu,fk_leftmenu=verifactu',	    // '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type'=>'left',			                // This is a Left menu entry
			'titre'=>'New_Verifactu',
			'mainmenu'=>'verifactu',
			'leftmenu'=>'verifactu_verifactu_new',
			'url'=>'/verifactu/verifactu_card.php?action=create',
			'langs'=>'verifactu@verifactu',	        // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position'=>1000+$r,
			'enabled'=>'$conf->verifactu->enabled',  // Define condition to show or hide menu entry. Use '$conf->verifactu->enabled' if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
			'perms'=>'$user->rights->verifactu->verifactu->write',			                // Use 'perms'=>'$user->rights->verifactu->level1->level2' if you want your menu with a permission rules
			'target'=>'',
			'user'=>2,				                // 0=Menu for internal users, 1=external users, 2=both
		);
		*/
		/* END MODULEBUILDER LEFTMENU VERIFACTU */
		// Exports profiles provided by this module
		$r = 1;
		/* BEGIN MODULEBUILDER EXPORT VERIFACTU */
		/*
		$langs->load("verifactu@verifactu");
		$this->export_code[$r]=$this->rights_class.'_'.$r;
		$this->export_label[$r]='VerifactuLines';	// Translation key (used only if key ExportDataset_xxx_z not found)
		$this->export_icon[$r]='verifactu@verifactu';
		// Define $this->export_fields_array, $this->export_TypeFields_array and $this->export_entities_array
		$keyforclass = 'Verifactu'; $keyforclassfile='/verifactu/class/verifactu.class.php'; $keyforelement='verifactu@verifactu';
		include DOL_DOCUMENT_ROOT.'/core/commonfieldsinexport.inc.php';
		//$this->export_fields_array[$r]['t.fieldtoadd']='FieldToAdd'; $this->export_TypeFields_array[$r]['t.fieldtoadd']='Text';
		//unset($this->export_fields_array[$r]['t.fieldtoremove']);
		//$keyforclass = 'VerifactuLine'; $keyforclassfile='/verifactu/class/verifactu.class.php'; $keyforelement='verifactuline@verifactu'; $keyforalias='tl';
		//include DOL_DOCUMENT_ROOT.'/core/commonfieldsinexport.inc.php';
		$keyforselect='verifactu'; $keyforaliasextra='extra'; $keyforelement='verifactu@verifactu';
		include DOL_DOCUMENT_ROOT.'/core/extrafieldsinexport.inc.php';
		//$keyforselect='verifactuline'; $keyforaliasextra='extraline'; $keyforelement='verifactuline@verifactu';
		//include DOL_DOCUMENT_ROOT.'/core/extrafieldsinexport.inc.php';
		//$this->export_dependencies_array[$r] = array('verifactuline'=>array('tl.rowid','tl.ref')); // To force to activate one or several fields if we select some fields that need same (like to select a unique key if we ask a field of a child to avoid the DISTINCT to discard them, or for computed field than need several other fields)
		//$this->export_special_array[$r] = array('t.field'=>'...');
		//$this->export_examplevalues_array[$r] = array('t.field'=>'Example');
		//$this->export_help_array[$r] = array('t.field'=>'FieldDescHelp');
		$this->export_sql_start[$r]='SELECT DISTINCT ';
		$this->export_sql_end[$r]  =' FROM '.MAIN_DB_PREFIX.'verifactu as t';
		//$this->export_sql_end[$r]  =' LEFT JOIN '.MAIN_DB_PREFIX.'verifactu_line as tl ON tl.fk_verifactu = t.rowid';
		$this->export_sql_end[$r] .=' WHERE 1 = 1';
		$this->export_sql_end[$r] .=' AND t.entity IN ('.getEntity('verifactu').')';
		$r++; */
		/* END MODULEBUILDER EXPORT VERIFACTU */

		// Imports profiles provided by this module
		$r = 1;
		/* BEGIN MODULEBUILDER IMPORT VERIFACTU */
		/*
		$langs->load("verifactu@verifactu");
		$this->import_code[$r]=$this->rights_class.'_'.$r;
		$this->import_label[$r]='VerifactuLines';	// Translation key (used only if key ExportDataset_xxx_z not found)
		$this->import_icon[$r]='verifactu@verifactu';
		$this->import_tables_array[$r] = array('t' => MAIN_DB_PREFIX.'verifactu_verifactu', 'extra' => MAIN_DB_PREFIX.'verifactu_verifactu_extrafields');
		$this->import_tables_creator_array[$r] = array('t' => 'fk_user_author'); // Fields to store import user id
		$import_sample = array();
		$keyforclass = 'Verifactu'; $keyforclassfile='/verifactu/class/verifactu.class.php'; $keyforelement='verifactu@verifactu';
		include DOL_DOCUMENT_ROOT.'/core/commonfieldsinimport.inc.php';
		$import_extrafield_sample = array();
		$keyforselect='verifactu'; $keyforaliasextra='extra'; $keyforelement='verifactu@verifactu';
		include DOL_DOCUMENT_ROOT.'/core/extrafieldsinimport.inc.php';
		$this->import_fieldshidden_array[$r] = array('extra.fk_object' => 'lastrowid-'.MAIN_DB_PREFIX.'verifactu_verifactu');
		$this->import_regex_array[$r] = array();
		$this->import_examplevalues_array[$r] = array_merge($import_sample, $import_extrafield_sample);
		$this->import_updatekeys_array[$r] = array('t.ref' => 'Ref');
		$this->import_convertvalue_array[$r] = array(
			't.ref' => array(
				'rule'=>'getrefifauto',
				'class'=(empty($conf->global->VERIFACTU_VERIFACTU_ADDON) ? 'mod_verifactu_standard' : $conf->global->VERIFACTU_VERIFACTU_ADDON),
				'path'=>"/core/modules/commande/".(empty($conf->global->VERIFACTU_VERIFACTU_ADDON) ? 'mod_verifactu_standard' : $conf->global->VERIFACTU_VERIFACTU_ADDON).'.php'
				'classobject'=>'Verifactu',
				'pathobject'=>'/verifactu/class/verifactu.class.php',
			),
			't.fk_soc' => array('rule' => 'fetchidfromref', 'file' => '/societe/class/societe.class.php', 'class' => 'Societe', 'method' => 'fetch', 'element' => 'ThirdParty'),
			't.fk_user_valid' => array('rule' => 'fetchidfromref', 'file' => '/user/class/user.class.php', 'class' => 'User', 'method' => 'fetch', 'element' => 'user'),
			't.fk_mode_reglement' => array('rule' => 'fetchidfromcodeorlabel', 'file' => '/compta/paiement/class/cpaiement.class.php', 'class' => 'Cpaiement', 'method' => 'fetch', 'element' => 'cpayment'),
		);
		$r++; */
		/* END MODULEBUILDER IMPORT VERIFACTU */
	}

	/**
	 *  Function called when module is enabled.
	 *  The init function add constants, boxes, permissions and menus (defined in constructor) into Dolibarr database.
	 *  It also creates data directories
	 *
	 *  @param      string  $options    Options when enabling module ('', 'noboxes')
	 *  @return     int             	1 if OK, 0 if KO
	 */
	public function init($options = '')
	{
		global $conf, $langs, $user;
		$langs->load("verifactu@verifactu");

		if (!extension_loaded('soap')) {
			$this->error = $langs->trans("SOAP_EXTENSION_NOT_INSTALLED");
			return -1;
		}

		// Include VeriFactu data types
		require_once(dol_buildpath('/verifactu/lib/verifactu-types.array.php', 0));

		//$result = $this->_load_tables('/install/mysql/', 'verifactu');
		$result = $this->_load_tables('/verifactu/sql/');
		if ($result < 0) {
			return -1; // Do not activate module if error 'not allowed' returned when loading module SQL queries (the _load_table run sql with run_sql with the error allowed parameter set to 'default')
		}

		// Create extrafields during init
		include_once DOL_DOCUMENT_ROOT . '/core/class/extrafields.class.php';
		$extrafields = new ExtraFields($this->db);
		$rang = 1000;
		//Campos adicionales de la factura
		$result = $extrafields->addExtraField(
			'verifactu_inicio_separador1',
			"verifactu_SECTION_SEPARATOR1",
			'separate',
			$rang++,
			null,
			'facture',
			0,
			0,
			'',
			array('options' => array('1' => null)),
			1,
			'($user->rights->verifactu->manage || $user->admin)',
			5,
			'',
			'',
			'',
			'verifactu@verifactu',
			'$conf->verifactu->enabled'
		);
		$result = $extrafields->addExtraField(
			'verifactu_estado',
			"verifactu_STATUS",
			'varchar',
			$rang++,
			255,
			'facture',
			0,
			0,
			'',
			'',
			1,
			'($user->rights->verifactu->manage || $user->admin)',
			5,
			'verifactu_STATUSTooltip',
			'',
			'',
			'verifactu@verifactu',
			'$conf->verifactu->enabled'
		);
		$result = $extrafields->addExtraField(
			'verifactu_error',
			"verifactu_ERROR",
			'text',
			$rang++,
			500,
			'facture',
			0,
			0,
			'',
			'',
			1,
			'($user->rights->verifactu->manage || $user->admin)',
			5,
			'verifactu_ERRORTooltip',
			'',
			'',
			'verifactu@verifactu',
			'$conf->verifactu->enabled'
		);
		$result = $extrafields->addExtraField(
			'verifactu_csv_factura',
			"verifactu_INVOICE_CSV",
			'text',
			$rang++,
			255,
			'facture',
			0,
			0,
			'',
			'',
			1,
			'($user->rights->verifactu->manage || $user->admin)',
			5,
			'verifactu_INVOICE_CSVTooltip',
			'',
			'',
			'verifactu@verifactu',
			'$conf->verifactu->enabled'
		);
		$result = $extrafields->addExtraField(
			'verifactu_id_factura',
			"verifactu_INVOICE_ID",
			'text',
			$rang++,
			255,
			'facture',
			0,
			0,
			'',
			'',
			1,
			'($user->rights->verifactu->manage || $user->admin)',
			5,
			'verifactu_INVOICE_IDTooltip',
			'',
			'',
			'verifactu@verifactu',
			'$conf->verifactu->enabled'
		);
		$result = $extrafields->addExtraField(
			'verifactu_fecha_factura',
			"verifactu_INVOICE_DATE",
			'date',
			$rang++,
			255,
			'facture',
			0,
			0,
			'',
			'',
			1,
			'($user->rights->verifactu->manage || $user->admin)',
			5,
			'verifactu_INVOICE_DATETooltip',
			'',
			'',
			'verifactu@verifactu',
			'$conf->verifactu->enabled'
		);
		$result = $extrafields->addExtraField(
			'verifactu_ultima_salida',
			"verifactu_LAST_OUTPUT",
			'text',
			$rang++,
			255,
			'facture',
			0,
			0,
			'',
			'',
			1,
			'($user->rights->verifactu->manage || $user->admin)',
			5,
			'verifactu_LAST_OUTPUTTooltip',
			'',
			'',
			'verifactu@verifactu',
			'$conf->verifactu->enabled'
		);
		$result = $extrafields->addExtraField(
			'verifactu_ultimafecha_modificacion',
			"verifactu_LAST_MODIFICATION_DATE",
			'datetime',
			$rang++,
			255,
			'facture',
			0,
			0,
			'',
			'',
			1,
			'($user->rights->verifactu->manage || $user->admin)',
			5,
			'verifactu_LAST_MODIFICATION_DATETooltip',
			'',
			'',
			'verifactu@verifactu',
			'$conf->verifactu->enabled'
		);
		$result = $extrafields->addExtraField(
			'verifactu_huella',
			"verifactu_HASH",
			'text',
			$rang++,
			255,
			'facture',
			0,
			0,
			'',
			'',
			1,
			'($user->rights->verifactu->manage || $user->admin)',
			5,
			'verifactu_HASHTooltip',
			'',
			'',
			'verifactu@verifactu',
			'$conf->verifactu->enabled'
		);
		$result = $extrafields->addExtraField(
			'verifactu_fecha_hora_generacion',
			"verifactu_GENERATION_DATETIME",
			'varchar',
			$rang++,
			50,
			'facture',
			0,
			0,
			'',
			'',
			1,
			'($user->rights->verifactu->manage || $user->admin)',
			5,
			'verifactu_GENERATION_DATETIMETooltip',
			'',
			'',
			'verifactu@verifactu',
			'$conf->verifactu->enabled'
		);

		$result = $extrafields->addExtraField(
			'verifactu_entorno',
			"VERIFACTU_ENVIRONMENT",
			'select',
			$rang++,
			50,
			'facture',
			0,
			0,
			'',
			array(
				"options" => array(
					'test' => 'Test environment',
					'production' => 'Production environment'
				)
			),
			1,
			'($user->rights->verifactu->manage || $user->admin)',
			5,
			'VERIFACTU_ENVIRONMENTTooltip',
			'',
			'',
			'verifactu@verifactu',
			'$conf->verifactu->enabled'
		);
		$result = $extrafields->addExtraField(
			'verifactu_modo',
			"VERIFACTU_MODE",
			'varchar',
			$rang++,
			255,
			'facture',
			0,
			0,
			'',
			'',
			1,
			'($user->rights->verifactu->manage || $user->admin)',
			5,
			'VERIFACTU_MODETooltip',
			'',
			'',
			'verifactu@verifactu',
			'$conf->verifactu->enabled'
		);

		$result = $extrafields->addExtraField(
			'verifactu_payload',
			"verifactu_PAYLOAD",
			'text',
			$rang++,
			50000,
			'facture',
			0,
			0,
			'',
			'',
			1,
			'($user->rights->verifactu->manage || $user->admin)',
			0,
			'verifactu_PAYLOADTooltip',
			'',
			'',
			'verifactu@verifactu',
			'$conf->verifactu->enabled'
		);

		// Determine the display value based on Dolibarr version
		$displayValue = (versioncompare(explode('.', DOL_VERSION), array(12)) < 0) ? 3 : 4;

		$result = $extrafields->addExtraField(
			'verifactu_inicio_separador2',
			"verifactu_SECTION_SEPARATOR2",
			'separate',
			$rang++,
			null,
			'facture',
			0,
			0,
			'',
			array('options' => array('1' => null)),
			1,
			'($user->rights->verifactu->manage || $user->admin)',
			$displayValue,
			'',
			'',
			'',
			'verifactu@verifactu',
			'$conf->verifactu->enabled'
		);
		$result = $extrafields->addExtraField(
			'verifactu_factura_tipo',
			"verifactu_INVOICE_TYPE",
			'select',
			$rang++,
			255,
			'facture',
			0,
			0,
			'F1',
			array(
				"options" => $tipoFacturas
			),
			1,
			'($user->rights->verifactu->manage || $user->admin)',
			$displayValue,
			'verifactu_INVOICE_TYPETooltip',
			'',
			'',
			'verifactu@verifactu',
			'$conf->verifactu->enabled'
		);
		$result = $extrafields->addExtraField(
			'verifactu_impuesto',
			"verifactu_TAX_TYPE",
			'select',
			$rang++,
			255,
			'facture',
			0,
			0,
			'01',
			array(
				"options" => $tipoImpuestos
			),
			1,
			'($user->rights->verifactu->manage || $user->admin)',
			$displayValue,
			'verifactu_TAX_TYPETooltip',
			'',
			'',
			'verifactu@verifactu',
			'$conf->verifactu->enabled'
		);
		$result = $extrafields->addExtraField(
			'verifactu_clave_regimen',
			"verifactu_TAX_REGIME",
			'select',
			$rang++,
			255,
			'facture',
			0,
			0,
			'01',
			array(
				"options" => $claveRegimen
			),
			1,
			'($user->rights->verifactu->manage || $user->admin)',
			$displayValue,
			'verifactu_TAX_REGIMETooltip',
			'',
			'',
			'verifactu@verifactu',
			'$conf->verifactu->enabled'
		);

		// Additional fields for special cases (hybrid strategy)
		// Field L9: CalificacionOperacion (only S1, S2, N1, N2)
		$result = $extrafields->addExtraField(
			'verifactu_calificacion_operacion',
			"verifactu_OPERATION_QUALIFICATION",
			'select',
			$rang++,
			255,
			'facture',
			0,
			0,
			'',
			array(
				"options" => $calificacionOperacion
			),
			1,
			'($user->rights->verifactu->manage || $user->admin)',
			$displayValue,
			'verifactu_OPERATION_QUALIFICATIONTooltip',
			'',
			'',
			'verifactu@verifactu',
			'$conf->verifactu->enabled'
		);

		// Field L10: OperacionExenta (only E1-E6)
		$result = $extrafields->addExtraField(
			'verifactu_operacion_exenta',
			"verifactu_EXEMPT_OPERATION",
			'select',
			$rang++,
			255,
			'facture',
			0,
			0,
			'',
			array(
				"options" => $operacionExenta
			),
			1,
			'($user->rights->verifactu->manage || $user->admin)',
			$displayValue,
			'verifactu_EXEMPT_OPERATIONTooltip',
			'',
			'',
			'verifactu@verifactu',
			'$conf->verifactu->enabled'
		);

		// VERIFACTU Incident Field (S/N)
		$result = $extrafields->addExtraField(
			'verifactu_incidencia',
			"verifactu_INCIDENT",
			'select',
			$rang++,
			255,
			'facture',
			0,
			0,
			'N',
			array(
				"options" => $incidenciaVeriFactu
			),
			1,
			'($user->rights->verifactu->manage || $user->admin)',
			$displayValue,
			'verifactu_INCIDENTTooltip',
			'',
			'',
			'verifactu@verifactu',
			'$conf->verifactu->enabled'
		);


		$rang = 1000;
		//Campos adicionales del cliente
		$result = $extrafields->addExtraField(
			'verifactu_inicio_separador1',
			"verifactu_SECTION_SEPARATOR1",
			'separate',
			$rang++,
			null,
			'thirdparty',
			0,
			0,
			'',
			array('options' => array('1' => null)),
			1,
			'($user->rights->verifactu->manage || $user->admin)',
			5,
			'',
			'',
			'',
			'verifactu@verifactu',
			'$conf->verifactu->enabled'
		);

		$result = $extrafields->addExtraField(
			'verifactu_tipo_identificacion',
			"verifactu_ID_TYPE",
			'select',
			$rang++,
			255,
			'thirdparty',
			0,
			0,
			'02',
			array(
				"options" => $tiposIdentificacion
			),
			1,
			'($user->rights->verifactu->manage || $user->admin)',
			1,
			'verifactu_ID_TYPETooltip',
			'',
			'',
			'verifactu@verifactu',
			'$conf->verifactu->enabled'
		);
		$result = $extrafields->addExtraField(
			'verifactu_impuesto',
			"verifactu_TAX_TYPE",
			'select',
			$rang++,
			255,
			'thirdparty',
			0,
			0,
			'',
			array(
				"options" => $tipoImpuestos
			),
			1,
			'($user->rights->verifactu->manage || $user->admin)',
			1,
			'verifactu_TAX_TYPETooltip',
			'',
			'',
			'verifactu@verifactu',
			'$conf->verifactu->enabled'
		);
		$result = $extrafields->addExtraField(
			'verifactu_clave_regimen',
			"verifactu_TAX_REGIME",
			'select',
			$rang++,
			255,
			'thirdparty',
			0,
			0,
			'',
			array(
				"options" => $claveRegimen
			),
			1,
			'($user->rights->verifactu->manage || $user->admin)',
			1,
			'verifactu_TAX_REGIMETooltip',
			'',
			'',
			'verifactu@verifactu',
			'$conf->verifactu->enabled'
		);

		// Additional fields for special cases (hybrid strategy)
		// Field L9: CalificacionOperacion (only S1, S2, N1, N2)
		$result = $extrafields->addExtraField(
			'verifactu_calificacion_operacion',
			"verifactu_OPERATION_QUALIFICATION",
			'select',
			$rang++,
			255,
			'thirdparty',
			0,
			0,
			'',
			array(
				"options" => $calificacionOperacion
			),
			1,
			'($user->rights->verifactu->manage || $user->admin)',
			1,
			'verifactu_OPERATION_QUALIFICATIONTooltip',
			'',
			'',
			'verifactu@verifactu',
			'$conf->verifactu->enabled'
		);

		// Field L10: OperacionExenta (only E1-E6)
		$result = $extrafields->addExtraField(
			'verifactu_operacion_exenta',
			"verifactu_EXEMPT_OPERATION",
			'select',
			$rang++,
			255,
			'thirdparty',
			0,
			0,
			'',
			array(
				"options" => $operacionExenta
			),
			1,
			'($user->rights->verifactu->manage || $user->admin)',
			1,
			'verifactu_EXEMPT_OPERATIONTooltip',
			'',
			'',
			'verifactu@verifactu',
			'$conf->verifactu->enabled'
		);

		//Campo de cliente de factura simplificada
		$result = $extrafields->addExtraField(
			'verifactu_factura_simplificada',
			"verifactu_SIMPLIFIED_INVOICE_CUSTOMER",
			'boolean',
			$rang++,
			255,
			'thirdparty',
			0,
			0,
			'',
			'',
			1,
			'($user->rights->verifactu->manage || $user->admin)',
			1,
			'verifactu_SIMPLIFIED_INVOICE_CUSTOMERTooltip',
			'',
			'',
			'verifactu@verifactu',
			'$conf->verifactu->enabled'
		);





		if (versioncompare(explode('.', DOL_VERSION), array(14)) >= 0) {

			require_once DOL_DOCUMENT_ROOT . '/core/class/defaultvalues.class.php';

			//Default values
			$dFalues = [
				['type' => 'mandatory', 'entity' => 1, 'page' => 'societe/card.php', 'param' => 'country_id', 'value' => '']

			];

			foreach ($dFalues as $dF) {
				$defaultvalues = new DefaultValues($this->db);
				$defaultvalues->type = $dF['type'];
				$defaultvalues->user_id = 0;
				$defaultvalues->page = $dF['page'];
				$defaultvalues->param = $dF['param'];
				$defaultvalues->value = $dF['value'];
				$defaultvalues->entity = $conf->entity;
				$result = $defaultvalues->create($user);
			}
		}


		// Permissions
		$this->remove($options);
		$badge = '<div class="center"><span class="badge badge-status8 classfortooltip badge-status" attr-status="' . $langs->trans('VERIFACTU_STATUS_NOT_SEND') . '">' . $langs->trans('VERIFACTU_STATUS_NOT_SEND') . '</span></div>';
		$sql1 = "UPDATE " . MAIN_DB_PREFIX . "facture_extrafields SET verifactu_estado='$badge' WHERE verifactu_estado IS NULL";

		$sql = array($sql1);
		dol_include_once('/verifactu/lib/verifactu.lib.php');
		return $this->_init($sql, $options);
	}

	/**
	 *  Function called when module is disabled.
	 *  Remove from database constants, boxes and permissions from Dolibarr database.
	 *  Data directories are not deleted
	 *
	 *  @param      string	$options    Options when enabling module ('', 'noboxes')
	 *  @return     int                 1 if OK, 0 if KO
	 */
	public function remove($options = '')
	{
		$sql = array();
		return $this->_remove($sql, $options);
	}

	public function getLastVersion()
	{
		// Funcion deshabilitada - ya no se consulta servidor externo
		return (object)[
			'needupdate' => false,
			'last_version' => $this->version,
			'current_version' => $this->version
		];
	}
}
