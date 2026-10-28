<?php
/**
 * Remove given products from open customer orders, keep the other lines.
 *
 * Args: product refs (space or comma separated), plus "go" to apply.
 *   "APA-SDF, BCL-PY2"      -> dry run: list the orders and what would happen
 *   "APA-SDF BCL-PY2 go"    -> apply
 *
 * Selected orders: draft (0) or validated (1), ordered before $before,
 * containing at least one of the products. Validated orders go draft ->
 * lines deleted -> validated again (same ref). If only target lines exist,
 * the whole order is cancelled instead (drafts are left alone).
 */
$before = '2026-07-01'; // only orders with date_commande before this day

if (!defined('DOL_DOCUMENT_ROOT')) { // CLI fallback
	define('NOSESSION', 1);
	require '/var/www/html/master.inc.php';
	$user = new User($db);
	$user->fetch(0, 'admin');
	$user->loadRights();
	$args = array_slice($argv, 1);
}
require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';

$words = preg_split('/[\s,]+/', implode(' ', $args), -1, PREG_SPLIT_NO_EMPTY);
$go = in_array('go', $words, true);
$products = array_values(array_diff($words, array('go')));
if (!$products) { echo "Usage : REF1 REF2 ... [go]\n"; return; }

// Resolve refs to product ids once: SQL ref matching is case-insensitive and PHP string compare is not
$in = implode(',', array_map(function ($p) use ($db) { return "'".$db->escape($p)."'"; }, $products));
$ids = array();
$res = $db->query("SELECT rowid, ref FROM ".MAIN_DB_PREFIX."product WHERE ref IN (".$in.") AND entity IN (".getEntity('product').")");
while ($res && ($o = $db->fetch_object($res))) { $ids[(int) $o->rowid] = $o->ref; }
if (!$ids) { echo "Aucun produit trouvé pour ".implode('/', $products)."\n"; return; }

$sql = "SELECT DISTINCT c.rowid FROM ".MAIN_DB_PREFIX."commande c";
$sql .= " JOIN ".MAIN_DB_PREFIX."commandedet d ON d.fk_commande = c.rowid";
$sql .= " WHERE d.fk_product IN (".implode(',', array_keys($ids)).") AND c.fk_statut IN (0, 1) AND c.entity IN (".getEntity('commande').")";
$sql .= " AND c.date_commande < '".$db->escape($before)."' ORDER BY c.rowid";
$res = $db->query($sql);
if (!$res) { echo "SQL error: ".$db->lasterror()."\n"; return; }
echo ($go ? 'APPLY' : 'DRY RUN')." - ".$db->num_rows($res)." commande(s) avant $before contenant ".implode('/', $products)."\n\n";

while ($row = $db->fetch_object($res)) {
	$c = new Commande($db);
	if ($c->fetch($row->rowid) <= 0) { echo "#$row->rowid : fetch impossible, ignorée\n\n"; continue; }
	$c->fetch_thirdparty();
	$targets = array_filter($c->lines, function ($l) use ($ids) { return isset($ids[(int) $l->fk_product]); });
	$others = array_filter($c->lines, function ($l) use ($ids) { return !isset($ids[(int) $l->fk_product]); });
	if (!$targets) { continue; }
	$wasValidated = ($c->statut == Commande::STATUS_VALIDATED);
	$desc = implode(', ', array_map(function ($l) { return $l->product_ref.' x'.$l->qty; }, $targets));
	$others_desc = $others ? implode(', ', array_map(function ($l) { return ($l->product_ref ?: 'ligne libre').' x'.$l->qty; }, $others)) : 'rien d\'autre';

	if (!$others) {
		$what = $wasValidated ? 'annuler la commande entière' : 'brouillon sans autre ligne, laissée telle quelle';
	} else {
		$what = 'supprimer '.count($targets).' ligne(s)'.($wasValidated ? ', repasser brouillon puis revalider' : '');
	}
	echo $c->ref.' ('.dol_print_date($c->date_commande, 'day').', statut '.$c->statut.', '.$c->thirdparty->name.")\n";
	echo "   cible : $desc\n   garde : $others_desc\n   -> $what";
	if (!$go) { echo "\n\n"; continue; }

	$db->begin();
	$ok = true;
	if (!$others) {
		if ($wasValidated) { $ok = (version_compare(DOL_VERSION, '23.0', '>=') ? $c->cancel($user) : $c->cancel()) > 0; } // $user first since Dolibarr 23
	} else {
		if ($wasValidated) { $ok = $c->setDraft($user) > 0; }
		foreach ($targets as $l) {
			if ($ok) { $ok = $c->deleteLine($user, $l->id) > 0; }
		}
		if ($ok && $wasValidated) { $c->fetch_lines(); $ok = $c->valid($user) > 0; } // deleteLine() leaves the deleted lines in memory
	}
	$ok ? $db->commit() : $db->rollback();
	echo $ok ? " : FAIT\n\n" : " : ERREUR ".$c->error."\n\n";
}
if (!$go) { echo "Rien n'a été modifié. Ajoute \"go\" aux arguments pour appliquer.\n"; }
