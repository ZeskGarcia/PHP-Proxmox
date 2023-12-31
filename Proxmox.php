<?php

namespace Proxmox;

class Proxmox {
    private $apiUrl;
    private $username;
    private $password;
    private $realm;
    private $ticket;
    private $CSRFPreventionToken;
  
    public function __construct($config)
    {
        if (is_array($config)) {
            $this->apiUrl = "https://" . $config['url'] . ":8006/api2/json";
            $this->username = $config['user'];
            $this->password = $config['pass'];
            $this->realm = $config['realm'];
        } else
            return false;
    }

    public function login()
    {
      $authUrl = $this->apiUrl . '/access/ticket';
      
      $authData = [
          'username' => $this->username,
          'password' => $this->password,
          'realm' => $this->realm
      ];
      
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $authUrl);
      curl_setopt($ch, CURLOPT_POST, 1);
      curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($authData));
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      $response = curl_exec($ch);
      curl_close($ch);
      
      $authResult = json_decode($response, true);
      
      if (isset($authResult['data']['ticket'])) {
        $this->ticket = $authResult['data']['ticket'];
        $this->CSRFPreventionToken = $authResult['data']['CSRFPreventionToken'];
        return true;
      } else 
        return false;
    }

    public function Request($url, $method = 'GET', $data = [])
    {
        $ch = curl_init();
        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'CSRFPreventionToken: ' . $this->CSRFPreventionToken,
            ],
            CURLOPT_COOKIE => "PVEAuthCookie=" . $this->ticket
        ];

        if ($method === 'POST') {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = http_build_query($data);
        }

        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);
        curl_close($ch);
    
        return $response;
    }
    
    public function getNodes()
    {
        if (empty($this->ticket) || empty($this->CSRFPreventionToken))
            if (!$this->login())
                return false;
        
        $apiURL = $this->apiUrl . '/nodes';

        $response = $this->Request($apiURL);

        $result = json_decode($response, 1);

        if (isset($result['data']))
            return $result['data'];
        else
            return false;
    }

    public function getNodeInfo($nodeName)
    {
        if (empty($this->ticket) || empty($this->CSRFPreventionToken))
            if (!$this->login())
                return false;

        $apiURL = $this->apiUrl . '/nodes/' . $nodeName . '/status';
        $response = $this->Request($apiURL);
        $result = json_decode($response, true);
        if (isset($result['data'])) 
            return $result['data'];
        else
            return false;
    }

    public function shutdownVM($node, $vmId)
    {
        if (empty($this->ticket) || empty($this->CSRFPreventionToken))
            if (!$this->login())
                return false;
        else
            if (empty($node) || empty($vmId))
                return false;

        $apiURL = $this->apiUrl . '/nodes/' . $node . '/qemu/' . $vmId . '/status/shutdown';

        $requestOptions = [
            CURLOPT_CUSTOMREQUEST => 'POST'
        ];

        $request = $this->Request($apiURL, 'POST', []);

        $result = json_decode($request, true);

        if (isset($result['data']) && $result['data'] == 'OK')
            return true;
        else
            return false;
    }

    public function startVM($node, $vmId)
    {
        if (empty($this->ticket) || empty($this->CSRFPreventionToken))
            if (!$this->login())
                return false;
        else
            if (empty($node) || empty($vmId))
                return false;
        
        $apiURL = $this->apiUrl . '/nodes/' . $node . '/qemu/' . $vmId . '/status/start';

        $request = $this->Request($apiURL, 'POST', []);

        $result = json_decode($request, true);
        
        if (isset($result['data']) && $result['data'] == 'OK')
            return true;
        else 
            return false;
    }

    public function stopVM($node, $vmId)
    {
        if (empty($this->ticket) || empty($this->CSRFPreventionToken))
            if (!$this->login())
                return false;
        else
            if (empty($node) || empty($vmId))
                return false;
        
        $apiURL = $this->apiUrl . '/nodes/' . $node . '/qemu/' . $vmId . '/status/stop';

        $request = $this->Request($apiURL, 'POST', []);

        $result = json_decode($request, true);
        
        if (isset($result['data']) && $result['data'] == 'OK')
            return true;
        else 
            return false;
    }

    public function getPVEVersion()
    {

        if (empty($this->ticket) || empty($this->CSRFPreventionToken))
            if (!$this->login())
                return false;
        
        $apiURL = $this->apiURL . '/version';

        $request = $this->Request($apiURL, 'GET', []);

        $result = json_decode($request, true);
        
        if (isset($result['data']))
            return $result['data']['version'];
        else 
            return false;
    }
}
