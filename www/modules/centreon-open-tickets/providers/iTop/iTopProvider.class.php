<?php
/*
 * Copyright 2017 Centreon (http://www.centreon.com/)
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

class iTopProvider extends AbstractProvider {
    const ARG_CALLER_ID = 1;
    const ARG_ORGANISATION_ID = 2;
    const ARG_TITLE = 3;
    const ARG_CONTENT = 4;
    const ARG_IMPACT = 5;
    const ARG_URGENCY = 6;
    const ARG_SERVICE_ID = 7;
    const ARG_CI = 8;

    const ITOP_LIST_ORGANIZATION = 21;
    const ITOP_LIST_SERVICE = 22;

    protected $_internal_arg_name = array(
        self::ARG_TITLE => 'title',
        self::ARG_CONTENT => 'content',
        self::ARG_IMPACT => 'impact',
        self::ARG_URGENCY => 'urgency',
    );

    /**
     * Set default extra value
     *
     * @return void
     */
    protected function _setDefaultValueExtra() {
        $this->default_data['address'] = 'itop.localdomain.tld';
        $this->default_data['apiurl'] = '/itop/webservices/rest.php?version=1.1';
        $this->default_data['ticketurl'] = '/itop/pages/UI.php?operation=details&class=Incident&id=';
        $this->default_data['https'] = 1;
        $this->default_data['callerid'] = 'Centreon Administration';
        $this->default_data['timeout'] = 60;

        $this->default_data['clones']['mappingTicket'] = array(
            array('Arg' => self::ARG_TITLE, 'Value' => 'Issue {include file="file:$centreon_open_tickets_path/providers/Abstract/templates/display_title.ihtml"}'),
            array('Arg' => self::ARG_CONTENT, 'Value' => '{$body}'),
            array('Arg' => self::ARG_IMPACT, 'Value' => '{$select.impact.value}')
        );
    }

    protected function _setDefaultValueMain() {
        parent::_setDefaultValueMain();

        $this->default_data['url'] = 'https://{$address}{$ticketurl}{$ticket_id}';

        $this->default_data['clones']['groupList'] = array(
            array('Id' => 'impact', 'Label' => _('Impact'), 'Type' => self::CUSTOM_TYPE, 'Filter' => '', 'Mandatory' => true)
        );
        $this->default_data['clones']['customList'] = array(
            array('Id' => 'impact', 'Value' => '1', 'Default' => ''),
            array('Id' => 'impact', 'Value' => '2', 'Default' => ''),
            array('Id' => 'impact', 'Value' => '3', 'Default' => ''),
        );

        $this->default_data['format_popup'] = $this->change_html_tags('<table class="table">
<tr>
    <td class="FormHeader" colspan="2"><h3 style="color: #00bfb3;">{$title}</h3></td>
</tr>
<tr>
    <td class="FormRowField" style="padding-left:15px;">{$custom_message.label}</td>
    <td class="FormRowValue" style="padding-left:15px;"><textarea id="custom_message" name="custom_message" cols="50" rows="6"></textarea></td>
</tr>
{include file="file:$centreon_open_tickets_path/providers/iTop/templates/format_popup_requiredFields.ihtml"}
{include file="file:$centreon_open_tickets_path/providers/Abstract/templates/groups.ihtml"}
</table>
');
    }

    /**
     * Check form
     *
     * @return a string
     */
    protected function _checkConfigForm() {
        $this->_check_error_message = '';
        $this->_check_error_message_append = '';

        $this->_checkFormValue('address', "Please set 'Address' value");
        $this->_checkFormValue('apiurl', "Please set 'API URL path' value");
        $this->_checkFormValue('ticketurl', "Please set 'ticket URL path' value");
        $this->_checkFormValue('timeout', "Please set 'Timeout' value");
        $this->_checkFormValue('username', "Please set 'Username' value");
        $this->_checkFormValue('password', "Please set 'Password' value");
        $this->_checkFormValue('callerid', "Please set 'Caller ID' value");
        $this->_checkFormValue('macro_ticket_id', "Please set 'Macro Ticket ID' value");
        $this->_checkFormInteger('timeout', "'Timeout' must be a number");
        $this->_checkFormInteger('confirm_autoclose', "'Confirm popup autoclose' must be a number");

        $this->_checkLists();

        if ($this->_check_error_message != '') {
            throw new Exception($this->_check_error_message);
        }
    }

    /**
     * Build the specifc config: from, to, subject, body, headers
     *
     * @return void
     */
    protected function _getConfigContainer1Extra() {
        $tpl = new Smarty();
        $tpl = initSmartyTplForPopup($this->_centreon_open_tickets_path, $tpl, 'providers/iTop/templates', $this->_centreon_path);

        $tpl->assign("centreon_open_tickets_path", $this->_centreon_open_tickets_path);
        $tpl->assign("img_brick", "./modules/centreon-open-tickets/images/brick.png");
        $tpl->assign("header", array("itop" => _("iTop")));
        $tpl->assign('webServiceUrl', './api/internal.php');

        // Form
        $address_html = '<input size="50" name="address" type="text" value="' . $this->_getFormValue('address') . '" />';
        $apiurl_html = '<input size="50" name="apiurl" type="text" value="' . $this->_getFormValue('apiurl') . '" />';
        $ticketurl_html = '<input size="50" name="ticketurl" type="text" value="' . $this->_getFormValue('ticketurl') . '" />';
        $username_html = '<input size="50" name="username" type="text" value="' . $this->_getFormValue('username') . '" />';
        $password_html = '<input size="50" name="password" type="password" value="' . $this->_getFormValue('password') . '" autocomplete="off" />';
        $callerid_html = '<input size="50" name="callerid" type="text" value="' . $this->_getFormValue('callerid') . '" />';
        $https_html = '<input type="checkbox" name="https" value="yes" ' . ($this->_getFormValue('https') == 'yes' ? 'checked' : '') . '/>';
        $timeout_html = '<input size="2" name="timeout" type="text" value="' . $this->_getFormValue('timeout') . '" />';

        $array_form = array(
            'address' => array('label' => _("Address") . $this->_required_field, 'html' => $address_html),
            'apiurl' => array('label' => _("API URL path"), 'html' => $apiurl_html),
            'ticketurl' => array('label' => _("Ticket URL path"), 'html' => $ticketurl_html),
            'username' => array('label' => _("Username") . $this->_required_field, 'html' => $username_html),
            'password' => array('label' => _("Password") . $this->_required_field, 'html' => $password_html),
            'callerid' => array('label' => _("Caller ID") . $this->_required_field, 'html' => $callerid_html),
            'https' => array('label' => _("Use https"), 'html' => $https_html),
            'timeout' => array('label' => _("Timeout"), 'html' => $timeout_html),
            'mappingticket' => array('label' => _("Mapping ticket arguments")),
        );

        // mapping Ticket clone
        $mappingTicketValue_html = '<input id="mappingTicketValue_#index#" name="mappingTicketValue[#index#]" size="20"  type="text" />';
        $mappingTicketArg_html = '<select id="mappingTicketArg_#index#" name="mappingTicketArg[#index#]" type="select-one">' .
        '<option value="' . self::ARG_TITLE . '">' . _('Title') . '</options>' .
        '<option value="' . self::ARG_CONTENT . '">' . _('Content') . '</options>' .
        '<option value="' . self::ARG_IMPACT . '">' . _('Impact') . '</options>' .
        '<option value="' . self::ARG_ORGANISATION_ID . '">' . _('Organization') . '</options>' .
        '</select>';
        $array_form['mappingTicket'] = array(
            array('label' => _("Argument"), 'html' => $mappingTicketArg_html),
            array('label' => _("Value"), 'html' => $mappingTicketValue_html),
        );

        $tpl->assign('form', $array_form);

        $this->_config['container1_html'] .= $tpl->fetch('conf_container1extra.ihtml');

        $this->_config['clones']['mappingTicket'] = $this->_getCloneValue('mappingTicket');
    }

    /**
     * Build the specific advanced config: -
     *
     * @return void
     */
    protected function _getConfigContainer2Extra() {

    }

    protected function saveConfigExtra() {
        $this->_save_config['simple']['address'] = $this->_submitted_config['address'];
        $this->_save_config['simple']['apiurl'] = $this->_submitted_config['apiurl'];
        $this->_save_config['simple']['ticketurl'] = $this->_submitted_config['ticketurl'];
        $this->_save_config['simple']['username'] = $this->_submitted_config['username'];
        $this->_save_config['simple']['password'] = $this->_submitted_config['password'];
        $this->_save_config['simple']['callerid'] = $this->_submitted_config['callerid'];
        $this->_save_config['simple']['https'] = (isset($this->_submitted_config['https']) && $this->_submitted_config['https'] == 'yes') ?
            $this->_submitted_config['https'] : '';
        $this->_save_config['simple']['timeout'] = $this->_submitted_config['timeout'];

        $this->_save_config['clones']['mappingTicket'] = $this->_getCloneSubmitted('mappingTicket', array('Arg', 'Value'));
    }

    public function validateFormatPopup() {
        $result = array('code' => 0, 'message' => 'ok');

        $this->validateFormatPopupLists($result);

        return $result;
    }

    /*
     * Get list of host_name
     */
    protected function getListOfCI($host_problems, $service_problems) {
        $iTopCI = array();

        if (isset($service_problems) && count($service_problems) > 0) {
            foreach ($service_problems as $service) {
                $iTopCI[] = $service['host_name'];
            }
        } else {
            foreach ($host_problems as $host) {
                $iTopCI[] = $host['host_name'];
            }
        }

        return $iTopCI;
    }

    /*
     * Get max critical status
     */
    protected function getMaxStatus($host_problems, $service_problems) {
        $status = 0;

        if (isset($service_problems) && count($service_problems) > 0) {
            foreach ($service_problems as $service) {
                if ($service['service_state'] == 2) {
                    $status = 2;
                    break;
                }
                if ($service['service_state'] == 1) {
                    $status = 1;
                }
                elseif ($service['service_state'] == 3 && $status != "1") {
                    $status = 3;
               }
            }
        } else {
            foreach ($host_problems as $host) {
                if ($host['host_state'] == 1) {
                    $status = 1;
                    break;
                }
                if ($host['host_state'] == 2) {
                    $status = 2;
                }
            }
        }

        return $status;
    }

    protected function doSubmit($db_storage, $contact, $host_problems, $service_problems, $extra_ticket_arguments=array()) {
        $result = array('ticket_id' => null, 'ticket_error_message' => null,
                        'ticket_is_ok' => 0, 'ticket_time' => time());

        $tpl = new Smarty();
        $tpl = initSmartyTplForPopup($this->_centreon_open_tickets_path, $tpl, 'providers/Abstract/templates', $this->_centreon_path);

        $tpl->assign("centreon_open_tickets_path", $this->_centreon_open_tickets_path);
        $tpl->assign('user', $contact);
        $tpl->assign('host_selected', $host_problems);
        $tpl->assign('service_selected', $service_problems);
        $this->assignSubmittedValues($tpl);

        $ticket_arguments = array();
        if (isset($this->rule_data['clones']['mappingTicket'])) {
            foreach ($this->rule_data['clones']['mappingTicket'] as $value) {
                $tpl->assign('string', $value['Value']);
                $result_str = $tpl->fetch('eval.ihtml');

                if ($result_str == '') {
                    $result_str = null;
                }

                $ticket_arguments[$this->_internal_arg_name[$value['Arg']]] = $result_str;
            }
        }

        /* Mini fix for get organization id and service id */
        $form = json_decode($_POST['data'], true);
        $form = $form['form'];
        if (!isset($form['select_itop_organization']) || !is_numeric($form['select_itop_organization']) || $form['select_itop_organization'] === -1) {
            throw new \Exception('Bad arguments.');
        }
        if (!isset($form['select_itop_service']) || !is_numeric($form['select_itop_service']) || $form['select_itop_service'] === -1) {
            throw new \Exception('Bad arguments.');
        }
        $ticket_arguments['itop_organization'] = $form['select_itop_organization'];
        $ticket_arguments['itop_service'] = $form['select_itop_service'];

        $code = $this->createTicketiTop($ticket_arguments, $host_problems, $service_problems);
        if ($code == -1) {
            $result['ticket_error_message'] = $this->ws_error;
            return $result;
        }

        $this->saveHistory($db_storage, $result, array('contact' => $contact, 'host_problems' => $host_problems, 'service_problems' => $service_problems,
            'ticket_value' => $this->_ticket_number, 'subject' => $ticket_arguments[$this->_internal_arg_name[self::ARG_TITLE]],
            'data_type' => self::DATA_TYPE_JSON, 'data' => json_encode(array('arguments' => $ticket_arguments))));

        return $result;
    }

    /*
     *
     * REST API
     *
     */
    protected function setWsError($error) {
        error_log($error);
        $this->ws_error = $error;
    }

    protected function createTicketiTop($ticket_arguments, $host_problems, $service_problems) {


        $aOperations = array(
            'operation' => 'centreon/create_ticket',
            'caller_id' => $this->rule_data['callerid'],
            'title' => $ticket_arguments[$this->_internal_arg_name[self::ARG_TITLE]],
            'description' => nl2br($ticket_arguments[$this->_internal_arg_name[self::ARG_CONTENT]]),
            'impact' => $ticket_arguments[$this->_internal_arg_name[self::ARG_IMPACT]],
            'urgence' => $this->getMaxStatus($host_problems, $service_problems),
            'organisation_id' => $ticket_arguments['itop_organization'],
            'service_id' => $ticket_arguments['itop_service'],
            'ci' => $this->getListOfCI($host_problems, $service_problems)
        );

        $aData = array();
        $aData['auth_user'] = $this->rule_data['username'];
        $aData['auth_pwd'] = $this->rule_data['password'];
        $aData['json_data'] = json_encode($aOperations);

        $data = $this->callRestAPI($aData);

        if ($data === 1) {
            return -1;
        }

        if (isset($data["ref"]) && $data["ref"]) {
            $this->_ticket_number = preg_replace('/I\-/', '', $data["ref"]);
        } else if (isset($data['code']) && $data['code'] === 100) {
            $this->setWsError("The CI matching with the hostname doesn't exists.");
            return -1;
        } else {
            $this->setWsError("Can't extract Ticket ID");
            return -1;
        }
        return 0;
    }

    /**
     * Get the list of iTop organization
     *
     * @return array
     */
    public function getOrganization($data = array()) {
        $aData = array(
            'auth_user' => $this->rule_data['username'],
            'auth_pwd' => $this->rule_data['password']
        );
        $organizations = $this->getCache('itop-organization');
        if (is_null($organizations)) {
            $aData['json_data'] = json_encode(array(
                'operation' => 'centreon/get_organization',
                'caller_id' => $this->rule_data['callerid']
            ));
            $returnValues = $this->callRestAPI($aData);
            if ($returnValues === 1) {
                throw new \Exception('Error during getting organizations.');
            }
            $objects = $returnValues['objects'];
            foreach ($objects as $name => $values) {
                $organizations[$values['key']] = $values['fields']['friendlyname'];
            }
            $this->setCache('itop-organization', $organizations, 24 * 3600);
        }
        return $organizations;
    }

    /**
     * Get the list of iTop organization
     *
     * @return array
     */
    public function getService($data = array()) {
        $aData = array(
            'auth_user' => $this->rule_data['username'],
            'auth_pwd' => $this->rule_data['password']
        );
        $organizationId = $data['organizationId'];
        if (is_null($organizationId) || $organizationId === -1) {
            throw new \Exception('Not organization set.');
        }
        $services = $this->getCache('itop-service-' . $organizationId);
        $aData['json_data'] = json_encode(array(
            'operation' => 'centreon/get_service',
            'caller_id' => $this->rule_data['callerid'],
            'org_id' => $organizationId
        ));
        $returnValues = $this->callRestAPI($aData);
        if ($returnValues === 1) {
            throw new \Exception('Error during getting services');
        }
        $objects = $returnValues['objects'];
        foreach ($objects as $name => $values) {
            $services[$values['key']] = $values['fields']['friendlyname'];
        }
        $this->setCache('itop-service-' . $organizationId, $services, 24 * 3600);
        return $services;
    }

    /**
     * Test the service iTop
     *
     * @param array $info The post information for webservice configuration
     * @return boolean
     */
    static public function test($info) {
        /* Test arguments */
        if (!isset($info['https']) ||
            !isset($info['address']) ||
            !isset($info['apiurl']) ||
            !isset($info['username']) ||
            !isset($info['password'])) {
            throw new \Exception('Missing arguments.');
        }

        $proto = 'http';
        $ssl = 0;
        if (isset($info['https']) && $info['https'] == 'yes') {
            $proto = 'https';
            $ssl = 1;
        }

        $url = $proto . '://' . $info['address'] . $info['apiurl'];

        $data = array(
            'json_data' => json_encode(array(
                'operation' => 'core/check_credentials',
                'user' => $info['username'],
                'password' => $info['password']
            )),
            'auth_user' => $info['username'],
            'auth_pwd' => $info['password']
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $returnJson = curl_exec($ch);
        if ($returnJson === false) {
            return false;
        }

        $data = json_decode($returnJson, true);

        if ($data['code'] === 0 && $data['authorized']) {
             return true;
        }

        return false;
    }

    protected function callRestAPI($data) {
        $proto = 'http';
        $ssl = 0;
        if (isset($this->rule_data['https']) && $this->rule_data['https'] == 'yes') {
            $proto = 'https';
            $ssl = 1;
        }

        $host = $this->rule_data['address'];
        $path = $this->rule_data['apiurl'];

        $url = $proto . '://' . $host . $path;

        $aHTTPHeaders = array();
        $curlOptions = array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_ENCODING => "",
            CURLOPT_USERAGENT => "spider",
            CURLOPT_AUTOREFERER => true,
            CURLOPT_CONNECTTIMEOUT => $this->rule_data['timeout'],
            CURLOPT_TIMEOUT => $this->rule_data['timeout'],
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSLVERSION => $ssl,
            CURLOPT_POST => count($data),
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_HTTPHEADER => $aHTTPHeaders,
        );

        $ch = curl_init($url);
        if ($ch == false) {
            $this->setWsError("Cannot init curl object");
            return 1;
        }

        curl_setopt_array($ch, $curlOptions);
        $result = curl_exec($ch);

        if ($result == false) {
            $this->setWsError(curl_error($ch));
            return 1;
        }

        curl_close($ch);

        $data = json_decode($result, true);

        if (!$data) {
            $this->setWsError($result);
            return 1;
        }

        return $data;
    }
}
