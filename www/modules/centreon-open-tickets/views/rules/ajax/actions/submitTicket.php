<?php
/*
 * Copyright 2016 Centreon (http://www.centreon.com/)
 *
 * Centreon is a full-fledged industry-strength solution that meets 
 * the needs in IT infrastructure and application monitoring for 
 * service performance.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *    http://www.apache.org/licenses/LICENSE-2.0  
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,*
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

function get_contact_information() {
    global $db, $centreon_bg;
    
    $result = array('alias' => '', 'email' => '', 'name' => '');
    $DBRESULT = $db->query("SELECT contact_name as `name`, contact_alias as `alias`, contact_email as email FROM contact WHERE contact_id = '" . $centreon_bg->user_id . "' LIMIT 1");
    if (($row = $DBRESULT->fetchRow())) {
        $result = $row;
    }
    
    return $result;
}

$resultat = array(
    "code" => 0,
    "msg" => 'ok'
);

// Load provider class
if (is_null($get_information['provider_id']) || is_null($get_information['form'])) {
    $resultat['code'] = 1;
    $resultat['msg'] = 'Please set provider_id or form';
    return ;
}

$provider_name = null;
foreach ($register_providers as $name => $id) {
    if ($id == $get_information['provider_id']) {
        $provider_name = $name;
        break;
    }
}

if (is_null($provider_name) || !file_exists($centreon_open_tickets_path . 'providers/' . $provider_name . '/' . $provider_name . 'Provider.class.php')) {
    $resultat['code'] = 1;
    $resultat['msg'] = 'Please set a provider';
    return ;
}
if (!isset($get_information['form']['widgetId']) || is_null($get_information['form']['widgetId']) || $get_information['form']['widgetId'] == '') {
    $resultat['code'] = 1;
    $resultat['msg'] = 'Please set widgetId';
    return ;
}

require_once $centreon_open_tickets_path . 'providers/' . $provider_name . '/' . $provider_name . 'Provider.class.php';

$classname = $provider_name . 'Provider';
$centreon_provider = new $classname($rule, $centreon_path, $centreon_open_tickets_path, $get_information['rule_id'], $get_information['form'], $get_information['provider_id']);
$centreon_provider->setWidgetId($get_information['form']['widgetId']);

// We get Host or Service
require_once $centreon_path . 'www/class/centreonDuration.class.php';

$selected_values = explode(',', $get_information['form']['selection']);
$db_storage = new centreonDBManager('centstorage');

$selected = $rule->loadSelection($db_storage, $get_information['form']['cmd'], $get_information['form']['selection']);

try {
    $contact_infos = get_contact_information();
    $resultat['result'] = $centreon_provider->submitTicket($db_storage, $contact_infos, $selected['host_selected'], $selected['service_selected']);
    
    if ($resultat['result']['ticket_is_ok'] == 1) { 
        require_once $centreon_path . 'www/class/centreonExternalCommand.class.php';
        $oreon = $_SESSION['centreon'];
        $external_cmd = new CentreonExternalCommand($oreon);
        
        foreach ($selected['host_selected'] as $value) {
            $command = "CHANGE_CUSTOM_HOST_VAR;%s;%s;%s";
            $external_cmd->setProcessCommand(sprintf($command, $value['name'], $centreon_provider->getMacroTicketId(), $resultat['result']['ticket_id']), $value['instance_id']);
            if ($centreon_provider->doAck()) {
                $command = "ACKNOWLEDGE_HOST_PROBLEM;%s;%s;%s;%s;%s;%s";
                $external_cmd->setProcessCommand(sprintf($command, $value['name'], 2, 0, 1, $contact_infos['alias'], 'open ticket: ' . $resultat['result']['ticket_id']), $value['instance_id']);
            }
        }
        foreach ($selected['service_selected'] as $value) {
            $command = "CHANGE_CUSTOM_SVC_VAR;%s;%s;%s;%s";
            $external_cmd->setProcessCommand(sprintf($command, $value['host_name'], $value['description'], $centreon_provider->getMacroTicketId(), $resultat['result']['ticket_id']), $value['instance_id']);
            if ($centreon_provider->doAck()) {
                $command = "ACKNOWLEDGE_SVC_PROBLEM;%s;%s;%s;%s;%s;%s;%s";
                $external_cmd->setProcessCommand(sprintf($command, $value['host_name'], $value['description'], 2, 0, 1, $contact_infos['alias'], 'open ticket: ' . $resultat['result']['ticket_id']), $value['instance_id']);
            }
        }
        
        $external_cmd->write();
    }
} catch (Exception $e) {
    $resultat['code'] = 1;
    $resultat['msg'] = $e->getMessage();
    $db->rollback();
}

?>
