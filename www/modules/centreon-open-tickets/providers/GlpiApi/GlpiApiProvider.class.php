<?php
/*
 * Copyright 2018 Centreon (http://www.centreon.com/)
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

class GlpiApiProvider extends AbstractProvider {
    protected $_glpi_connected = 0;
    protected $_glpi_session = null;
    protected $contentType = 'application/json';
    
    const GPLI_ENTITIES_TYPE = 10;
    const GPLI_GROUPS_TYPE = 11;
    const GLPI_ITIL_CATEGORIES_TYPE = 12;
    
    const ARG_CONTENT = 1;
    const ARG_ENTITY = 2;
    const ARG_URGENCY = 3;
    const ARG_IMPACT = 4;
    const ARG_CATEGORY = 5;
    const ARG_USER = 6;
    const ARG_USER_EMAIL = 7;
    const ARG_GROUP = 8;
    const ARG_GROUP_ASSIGN = 9;
    const ARG_TITLE = 10;
    
    protected $_internal_arg_name = array(
        self::ARG_CONTENT => 'content',
        self::ARG_ENTITY => 'entity',
        self::ARG_URGENCY => 'urgency',
        self::ARG_IMPACT => 'impact',
        self::ARG_CATEGORY => 'category',
        self::ARG_USER => 'user',
        self::ARG_USER_EMAIL => 'user_email',
        self::ARG_GROUP => 'group',
        self::ARG_GROUP_ASSIGN => 'groupassign',
        self::ARG_TITLE => 'title',
    );

    function __destruct() {
        $this->logoutGlpi();
    }
    
    /**
     * Set default extra value 
     *
     * @return void
     */
    protected function _setDefaultValueExtra() {
        $this->default_data['address'] = '127.0.0.1';
        $this->default_data['port'] = '80';
        $this->default_data['path'] = '/glpi/apirest.php';
        $this->default_data['https'] = 0;
        $this->default_data['timeout'] = 60;
        
        $this->default_data['clones']['mappingTicket'] = array(
            array('Arg' => self::ARG_TITLE, 'Value' => 'Issue {include file="file:$centreon_open_tickets_path/providers/Abstract/templates/display_title.ihtml"}'),
            array('Arg' => self::ARG_CONTENT, 'Value' => '{$body}'),
            array('Arg' => self::ARG_ENTITY, 'Value' => '{$select.gpli_entity.id}'),
            array('Arg' => self::ARG_CATEGORY, 'Value' => '{$select.glpi_itil_category.id}'),
            array('Arg' => self::ARG_GROUP_ASSIGN, 'Value' => '{$select.glpi_group.id}'),
            array('Arg' => self::ARG_USER_EMAIL, 'Value' => '{$user.email}'),
            array('Arg' => self::ARG_URGENCY, 'Value' => '{$select.urgency.value}'),
            array('Arg' => self::ARG_IMPACT, 'Value' => '{$select.impact.value}'),
        );
    }
    
    protected function _setDefaultValueMain() {
        parent::_setDefaultValueMain();

        $proto = 'http';
        if (isset($this->rule_data['https']) && $this->rule_data['https'] == 'yes') {
            $proto = 'https';
        }

        $this->default_data['url'] = '$proto://{$address}:{$port}/front/ticket.form.php?id={$ticket_id}';
        
        $this->default_data['clones']['groupList'] = array(
            array('Id' => 'gpli_entity', 'Label' => _('Entity'), 'Type' => self::GPLI_ENTITIES_TYPE, 'Filter' => '', 'Mandatory' => ''),
            array('Id' => 'glpi_group', 'Label' => _('Glpi group'), 'Type' => self::GPLI_GROUPS_TYPE, 'Filter' => '', 'Mandatory' => ''),
            array('Id' => 'glpi_itil_category', 'Label' => _('Itil category'), 'Type' => self::GLPI_ITIL_CATEGORIES_TYPE, 'Filter' => '', 'Mandatory' => ''),
            array('Id' => 'urgency', 'Label' => _('Urgency'), 'Type' => self::CUSTOM_TYPE, 'Filter' => '', 'Mandatory' => ''),
            array('Id' => 'impact', 'Label' => _('Impact'), 'Type' => self::CUSTOM_TYPE, 'Filter' => '', 'Mandatory' => ''),
        );
        $this->default_data['clones']['customList'] = array(
            array('Id' => 'urgency', 'Value' => '1', 'Default' => ''),
            array('Id' => 'urgency', 'Value' => '2', 'Default' => ''),
            array('Id' => 'urgency', 'Value' => '3', 'Default' => ''),
            array('Id' => 'urgency', 'Value' => '4', 'Default' => ''),
            array('Id' => 'urgency', 'Value' => '5', 'Default' => ''),
            array('Id' => 'impact', 'Value' => '1', 'Default' => ''),
            array('Id' => 'impact', 'Value' => '2', 'Default' => ''),
            array('Id' => 'impact', 'Value' => '3', 'Default' => ''),
            array('Id' => 'impact', 'Value' => '4', 'Default' => ''),
            array('Id' => 'impact', 'Value' => '5', 'Default' => ''),
        );
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
        $this->_checkFormValue('port', "Please set 'TCP port' value");
        $this->_checkFormValue('path', "Please set 'API URL path' value");
        $this->_checkFormValue('timeout', "Please set 'Timeout' value");
        $this->_checkFormValue('user_token', "Please set 'User API token' value");
        $this->_checkFormValue('app_token', "Please set 'Application API Token' value");
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
        $tpl = initSmartyTplForPopup($this->_centreon_open_tickets_path, $tpl, 'providers/GlpiApi/templates', $this->_centreon_path);
        
        $tpl->assign("centreon_open_tickets_path", $this->_centreon_open_tickets_path);
        $tpl->assign("img_brick", "./modules/centreon-open-tickets/images/brick.png");
        $tpl->assign("header", array("glpi" => _("Glpi")));
        
        // Form
        $address_html = '<input size="50" name="address" type="text" value="' . $this->_getFormValue('address') . '" />';
        $port_html = '<input size="5" name="port" type="text" value="' . $this->_getFormValue('port') . '" />';
        $path_html = '<input size="50" name="path" type="text" value="' . $this->_getFormValue('path') . '" />';
        $user_token_html = '<input size="50" name="user_token" type="text" value="' . $this->_getFormValue('user_token') . '" />';
        $app_token_html = '<input size="50" name="app_token" type="text" value="' . $this->_getFormValue('app_token')  . '" />';
        $https_html = '<input type="checkbox" name="https" value="yes" ' . ($this->_getFormValue('https') == 'yes' ? 'checked' : '') . '/>';
        $timeout_html = '<input size="2" name="timeout" type="text" value="' . $this->_getFormValue('timeout') . '" />';

        $array_form = array(
            'address' => array('label' => _("Address") . $this->_required_field, 'html' => $address_html),
            'port' => array('label' => _("Port"), 'html' => $port_html),
            'path' => array('label' => _("API URL path") . $this->_required_field, 'html' => $path_html),
            'user_token' => array('label' => _("User API token") . $this->_required_field, 'html' => $user_token_html),
            'app_token' => array('label' => _("Application API Token") . $this->_required_field, 'html' => $app_token_html),
            'https' => array('label' => _("Use https"), 'html' => $https_html),
            'timeout' => array('label' => _("Timeout"), 'html' => $timeout_html),
            'mappingticket' => array('label' => _("Mapping ticket arguments")),
        );
        
        // mapping Ticket clone
        $mappingTicketValue_html = '<input id="mappingTicketValue_#index#" name="mappingTicketValue[#index#]" size="20"  type="text" />';
        $mappingTicketArg_html = '<select id="mappingTicketArg_#index#" name="mappingTicketArg[#index#]" type="select-one">' .
        '<option value="' . self::ARG_TITLE . '">' . _('Title') . '</options>' .
        '<option value="' . self::ARG_CONTENT . '">' . _('Content') . '</options>' .
        '<option value="' . self::ARG_ENTITY . '">' . _('Entity') . '</options>' .
        '<option value="' . self::ARG_URGENCY . '">' . _('Urgency') . '</options>' .
        '<option value="' . self::ARG_IMPACT . '">' . _('Impact') . '</options>' .
        '<option value="' . self::ARG_CATEGORY . '">' . _('Category') . '</options>' .
        '<option value="' . self::ARG_USER . '">' . _('User') . '</options>' .
        '<option value="' . self::ARG_USER_EMAIL . '">' . _('User email') . '</options>' .
        '<option value="' . self::ARG_GROUP . '">' . _('Group') . '</options>' .
        '<option value="' . self::ARG_GROUP_ASSIGN . '">' . _('Group assign') . '</options>' .
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
        $this->_save_config['simple']['port'] = $this->_submitted_config['port'];
        $this->_save_config['simple']['path'] = $this->_submitted_config['path'];
        $this->_save_config['simple']['user_token'] = $this->_submitted_config['user_token'];
        $this->_save_config['simple']['app_token'] = $this->_submitted_config['app_token'];
        $this->_save_config['simple']['https'] = (isset($this->_submitted_config['https']) && $this->_submitted_config['https'] == 'yes') ? 
            $this->_submitted_config['https'] : '';
        $this->_save_config['simple']['timeout'] = $this->_submitted_config['timeout'];
        
        $this->_save_config['clones']['mappingTicket'] = $this->_getCloneSubmitted('mappingTicket', array('Arg', 'Value'));
    }
    
    protected function getGroupListOptions() {        
        $str = '<option value="' . self::GPLI_ENTITIES_TYPE . '">Glpi entities</options>' .
        '<option value="' . self::GPLI_GROUPS_TYPE . '">Glpi groups</options>' .
        '<option value="' . self::GLPI_ITIL_CATEGORIES_TYPE . '">Glpi itil categories</options>';
        return $str;
    }
    
    protected function assignGlpiEntities($entry, &$groups_order, &$groups) {
        // no filter $entry['Filter']. preg_match used
        $code = $this->listEntitiesGlpi();
        
        $groups[$entry['Id']] = array('label' => _($entry['Label']) . 
            (isset($entry['Mandatory']) && $entry['Mandatory'] == 1 ? $this->_required_field : ''));
        $groups_order[] = $entry['Id'];
        
        if ($code == -1) {
            $groups[$entry['Id']]['code'] = -1;
            $groups[$entry['Id']]['msg_error'] = $this->ws_error;
            return 0;
        }

        $result = array();
        foreach ($this->glpi_call_response['response']['myentities'] as $row) {
            if (!isset($entry['Filter']) || is_null($entry['Filter']) || $entry['Filter'] == '') {
                $result[$row['id']] = $this->to_utf8($row['name']);
                continue;
            }
            
            if (preg_match('/' . $entry['Filter'] . '/', $row['name'])) {
                $result[$row['id']] = $this->to_utf8($row['name']);
            }
        }
        
        $this->saveSession('glpi_entities', $this->glpi_call_response['response']['myentities']);
        $groups[$entry['Id']]['values'] = $result;
    }
    
    protected function assignGlpiGroups($entry, &$groups_order, &$groups) {
        $filter = null;
        if (isset($entry['Filter']) && !is_null($entry['Filter']) && $entry['Filter'] != '') {
            $filter = $entry['Filter'];
        }
        $code = $this->listGroupsGlpi($filter);
        
        $groups[$entry['Id']] = array('label' => _($entry['Label']) . 
            (isset($entry['Mandatory']) && $entry['Mandatory'] == 1 ? $this->_required_field : ''));
        $groups_order[] = $entry['Id'];
        
        if ($code == -1) {
            $groups[$entry['Id']]['code'] = -1;
            $groups[$entry['Id']]['msg_error'] = $this->ws_error;
            return 0;
        }
        $result = array();
        foreach ($this->glpi_call_response['response'] as $row) {
            $result[$row['id']] = $this->to_utf8($row['completename']);
        }
        
        $this->saveSession('glpi_groups', $this->glpi_call_response['response']);
        $groups[$entry['Id']]['values'] = $result;
    }
    
    protected function assignItilCategories($entry, &$groups_order, &$groups) {
        $filter = null;
        if (isset($entry['Filter']) && !is_null($entry['Filter']) && $entry['Filter'] != '') {
            $filter = $entry['Filter'];
        }
        $code = $this->listItilCategoriesGlpi($filter);
        
        $groups[$entry['Id']] = array('label' => _($entry['Label']) . 
            (isset($entry['Mandatory']) && $entry['Mandatory'] == 1 ? $this->_required_field : ''));
        $groups_order[] = $entry['Id'];
        
        if ($code == -1) {
            $groups[$entry['Id']]['code'] = -1;
            $groups[$entry['Id']]['msg_error'] = $this->ws_error;
            return 0;
        }
        
        $result = array();
        foreach ($this->glpi_call_response['response'] as $row) {
            $result[$row['id']] = $this->to_utf8($row['name']);
        }
        
        $this->saveSession('glpi_itil_categories', $this->glpi_call_response['response']);
        $groups[$entry['Id']]['values'] = $result;
    }
        
    protected function assignOthers($entry, &$groups_order, &$groups) {
        if ($entry['Type'] == self::GPLI_ENTITIES_TYPE) {
            $this->assignGlpiEntities($entry, $groups_order, $groups);
        } else if ($entry['Type'] == self::GPLI_GROUPS_TYPE) {
            $this->assignGlpiGroups($entry, $groups_order, $groups);
        } else if ($entry['Type'] == self::GLPI_ITIL_CATEGORIES_TYPE) {
            $this->assignItilCategories($entry, $groups_order, $groups);
        }
    }
    
    public function validateFormatPopup() {
        $result = array('code' => 0, 'message' => 'ok');
        
        $this->validateFormatPopupLists($result);
        
        return $result;
    }
    
    protected function assignSubmittedValuesSelectMore($select_input_id, $selected_id) {
        $session_name = null;
        foreach ($this->rule_data['clones']['groupList'] as $value) {
            if ($value['Id'] == $select_input_id) {                    
                if ($value['Type'] == self::GPLI_ENTITIES_TYPE) {
                    $session_name = 'glpi_entities';
                } else if ($value['Type'] == self::GPLI_GROUPS_TYPE) {
                    $session_name = 'glpi_groups';
                } else if ($value['Type'] == self::GLPI_ITIL_CATEGORIES_TYPE) {
                    $session_name = 'glpi_itil_categories';
                }
            }
        }
        
        if (is_null($session_name) && $selected_id == -1) {
            return array();
        }
        if ($selected_id == -1) {
            return array('id' => null, 'value' => null);
        }
        
        $result = $this->getSession($session_name);
        
        if (is_null($result)) {
            return array();
        }

        foreach ($result as $value)  {
            if ($value['id'] == $selected_id) {                
                return $value;
            }
        }
        
        return array();
    }
    
    protected function doSubmit($db_storage, $contact, $host_problems, $service_problems, $extra_ticket_arguments=array()) {
        $result = array('ticket_id' => null, 'ticket_error_message' => null, 
            'ticket_is_ok' => 0, 'ticket_time' => time());
        /* Build the short description */
        $title = '';
        for ($i = 0; $i < count($host_problems); $i++) {
            if ($title !== '') {
                $title .= ' | ';
            }
            $title .= $host_problems[$i]['name'];
        }
        for ($i = 0; $i < count($service_problems); $i++) {
            if ($title !== '') {
                $title .= ' | ';
            }
            $title .= $service_problems[$i]['host_name'] . ' - ' . $service_problems[$i]['description'];
        }

        /* Get default body */
        $tpl = $this->initSmartyTemplate();
        $tpl = initSmartyTplForPopup($this->_centreon_open_tickets_path, $tpl, 'providers/Abstract/templates', $this->_centreon_path);
        $tpl->assign("centreon_open_tickets_path", $this->_centreon_open_tickets_path);
        $tpl->assign('user', $contact);
        $tpl->assign('host_selected', $host_problems);
        $tpl->assign('service_selected', $service_problems);
        $this->assignSubmittedValues($tpl);
        $tpl->assign('string', '{$body}');
        $body = $tpl->fetch('eval.ihtml');
        
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
        
        $ticketProperties = array(
            'name' => 'Incident on ' . $title,
            'content' => $body,
            'urgency' => $ticket_arguments[$this->_internal_arg_name[self::ARG_URGENCY]],
            'impact' => $ticket_arguments[$this->_internal_arg_name[self::ARG_IMPACT]],
            'itilcategories_id' => $ticket_arguments[$this->_internal_arg_name[self::ARG_CATEGORY]],
            'entity_id' => $ticket_arguments[$this->_internal_arg_name[self::ARG_ENTITY]],
        );

        $data = array ('input' => $ticketProperties);
        
        $code = $this->createTicketGlpi($data);
        if ($code == -1) {
            $result['ticket_error_message'] = $this->ws_error;
            return $result;
        }
        
        $this->saveHistory(
            $db_storage, 
            $result, 
            array(
                'contact' => $contact, 
                'host_problems' => $host_problems, 
                'service_problems' => $service_problems, 
                'ticket_value' => $this->glpi_call_response['response']['id'], 
                'subject' => 'Incident on ' . $title, 
                'data_type' => self::DATA_TYPE_JSON, 
                'data' => json_encode($ticket_arguments)
            )
        );
        return $result;
    }

    /*
     *
     * Rest API Calls
     *
     */
    protected function setWsError($error) {
        $this->ws_error = $error;
    }

    protected function loginGlpi() {
        $proto = 'http';
        $ssl = 0;

        if (isset($this->rule_data['https']) && $this->rule_data['https'] == 'yes') {
            $proto = 'https';
            $ssl = 1;
        }

        $url = $proto . '://' . $this->rule_data['address'] . ":" . $this->rule_data['port'] . $this->rule_data['path'];
        $url = trim($url, '/') . '/initSession';

        /* Add content type to headers */
        $headers[] = 'Content-type: ' . $this->contentType;
        $headers[] = 'Authorization: user_token: ' . trim($this->rule_data['user_token']);
        $headers[] = 'App-Token: ' . trim($this->rule_data['app_token']);
        $headers[] = 'Connection: close';

        $curlOptions = array(
            CURLOPT_URL => $url,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_HEADER => false,
            CURLOPT_CONNECTTIMEOUT => trim($this->rule_data['timeout']),
            CURLOPT_TIMEOUT => trim($this->rule_data['timeout']),
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPGET => true,
            CURLOPT_SSL_VERIFYPEER => false,
        );

        $ch = curl_init();
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

        $this->_glpi_connected = 1;
        $this->_glpi_session = $data['session_token'];
        return 0;
    }

    protected function logoutGlpi() {
        if ($this->_glpi_connected == 0) {
            return 0;
        }

        $proto = 'http';
        $ssl = 0;

        if (isset($this->rule_data['https']) && $this->rule_data['https'] == 'yes') {
            $proto = 'https';
            $ssl = 1;
        }

        $url = $proto . '://' . $this->rule_data['address'] . ":" . $this->rule_data['port'] . $this->rule_data['path'];
        $url = trim($url, '/') . '/killSession';

        /* Add content type to headers */
        $headers[] = 'Content-type: ' . $this->contentType;
        $headers[] = 'Session-Token: ' . $this->_glpi_session;
        $headers[] = 'App-Token: ' . trim($this->rule_data['app_token']);
        $headers[] = 'Connection: close';

        $curlOptions = array(
            CURLOPT_URL => $url,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_HEADER => false,
            CURLOPT_CONNECTTIMEOUT => trim($this->rule_data['timeout']),
            CURLOPT_TIMEOUT => trim($this->rule_data['timeout']),
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPGET => true,
            CURLOPT_SSL_VERIFYPEER => false,
        );

        $ch = curl_init();
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

        $this->_glpi_connected = 0;
        $this->_glpi_session = null;
        return 0;
    }

    protected function request($method, $uri, $data = null) {
        $array_result = array('code' => -1);

        $proto = 'http';
        $ssl = 0;

        if (isset($this->rule_data['https']) && $this->rule_data['https'] == 'yes') {
            $proto = 'https';
            $ssl = 1;
        }

        $url = $proto . '://' . $this->rule_data['address'] . ":" . $this->rule_data['port'] . $this->rule_data['path'] . $uri;

        /* Add content type to headers */
        $headers[] = 'Content-type: ' . $this->contentType;
        $headers[] = 'Session-Token: ' . $this->_glpi_session;
        $headers[] = 'App-Token: ' . trim($this->rule_data['app_token']);
        $headers[] = 'Connection: close';

        $ch = curl_init();
        if ($ch == false) {
            $this->setWsError("Cannot init curl object");
            return $array_result;
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->rule_data['timeout']);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->rule_data['timeout']);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        switch ($method) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                break;
            case 'GET':
                curl_setopt($ch, CURLOPT_HTTPGET, true);
                break;
            default:
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
                break;
        }

        if (!is_null($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $result = curl_exec($ch);
        if ($result == false) {
            $this->setWsError(curl_error($ch));
            return $array_result;
        }

        curl_close($ch);
        $decodedContent = json_decode($result, true);
        if (!$decodedContent) {
            $this->setWsError($result);
            return $array_result;
        }

        if (!preg_match('/ERROR/', $decodedContent[0])) {
            $array_result['response'] = $decodedContent;
            $array_result['code'] = 0;
        } else {
            $this->setWsError($decodedContent[1]);
        }
        return $array_result;
    }

    protected function listEntitiesGlpi() {
        if ($this->_glpi_connected == 0) {
            if ($this->loginGlpi() == -1) {
                return -1;
            }
        }

        $this->glpi_call_response = $this->request('GET', '/getMyEntities/');
        if ($this->glpi_call_response['code'] == -1) {
            return -1;
        }

        return 0;
    }
    
    protected function listGroupsGlpi($filter=null) {
        if ($this->_glpi_connected == 0) {
            if ($this->loginGlpi() == -1) {
                return -1;
            }
        }

        $this->glpi_call_response = $this->request('GET', '/Group/?name=' . $filter);
        if ($this->glpi_call_response['code'] == -1) {
            return -1;
        }

        return 0;
    }

    protected function listItilCategoriesGlpi($filter=null) {
        if ($this->_glpi_connected == 0) {
            if ($this->loginGlpi() == -1) {
                return -1;
            }
        }

        $this->glpi_call_response = $this->request('GET', '/itilcategory/?name' . $filter);
        if ($this->glpi_call_response['code'] == -1) {
            return -1;
        }

        return 0;
    }

    protected function createTicketGlpi($data) {
        if ($this->_glpi_connected == 0) {
            if ($this->loginGlpi() == -1) {
                return -1;
            }
        }
  
        $this->glpi_call_response = $this->request('POST', '/Ticket/', $data);
        if ($this->glpi_call_response['code'] == -1) {
            return -1;
        }

        return 0;
    }
}
