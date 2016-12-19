<?php

namespace MichelMelo\OneSignal;

use GuzzleHttp\Client;

class OneSignalClient
{
    const API_URL = "https://onesignal.com/api/v1";

    const ENDPOINT_NOTIFICATIONS = "/notifications";
    const ENDPOINT_PLAYERS = "/players";

    private $client;
    private $headers;
    private $appId;
    private $restApiKey;
    private $userAuthKey;
    private $additionalParams;

    /**
     * @var bool
     */
    public $requestAsync = false;

    /**
     * @var Callable
     */
    private $requestCallback;

    /**
     * Turn on, turn off async requests
     *
     * @param bool $on
     * @return $this
     */
    public function async($on = true)
    {
        $this->requestAsync = $on;
        return $this;
    }

    /**
     * Callback to execute after OneSignal returns the response
     * @param Callable $requestCallback
     * @return $this
     */
    public function callback(Callable $requestCallback)
    {
        $this->requestCallback = $requestCallback;
        return $this;
    }

    public function __construct($appId, $restApiKey, $userAuthKey)
    {
        $this->appId = $appId;
        $this->restApiKey = $restApiKey;
        $this->userAuthKey = $userAuthKey;

        $this->client = new Client();
        $this->headers = ['headers' => []];
        $this->additionalParams = [];
    }

    public function testCredentials() {
        return "APP ID: ".$this->appId." REST: ".$this->restApiKey;
    }

    private function requiresAuth() {
        $this->headers['headers']['Authorization'] = 'Basic '.$this->restApiKey;
    }

    private function usesJSON() {
        $this->headers['headers']['Content-Type'] = 'application/json';
    }

    public function addParams($params = [])
    {
        $this->additionalParams = $params;

        return $this;
    }

    public function setParam($key, $value)
    {
        $this->additionalParams[$key] = $value;

        return $this;
    }

    public function sendNotificationToUser($message, $userId, $url = null, $data = null, $buttons = null) {
        $contents = array(
            "en" => $message
        );

        $params = array(
            'app_id' => $this->appId,
            'contents' => $contents,
            'include_player_ids' => array($userId)
        );

        if (isset($url)) {
            $params['url'] = $url;
        }

        if (isset($data)) {
            $params['data'] = $data;
        }

        if (isset($button)) {
            $params['buttons'] = $buttons;
        }

        $this->sendNotificationCustom($params);
    }

    public function sendNotificationToAll($message, $title = null, $url = null, $data = null, $buttons = null) {
        $contents = array(
            "en" => $message
        );
        $heading = ($title == null) ? " " : $title;
        $headings =  array(
                "en" => $heading
                );
        $subtitle = array(
                "en"=> $heading
                );

        $params = array(
            'app_id' => $this->appId,
            'contents' => $contents,
            'headings' => $headings,
            'subtitle' => $subtitle,
            'included_segments' => array('All')
        );
        if (isset($url)) {
            $params['url'] = $url;
        }

        if (isset($data)) {
            $params['data'] = $data;
        }

        if (isset($button)) {
            $params['buttons'] = $buttons;
        }
        //dd($params);
        $this->sendNotificationCustom($params);
    }

    public function sendNotificationToSegment($message, $segment, $url = null, $data = null, $buttons = null) {
        $contents = array(
            "en" => $message
        );

        $params = array(
            'app_id' => $this->appId,
            'contents' => $contents,
            'included_segments' => [$segment]
        );

        if (isset($url)) {
            $params['url'] = $url;
        }

        if (isset($data)) {
            $params['data'] = $data;
        }

        if (isset($button)) {
            $params['buttons'] = $buttons;
        }

        $this->sendNotificationCustom($params);
    }

    /**
     * Send a notification with custom parameters defined in
     * https://documentation.onesignal.com/v2.0/docs/notifications-create-notification
     * @param array $parameters
     * @return mixed
     */
    public function sendNotificationCustom($parameters = []){
        $this->requiresAuth();
        $this->usesJSON();

        // Make sure to use app_id
        $parameters['app_id'] = $this->appId;

        // Make sure to use included_segments
        if (empty($parameters['included_segments']) && empty($parameters['include_player_ids'])) {
            $parameters['included_segments'] = ['all'];
        }

        $parameters = array_merge($parameters, $this->additionalParams);

        $this->headers['body'] = json_encode($parameters);
        $this->headers['buttons'] = json_encode($parameters);
        $this->headers['verify'] = false;
        return $this->post(self::ENDPOINT_NOTIFICATIONS);
    }

    /**
     * Creates a user/player
     *
     * @param array $parameters
     * @return mixed
     * @throws \Exception
     */
    public function createPlayer(Array $parameters) {
        if(!isset($parameters['device_type']) or !is_numeric($parameters['device_type'])) {
            throw new \Exception('The `device_type` param is required as integer to create a player(device)');
        }
        return $this->sendPlayer($parameters, 'POST', self::ENDPOINT_PLAYERS);
    }

    /**
     * Edit a user/player
     *
     * @param array $parameters
     * @return mixed
     */
    public function editPlayer(Array $parameters) {
        return $this->sendPlayer($parameters, 'PUT', self::ENDPOINT_PLAYERS . '/' . $parameters['id']);
    }

    /**
     * Create or update a by $method value
     *
     * @param array $parameters
     * @param $method
     * @param $endpoint
     * @return mixed
     */
    private function sendPlayer(Array $parameters, $method, $endpoint)
    {
        $this->requiresAuth();
        $this->usesJSON();

        $parameters['app_id'] = $this->appId;
        $this->headers['body'] = json_encode($parameters);

        $method = strtolower($method);

        return $this->{$method}($endpoint);
    }
    /**
     * Get a set of Players from an App
     *
     * @param string $app_id Application ID
     * @param int    $limit
     * @param int    $offset
     * @return \Psr\Http\Message\ResponseInterface OneSignal API response
     */
    public function getPlayers($app_id = '', $limit = 300, $offset = 0)
    {
        $this->requiresAuth();
        $this->usesJSON();
        //$headers = $this->headerInit(false, true);
        if ($app_id == '') {
            $app_id = $this->appId;
        }
        $data = ["app_id" => $app_id];
        if ($limit) {
            $data[ 'limit' ] = $limit;
        }
        if ($offset) {
            $data[ 'offset' ] = $offset;
        }
        $headers[ 'query' ] = $data;
        //dd($headers );
        $parameters['app_id'] = $this->appId;
        
        return $this->get("players", $headers);
    }

    protected function get($endPoint, $headers = [])
    {
        $this->requiresAuth();
        $this->usesJSON();
        $parameters = array_merge($headers, $this->headers);
        //dd(self::API_URL . "/" . $endPoint .'?app_id='. $this->appId);
        
        $url = self::API_URL . "/" . $endPoint.'?app_id='. $this->appId;
        $res = $this->client->request('GET', $url, $this->headers);
        
        $resposta = array();
        //echo $res->getHeaderLine('content-type');
        $resposta = json_decode($res->getBody());
        //dd($resposta);

        //echo $res->getBody();

        //$response = $this->getDevices(); 
       // dd("".$res->getBody());
        //$response->getBody();
        return $resposta;
    }

    public function post($endPoint) {
        if($this->requestAsync === true) {
            $promise = $this->client->postAsync(self::API_URL . $endPoint, $this->headers);
            return (is_callable($this->requestCallback) ? $promise->then($this->requestCallback) : $promise);
        }
        return $this->client->post(self::API_URL . $endPoint, $this->headers);
    }

    public function put($endPoint) {
        if($this->requestAsync === true) {
            $promise = $this->client->putAsync(self::API_URL . $endPoint, $this->headers);
            return (is_callable($this->requestCallback) ? $promise->then($this->requestCallback) : $promise);
        }
        return $this->client->put(self::API_URL . $endPoint, $this->headers);
    }
}
