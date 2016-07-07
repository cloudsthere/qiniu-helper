<?php

namespace QiniuHelper;

use Qiniu\Auth as QiniuAuth;

class Callback
{
    const API = 'http://172.30.251.210/callback.php';
    private $auth;
    private $contentType; 
    private $body;
    private $authorization;

    function __construct(QiniuAuth $auth){
        $this->auth = $auth;
        $this->body = file_get_contents('php://input');

        if(isset($_SERVER['HTTP_AUTHORIZATION']))
            $this->authorization = $_SERVER['HTTP_AUTHORIZATION'];

        if(isset($_SERVER['CONTENT_TYPE']))
            $this->contentType = $_SERVER['CONTENT_TYPE'];

    }

    public function verify(){
        if(empty($this->authorization))
            return null;

        $isQiniuCallback = $this->auth->verifyCallback($this->contentType, $this->authorization, self::API, $this->body);

        if ($isQiniuCallback) {
            $resp = array('ret' => 'success');
        } else {
            $resp = array('ret' => 'failed');
        }
        echo json_encode($resp);

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