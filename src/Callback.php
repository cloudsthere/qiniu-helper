<?php

namespace QiniuHelper;



class Callback
{

    private $auth;
    private $contentType; 
    private $body;
    private $authorization;
    private $callback_url;

    function __construct($helper){
        $this->auth = $helper['Auth'];
        $this->callback_url = $helper->config('notify_url');
        $this->body = file_get_contents('php://input');
        $headers = apache_request_headers();

        if(isset($headers['Authorization']))
            $this->authorization = $headers['Authorization'];

        if(isset($headers['Content-Type']))
            $this->contentType = $headers['Content-Type'];

    }

    public function verify(){
        if(empty($this->authorization)){
            return null;
        }

        $isQiniuCallback = $this->auth->verifyCallback($this->contentType, $this->authorization, $this->callback_url, $this->body);

        return $isQiniuCallback;
    }

    public function read(){
        if($this->contentType == 'application/x-www-form-urlencoded'){
            $pairs = explode('&', $this->body);
            $params = [];
            foreach($pairs as $pair){
                list($key, $value) = explode('=', $pair);
                $params[$key] = $value;
            }
        }elseif($this->contentType == 'application/json'){
            $params = json_decode($this->body, true);
        }
        return $params;
    }

    public function getBody(){
        return $this->body;
    }

    public function getContentType(){
        return $this->contentType;
    }

}