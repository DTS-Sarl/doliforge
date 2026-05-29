<?php
/* Copyright (C) 2024 DTS SARL
 * Triggers du module monmodule
 *
 * Nommage strict : interface_NN_modMonModule_NomTrigger.class.php
 * NN = priorité 00-99 (99 = exécuté en dernier)
 *
 * Activé par : $this->module_parts['triggers'] = 1  (dans le descripteur)
 */

require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';

class InterfaceMonModuleTrigger extends DolibarrTriggers
{
	public function __construct($db)
	{
		$this->db          = $db;
		$this->name        = preg_replace('/^Interface/i', '', get_class($this));
		$this->family      = 'monmodule';
		$this->description = 'Triggers du module MonModule';
		$this->version     = '1.0.0';
		$this->picto       = 'monobjet@monmodule';
	}

	/**
	 * Réagir aux événements Dolibarr
	 *
	 * Retours : 0 = non concerné/non bloquant, > 0 = traité, < 0 = erreur bloquante
	 */
	public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
	{
		// INDISPENSABLE : vérifier que le module est activé
		// Le trigger est chargé même si le module est désactivé
		if (!isModEnabled('monmodule')) return 0;

		switch ($action) {

			// ---- Exemple : réagir à la validation d'une facture ----
			case 'BILL_VALIDATE':
				return $this->onBillValidate($object, $user);

			// ---- Exemple : réagir à la création d'un tiers ----
			case 'COMPANY_CREATE':
				return $this->onCompanyCreate($object, $user);

			// Ajouter d'autres cas ici
			// Référence complète des événements : references/hooks-et-triggers.md
		}

		return 0;
	}

	// ============================================================
	// Handlers privés — un par événement
	// ============================================================

	/**
	 * Traitement non-bloquant lors de la validation d'une facture
	 */
	private function onBillValidate($object, $user)
	{
		try {
			dol_syslog(
				'MonModule::trigger BILL_VALIDATE facture id='.$object->id.' ref='.$object->ref,
				LOG_DEBUG
			);

			// TODO : logique métier ici
			// Ex : créer un enregistrement lié, envoyer une notification, etc.

		} catch (Throwable $e) {
			// Logger sans jamais bloquer l'opération principale
			dol_syslog(
				'MonModule::trigger BILL_VALIDATE error: '.$e->getMessage(),
				LOG_ERR
			);
		}

		return 0; // Toujours 0 (non-bloquant)
	}

	/**
	 * Traitement non-bloquant lors de la création d'un tiers
	 */
	private function onCompanyCreate($object, $user)
	{
		try {
			dol_syslog(
				'MonModule::trigger COMPANY_CREATE tiers id='.$object->id,
				LOG_DEBUG
			);

			// TODO : logique métier ici

		} catch (Throwable $e) {
			dol_syslog(
				'MonModule::trigger COMPANY_CREATE error: '.$e->getMessage(),
				LOG_ERR
			);
		}

		return 0;
	}
}
