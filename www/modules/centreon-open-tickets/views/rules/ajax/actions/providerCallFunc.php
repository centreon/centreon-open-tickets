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

$resultat = array(
  "code" => 0,
  "msg" => 'ok'
);

if (is_null($get_information['provider_id'])) {
  $resultat['code'] = 1;
  $resultat['msg'] = 'Please set provider_id';
  return ;
}

if (is_null($get_information['provider_func'])) {
  $resultat['code'] = 1;
  $resultat['msg'] = 'Please set provider_func';
  return ;
}

foreach ($register_providers as $name => $id) {
  if ($id == $get_information['provider_id']) {
      $provider_name = $name;
      break;
  }
}

if (is_null($provider_name) || !file_exists($centreon_open_tickets_path . 'providers/' . $provider_name . '/' . $provider_name . 'Provider.class.php')) {
  $resultat['code'] = 1;
  $resultat['msg'] = 'Please set a provider';
}

require_once $centreon_open_tickets_path . 'providers/' . $provider_name . '/' . $provider_name . 'Provider.class.php';

$classname = $provider_name . 'Provider';
$centreon_provider = new $classname($rule, $centreon_path, $centreon_open_tickets_path, $get_information['rule_id'], null, $get_information['provider_id']);

$method = $get_information['provider_func'];
if (!method_exists($centreon_provider, $method)) {
  $resultat['code'] = 1;
  $resultat['msg'] = 'The provider function does not exists.';
  return;
}

try {
  $data = array();
  if (array_key_exists('provider_data', $get_information)) {
    $data = $get_information['provider_data'];
  }
  $resultat['data'] = $centreon_provider->$method($data);
} catch (\Exception $e) {
  $resultat['code'] = 1;
  $resultat['msg'] = $e->getMessage();
}
