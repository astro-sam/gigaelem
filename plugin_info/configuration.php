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

require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';
include_file('core', 'authentification', 'php');
if (!isConnect()) {
    include_file('desktop', '404', 'php');
    die();
}
?>
<form class="form-horizontal">
    <fieldset>
        <div class="form-group">
            <label class="col-sm-2 control-label">{{user_id}}</label>
            <div class="col-sm-3">
                <input type="text" class="configKey form-control" data-l1key="username" placeholder="Identifiant utilisateur"/>
            </div>
        </div>
        <div class="form-group">
            <label class="col-sm-2 control-label">{{Mot de passe}}</label>
            <div class="col-sm-3">
                <input type="password" class="configKey form-control" data-l1key="password" placeholder="Mot de passe" />
            </div>
        </div>
		<div class="form-group">
			<label class="col-lg-2 control-label">{{Verification}}&nbsp;<sup><i class="fas fa-question-circle tooltips" title="Attention à sauvegarder les parametres avant de verifier la connexion"></i></sup></label>
			<div class="col-lg-2">
			<a class="btn btn-warning" id="bt_checkGigasetId"><i class='fa fa-refresh'></i> {{Verifier la connexion}}</a>
			</div>
		</div>
		<div class="form-group">
			<label class="col-lg-2 control-label">{{Latence API}}&nbsp;<sup><i class="fas fa-question-circle tooltips" title="Temps de réponse de l'API Gigaset. Si la vérification est sollicitée trop rapidement apres un changement de mode, le statut n'est pas a jour..."></i></sup></label>
			<div class="col-lg-2">
				<select class="configKey form-control" data-l1key="api_latency">
					<option value="2">{{2 secondes}}</option>
					<option value="5" selected="selected">{{5 secondes - recommandé}}</option>
					<option value="10">{{10 secondes}}</option>
				</select>
			</div>
		</div>
	</fieldset>
</form>
<script>
    $('#bt_checkGigasetId').on('click', function () {
        $.ajax({// fonction permettant de faire de l'ajax
            type: "POST", // methode de transmission des données au fichier php
            url: "plugins/gigaelem/core/ajax/gigaelem.ajax.php", // url du fichier php
            data: {
                action: "checkLogin",
            },
            dataType: 'json',
            error: function (request, status, error) {
                handleAjaxError(request, status, error);
            },
            success: function (data) { // si l'appel a bien fonctionné
                if (data.state != 'ok') {
                    $('#div_alert').showAlert({message: data.result, level: 'danger'});
                    return;
                }
                $('#div_alert').showAlert({message: '{{Connexion OK - '+data.result+'}}', level: 'success'});
            }
        });
    });
</script>
