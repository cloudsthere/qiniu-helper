<?php

namespace QiniuHelper\ServiceProviders;

use Qiniu\Processing\Operation;
use Pimple\Container;
use Pimple\ServiceProviderInterface;

class OperationServiceProvider implements ServiceProviderInterface
{
    public function register(Container $pimple){

        $pimple['Operation'] = function($pimple){

            return new Operation($pimple->domain, $pimple['Auth']);
        };
    }

}