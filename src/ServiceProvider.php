<?php

namespace QiniuHelper;

use Illuminate\Support\ServiceProvider as LaravelServiceProvider;
use QiniuHelper\CacheDriver\LaravelCacheDriver;

class ServiceProvider extends LaravelServiceProvider {
	/**
	 * 延迟加载
	 * @var boolean
	 */
	protected $defer = true;

	public function boot() {
		$this->publishes([
			__DIR__ . '/config.php' => config_path('qiniu.php'),
		], 'config');
	}

	/**
	 * @return mixed
	 */
	public function register() {
		// 结合默认配置和自定义配置，防止自定义配置不完整
		$this->mergeConfigFrom(__DIR__ . '/config.php', 'qiniu');

		$this->app->singleton(['QiniuHelper\\QiniuHelper' => 'qiniu'], function ($app) {

			$app = new QiniuHelper(config('qiniu'));
			if (config('qiniu')['use_laravel_cache']) {
				$app->setCache(new LaravelCacheDriver);
			}

			return $app;
		});
	}

	public function provides() {
		return ['qiniu', QiniuHelper::class];
	}
}