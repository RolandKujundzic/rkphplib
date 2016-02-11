<?php

namespace rkphplib\lib;

require_once(dirname(__DIR__).'/XML.class.php');


/**
 * Execute API call.
 * 
 * Configuration keys are:
 *  - api_url: required e.g. https://domain.tld/api/v1.0
 *  - api_token: required e.g. iSFxH73p91Klm
 *  - api_auth:  request|header|basic_auth
 *  - content: GET/POST or php://input (application/json or application/xml)
 *  - accept: Result format (application/json = default, application/jsonp, or application/xml)
 * 
 * If api_auth method is basic_auth use api_token = login:password.
 * Use conf.accept=application/xml and conf.content=application/xml to send and receive xml.
 *
 * @param map $conf 
 * @param string $method (GET|POST|PUT|DELETE|PATCH)
 * @param string $uri e.g. do/somthing
 * @param map|string $data use xml if content=application/xml (default = null)
 * @return map|string decode json result if accept is empty or accept=application/json
 */
function api_call($conf, $method, $uri, $data = null) {
  $ch = curl_init();

  if (!in_array($method, array('GET', 'POST', 'PUT', 'DELETE', 'PATCH'))) {
    throw new Exception("Invalid API method [$method] use GET|POST|PUT|DELETE|PATCH");
  }

  $header = array();

  if (!empty($conf['api_token'])) {
    if ($conf['api_auth'] == 'request' && is_array($data)) {
      $data['api_token'] = $conf['api_token'];
    }
    else if ($conf['api_auth'] == 'header') {
      array_push($header, 'X-AUTH-TOKEN: '.$conf['api_token']);
    }
    else if ($conf['api_auth'] == 'basic_auth') {
      curl_setopt($ch, CURLOPT_USERPWD, $conf['api_token']);
    }
  }

  if (!empty($conf['accept'])) {
    array_push($header, 'ACCEPT: '.$conf['accept']);
  }

  if (!empty($conf['content'])) {
    array_push($header, 'CONTENT-TYPE: '.$conf['content']);
    if (is_string($data)) {
      // raw data request
      array_push($header, 'X-HTTP-Method-Override: '.$method);

			if ($conf['content'] == 'application/xml' && is_array($data)) {
				$data = XML::fromJSON($data);
			}

      curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    }
  }

  if (count($header) > 0) {
    curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
  }

  if ($method == 'GET') {
    if (is_array($data) && count($data) > 0) {
      $uri .= '?'.http_build_query($data);
    }
  }
  else {
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

    if (($method == 'PUT' || $method == 'DELETE') && is_array($data) && count($data) > 0) {
      $data = http_build_query($data);
    }

    if (!is_null($data)) {
      curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    }
  }

  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
  curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_URL, $conf['api_url'].'/'.$uri);

  $result = curl_exec($ch);
  curl_close($ch);

  if (empty($conf['accept']) || $conf['accept'] != 'application/json') {
    $result = json_decode($result, true);
  }

  return $result;
}

