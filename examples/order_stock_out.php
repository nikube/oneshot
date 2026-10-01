<?php
/**
 * Catch-up: take out of stock the orders already classified delivered, with
 * no invoice, that never had a stock movement (closed before SuperStock's
 * "stock out on delivered orders" was live).
 *
 * Args (any order, all optional):
 *   2026-10-01   only orders with date_commande on or after this day (default below)
 *   wh=3         warehouse id (default: SuperStock setting SUPERSTOCK_ORDER_WAREHOUSE)
 *   go           apply; without it, dry run: list the orders and what would move
 *
 * The movements are made by SuperStock's own trigger, so they are exactly
 * the ones a "Classer livrée" would have made: reopening such an order puts
 * the stock back, and an order that already moved is skipped (safe to re-run).
 */
$since = '2026-10-01'; // default first order day

if (!defined('DOL_DOCUMENT_ROOT')) { // CLI fallback
	define('NOSESSION', 1);
	require '/var/www/html/master.inc.php';
	$user = new User($db);
	$user->fetch(0, 'admin');
	$user->loadRights();
	$args = array_slice($argv, 1);
}
require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';

$go = false;
foreach ($args as $a) {
	if ($a === 'go') {
		$go = true;
	} elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $a)) {
		$since = $a;
	} elseif (preg_match('/^wh=(\d+)$/', $a, $m)) {
		$conf->global->SUPERSTOCK_ORDER_WAREHOUSE = (int) $m[1]; // this run only
	} else {
		echo "Argument inconnu : $a\nUsage : [AAAA-MM-JJ] [wh=ID_ENTREPOT] [go]\n";
		return;
	}
}

if (!dol_include_once('/superstock/core/triggers/interface_99_modSuperStock_SuperStockOrder.class.php') || !isModEnabled('superstock')) {
	echo "SuperStock >= 0.5.0 doit être installé et activé.\n";
	return;
}
$wh = getDolGlobalInt('SUPERSTOCK_ORDER_WAREHOUSE');
$whLabel = '';
if ($wh > 0) {
	$r = $db->query("SELECT ref FROM ".MAIN_DB_PREFIX."entrepot WHERE rowid = ".((int) $wh)." AND entity IN (".getEntity('stock').")");
	$whLabel = ($r && ($o = $db->fetch_object($r))) ? $o->ref : '';
}
if ($whLabel === '') {
	echo "Entrepôt introuvable (id $wh) : règle l'entrepôt de sortie dans SuperStock ou passe wh=ID.\n";
	return;
}
foreach (array('BILL', 'VALIDATE_ORDER', 'SHIPMENT', 'SHIPMENT_CLOSE') as $mode) {
	if (getDolGlobalString('STOCK_CALCULATE_ON_'.$mode)) {
		echo "Règle native STOCK_CALCULATE_ON_$mode active : SuperStock ne sort rien dans ce mode. Rien fait.\n";
		return;
	}
}

// Delivered, no linked invoice, ordered since $since; "moved" = already has a net stock movement
$sql = "SELECT c.rowid, EXISTS (SELECT 1 FROM ".MAIN_DB_PREFIX."stock_mouvement m";
$sql .= " WHERE m.fk_origin = c.rowid AND m.origintype = 'commande'";
$sql .= " GROUP BY m.fk_product, m.fk_entrepot, m.batch HAVING ABS(SUM(m.value)) > 0.000001) AS moved";
$sql .= " FROM ".MAIN_DB_PREFIX."commande c";
$sql .= " WHERE c.fk_statut = ".Commande::STATUS_CLOSED." AND c.entity IN (".getEntity('commande').")";
$sql .= " AND c.date_commande >= '".$db->escape($since)."'";
$sql .= " AND NOT EXISTS (SELECT 1 FROM ".MAIN_DB_PREFIX."element_element ee";
$sql .= " WHERE ee.fk_source = c.rowid AND ee.sourcetype = 'commande' AND ee.targettype = 'facture')";
$sql .= " ORDER BY c.rowid";
$res = $db->query($sql);
if (!$res) { echo "SQL error: ".$db->lasterror()."\n"; return; }
echo ($go ? 'APPLY' : 'DRY RUN')." - ".$db->num_rows($res)." commande(s) livrée(s) sans facture depuis le $since, sortie de l'entrepôt $whLabel (id $wh)\n\n";

$trigger = new InterfaceSuperStockOrder($db);
$count = array('sorties' => 0, 'déjà sorties' => 0, 'sans produit' => 0, 'erreurs' => 0);
while ($row = $db->fetch_object($res)) {
	$c = new Commande($db);
	$c->fetch($row->rowid);
	$c->fetch_thirdparty();
	$lines = array_filter($c->lines, function ($l) { return !empty($l->fk_product) && $l->product_type == 0 && !empty($l->qty); });
	$desc = implode(', ', array_map(function ($l) { return $l->product_ref.' x'.$l->qty; }, $lines));
	echo $c->ref.' ('.dol_print_date($c->date_commande, 'day').', '.$c->thirdparty->name.') : ';

	if ($row->moved) { echo "déjà sortie du stock, ignorée\n"; $count['déjà sorties']++; continue; }
	if (!$lines) { echo "aucune ligne produit, rien à sortir\n"; $count['sans produit']++; continue; }
	echo $desc;
	if (!$go) { echo "\n"; $count['sorties']++; continue; }

	$db->begin();
	$trigger->errors = array();
	$result = $trigger->runTrigger('ORDER_CLOSE', $c, $user, $langs, $conf);
	if ($result > 0) {
		$db->commit();
		echo " : FAIT\n";
		$count['sorties']++;
	} elseif ($result == 0) { // moved meanwhile
		$db->rollback();
		echo " : déjà sortie du stock, ignorée\n";
		$count['déjà sorties']++;
	} else {
		$db->rollback();
		echo " : ERREUR ".implode(', ', array_filter($trigger->errors))."\n";
		$count['erreurs']++;
	}
}
echo "\n".implode(', ', array_map(function ($k, $v) { return "$v $k"; }, array_keys($count), $count))."\n";
if (!$go) { echo "Rien n'a été modifié. Ajoute \"go\" aux arguments pour appliquer.\n"; }
