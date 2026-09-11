<?php
/**
 * Cancel validated customer orders by ref.
 * Oneshot: paste as cancel_orders.php, args = "CO2601-0012 CO2601-0034"
 * CLI:     php cancel_orders.php CO2601-0012 CO2601-0034
 */
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
	if ($c->statut != Commande::STATUS_VALIDATED) { echo "$ref: statut {$c->statut}, ignorée\n"; continue; }
	$db->begin();
	$r = $c->cancel($user); // cancel($user, $idwarehouse) if stock is decremented at order validation
	$r > 0 ? $db->commit() : $db->rollback();
	echo "$ref: ".($r > 0 ? 'annulée' : 'ERREUR '.$c->error)."\n";
}
