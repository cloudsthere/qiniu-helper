<?php

namespace QiniuHelper\ServiceProviders;

use Qiniu\PersistentFop;
use Pimple\Container;
use Pimple\ServiceProviderInterface;

class PersistentFopServiceProvider implements ServiceProviderInterface
{
    public function register(Container $pimple){

        $pimple['PersistentFop'] = function($pimple){

            return new persistentFop($pimple['Auth']);
        };
    }

}