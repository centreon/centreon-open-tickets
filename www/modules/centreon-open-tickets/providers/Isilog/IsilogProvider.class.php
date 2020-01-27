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

class IsilogProvider extends AbstractProvider {
    protected $_close_advanced = 1;
    protected $_proxy_enabled = 1;

    const ARG_CONTENT = 1;
    const ARG_TITLE = 2;

    protected $_internal_arg_name = array(
        self::ARG_CONTENT => 'content',
        self::ARG_TITLE => 'title'
    );

    /*
    * Set default values for our rule form options
    *
    * @return {void}
    */
    protected function _setDefaultValueExtra() {
        $this->default_data['address'] = '127.0.0.1';
        $this->default_data['protocol'] = 'http';
        $this->default_data['user'] = '';
        $this->default_data['password'] = '';
        $this->default_data['database'] = '';
        $this->default_data['timeout'] = 60;

        $this->default_data['clones']['mappingTicket'] = array(
            array(
                'Arg' => self::ARG_TITLE,
                'Value' => 'Issue {include file="file:$centreon_open_tickets_path/providers' .
                    '/Abstract/templates/display_title.ihtml"}'
            ),
            array(
                'Arg' => self::ARG_CONTENT,
                'Value' => '{$body}'
            )
        );
    }

    /*
    * Set default values for the widget popup when opening a ticket
    *
    * @return {void}
    */
    protected function _setDefaultValueMain($body_html = 0) {
        parent::_setDefaultValueMain($body_html);

        $this->default_data['url'] = '{$protocol}://{$address}/';
    }

    /*
    * Verify if every mandatory form field is filled with data
    *
    * @return {void}
    *
    * @throw \Exception when a form field is not set
    */
    protected function _checkConfigForm() {
        $this->_check_error_message = '';
        $this->_check_error_message_append = '';

        $this->_checkFormValue('address', 'Please set "Address" value');
        $this->_checkFormValue('database', 'Please set "Database name" value');
        $this->_checkFormValue('protocol', 'Please set "Protocol" value');
        $this->_checkFormValue('username', 'Please set "Username" value');
        $this->_checkFormValue('password', 'Please set "Password" value');
        $this->_checkFormInteger('timeout', '"Timeout" must be an integer');

        $this->_checkLists();

        if ($this->_check_error_message != '') {
            throw new Exception($this->_check_error_message);
        }
    }

    /*
    * Initiate your html configuration and let Smarty display it in the rule form
    *
    * @return {void}
    */
    protected function _getConfigContainer1Extra() {
        // initiate smarty and a few variables.
        $tpl = new Smarty();
        $tpl = initSmartyTplForPopup($this->_centreon_open_tickets_path, $tpl, 'providers/Isilog/templates',
            $this->_centreon_path);
        $tpl->assign('centreon_open_tickets_path', $this->_centreon_open_tickets_path);
        $tpl->assign('img_brick', './modules/centreon-open-tickets/images/brick.png');
        $tpl->assign('header', array('Isilog' => _("Isilog")));
        $tpl->assign('webServiceUrl', './api/internal.php');

        /*
        * we create the html that is going to be displayed
        */
        $address_html = '<input size="50" name="address" type="text" value="' .
            $this->_getFormValue('address') .'" />';
        $username_html = '<input size="50" name="username" type="text" value="' .
            $this->_getFormValue('username') . '" />';
        $protocol_html = '<input size="50" name="protocol" type="text" value="' .
            $this->_getFormValue('protocol') . '" />';
        $password_html = '<input size="50" name="password" type="text" value="' .
            $this->_getFormValue('password') . '" autocomplete="off" />';
        $timeout_html = '<input size="50" name="timeout" type="text" value="' .
            $this->_getFormValue('timeout') . '" :>';
        $database_html = '<input size="50" name="database" type="text" value="' .
            $this->_getFormValue('database') . '" />';

        // this array is here to link a label with the html code that we've wrote above
        $array_form = array(
            'address' => array(
                'label' => _('Address') . $this->_required_field,
                'html' => $address_html
            ),
            'username' => array(
                'label' => _('Username') . $this->_required_field,
                'html' => $username_html
            ),
            'protocol' => array(
                'label' => _('Protocol') . $this->_required_field,
                'html' => $protocol_html
            ),
            'password' => array(
                'label' => _('Password') . $this->_required_field,
                'html' => $password_html
            ),
            'database' => array(
                'label' => _('Database name') . $this->_required_field,
                'html' => $database_html
            ),
            'timeout' => array(
                'label' => _('Timeout'),
                'html' => $timeout_html
            ),
            //we add a key to our array
            'mappingTicketLabel' => array(
                'label' => _('Mapping ticket arguments')
            )
        );

        // html
        $mappingTicketValue_html = '<input id="mappingTicketValue_#index#" ' .
        'name="mappingTicketValue[#index#]" size="20" type="text"';

        // html code for a dropdown list where we will be able to select something from the following list
        $mappingTicketArg_html = '<select id="mappingTicketArg_#index#" ' .
            'name="mappingTicketArg[#index#]" type="select-one">' .
            '<option value="' . self::ARG_TITLE . '">' . _('Title') . '</option>' .
            '<option value="' . self::ARG_CONTENT . '">' . _('Content') . '</option>' .
            '</select>';

        // we asociate the label with the html code but for the arguments that we've been working on lately
        $array_form['mappingTicket'] = array(
          array(
            'label' => _('Argument'),
            'html' => $mappingTicketArg_html
          ),
          array(
            'label' => _('Value'),
            'html' => $mappingTicketValue_html
          )
        );

        $tpl->assign('form', $array_form);
        $this->_config['container1_html'] .= $tpl->fetch('conf_container1extra.ihtml');
        $this->_config['clones']['mappingTicket'] = $this->_getCloneValue('mappingTicket');
    }

    protected function _getConfigContainer2Extra() {}

    /*
    * Saves the rule form in the database
    *
    * @return {void}
    */
    protected function saveConfigExtra() {
        $this->_save_config['simple']['address'] = $this->_submitted_config['address'];
        $this->_save_config['simple']['username'] = $this->_submitted_config['username'];
        $this->_save_config['simple']['protocol'] = $this->_submitted_config['protocol'];
        $this->_save_config['simple']['password'] = $this->_submitted_config['password'];
        $this->_save_config['simple']['database'] = $this->_submitted_config['database'];
        $this->_save_config['simple']['timeout'] = $this->_submitted_config['timeout'];

        // saves the ticket arguments
        $this->_save_config['clones']['mappingTicket'] = $this->_getCloneSubmitted('mappingTicket', array('Arg', 'Value'));
    }

    /*
    * checks if all mandatory fields have been filled
    *
    * @return {array} telling us if there is a missing parameter
    */
    public function validateFormatPopup() {
        $result = array('code' => 0, 'message' => 'ok');
        $this->validateFormatPopupLists($result);

        return $result;
    }

    /*
    * brings all parameters together in order to build the ticket arguments and save
    * ticket data in the database
    *
    * @param {object} $db_storage centreon storage database informations
    * @param {array} $contact centreon contact informations
    * @param {array} $host_problems centreon host information
    * @param {array} $service_problems centreon service information
    * @param {array} $extraTicketArguments
    *
    * @return {array} $result will tell us if the submit ticket action resulted in a ticket being opened
    */
    protected function doSubmit($db_storage, $contact, $host_problems, $service_problems, $extraTicketArguments=array()) {
        // initiate a result array
        $result = array(
            'ticket_id' => null,
            'ticket_error_message' => null,
            'ticket_is_ok' => 0,
            'ticket_time' => time()
        );

        // initiate smarty variables
        $tpl = new Smarty();
        $tpl = initSmartyTplForPopup($this->_centreon_open_tickets_path, $tpl, 'providers/Abstract/templates',
        $this->_centreon_path);

        $tpl->assign('centreon_open_tickets_path', $this->_centreon_open_tickets_path);
        $tpl->assign('user', $contact);
        $tpl->assign('host_selected', $host_problems);
        $tpl->assign('service_selected', $service_problems);
        // assign submitted values from the widget to the template
        $this->assignSubmittedValues($tpl);

        $ticketArguments = $extraTicketArguments;
        if (isset($this->rule_data['clones']['mappingTicket'])) {
            // for each ticket argument in the rule form, we retrieve its value
            foreach ($this->rule_data['clones']['mappingTicket'] as $value) {
                $tpl->assign('string', $value['Value']);
                $resultString = $tpl->fetch('eval.ihtml');
                if ($resultString == '') {
                    $resultstring = null;
                }
                $ticketArguments[$this->_internal_arg_name[$value['Arg']]] = $resultString;
            }
        }

        // we try to open the ticket
        try {
            $ticketId = $this->createTicket($ticketArguments);
        } catch (\Exception $e) {
            $result['ticket_error_message'] = $e->getMessage();
            return $result;
        }

        // we save ticket data in our database
        $this->saveHistory($db_storage, $result, array(
            'contact' => $contact,
            'host_problems' => $host_problems,
            'service_problems' => $service_problems,
            'ticket_value' => $ticketId,
            'subject' => $ticketArguments[self::ARG_TITLE],
            'data_type' => self::DATA_TYPE_JSON,
            'data' => json_encode($ticketArguments)
        ));

        return $result;
    }

    /*
    * handle ticket creation in isilog
    *
    * @params {array} $ticketArguments contains all the ticket arguments
    *
    * @return {string} $ticketId ticket id
    *
    * throw \Exception if we can't open a ticket
    * throw \Exception if the soap webservice return an error
    */
    protected function createTicket($ticketArguments) {
        // L_TITRENEWS , title
        // DE_SYMPAPPEL , body
        // IDT_APPEL , ticket id

        $soapInfo = array(
            'action' => 'http://isilog.fr/IsiAddAndGetCall',
            'webservice' => 'webservices/isihelpdeskservice.asmx',
            'isicallprogram' => 'IsiAddAndGetCall',
            'body' => '<soapenv:Body>
                    <isil:IsiAddAndGetCall>
                        <isil:pIsiCallEntity>
                            <isil:IsiFields>
                                <isil:IsiWsDataField>
                                    <isil:IsiField>L_TITRENEWS</isil:IsiField>
                                    <isil:IsiValue>' . $ticketArguments['title'] . '</isil:IsiValue>
                                </isil:IsiWsDataField>
                                <isil:IsiWsDataField>
                                    <isil:IsiField>DE_SYMPAPPEL</isil:IsiField>
                                    <isil:IsiValue>' . $ticketArguments['content'] . '</isil:IsiValue>
                                </isil:IsiWsDataField>
                            </isil:IsiFields>
                        </isil:pIsiCallEntity>
                    </isil:IsiAddAndGetCall>
                </soapenv:Body>'
        );


        // open ticket in isilog and put result in a SimpleXMLElement Object
        try {
            $this->isilogCallResult = simplexml_load_string($this->callWebservice($soapInfo));
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage(), $e->getCode());
        }

        // extract the xml with the data from the soap envelope
        $xmlResult=$this->isilogCallResult->children('soap', true)->Body->children()->IsiAddAndGetCallResponse->IsiAddAndGetCallResult;
        $fixEncoding = preg_replace('/encoding="utf-16"/i', 'encoding="utf-8"', $xmlResult[0]);
        $ticketData = simplexml_load_string($fixEncoding);

        // check if soap webservice returned an error
        if ($ticketData->children()->Statut[0] != 'responseOk') {
            throw new \Exception($ticketData->children()->Trace[0], 12);
        }

        foreach ($ticketData->children()->Objects->anyType->IsiFields->IsiWsDataField as $isiField) {
            if ($isiField->IsiField == 'IDT_APPEL') {
                $humanTicketId = $isiField->IsiValue;
            }

            if ($isiField->IsiField == 'NO_APPEL') {
                $isilogTicketId = $isiField->IsiValue;
            }
        }

        $ticketId = $humanTicketId   . '_' . $isilogTicketId;
        // $ticketId = $humanTicketId;

        return $ticketId;
    }

    protected function callWebservice($soapInfo) {
        $soapHeader = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:isil="http://isilog.fr">
                <soapenv:Header>
                  <isil:IsiWsAuthHeader>
                     <isil:IsiCallProgram>' . $soapInfo['isicallprogram'] . '</isil:IsiCallProgram>
                     <isil:IsiDataBaseID>' . $this->rule_data['database'] . '</isil:IsiDataBaseID>
                     <isil:IsiLogin>' . $this->rule_data['username'] . '</isil:IsiLogin>
                     <isil:IsiPassword>' . $this->rule_data['password'] . '</isil:IsiPassword>
                  </isil:IsiWsAuthHeader>
                </soapenv:Header>';

        $curlEndpoint = $this->rule_data['protocol'] . '://' . $this->rule_data['address'] . '/' . $soapInfo['webservice'];

        $soapEnvelope = $soapHeader . $soapInfo['body'] . '</soapenv:Envelope>';
        $curlHeader = array(
            'Content-Type: text/xml;charset=UTF-8',
            'SOAPAction: ' . $soapInfo['action'],
            'Content-Length: ' . strlen($soapEnvelope)
        );


        // initiate our curl options
        $curl = curl_init($curlEndpoint);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $curlHeader);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($curl, CURLOPT_TIMEOUT, $this->rule_data['timeout']);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $soapEnvelope);

        // if proxy is set, we add it to curl
        if ($this->_getFormValue('proxy_address') != '' && $this->_getFormValue('proxy_port') != '') {
            curl_setopt($curl, CURLOPT_PROXY, $this->_getFormValue('proxy_address') . ':' . $this->_getFormValue('proxy_port'));
            // if proxy authentication configuration is set, we add it to curl
            if ($this->_getFormValue('proxy_username') != '' && $this->_getFormValue('proxy_password') != '') {
                curl_setopt($curl, CURLOPT_PROXYUSERPWD, $this->_getFormValue('proxy_username') . ':' . $this->_getFormValue('proxy_password'));
            }
        }

        $file = fopen("/var/opt/rh/rh-php72/log/php-fpm/isilog_caro", "a") or die ("Unable to open file!");
        fwrite($file, print_r($soapEnvelope,true));
        fclose($file);
        $curlResult = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        $file = fopen("/var/opt/rh/rh-php72/log/php-fpm/isilog_call_result", "a") or die ("Unable to open file!");
        fwrite($file, print_r($curlResult,true));
        fclose($file);
        if ($httpCode > 301) {
            throw new Exception('curl result: ' . $curlResult . '|| HTTP return code: ' . $httpCode, 11);
        }

        return $curlResult;
    }

    /*
    * close a ticket in Glpi
    *
    * @params {string} $ticketId the ticket id
    *
    * @return {bool}
    *
    * throw \Exception if it can't close the ticket
    */
    protected function closeTicketIsilog($ticketId) {

        preg_match('/(\w+)_(\w+)/', $ticketId, $matches);
        // $soapBody='<soapenv:Body>
        //         <isil:IsiCloseWithoutResponse>
        //             <isil:codeNature>' . $matches[1] . '</isil:codeNature>
        //             <isil:nbJoursEcheance>0</isil:nbJoursEcheance>
        //         </isil:IsiCloseWithoutResponse>
        //     </soapenv:Body>';

        $soapInfo = array(
            'action' => 'http://isilog.fr/IsiUpdateAndGetCall',
            'webservice' => 'webservices/isihelpdeskservice.asmx',
            'isicallprogram' => 'IsiUpdateAndGetCall',
            'body' => '<soapenv:Body>
                    <isil:IsiUpdateAndGetCall>
                        <isil:pIsiCallEntity>
                            <isil:IsiFields>
                                <isil:IsiWsDataField>
                                    <isil:IsiField>NO_APPEL</isil:IsiField>
                                    <isil:IsiValue>' . $matches[2] . '</isil:IsiValue>
                                </isil:IsiWsDataField>
                                <isil:IsiWsDataField>
                                    <isil:IsiField>C_ETAT</isil:IsiField>
                                    <isil:IsiValue>RESOLU</isil:IsiValue>
                                </isil:IsiWsDataField>
                            </isil:IsiFields>
                        </isil:pIsiCallEntity>
                    </isil:IsiUpdateAndGetCall>
                </soapenv:Body>');

        try {
            $this->isilogCallResult = $this->callWebservice($soapInfo);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage(), $e->getCode());
        }

        return 0;
    }

    /*
    * check if the close option is enabled, if so, try to close every selected ticket
    *
    * @param {array} $tickets
    *
    * @return {void}
    */
    public function closeTicket(&$tickets) {
        if ($this->doCloseTicket()) {
            foreach ($tickets as $k => $v) {
                try {
                    $this->closeTicketIsilog($k);
                    $tickets[$k]['status'] = 2;
                } catch (\Exception $e) {
                    $tickets[$k]['status'] = -1;
                    $tickets[$k]['msg_error'] = $e->getMessage();
                }
            }
        } else {
            parent::closeTicket($tickets);
        }
    }
}
