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

class Automatic
{
    protected $centreon;
    protected $dbCentstorage;
    protected $dbCentreon;

    /**
     * Constructor
     *
     * @param CentreonDB $db
     * @return void
     */
    public function __construct($centreon, $dbCentstorage, $dbCentreon)
    {
        $this->centreon = $centreon;
        $this->dbCentstorage = $dbCentstorage;
        $this->dbCentreon = $dbCentreon;
    }

    protected function debug($message)
    {
        $fp = fopen('/var/log/php-fpm/debug.txt', 'a+');
        fwrite($fp, $message . "\n");
    }

    /**
     * Test provider exists and returns the ruleId
     *
     * @param string   $name
     * @return integer
     */
    protected function getProviderId($name)
    {
        $stmt = $this->dbCentreon->prepare(
            "SELECT rule_id FROM mod_open_tickets_rule
            WHERE alias = :alias AND activate = '1'"
        );
        $stmt->bindParam(':alias', $name, PDO::PARAM_STR);
        $stmt->execute();
        if (!($row = $stmt->fetch(PDO::FETCH_ASSOC))) {
            throw new Exception('Wrong parameter rule_id');
        }

        return $row['rule_id'];
    }

    /**
     * Get contact information
     *
     * @param string   $name
     * @return mixed
     */
    function getContactInformation($params)
    {  
        $rv = ['alias' => '', 'email' => '', 'name' => ''];
        $dbResult = $this->dbCentreon->query(
            "SELECT
                contact_name as `name`,
                contact_alias as `alias`,
                contact_email as email
            FROM contact
            WHERE contact_id = '" . $this->centreon->user->user_id . "' LIMIT 1"
        );
        if (($row = $dbResult->fetch())) {
            $rv = $row;
        }

        if (isset($params['contact_name'])) {
            $row['name'] = $params['contact_name'];
        }
        if (isset($params['contact_alias'])) {
            $row['alias'] = $params['contact_alias'];
        }
        if (isset($params['contact_email'])) {
            $row['email'] = $params['contact_email'];
        }

        return $rv;
    }

    /**
     * Get service information
     *
     * @param mixed   $params
     * @return mixed
     */
    function getServiceInformation($params)
    {
        $query = 'SELECT
            services.*,
            hosts.address,
            hosts.state AS host_state,
            hosts.host_id,
            hosts.name AS host_name,
            hosts.instance_id
            FROM services, hosts
            WHERE services.host_id = :host_id AND
                services.service_id = :service_id AND
                services.host_id = hosts.host_id';
        if (!$this->centreon->user->admin) {
            $query .=
                ' AND EXISTS(
                    SELECT * FROM centreon_acl WHERE centreon_acl.group_id IN (' . $this->centreon->user->grouplistStr . ') AND ' .
                '   centreon_acl.host_id = :host_id AND centreon_acl.service_id = :service_id
                 )';
        }
        $stmt = $this->dbCentstorage->prepare($query);
        $stmt->bindParam(':host_id', $params['host_id'], PDO::PARAM_INT);
        $stmt->bindParam(':service_id', $params['service_id'], PDO::PARAM_INT);
        $stmt->execute();
        if (!($service = $stmt->fetch(PDO::FETCH_ASSOC))) {
            throw new Exception('Wrong parameter host_id/service_id or acl');
        }

        $stmt = $this->dbCentstorage->prepare(
            'SELECT host_id, service_id, COUNT(*) AS num_metrics 
            FROM index_data, metrics 
            WHERE index_data.host_id = :host_id AND
                index_data.service_id = :service_id AND
                index_data.id = metrics.index_id 
            GROUP BY host_id, service_id'
        );
        $stmt->bindParam(':host_id', $params['host_id'], PDO::PARAM_INT);
        $stmt->bindParam(':service_id', $params['service_id'], PDO::PARAM_INT);
        $stmt->execute();
        $service['num_metrics'] = 0;
        if (($row = $stmt->fetch(PDO::FETCH_ASSOC))) {
            $service['num_metrics'] = $row['num_metrics'];
        }

        $service['service_state'] = $service['state'];
        $service['state_str'] = $params['service_state'];
        $service['last_state_change_duration'] = CentreonDuration::toString(
            time() - $service['last_state_change']
        );
        $service['last_hard_state_change_duration'] = CentreonDuration::toString(
            time() - $service['last_hard_state_change']
        );

        if (isset($params['last_service_state_change'])) {
            $service['last_state_change_duration'] = CentreonDuration::toString(
                time() - $params['last_service_state_change']
            );
            $service['last_hard_state_change_duration'] = CentreonDuration::toString(
                time() - $params['last_service_state_change']
            );
        }
        if (isset($params['service_output'])) {
            $service['output'] = $params['service_output'];
        }
        if (isset($params['service_description'])) {
            $service['description'] = $params['service_description'];
        }
        if (isset($params['host_name'])) {
            $service['host_name'] = $params['host_name'];
        }
        if (isset($params['host_name'])) {
            $service['host_name'] = $params['host_name'];
        }

        return $service;
    }

    /**
     * Open a service ticket
     *
     * @param array $params
     * @return array
     */
    public function openService($params)
    {
        $ruleId = $this->getProviderId($params['provider_name']);
        $contact = $this->getContactInformation($params);
        $service = $this->getServiceInformation($params);

        return ['code' => 0, 'message' => 'open ticket opened'];
    }
}
