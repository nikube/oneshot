<?php
/**
 * Remove the lines of given products from customer orders, keep the rest.
 * Order goes draft -> lines deleted -> validated again (same ref). If only
 * target lines remain, the whole order is cancelled instead.
 * Only draft (0) and validated (1) orders are touched.
 *
 * Oneshot: args = "CO2601-0012 CO2601-0034"
 * CLI:     php remove_products.php CO2601-0012 CO2601-0034
 */
$products = array('APA-SDF', 'BCL-PY2'); // <- product refs to remove

if (!defined('DOL_DOCUMENT_ROOT')) { // CLI fallback
	define('NOSESSION', 1);
	require '/var/www/html/master.inc.php';
	$user = new User($db);
	$user->fetch(0, 'admin');
	$user->loadRights();
	$args = array_slice($argv, 1);
}
require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';

foreach ($args as $ref) {
	$c = new Commande($db);
	if ($c->fetch(0, $ref) <= 0) { echo "$ref: introuvable\n"; continue; }
	if (!in_array($c->statut, array(Commande::STATUS_DRAFT, Commande::STATUS_VALIDATED))) { echo "$ref: statut {$c->statut}, ignorée\n"; continue; }
	$c->fetch_lines();
	$targets = array_filter($c->lines, function ($l) use ($products) { return in_array($l->product_ref, $products); });
	if (!$targets) { echo "$ref: aucune ligne ".implode('/', $products)."\n"; continue; }

	$wasValidated = ($c->statut == Commande::STATUS_VALIDATED);
	$db->begin();
	$ok = true;
	if (count($targets) == count($c->lines)) {
		$ok = $wasValidated ? $c->cancel($user) > 0 : true; // draft with only target lines: left as is
		$what = $wasValidated ? 'annulée (que ces articles)' : 'brouillon, que ces articles, non touchée';
	} else {
		if ($wasValidated) { $ok = $c->setDraft($user) > 0; }
		foreach ($targets as $l) {
			if ($ok) { $ok = $c->deleteLine($user, $l->id) > 0; }
		}
		if ($ok && $wasValidated) { $ok = $c->valid($user) > 0; }
		$what = count($targets).' ligne(s) supprimée(s)'.($wasValidated ? ', revalidée' : '');
	}
	$ok ? $db->commit() : $db->rollback();
	echo "$ref: ".($ok ? $what : 'ERREUR '.$c->error)."\n";
}
