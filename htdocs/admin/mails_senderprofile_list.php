<?php
/* Copyright (C) 2007-2017 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2018	   Ferran Marcet 		<fmarcet@2byte.es>
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
 *   	\file       htdocs/admin/mails_senderprofile_list.php
 *		\ingroup    core
 *		\brief      Page to adminsiter email sender profiles
 */

require '../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formcompany.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/emailsenderprofile.class.php';

// Load translation files required by the page
$langs->loadLangs(array("errors", "admin", "mails", "languages"));

$action     = GETPOST('action', 'aZ09') ?GETPOST('action', 'aZ09') : 'view'; // The action 'add', 'create', 'edit', 'update', 'view', ...
$massaction = GETPOST('massaction', 'alpha'); // The bulk action (combo box choice into lists)
$show_files = GETPOST('show_files', 'int'); // Show files area generated by bulk actions ?
$confirm    = GETPOST('confirm', 'alpha'); // Result of a confirmation
$cancel     = GETPOST('cancel', 'alpha'); // We click on a Cancel button
$toselect   = GETPOST('toselect', 'array'); // Array of ids of elements selected into a list
$contextpage = GETPOST('contextpage', 'aZ') ? GETPOST('contextpage', 'aZ') : 'emailsenderprofilelist'; // To manage different context of search
$backtopage = GETPOST('backtopage', 'alpha'); // Go back to a dedicated page
$optioncss  = GETPOST('optioncss', 'aZ'); // Option for the css output (always '' except when 'print')

$id = GETPOST('id', 'int');
$rowid = GETPOST('rowid', 'alpha');

// Load variable for pagination
$limit = GETPOST('limit', 'int') ? GETPOST('limit', 'int') : $conf->liste_limit;
$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
$page = GETPOSTISSET('pageplusone') ? (GETPOST('pageplusone') - 1) : GETPOST("page", 'int');
if (empty($page) || $page == -1 || GETPOST('button_search', 'alpha') || GETPOST('button_removefilter', 'alpha') || (empty($toselect) && $massaction === '0')) {
	$page = 0;
}     // If $page is not defined, or '' or -1 or if we click on clear filters or if we select empty mass action
$offset = $limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;

// Initialize technical objects
$object = new EmailSenderProfile($db);
$extrafields = new ExtraFields($db);
$diroutputmassaction = $conf->admin->dir_output.'/temp/massgeneration/'.$user->id;
$hookmanager->initHooks(array('emailsenderprofilelist')); // Note that conf->hooks_modules contains array

// Fetch optionals attributes and labels
$extrafields->fetch_name_optionals_label($object->table_element);

$search_array_options = $extrafields->getOptionalsFromPost($object->table_element, '', 'search_');

// Default sort order (if not yet defined by previous GETPOST)
if (!$sortfield) {
	$sortfield = "t.".key($object->fields); // Set here default search field. By default 1st field in definition.
}
if (!$sortorder) {
	$sortorder = "ASC";
}

// Initialize array of search criterias
$search_all = GETPOST("search_all", 'alpha');
$search = array();
foreach ($object->fields as $key => $val) {
	if (GETPOST('search_'.$key, 'alpha')) {
		$search[$key] = GETPOST('search_'.$key, 'alpha');
	}
}

// List of fields to search into when doing a "search in all"
$fieldstosearchall = array();
foreach ($object->fields as $key => $val) {
	if (!empty($val['searchall'])) {
		$fieldstosearchall['t.'.$key] = $val['label'];
	}
}

// Definition of fields for list
$arrayfields = array();
foreach ($object->fields as $key => $val) {
	// If $val['visible']==0, then we never show the field
	if (!empty($val['visible'])) {
		$arrayfields['t.'.$key] = array('label'=>$val['label'], 'checked'=>(($val['visible'] < 0) ? 0 : 1), 'enabled'=>($val['enabled'] && ($val['visible'] != 3)), 'position'=>$val['position']);
	}
}
// Extra fields
if (!empty($extrafields->attributes[$object->table_element]['label']) && is_array($extrafields->attributes[$object->table_element]['label']) && count($extrafields->attributes[$object->table_element]['label']) > 0) {
	foreach ($extrafields->attributes[$object->table_element]['label'] as $key => $val) {
		if (!empty($extrafields->attributes[$object->table_element]['list'][$key])) {
			$arrayfields["ef.".$key] = array(
				'label'=>$extrafields->attributes[$object->table_element]['label'][$key],
				'checked'=>(($extrafields->attributes[$object->table_element]['list'][$key] < 0) ? 0 : 1),
				'position'=>$extrafields->attributes[$object->table_element]['pos'][$key],
				'enabled'=>(abs($extrafields->attributes[$object->table_element]['list'][$key]) != 3 && $extrafields->attributes[$object->table_element]['perms'][$key])
			);
		}
	}
}
$object->fields = dol_sort_array($object->fields, 'position');
$arrayfields = dol_sort_array($arrayfields, 'position');

$permissiontoread = $user->admin;
$permissiontoadd = $user->admin;
$permissiontodelete = $user->admin;

if ($id > 0) {
	$object->fetch($id);
}

// Security check
$socid = 0;
if ($user->socid > 0) {	// Protection if external user
	//$socid = $user->socid;
	accessforbidden();
}
// A non admin user can see profiles but limited to its own user
if (!$user->admin) {
	if ($object->id > 0 && $object->private != $user->id) {
		accessforbidden();
	}
}


/*
 * Actions
 */

if (GETPOST('cancel', 'alpha')) {
	$action = 'list'; $massaction = '';
}
if (!GETPOST('confirmmassaction', 'alpha') && $massaction != 'presend' && $massaction != 'confirm_presend') {
	$massaction = '';
}

$parameters = array();
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action); // Note that $action and $object may have been modified by some hooks
if ($reshook < 0) {
	setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

if (empty($reshook)) {
	// Actions cancel, add, update, delete or clone
	$backurlforlist = $_SERVER["PHP_SELF"].'?action=list';
	include DOL_DOCUMENT_ROOT.'/core/actions_addupdatedelete.inc.php';

	// Selection of new fields
	include DOL_DOCUMENT_ROOT.'/core/actions_changeselectedfields.inc.php';

	// Purge search criteria
	if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')) { // All tests are required to be compatible with all browsers
		foreach ($object->fields as $key => $val) {
			$search[$key] = '';
		}
		$toselect = '';
		$search_array_options = array();
	}
	if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')
		|| GETPOST('button_search_x', 'alpha') || GETPOST('button_search.x', 'alpha') || GETPOST('button_search', 'alpha')) {
		$massaction = ''; // Protection to avoid mass action if we force a new search during a mass action confirmation
	}

	// Mass actions
	$objectclass = 'EmailSenderProfile';
	$objectlabel = 'EmailSenderProfile';
	$uploaddir = $conf->admin->dir_output.'/senderprofiles';
	include DOL_DOCUMENT_ROOT.'/core/actions_massactions.inc.php';

	if ($action == 'delete') {
		$sql = "DELETE FROM ".MAIN_DB_PREFIX."c_email_senderprofile WHERE rowid = ".GETPOST('id', 'int');
		$resql = $db->query($sql);
		if ($resql) {
			setEventMessages($langs->trans("RecordDeleted"), null, 'mesgs');
		} else {
			setEventMessages($langs->trans("Error").' '.$db->lasterror(), null, 'errors');
		}
	}
}



/*
 * View
 */

$form = new Form($db);

$now = dol_now();

//$help_url="EN:Module_EmailSenderProfile|FR:Module_EmailSenderProfile_FR|ES:Módulo_EmailSenderProfile";
$help_url = '';
$title = $langs->trans("EMailsSetup");

llxHeader('', $title);

$linkback = '';
$titlepicto = 'title_setup';

print load_fiche_titre($title, $linkback, $titlepicto);

$head = email_admin_prepare_head();

print dol_get_fiche_head($head, 'senderprofiles', '', -1);

print '<span class="opacitymedium">'.$langs->trans("EMailsSenderProfileDesc")."</span><br>\n";
print "<br>\n";

// Build and execute select
// --------------------------------------------------------------------
$sql = 'SELECT ';
foreach ($object->fields as $key => $val) {
	$sql .= 't.'.$key.', ';
}
// Add fields from extrafields
if (!empty($extrafields->attributes[$object->table_element]['label'])) {
	foreach ($extrafields->attributes[$object->table_element]['label'] as $key => $val) {
		$sql .= ($extrafields->attributes[$object->table_element]['type'][$key] != 'separate' ? "ef.".$key.' as options_'.$key.', ' : '');
	}
}
// Add fields from hooks
$parameters = array();
$reshook = $hookmanager->executeHooks('printFieldListSelect', $parameters, $object); // Note that $action and $object may have been modified by hook
$sql .= preg_replace('/^,/', '', $hookmanager->resPrint);
$sql = preg_replace('/,\s*$/', '', $sql);
$sql .= " FROM ".MAIN_DB_PREFIX.$object->table_element." as t";
if (!empty($extrafields->attributes[$object->table_element]['label']) && is_array($extrafields->attributes[$object->table_element]['label']) && count($extrafields->attributes[$object->table_element]['label'])) {
	$sql .= " LEFT JOIN ".MAIN_DB_PREFIX.$object->table_element."_extrafields as ef on (t.rowid = ef.fk_object)";
}
if ($object->ismultientitymanaged == 1) {
	$sql .= " WHERE t.entity IN (".getEntity($object->element).")";
} else {
	$sql .= " WHERE 1 = 1";
}
foreach ($search as $key => $val) {
	if ($key == 'status' && $search[$key] == -1) {
		continue;
	}
	$mode_search = (($object->isInt($object->fields[$key]) || $object->isFloat($object->fields[$key])) ? 1 : 0);
	if (strpos($object->fields[$key]['type'], 'integer:') === 0 || $key == 'active') {
		if ($search[$key] == '-1') {
			$search[$key] = '';
		}
		$mode_search = 2;
	}
	if ($search[$key] != '') {
		$sql .= natural_search($key, $search[$key], (($key == 'status') ? 2 : $mode_search));
	}
}
if ($search_all) {
	$sql .= natural_search(array_keys($fieldstosearchall), $search_all);
}
// If non admin, restrict list to itself
if (empty($user->admin)) {
	$sql .= " AND private = ".((int) $user->id);
}
//$sql.= dolSqlDateFilter("t.field", $search_xxxday, $search_xxxmonth, $search_xxxyear);
// Add where from extra fields
include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_search_sql.tpl.php';
// Add where from hooks
$parameters = array();
$reshook = $hookmanager->executeHooks('printFieldListWhere', $parameters, $object); // Note that $action and $object may have been modified by hook
$sql .= $hookmanager->resPrint;

/* If a group by is required
$sql.= " GROUP BY "
foreach($object->fields as $key => $val)
{
	$sql.='t.'.$key.', ';
}
// Add fields from extrafields
if (! empty($extrafields->attributes[$object->table_element]['label'])) {
	foreach ($extrafields->attributes[$object->table_element]['label'] as $key => $val) $sql.=($extrafields->attributes[$object->table_element]['type'][$key] != 'separate' ? "ef.".$key.', ' : '');
}
// Add where from hooks
$parameters=array();
$reshook=$hookmanager->executeHooks('printFieldListGroupBy',$parameters);    // Note that $action and $object may have been modified by hook
$sql.=$hookmanager->resPrint;
$sql=preg_replace('/,\s*$/','', $sql);
*/

$sql .= $db->order($sortfield, $sortorder);

// Count total nb of records
$nbtotalofrecords = '';
if (empty($conf->global->MAIN_DISABLE_FULL_SCANLIST)) {
	$resql = $db->query($sql);
	$nbtotalofrecords = $db->num_rows($resql);
	if (($page * $limit) > $nbtotalofrecords) {	// if total of record found is smaller than page * limit, goto and load page 0
		$page = 0;
		$offset = 0;
	}
}
// if total of record found is smaller than limit, no need to do paging and to restart another select with limits set.
if (is_numeric($nbtotalofrecords) && ($limit > $nbtotalofrecords || empty($limit))) {
	$num = $nbtotalofrecords;
} else {
	if ($limit) {
		$sql .= $db->plimit($limit + 1, $offset);
	}

	$resql = $db->query($sql);
	if (!$resql) {
		dol_print_error($db);
		exit;
	}

	$num = $db->num_rows($resql);
}


// Output page
// --------------------------------------------------------------------

$arrayofselected = is_array($toselect) ? $toselect : array();

$param = '';
if (!empty($contextpage) && $contextpage != $_SERVER["PHP_SELF"]) {
	$param .= '&contextpage='.urlencode($contextpage);
}
if ($limit > 0 && $limit != $conf->liste_limit) {
	$param .= '&limit='.urlencode($limit);
}
foreach ($search as $key => $val) {
	if (is_array($search[$key]) && count($search[$key])) {
		foreach ($search[$key] as $skey) {
			$param .= '&search_'.$key.'[]='.urlencode($skey);
		}
	} else {
		$param .= '&search_'.$key.'='.urlencode($search[$key]);
	}
}
if ($optioncss != '') {
	$param .= '&optioncss='.urlencode($optioncss);
}
// Add $param from extra fields
include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_search_param.tpl.php';

// List of mass actions available
$arrayofmassactions = array(
	//'presend'=>img_picto('', 'email', 'class="pictofixedwidth"').$langs->trans("SendByMail"),
	//'builddoc'=>img_picto('', 'pdf', 'class="pictofixedwidth"').$langs->trans("PDFMerge"),
);
//if ($permissiontodelete) $arrayofmassactions['predelete'] = img_picto('', 'delete', 'class="pictofixedwidth"').$langs->trans("Delete");
//if (GETPOST('nomassaction', 'int') || in_array($massaction, array('presend', 'predelete'))) $arrayofmassactions = array();
$massactionbutton = $form->selectMassAction('', $arrayofmassactions);

print '<form method="POST" id="searchFormList" action="'.$_SERVER["PHP_SELF"].'">';
if ($optioncss != '') {
	print '<input type="hidden" name="optioncss" value="'.$optioncss.'">';
}
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="formfilteraction" id="formfilteraction" value="list">';
print '<input type="hidden" name="action" value="'.($action == 'create' ? 'add' : ($action == 'edit' ? 'update' : 'list')).'">';
print '<input type="hidden" name="sortfield" value="'.$sortfield.'">';
print '<input type="hidden" name="sortorder" value="'.$sortorder.'">';
print '<input type="hidden" name="page" value="'.$page.'">';
print '<input type="hidden" name="id" value="'.$id.'">';
print '<input type="hidden" name="contextpage" value="'.$contextpage.'">';

$newcardbutton = '';
if ($action != 'create') {
	$newcardbutton = dolGetButtonTitle($langs->trans('New'), '', 'fa fa-plus-circle', $_SERVER['PHP_SELF'].'?action=create', '', $permissiontoadd);

	if ($action == 'edit') {
		print '<table class="border centpercent tableforfield">';
		print '<tr><td class="fieldrequired">'.$langs->trans("Label").'</td><td><input type="text" name="label" value="'.(GETPOSTISSET('label') ? GETPOST('label', 'alphanohtml') : $object->label).'"></td></tr>';
		print '<tr><td>'.$langs->trans("Email").'</td><td><input type="text" name="email" value="'.(GETPOSTISSET('email') ? GETPOST('email', 'alphanohtml') : $object->email).'"></td></tr>';
		print '<tr><td>'.$langs->trans("Signature").'</td><td>';
		require_once DOL_DOCUMENT_ROOT.'/core/class/doleditor.class.php';
		$doleditor = new DolEditor('signature', (GETPOSTISSET('signature') ? GETPOST('signature', 'restricthtml') : $object->signature), '', 138, 'dolibarr_notes', 'In', true, true, empty($conf->global->FCKEDITOR_ENABLE_USERSIGN) ? 0 : 1, ROWS_4, '90%');
		print $doleditor->Create(1);
		print '</td></tr>';
		print '<tr><td>'.$langs->trans("User").'</td><td>';
		print $form->select_dolusers((GETPOSTISSET('private') ? GETPOST('private', 'int') : $object->private), 'private', 1, null, 0, ($user->admin ? '' : $user->id));
		print '</td></tr>';
		print '<tr><td>'.$langs->trans("Position").'</td><td><input type="text" name="position" class="maxwidth50" value="'.(GETPOSTISSET('position') ? GETPOST('position', 'int') : $object->position).'"></td></tr>';
		print '<tr><td>'.$langs->trans("Status").'</td><td>';
		print $form->selectarray('active', $object->fields['active']['arrayofkeyval'], (GETPOSTISSET('active') ? GETPOST('active', 'int') : $object->active), 0, 0, 0, '', 1);
		print '</td></tr>';
		print '</table>';
		print '<br>';
		print '<div class="center">';
		print '<input class="button button-save" type="submit" name="save" value="'.$langs->trans("Save").'">';
		print ' &nbsp; ';
		print '<input class="button button-cancel" type="submit" name="cancel" value="'.$langs->trans("Cancel").'">';
		print '</div>';
	}
} else {
	/*print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
	if ($optioncss != '') print '<input type="hidden" name="optioncss" value="'.$optioncss.'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="add">';
	print '<input type="hidden" name="sortfield" value="'.$sortfield.'">';
	print '<input type="hidden" name="sortorder" value="'.$sortorder.'">';
	print '<input type="hidden" name="page" value="'.$page.'">';
	print '<input type="hidden" name="contextpage" value="'.$contextpage.'">';
	*/
	print '<table class="border centpercent tableforfield">';
	print '<tr><td class="fieldrequired">'.$langs->trans("Label").'</td><td><input type="text" name="label" value="'.GETPOST('label', 'alphanohtml').'" autofocus></td></tr>';
	print '<tr><td class="fieldrequired">'.$langs->trans("Email").'</td><td><input type="text" name="email" value="'.GETPOST('email', 'alphanohtml').'"></td></tr>';
	print '<tr><td>'.$langs->trans("Signature").'</td><td>';
	require_once DOL_DOCUMENT_ROOT.'/core/class/doleditor.class.php';
	$doleditor = new DolEditor('signature', GETPOST('signature'), '', 138, 'dolibarr_notes', 'In', true, true, empty($conf->global->FCKEDITOR_ENABLE_USERSIGN) ? 0 : 1, ROWS_4, '90%');
	print $doleditor->Create(1);
	print '</td></tr>';
	print '<tr><td>'.$langs->trans("User").'</td><td>';
	print $form->select_dolusers((GETPOSTISSET('private') ? GETPOST('private', 'int') : -1), 'private', 1, null, 0, ($user->admin ? '' : $user->id));
	print '</td></tr>';
	print '<tr><td>'.$langs->trans("Position").'</td><td><input type="text" name="position" class="maxwidth50" value="'.GETPOST('position', 'int').'"></td></tr>';
	print '<tr><td>'.$langs->trans("Status").'</td><td>';
	print $form->selectarray('active', $object->fields['active']['arrayofkeyval'], GETPOST('active', 'int'), 0);
	print '</td></tr>';
	print '</table>';
	print '<br>';
	print '<div class="center">';
	print '<input class="button button-save" type="submit" name="save" value="'.$langs->trans("Save").'">';
	print ' &nbsp; ';
	print '<input class="button button-cancel" type="submit" name="cancel" value="'.$langs->trans("Cancel").'">';
	print '</div>';
	//print '</form>';
}

print_barre_liste('', $page, $_SERVER["PHP_SELF"], $param, $sortfield, $sortorder, $massactionbutton, $num, $nbtotalofrecords, 'title_companies', 0, $newcardbutton, '', $limit);
//print_barre_liste($title, $page, $_SERVER["PHP_SELF"], $param, $sortfield, $sortorder, $massactionbutton, $num, $nbtotalofrecords, 'title_companies', 0, '', '', $limit);

$topicmail = "Information";
//$modelmail="subscription";
$objecttmp = new EmailSenderProfile($db);
//$trackid = (($action == 'testhtml') ? "testhtml" : "test");
include DOL_DOCUMENT_ROOT.'/core/tpl/massactions_pre.tpl.php';

if ($search_all) {
	foreach ($fieldstosearchall as $key => $val) {
		$fieldstosearchall[$key] = $langs->trans($val);
	}
	print '<div class="divsearchfieldfilter">'.$langs->trans("FilterOnInto", $search_all).join(', ', $fieldstosearchall).'</div>';
}

$moreforfilter = '';
/*$moreforfilter.='<div class="divsearchfield">';
$moreforfilter.= $langs->trans('MyFilter') . ': <input type="text" name="search_myfield" value="'.dol_escape_htmltag($search_myfield).'">';
$moreforfilter.= '</div>';*/

$parameters = array();
$reshook = $hookmanager->executeHooks('printFieldPreListTitle', $parameters, $object); // Note that $action and $object may have been modified by hook
if (empty($reshook)) {
	$moreforfilter .= $hookmanager->resPrint;
} else {
	$moreforfilter = $hookmanager->resPrint;
}

if (!empty($moreforfilter)) {
	print '<div class="liste_titre liste_titre_bydiv centpercent">';
	print $moreforfilter;
	print '</div>';
}

$varpage = empty($contextpage) ? $_SERVER["PHP_SELF"] : $contextpage;
$selectedfields = $form->multiSelectArrayWithCheckbox('selectedfields', $arrayfields, $varpage); // This also change content of $arrayfields
$selectedfields .= (count($arrayofmassactions) ? $form->showCheckAddButtons('checkforselect', 1) : '');

print '<div class="div-table-responsive">'; // You can use div-table-responsive-no-min if you dont need reserved height for your table
print '<table class="tagtable liste'.($moreforfilter ? " listwithfilterbefore" : "").'">'."\n";


// Fields title search
// --------------------------------------------------------------------
print '<tr class="liste_titre">';
foreach ($object->fields as $key => $val) {
	$cssforfield = (empty($val['css']) ? '' : $val['css']);
	if ($key == 'status') {
		$cssforfield .= ($cssforfield ? ' ' : '').'center';
	} elseif (in_array($val['type'], array('date', 'datetime', 'timestamp'))) {
		$cssforfield .= ($cssforfield ? ' ' : '').'center';
	} elseif (in_array($val['type'], array('timestamp'))) {
		$cssforfield .= ($cssforfield ? ' ' : '').'nowrap';
	} elseif (in_array($val['type'], array('double(24,8)', 'double(6,3)', 'integer', 'real', 'price')) && $val['label'] != 'TechnicalID') {
		$cssforfield .= ($cssforfield ? ' ' : '').'right';
	}
	if (!empty($arrayfields['t.'.$key]['checked'])) {
		print '<td class="liste_titre'.($cssforfield ? ' '.$cssforfield : '').'">';
		if (!empty($val['arrayofkeyval']) && is_array($val['arrayofkeyval'])) {
			print $form->selectarray('search_'.$key, $val['arrayofkeyval'], empty($search[$key]) ? '' : $search[$key], $val['notnull'], 0, 0, '', 1, 0, 0, '', 'maxwidth100', 1);
		} elseif (strpos($val['type'], 'integer:') === 0) {
			print $object->showInputField($val, $key, empty($search[$key]) ? '' : $search[$key], '', '', 'search_', 'maxwidth150', 1);
		} elseif (!preg_match('/^(date|timestamp)/', $val['type'])) {
			print '<input type="text" class="flat maxwidth75" name="search_'.$key.'" value="'.dol_escape_htmltag(empty($search[$key]) ? '' : $search[$key]).'">';
		}
		print '</td>';
	}
}
// Extra fields
include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_search_input.tpl.php';
// Fields from hook
$parameters = array('arrayfields'=>$arrayfields);
$reshook = $hookmanager->executeHooks('printFieldListOption', $parameters, $object); // Note that $action and $object may have been modified by hook
print $hookmanager->resPrint;
// Action column
print '<td class="liste_titre maxwidthsearch">';
$searchpicto = $form->showFilterButtons();
print $searchpicto;
print '</td>';
print '</tr>'."\n";


// Fields title label
// --------------------------------------------------------------------
print '<tr class="liste_titre">';
foreach ($object->fields as $key => $val) {
	$cssforfield = (empty($val['css']) ? '' : $val['css']);
	if ($key == 'status') {
		$cssforfield .= ($cssforfield ? ' ' : '').'center';
	} elseif (in_array($val['type'], array('date', 'datetime', 'timestamp'))) {
		$cssforfield .= ($cssforfield ? ' ' : '').'center';
	} elseif (in_array($val['type'], array('timestamp'))) {
		$cssforfield .= ($cssforfield ? ' ' : '').'nowrap';
	} elseif (in_array($val['type'], array('double(24,8)', 'double(6,3)', 'integer', 'real', 'price')) && $val['label'] != 'TechnicalID') {
		$cssforfield .= ($cssforfield ? ' ' : '').'right';
	}
	if (!empty($arrayfields['t.'.$key]['checked'])) {
		print getTitleFieldOfList($arrayfields['t.'.$key]['label'], 0, $_SERVER['PHP_SELF'], 't.'.$key, '', $param, ($cssforfield ? 'class="'.$cssforfield.'"' : ''), $sortfield, $sortorder, ($cssforfield ? $cssforfield.' ' : ''))."\n";
	}
}
// Extra fields
include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_search_title.tpl.php';

// Hook fields
$parameters = array('arrayfields'=>$arrayfields, 'param'=>$param, 'sortfield'=>$sortfield, 'sortorder'=>$sortorder);
$reshook = $hookmanager->executeHooks('printFieldListTitle', $parameters, $object); // Note that $action and $object may have been modified by hook
print $hookmanager->resPrint;
// Action column
print getTitleFieldOfList($selectedfields, 0, $_SERVER["PHP_SELF"], '', '', '', '', $sortfield, $sortorder, 'center maxwidthsearch ')."\n";
print '</tr>'."\n";


// Detect if we need a fetch on each output line
$needToFetchEachLine = 0;
if (!empty($extrafields->attributes[$object->table_element]['computed']) && is_array($extrafields->attributes[$object->table_element]['computed']) && count($extrafields->attributes[$object->table_element]['computed']) > 0) {
	foreach ($extrafields->attributes[$object->table_element]['computed'] as $key => $val) {
		if (preg_match('/\$object/', $val)) {
			$needToFetchEachLine++; // There is at least one compute field that use $object
		}
	}
}


// Loop on record
// --------------------------------------------------------------------
$i = 0;
$totalarray = array();
while ($i < ($limit ? min($num, $limit) : $num)) {
	$obj = $db->fetch_object($resql);
	if (empty($obj)) {
		break; // Should not happen
	}

	// Store properties in $object
	$object->setVarsFromFetchObj($obj);

	// Show here line of result
	print '<tr class="oddeven">';
	foreach ($object->fields as $key => $val) {
		$cssforfield = (empty($val['css']) ? '' : $val['css']);
		if (in_array($val['type'], array('date', 'datetime', 'timestamp'))) {
			$cssforfield .= ($cssforfield ? ' ' : '').'center';
		} elseif ($key == 'status') {
			$cssforfield .= ($cssforfield ? ' ' : '').'center';
		}

		if (in_array($val['type'], array('timestamp'))) {
			$cssforfield .= ($cssforfield ? ' ' : '').'nowrap';
		} elseif ($key == 'ref') {
			$cssforfield .= ($cssforfield ? ' ' : '').'nowrap';
		}

		if (in_array($val['type'], array('double(24,8)', 'double(6,3)', 'integer', 'real', 'price')) && $key != 'status') {
			$cssforfield .= ($cssforfield ? ' ' : '').'right';
		}

		if (!empty($arrayfields['t.'.$key]['checked'])) {
			print '<td'.($cssforfield ? ' class="'.$cssforfield.'"' : '').'>';
			if ($key == 'status' || $key == 'active') {
				print $object->getLibStatut(5);
			} else {
				print $object->showOutputField($val, $key, $object->$key, '');
			}
			print '</td>';
			if (!$i) {
				$totalarray['nbfield']++;
			}
			if (!empty($val['isameasure'])) {
				if (!$i) {
					$totalarray['pos'][$totalarray['nbfield']] = 't.'.$key;
				}
				if (!isset($totalarray['val'])) {
					$totalarray['val'] = array();
				}
				if (!isset($totalarray['val']['t.'.$key])) {
					$totalarray['val']['t.'.$key] = 0;
				}
				$totalarray['val']['t.'.$key] += $object->$key;
			}
		}
	}
	// Extra fields
	include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_print_fields.tpl.php';
	// Fields from hook
	$parameters = array('arrayfields'=>$arrayfields, 'object'=>$object, 'obj'=>$obj, 'i'=>$i, 'totalarray'=>&$totalarray);
	$reshook = $hookmanager->executeHooks('printFieldListValue', $parameters, $object); // Note that $action and $object may have been modified by hook
	print $hookmanager->resPrint;
	// Action column
	print '<td class="nowrap center">';
	$url = $_SERVER["PHP_SELF"].'?id='.$obj->rowid;
	if ($limit) {
		$url .= '&limit='.urlencode($limit);
	}
	if ($page) {
		$url .= '&page='.urlencode($page);
	}
	if ($sortfield) {
		$url .= '&sortfield='.urlencode($sortfield);
	}
	if ($sortorder) {
		$url .= '&page='.urlencode($sortorder);
	}
	print '<a class="editfielda reposition marginrightonly marginleftonly" href="'.$url.'&action=edit&rowid='.$obj->rowid.'">'.img_edit().'</a>';
	//print ' &nbsp; ';
	print '<a class=" marginrightonly marginleftonly" href="'.$url.'&action=delete&token='.newToken().'">'.img_delete().'</a>  &nbsp; ';
	if ($massactionbutton || $massaction) {   // If we are in select mode (massactionbutton defined) or if we have already selected and sent an action ($massaction) defined
		$selected = 0;
		if (in_array($object->id, $arrayofselected)) {
			$selected = 1;
		}
		print '<input id="cb'.$object->id.'" class="flat checkforselect" type="checkbox" name="toselect[]" value="'.$object->rowid.'"'.($selected ? ' checked="checked"' : '').'>';
	}
	print '</td>';
	if (!$i) {
		$totalarray['nbfield']++;
	}

	print '</tr>'."\n";

	$i++;
}

// Show total line
include DOL_DOCUMENT_ROOT.'/core/tpl/list_print_total.tpl.php';


// If no record found
if ($num == 0) {
	$colspan = 1;
	foreach ($arrayfields as $key => $val) {
		if (!empty($val['checked'])) {
			$colspan++;
		}
	}
	print '<tr><td colspan="'.$colspan.'" class="opacitymedium">'.$langs->trans("NoRecordFound").'</td></tr>';
}


$db->free($resql);

$parameters = array('arrayfields'=>$arrayfields, 'sql'=>$sql);
$reshook = $hookmanager->executeHooks('printFieldListFooter', $parameters, $object); // Note that $action and $object may have been modified by hook
print $hookmanager->resPrint;

print '</table>'."\n";
print '</div>'."\n";

print '</form>'."\n";

if (in_array('builddoc', $arrayofmassactions) && ($nbtotalofrecords === '' || $nbtotalofrecords)) {
	$hidegeneratedfilelistifempty = 1;
	if ($massaction == 'builddoc' || $action == 'remove_file' || $show_files) {
		$hidegeneratedfilelistifempty = 0;
	}

	require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
	$formfile = new FormFile($db);

	// Show list of available documents
	$urlsource = $_SERVER['PHP_SELF'].'?sortfield='.$sortfield.'&sortorder='.$sortorder;
	$urlsource .= str_replace('&amp;', '&', $param);

	$filedir = $diroutputmassaction;
	$genallowed = $permissiontoread;
	$delallowed = $permissiontoadd;

	print $formfile->showdocuments('massfilesarea_emailsenderprofile', '', $filedir, $urlsource, 0, $delallowed, '', 1, 1, 0, 48, 1, $param, $title, '', '', '', null, $hidegeneratedfilelistifempty);
}

print dol_get_fiche_end();

// End of page
llxFooter();
$db->close();
