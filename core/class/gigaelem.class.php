<?php

/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

// ini_set('display_errors',1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

/*
 * merci aux tutoriels : https://forum.jeedom.com/viewtopic.php?t=37630
 */

/* * ***************************Includes********************************* */
require_once __DIR__  . '/../../../../core/php/core.inc.php';

if (!class_exists('GigasetElementsApiClient')) {
	require_once dirname(__FILE__) . '/../../3rdparty/GigasetElementsApiClient.php';
}


class gigaelem extends eqLogic {
    /*     * *************************Attributs****************************** */

    
  /*
   * Permet de définir les possibilités de personnalisation du widget (en cas d'utilisation de la fonction 'toHtml' par exemple)
   * Tableau multidimensionnel - exemple: array('custom' => true, 'custom::layout' => false)
	public static $_widgetPossibility = array();
	*/
	
	private static $_client = null;
	public static $_widgetPossibility = array('custom' => true); // permet de customiser le widget 
	public static $_status_info = array();

    /*     * ***********************Methode static*************************** */


    public static function GetApiLog() {
		foreach (self::$_client->ge_log as $line) {
		    log::add('gigaelem', 'debug', "<GEApiClient> - ".$line);
		}
		self::$_client->ge_log = [];
	}
    /*
     * Connexion à l'API Gigaset 
	 */
	public static function getClient() {
		if (self::$_client == null) {
			 self::$_client =  new GigasetElementsApiClient(array(
				'username' => config::byKey('username', 'gigaelem'),
				'password' => config::byKey('password', 'gigaelem')
			 ));
		}
		try	{
				self::$_client->getUserToken();
			}
		catch(GEClientException $ex) {
				$error_msg = "<getClient> - An error happened  while trying to retrieve client token : code [".$ex->getCode()."] - " . $ex->getMessage() . "\n";
				log::add('gigaelem', 'error', $error_msg);
				return false;
			}
		//
		self::GetApiLog();
		
		//
		log::add('gigaelem', 'debug', "<getClient> - client connecté");
        return self::$_client;
	}

	/*
	 * Recupération du status Gigaset Elements
	 */
	public static function getGeStatus() {
		log::add('gigaelem', 'debug', "<getGeStatus> - recuperation status");
		$client = self::getClient();
		if (!$client) {
			return '{"system_health":"red","status_msg_id":"no_connection"}';
		} else {
			$_health =  $client->getHealth(true); // provide detailed health info
			self::GetApiLog();
			return $_health;
		}
	}

	/*
	 * Recupération de l'état de l'alarme
	 */
	public static function getAlarmMode() {
		log::add('gigaelem', 'debug', "<getAlarmMode> - recuperation mode alarme");
		$client = self::getClient();
		if (!$client) return false;
		return $client->getFromBase("modes");
		// active_mode": "home",
        // requestedMode": "home",
        // modeTransitionInProgress": false
	}

	/*
	 * Changement de modes
	 */
	public static function setAlarmMode($mode) {
		//
		log::add('gigaelem', 'debug', "<setAlarmMode> - recuperation mode actif");
		$client = self::getClient();
		if (!$client) return false;
		$previous = $client->getFromBase("modes")["active_mode"];
		//
		log::add('gigaelem', 'debug', "<setAlarmMode> - passage vers mode [".$mode."]");
		return $client->switchMode($mode);
    }	



	
    /*     * ***********************Cron *************************** */

	
    /*
     * Fonction exécutée automatiquement toutes les minutes par Jeedom
      public static function cron() {

      }
     */

	
    /*
     * Fonction exécutée automatiquement toutes les 5 minutes par Jeedom
	 */
	public static function cron5() {
		//
		log::add('gigaelem', 'debug', "<cron> - mise à jour");
		if ($_eqLogic_id == null) { // La fonction n’a pas d’argument donc on recherche tous les équipements du plugin
			$eqLogics = self::byType('gigaelem', true);
		} else { // La fonction a l’argument id(unique) d’un équipement(eqLogic)
			$eqLogics = array(self::byId($_eqLogic_id));
		}		  
		//
		foreach ($eqLogics as $element) {//parcours tous les équipements selectionnes
			if ($element->getIsEnable() == 1) {//vérifie que l'équipement est actif
				$cmd = $element->getCmd(null, 'refresh');//retourne la commande "refresh si elle existe
				if (!is_object($cmd)) {//Si la commande n'existe pas
					continue; //continue la boucle
				}
				log::add('gigaelem', 'debug', "<cron> - refresh equipement ".$element->getName());
				$cmd->execCmd(); // la commande existe on la lance
			}
		}
	}

    /*
     * Fonction exécutée automatiquement toutes les heures par Jeedom
      public static function cronHourly() {

      }
     */

    /*
     * Fonction exécutée automatiquement tous les jours par Jeedom
      public static function cronDaily() {

      }
     */



    /*     * *********************Méthodes d'instance************************* */

    public function preInsert() {
        
    }

    public function postInsert() {
        
    }

    public function preSave() {
        
    }

    public function postSave() {
		// création des commandes
		
		// status
		$status = $this->getCmd(null, 'status');
		if (!is_object($status)) {
			$status = new gigaelemCmd();
			$status->setName(__('Status', __FILE__));
		}
		$status->setLogicalId('status');
		$status->setEqLogic_id($this->getId());
		$status->setType('info');
		// $status->setTemplate('dashboard','default');//template pour le dashboard
		// $status->setDisplay("showNameOndashboard",0);
		$status->setSubType('string');
		$status->save();	

		// status message
		$status_message = $this->getCmd(null, 'status_message');
		if (!is_object($status_message)) {
			$status_message = new gigaelemCmd();
			$status_message->setName(__('Message', __FILE__));
		}
		$status_message->setLogicalId('status_message');
		$status_message->setEqLogic_id($this->getId());
		$status_message->setType('info');
		$status_message->setSubType('string');
		$status_message->save();	

		// mode
		$mode = $this->getCmd(null, 'mode');
		if (!is_object($mode)) {
			$mode = new gigaelemCmd();
			$mode->setName(__('Mode', __FILE__));
		}
		$mode->setLogicalId('mode');
		$mode->setEqLogic_id($this->getId());
		$mode->setType('info');
		$mode->setSubType('string');
		$mode->save();	
		
		$refresh = $this->getCmd(null, 'refresh');
		if (!is_object($refresh)) {
			$refresh = new gigaelemCmd();
			$refresh->setName(__('Rafraichir', __FILE__));
		}
		$refresh->setEqLogic_id($this->getId());
		$refresh->setLogicalId('refresh');
		$refresh->setType('action');
		$refresh->setSubType('other');
		$refresh->save();        

		// Alarm modes
		$mode_home = $this->getCmd(null, 'mode_home');
		if (!is_object($mode_home)) {
			$mode_home = new gigaelemCmd();
			$mode_home->setName(__('Présence', __FILE__));
		}
		$mode_home->setEqLogic_id($this->getId());
		$mode_home->setLogicalId('mode_home');
		$mode_home->setType('action');
		$mode_home->setSubType('other');
		$mode_home->save();        

		$mode_away = $this->getCmd(null, 'mode_away');
		if (!is_object($mode_away)) {
			$mode_away = new gigaelemCmd();
			$mode_away->setName(__('Absence', __FILE__));
		}
		$mode_away->setEqLogic_id($this->getId());
		$mode_away->setLogicalId('mode_away');
		$mode_away->setType('action');
		$mode_away->setSubType('other');
		$mode_away->save();        

		$mode_night = $this->getCmd(null, 'mode_night');
		if (!is_object($mode_night)) {
			$mode_night = new gigaelemCmd();
			$mode_night->setName(__('Nuit', __FILE__));
		}
		$mode_night->setEqLogic_id($this->getId());
		$mode_night->setLogicalId('mode_night');
		$mode_night->setType('action');
		$mode_night->setSubType('other');
		$mode_night->save();        

		$mode_custom = $this->getCmd(null, 'mode_custom');
		if (!is_object($mode_custom)) {
			$mode_custom = new gigaelemCmd();
			$mode_custom->setName(__('Personnalisé', __FILE__));
		}
		$mode_custom->setEqLogic_id($this->getId());
		$mode_custom->setLogicalId('mode_custom');
		$mode_custom->setType('action');
		$mode_custom->setSubType('other');
		$mode_custom->save();        
    }

    public function preUpdate() {
        
    }

    public function postUpdate() {
		self::cron5($this->getId());// lance la fonction cron5 avec l’id de l’eqLogic
    }

    public function preRemove() {
        
    }

    public function postRemove() {
        
    }

	public function modeLabel($mode) {
		//
		$_mode_label = array(
			'home' => 'Présence',
			'away' => 'Absent',
			'night' => 'Nuit',
			'custom' => 'Personnalisé',
			'disconnected' => 'Déconnecté'
		);
		$_modechg = strpos($mode,">");
		if ($_modechg) {
			$modes = explode(">",$mode);
			return $_mode_label[$modes[0]]." > ".$_mode_label[$modes[1]];
		}
		else return $_mode_label[$mode];
	}
	public function toHtml($_version = 'dashboard') {
		log::add('gigaelem', 'debug', "<toHtml> - appel version [".$_version."] ");
		// messages propres (en attendant l'internationalisation qui marche)

		$_status_label = array(
			'no_connection' => 'Connexion impossible',
			'sensor_not_calibrated' => 'Capteur non calibré',
			'endnode_offline' => 'Appareil Hors-Ligne',
			'endnode_battery_is_low' => 'Appareil : batterie faible',
			'good' => 'All is good'
		);
		// permet l'activation de la tuile custom ou non selon configuration
      	if ($this->getConfiguration('eq_widget','') == "core"){
          	self::$_widgetPossibility = array('custom' => 'layout');
          	return eqLogic::toHtml($_version);
        }
      	//récupère les informations de l'équipement
		$replace = $this->preToHtml($_version); 
		if (!is_array($replace)) {
			return $replace;
		}

		// permet de cacher la tuile si elle n'est pas activée dans la configuration
      	$version = jeedom::versionAlias($_version);
		if ($this->getDisplay('hideOn' . $version) == 1) {
			return '';
		}

		$this->emptyCacheWidget(); //vide le cache. Pratique pour le développement

      	$_eqType = $this->getConfiguration('eq_type'); // type de widget, pour adapter le layout
		
		// liste les equipements
		// $eqLog_id = $this->getLogicalId();
		// list($roomid, $homeid) = explode('|', $this->getLogicalId());
		
		// application des parametres dans le HTML - ici commande HOME
		// infos
		$current_mode = $this->getCmd(null, 'mode');
		$replace['#mode#'] = $current_mode->execCmd();
		$replace['#mode_label#'] = $this->modeLabel($replace['#mode#']);
		$current_status = $this->getCmd(null, 'status');
		$replace['#status#'] = $current_status->execCmd();
		$status_message = $this->getCmd(null, 'status_message');
		$replace['#status_message#'] = $_status_label[$status_message->execCmd()];
		// actions
		$refresh = $this->getCmd(null, 'refresh');
		$replace['#refresh_id#'] = $refresh->getId();
		$home = $this->getCmd(null, 'mode_home');
		$replace['#home_id#'] = $home->getId();
		$away = $this->getCmd(null, 'mode_away');
		$replace['#away_id#'] = $away->getId();
		$night = $this->getCmd(null, 'mode_night');
		$replace['#night_id#'] = $night->getId();
		$custom = $this->getCmd(null, 'mode_custom');
		$replace['#custom_id#'] = $custom->getId();
		//
		// for TEST $replace['#debug_frame#'] = implode("|",array_keys($replace));
		//  retourne le template qui se nomme eqlogic pour le widget	  
		$template = getTemplate('core', $version, 'eqLogic', 'gigaelem');
		return $this->postToHtml($_version, template_replace($replace, $template)); 
   
	}

    /*
     * Non obligatoire mais ca permet de déclencher une action après modification de variable de configuration
    public static function postConfig_<Variable>() {
    }
     */

    /*
     * Non obligatoire mais ca permet de déclencher une action avant modification de variable de configuration
    public static function preConfig_<Variable>() {
    }
     */

    /*     * **********************Getteur Setteur*************************** */
}

class gigaelemCmd extends cmd {
    /*     * *************************Attributs****************************** */


    /*     * ***********************Methode static*************************** */


    /*     * *********************Methode d'instance************************* */

    /*
     * Non obligatoire permet de demander de ne pas supprimer les commandes même si elles ne sont pas dans la nouvelle configuration de l'équipement envoyé en JS
      public function dontRemoveCmd() {
      return true;
      }
     */

    public function execute($_options = array()) {
		$eqlogic = $this->getEqLogic(); //récupère l'éqlogic de la commande $this
		log::add('gigaelem', 'debug', "<execute> - commande : ".$this->getLogicalId());
		// detailed info
		$eqlogic->_status_info = $eqlogic->getGeStatus();
		if (!isset($eqlogic->_status_info['status_msg_id']))
			$eqlogic->_status_info['status_msg_id'] = 'good'; // quand tout va bien, pas de message...
		// 
		$new_mode = "";
		//
		$modeChange = false;
		$_modeinfo = $eqlogic->getAlarmMode();
		if (!$_modeinfo) {
			$current_mode = "disconnected";
			$target_mode = "disconnected";
			$mode_in_progress = "disconnected";
		} else {
			$current_mode = $_modeinfo['active_mode'];
			$target_mode = $_modeinfo['requestedMode'];
			$mode_in_progress = $_modeinfo['modeTransitionInProgress'];
		}
		//
		switch ($this->getLogicalId()) {	//vérifie le logicalid de la commande 			
			// mode actuel
			case 'refresh':
				$eqlogic->checkAndUpdateCmd('status', $eqlogic->_status_info['system_health']);
				$eqlogic->checkAndUpdateCmd('status_message', $eqlogic->_status_info['status_msg_id']);
				$eqlogic->checkAndUpdateCmd('mode', $current_mode);
				$eqlogic->refreshWidget();
				break;
			// changement de mode
			case 'mode_home':
				$new_mode = "home";
				if ($new_mode!=$current_mode) $modeChange = true;
				break;
			case 'mode_away':
				$new_mode = "away";
				if ($new_mode!=$current_mode) $modeChange = true;
				break;
			case 'mode_night':
				$new_mode = "night";
				if ($new_mode!=$current_mode) $modeChange = true;
				break;
			case 'mode_custom':
				$new_mode = "custom";
				if ($new_mode!=$current_mode) $modeChange = true;
				break;
			// cas par défaut
			default:
				log::add('gigaelem','error','<execute> - Commande invalide : ['.$this->getLogicalId().']');
				break;
		}
		// case of mode change
		if ($new_mode==$current_mode)
			log::add('gigaelem', 'debug', "<execute> - systeme déja en mode [".$new_mode."]");
		//
		if ($modeChange) {
			log::add('gigaelem', 'info', "<execute> - passage de [".$current_mode."] vers [".$new_mode."]");
			$info = $eqlogic->setAlarmMode($new_mode);
			if (!$info) { // probleme de mise à jour
				// erreur
				log::add('gigaelem', 'error', "<execute> - probleme lors du passage en mode [".$new_mode."] !");
			}
			$lat = config::byKey('api_latency', 'gigaelem');
			log::add('gigaelem', 'debug', "<execute> - attente API (latence = ".$lat.")");
			sleep($lat);
			$check_mode_info = $eqlogic->getAlarmMode();
			// Vérification du changment de mode
			$lazy_counter = 0;
			while ($check_mode_info['modeTransitionInProgress']) {
				$lazy_counter=$lazy_counter+1;
				log::add('gigaelem', 'debug', "<execute> - transition en cours, de [".$check_mode_info['active_mode']."] vers [".$check_mode_info['requestedMode']."] - iteration [".$lazy_counter."]");
				sleep($lat);
				$check_mode_info = $eqlogic->getAlarmMode();
				if ($lazy_counter>5) break; // securité pour les cas difficiles
			}
			// au final : on recupère le nouveau mode
			$check_mode = $check_mode_info["active_mode"];
			log::add('gigaelem', 'debug', "<execute> - nouveau mode : [".$check_mode."]");
			$eqlogic->checkAndUpdateCmd('mode', $check_mode); // on ne met à jour que le mode ici, pas le reste
			$eqlogic->refreshWidget();
			
			// active_mode": "home",
			// requestedMode": "home",
			// modeTransitionInProgress": false
		}
	
    }
    /*     * **********************Getteur Setteur*************************** */
}


