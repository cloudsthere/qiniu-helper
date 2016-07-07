<?php

namespace QiniuHelper\ServiceProviders;

use Qiniu\Auth;
use Pimple\Container;
use Pimple\ServiceProviderInterface;

class AuthServiceProvider implements ServiceProviderInterface {
	public function register(Container $pimple) {
		$pimple['Auth'] = function ($pimple) {
			$auth = new Auth(
				$pimple->config('accessKey'),
				$pimple->config('secretKey')
			);

			return $auth;
		};
	}
}