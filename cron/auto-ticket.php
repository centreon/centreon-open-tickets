<?php
/*
 * Copyright 2005-2015 Centreon
 * Centreon is developped by : Julien Mathis and Romain Le Merlus under
 * GPL Licence 2.0.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation ; either version 2 of the License.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
 * PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program; if not, see <http://www.gnu.org/licenses>.
 *
 * Linking this program statically or dynamically with other modules is making a
 * combined work based on this program. Thus, the terms and conditions of the GNU
 * General Public License cover the whole combination.
 *
 * As a special exception, the copyright holders of this program give Centreon
 * permission to link this program with independent modules to produce an executable,
 * regardless of the license terms of these independent modules, and to copy and
 * distribute the resulting executable under terms of Centreon choice, provided that
 * Centreon also meet, for each linked independent module, the terms  and conditions
 * of the license of that module. An independent module is a module which is not
 * derived from this program. If you modify this program, you may extend this
 * exception to your version of the program, but you are not obliged to do so. If you
 * do not wish to do so, delete this exception statement from your version.
 *
 * For more information : contact@centreon.com
 *
 */

include_once "DB.php";

require_once realpath(dirname(__FILE__) . "/../config/centreon.config.php");
include_once _CENTREON_PATH_ . "/www/class/centreonDB.class.php";

$centreonDbName = $conf_centreon['db'];

function programExit($msg)
{
    echo "[" . date("Y-m-d H:i:s") . "] " . $msg . "\n";
    exit;
}

$nbProc = exec('ps -o args -p $(/sbin/pidof -o $$ -o $PPID -o %PPID -x php || echo 1000000) | grep -c ' . __FILE__);
if ((int) $nbProc > 0) {
    programExit("More than one auto-ticket.php process currently running. Going to exit...");
}

ini_set('max_execution_time', 0);

try {

    $pearDB = new CentreonDB();

    $query =
    "SELECT DISTINCT w.widget_id
    FROM widgets w
    INNER JOIN widget_views wv ON w.widget_id = wv.widget_id
    INNER JOIN widget_preferences wpr ON wv.widget_view_id = wpr.widget_view_id
    INNER JOIN widget_parameters wpa ON wpa.parameter_id = wpr.parameter_id
    WHERE wpr.preference_value = '1' AND wpa.parameter_code_name = 'auto_ticket_creation'";

    $DBRESULT = $pearDB->query($query);
    if (PEAR::isError($DBRESULT)) {
        print "Cannot Get Widget Ids";
        exit(1);
    }

    $widgets = array();
    while($row = $DBRESULT->fetchRow()){
        $widgets[] = $row['widget_id'];
    }

    //Log in to centreon
    $curl = curl_init();

    curl_setopt($curl, CURLOPT_URL, 'https://monitor-pre.upc.edu/centreon/index.php');
    curl_setopt($curl, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.0; en-US; rv:1.7.12) Gecko/20050915 Firefox/1.0.7");
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_HEADER, 0);
    curl_setopt($curl, CURLOPT_TIMEOUT, 60);
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($curl, CURLOPT_COOKIEJAR, 'cookie.txt');
    curl_setopt($curl, CURLOPT_COOKIEFILE, 'cookie.txt');

    // Send the request & save response to $resp
    $resp = curl_exec($curl);

    $pos = strpos($resp, 'centreon_token');
    $token = substr($resp, $pos+37, 32);

    curl_setopt($curl, CURLOPT_URL, 'https://monitor-pre.upc.edu/centreon/index.php');
    curl_setopt($curl, CURLOPT_POST, 1);
    curl_setopt($curl, CURLOPT_POSTFIELDS, array(
            'useralias' => 'admin',
            'password' => 'ubmMpnmT5',
            'submitLogin' => 'Connect',
            'centreon_token' => $token
        )
    );
    curl_exec($curl);

    foreach($widgets as $widgetId){
        curl_setopt($curl, CURLOPT_URL, 'https://monitor-pre.upc.edu/centreon/widgets/open-tickets/src/index.php?widgetId='.$widgetId.'&page=0&auto=true');
        curl_setopt($curl, CURLOPT_POST, 0);
        curl_exec($curl);
    }

    curl_close($curl);
    unlink('/usr/share/centreon/cron/cookie.txt');
} catch (Exception $e) {
    programExit($e->getMessage());
}
