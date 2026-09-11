<?php
/* Copyright (C) 2026 Anatole Conseil
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    admin/index.php
 * \ingroup oneshot
 * \brief   List, store and run one-off scripts
 */

$res = 0;
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res && file_exists("../../../../main.inc.php")) {
	$res = @include "../../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

global $conf, $db, $langs, $user;

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';

$langs->loadLangs(array('oneshot@oneshot', 'admin'));

if (!$user->admin) {
	accessforbidden();
}

$dir = DOL_DATA_ROOT.'/oneshot';
dol_mkdir($dir);

$action = GETPOST('action', 'aZ09');
$name = GETPOST('name', 'alphanohtml');
$output = '';
$duration = 0;
$ran = false;

// Script name: flat file, php extension, no path tricks
$validName = (bool) preg_match('/^[A-Za-z0-9_.-]+\.php$/', $name) && strpos($name, '..') === false;
$path = $dir.'/'.$name;

if ($action == 'save' && $validName) {
	if (file_put_contents($path, GETPOST('content', 'none')) === false) {
		setEventMessages($langs->trans('OneshotSaveError', $name), null, 'errors');
	} else {
		setEventMessages($langs->trans('OneshotSaved', $name), null);
	}
} elseif ($action == 'delete' && $validName && file_exists($path)) {
	unlink($path);
	setEventMessages($langs->trans('OneshotDeleted', $name), null);
} elseif ($action == 'run' && $validName && file_exists($path)) {
	$args = preg_split('/\s+/', trim(GETPOST('args', 'alphanohtml')), -1, PREG_SPLIT_NO_EMPTY);
	set_time_limit(0);
	dol_syslog('oneshot: '.$user->login.' runs '.$name.' '.implode(' ', $args), LOG_NOTICE);

	// Closure keeps the script out of this page's scope; it still sees $db, $user, $conf, $langs, $args
	$runner = static function ($path, $args) use ($db, $user, $conf, $langs) {
		include $path;
	};
	$start = microtime(true);
	ob_start();
	try {
		$runner($path, $args);
	} catch (Throwable $e) {
		print "\n[".get_class($e).'] '.$e->getMessage()."\n".$e->getTraceAsString();
	}
	$output = ob_get_clean();
	$duration = microtime(true) - $start;
	$ran = true;
}

llxHeader('', $langs->trans('OneshotTitle'));

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($langs->trans('OneshotTitle'), $linkback, 'technic');
print '<span class="opacitymedium">'.$langs->trans('OneshotIntro', $dir).'</span><br><br>';

if ($ran) {
	print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
	print '<tr class="liste_titre"><td>'.$langs->trans('OneshotOutput', $name, round($duration, 2)).'</td></tr>';
	print '<tr class="oddeven"><td><pre style="white-space:pre-wrap;max-height:60vh;overflow:auto">'.dol_escape_htmltag($output === '' ? $langs->trans('OneshotNoOutput') : $output, 0, 1).'</pre></td></tr>';
	print '</table></div><br>';
}

// Script list
$scripts = array_map('basename', glob($dir.'/*.php') ?: array());
sort($scripts);

print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
print '<tr class="liste_titre"><td>'.$langs->trans('OneshotScript').'</td><td>'.$langs->trans('OneshotArgs').'</td><td class="right"></td></tr>';
if (empty($scripts)) {
	print '<tr class="oddeven"><td colspan="3"><span class="opacitymedium">'.$langs->trans('OneshotNoScript').'</span></td></tr>';
}
foreach ($scripts as $s) {
	print '<tr class="oddeven">';
	print '<form method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="name" value="'.dol_escape_htmltag($s).'">';
	print '<td><strong>'.dol_escape_htmltag($s).'</strong><br><span class="opacitymedium small">'.dol_print_date(filemtime($dir.'/'.$s), 'dayhour').' · '.dol_print_size(filesize($dir.'/'.$s)).'</span></td>';
	print '<td><input type="text" name="args" class="minwidth300" placeholder="'.$langs->trans('OneshotArgsPlaceholder').'"></td>';
	print '<td class="right nowraponall">';
	print '<a class="button small" href="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'?action=edit&name='.urlencode($s).'">'.$langs->trans('Edit').'</a> ';
	print '<button type="submit" name="action" value="run" class="button small">'.$langs->trans('OneshotRun').'</button> ';
	print '<button type="submit" name="action" value="delete" class="button small butActionDelete" onclick="return confirm(\''.dol_escape_js($langs->trans('OneshotConfirmDelete', $s)).'\')">'.$langs->trans('Delete').'</button>';
	print '</td></form></tr>';
}
print '</table></div><br>';

// Add / replace a script
$editName = ($action == 'edit' && $validName && file_exists($path)) ? $name : '';
print '<form method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="save">';
print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
print '<tr class="liste_titre"><td>'.$langs->trans('OneshotAdd').'</td></tr>';
print '<tr class="oddeven"><td>';
print '<input type="text" name="name" class="minwidth300" required pattern="[A-Za-z0-9_.-]+\.php" placeholder="cancel_orders.php" value="'.dol_escape_htmltag($editName).'"><br>';
print '<textarea name="content" rows="18" class="centpercent" style="font-family:monospace" required>';
print dol_escape_htmltag($editName ? file_get_contents($path) : "<?php\n// \$db, \$user, \$conf, \$langs and \$args (array) are available\n", 0, 1);
print '</textarea><br>';
print '<input type="submit" class="button" value="'.$langs->trans('Save').'">';
print '</td></tr></table></div>';
print '</form>';

llxFooter();
$db->close();
