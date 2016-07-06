<?php

namespace QiniuHelper\ServiceProviders;

use Qiniu\Storage\BucketManager;
use Pimple\Container;
use Pimple\ServiceProviderInterface;

class BucketManagerServiceProvider implements ServiceProviderInterface
{
    public function register(Container $pimple){

        $pimple['BucketManager'] = function($pimple){

            return new BucketManager($pimple['Auth']);
        };
    }

}