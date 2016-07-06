<?php

namespace QiniuHelper\ServiceProviders;

use Qiniu\Storage\UploadManager;
use Pimple\Container;
use Pimple\ServiceProviderInterface;

class UploadManagerServiceProvider implements ServiceProviderInterface
{
    public function register(Container $pimple){
        $pimple['UploadManager'] = function($pimple){
            return new UploadManager();
        };
    }
}