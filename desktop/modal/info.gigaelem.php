<?php
/* This file is part of Plugin openzwave for jeedom.
 *
 * Plugin openzwave for jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Plugin openzwave for jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Plugin openzwave for jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

if (!isConnect('admin')) {
	throw new Exception('401 Unauthorized');
}
?>
<div class="col-xs-12 eqLogic">
	<ul class="nav nav-tabs" role="tab-list">
		<li role="presentation" class="active">
			<a href="#eventsTab" role="tab" data-toggle="tab">
				<i class="fas fa-list-alt"></i>
					<span class="hidden-xs">Evenements</span>
			</a>
		</li>
		<li role="presentation">
			<a href="#elementsTab" role="tab" data-toggle="tab">
				<i class="fas fa-cubes"></i>
					<span class="hidden-xs">Elements</span>
			</a>
		</li>
	</ul>
	<div class="tab-content" style="height:calc(100% - 50px);overflow:auto;overflow-x: hidden;">
		<div role="tabpanel" class="tab-pane active" id="eventsTab">
		<script>
			$('.eqLogic[data-eqLogic_uid=#uid#] .refresh').on('click', function () {
				jeedom.cmd.execute({id: '#refresh_id#'});
			});
		</script>
		</div>
		<div role="tabpanel" class="tab-pane" id="elementsTab">
		Elements
		</div>
	</div>

</div>
