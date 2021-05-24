<?php
/*
 * Copyright 2016-2019 Centreon (http://www.centreon.com/)
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

$resultat = array(
    "code" => 0,
    "msg" => 'ok',
);

// We get Host or Service
$selected_values = explode(',', $get_information['form']['selection']);
$db_storage = new centreonDBManager('centstorage');

$problems = array();

# check services and hosts
$selected_str = '';
$selected_str_append = '';
$hosts_selected_str = '';
$hosts_selected_str_append = '';
$hosts_done = array();
$services_done = array();
foreach ($selected_values as $value) {
    $str = explode(';', $value);
    $selected_str .= $selected_str_append . 'services.host_id = ' . $str[0] . ' AND services.service_id = ' . $str[1];
    $selected_str_append = ' OR ';

    if (!isset($hosts_done[$str[0]])) {
        $hosts_selected_str .= $hosts_selected_str_append . $str[0];
        $hosts_selected_str_append = ', ';
        $hosts_done[$str[0]] = 1;
    }
}

$query = "(SELECT DISTINCT services.description, hosts.name as host_name, hosts.instance_id FROM services, hosts
    WHERE (" . $selected_str . ') AND services.host_id = hosts.host_id';
if (!$centreon_bg->is_admin) {
    $query .= " AND EXISTS(
        SELECT * FROM centreon_acl WHERE centreon_acl.group_id IN (" .
            $centreon_bg->grouplistStr . "
        )
        AND hosts.host_id = centreon_acl.host_id
        AND services.service_id = centreon_acl.service_id
    )";
}
$query .= ") UNION ALL (
    SELECT DISTINCT NULL as description, hosts.name as host_name, hosts.instance_id
    FROM hosts
    WHERE hosts.host_id IN (" .
        $hosts_selected_str . "
    )";
if (!$centreon_bg->is_admin) {
    $query .= " AND EXISTS(
        SELECT * FROM centreon_acl
        WHERE centreon_acl.group_id IN (" .
            $centreon_bg->grouplistStr . "
        )
        AND hosts.host_id = centreon_acl.host_id
    )";
}
$query .= ") ORDER BY `host_name`, `description`";

$hosts_done = array();

$dbResult = $db_storage->query($query);
while (($row = $dbResult->fetch())) {
    if (isset($hosts_done[$row['host_name'] . ';' . $row['description']])) {
        continue;
    }

    $problems[] = $row;
    $hosts_done[$row['host_name'] . ';' . $row['description']] = 1;
}

try {
    #fwrite($fp, print_r($problems, true) . "===\n");
    require_once $centreon_path . 'www/class/centreonExternalCommand.class.php';
    $oreon = $_SESSION['centreon'];
    $external_cmd = new CentreonExternalCommand($oreon);
    $method_external_name = 'set_process_command';
    if (method_exists($external_cmd, $method_external_name) == false) {
        $method_external_name = 'setProcessCommand';
    }

    $error_msg = array();

    foreach ($problems as $row) {
        if (is_null($row['description']) || $row['description'] == '') {
            $command = "SCHEDULE_FORCED_HOST_CHECK;%s;%s";
            call_user_func_array(
                array($external_cmd, $method_external_name),
                array(
                    sprintf(
                        $command,
                        $row['host_name'],
                        time()
                    ),
                    $row['instance_id']
                )
            );
            continue;
        }

        $command = "SCHEDULE_FORCED_SVC_CHECK;%s;%s;%s";
        call_user_func_array(
            array($external_cmd, $method_external_name),
            array(
                sprintf(
                    $command,
                    $row['host_name'],
                    $row['description'],
                    time()
                ),
                $row['instance_id']
            )
        );
    }

    $external_cmd->write();
} catch (Exception $e) {
    $resultat['code'] = 1;
    $resultat['msg'] = $e->getMessage();
    $db->rollback();
}

$resultat['msg'] = '
<table class="table">
    <tr>
        <td class="FormHeader" colspan="2"><h3 style="color: #00bfb3;">' . _('Service Schedule forced check') . '</td>
    </tr>
    <tr>
        <td class="FormRowField" style="padding-left:15px;">Service schedule forced check</td>
    </tr>';
$resultat['msg'] .= '</table>';