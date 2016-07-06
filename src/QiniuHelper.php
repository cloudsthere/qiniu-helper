<?php

namespace QiniuHelper;

use Doctrine\Common\Cache\Cache;
use Doctrine\Common\Cache\FilesystemCache;
use Pimple\Container;
use Qiniu\Processing\PersistentFop;

class QiniuHelper extends Container {
	const CACHE_EXPIRE = 3600;
	const CACHE_PREFIX = 'QiniuHelper.uploadToken.';
	/**
	 * @var mixed
	 */
	public $config;
	/**
	 * @var array
	 */
	private $providers = [
		ServiceProviders\AuthServiceProvider::class,
		ServiceProviders\UploadManagerServiceProvider::class,
        ServiceProviders\BucketManagerServiceProvider::class,
		ServiceProviders\OperationServiceProvider::class,
	];

	/**
	 * @var mixed
	 */
	private $cache;
	/**
	 * @var mixed
	 */
	public $domain;
	/**
	 * @var mixed
	 */
	public $bucket;
	/**
	 * @var mixed
	 */
	private $build;
	/**
	 * @var array
	 */
	private $opStack = [];
	/**
	 * @var mixed
	 */
	private $policy;
	/**
	 * @var mixed
	 */
	private $error;

	/**
	 * @param array $config
	 */
	function __construct($config = []) {
		$this->config = $config;
		$this->domain = current($config['bucket']);
		$this->bucket = key($config['bucket']);
		$this->registerProviders();
	}

	/**
	 * @param $bucket
	 * @param $domain
	 * @return mixed
	 */
	public function setBucket($bucket, $domain = '') {

		$buckets = $this->config['bucket'];
		if (!array_key_exists($bucket, $buckets) && empty($domain)) {
			throw new InvalidArgumentsException('If bucket is undefined, parameter 2 (domain) is expected');
		}

		if (!empty($domain)) {
			$this->config['bucket'][$bucket] = $domain;
		}

		$this->bucket = $bucket;
		$this->domain = $this->config['bucket'][$bucket];

		return $this;
	}

	/**
	 * @param $key
	 */
	public function key_to_url($key) {
		return 'http://' . $this->domain . '/' . $key;
	}

	/**
	 * @return mixed
	 */
	public function getError() {
		$error = $this->error;
		$this->error = null;
		return $error;
	}

    public function imageInfo($key){
        return $this->run('Operation', 'execute', [$key, 'imageInfo']);
    }

    public function imageExif($key){
        return $this->run('Operation', 'execute', [$key, 'exif']);
    }

    public function imagePreviewUrl($key, $ops = []){
        return $this->run('Operation', 'buildUrl', [$key, $ops]);
    }


	/**
	 * 持久化操作
	 * @param  string $key
	 * @param  string or array $fops 若有多个操作，传入数组
	 * @param string $pipline
	 * @param string $notify_url
	 * @param boolean $force 是否覆盖已有相同文件，未知会产生什么变化
	 * @return stirng PersistentFopId
	 */
	public function fop($key, $fops, $pipline = null, $force = false) {

		$fop = new PersistentFop($this['Auth'], $this->bucket, $pipline, $this->config['notify_url'], $force);
		return $this->response($fop->execute($key, $fops));
	}

	/**
	 * @param $id
	 * @return mixed
	 */
	public function fopStatus($id) {

		return $this->response(PersistentFop::status($id));
	}

	/**
	 * @param $url
	 */
	public function urlEncode($url) {
		return \Qiniu\base64_urlSafeEncode($url);
	}

	/**
	 * @param $keys
	 * @param $saveAs
	 * @param $pipline
	 * @return mixed
	 */
	public function zip($keys, $saveAs = '', $pipline = '') {
		if (is_string($keys)) {
			$keys = [$keys];
		}

		$iniKey = $keys[0];
		$keys = array_map([$this, 'key_to_url'], $keys);

		$fops = 'mkzip/2';
		foreach ($keys as $key) {
			$fops .= '/url/' . \Qiniu\base64_urlSafeEncode($key);
		}
		if (!empty($saveAs)) {
			$fops .= '|saveas/' . \Qiniu\base64_urlSafeEncode($this->bucket . ":" . $saveAs);
		}

		return $this->fop($iniKey, $fops, $pipline, $this->config['notify_url']);
	}

	/**
	 * @param $filename
	 * @param $key
	 * @param null $params
	 * @param null $mime
	 * @param $checkCrc
	 * @return mixed
     * @link 
	 */
	public function putFile($filePath, $key = null, $params = null, $mime = 'application/octet-stream', $checkCrc = false) {
		$key = $this->keyFilter($key);
		return $this->run('UploadManager', 'putFile', [$this->uploadToken(), $key, $filename, $params, $mime, $checkCrc]);
	}

	/**
	 * @param $forceRefresh
	 * @return mixed
	 */
	public function uploadToken($forceRefresh = false) {

		$cacheKey = self::CACHE_PREFIX . $this->config['accessKey'];

		$token = $this->getCache()->fetch($cacheKey);

		if (!$token || $forceRefresh || !empty($this->policy)) {

			$token = $this['Auth']->uploadToken($this->bucket, null, self::CACHE_EXPIRE, $this->policy);

			if (!empty($this->policy)) {
				$this->policy = null;
			} else {
				$this->getCache()->save($cacheKey, $token, self::CACHE_EXPIRE);
			}

		}

		return $token;
	}

	/**
	 * @param array $policy
	 * @return mixed
	 */
	public function withPolicy(Array $policy) {
		$this->policy = $policy;
		return $this;
	}

	/**
	 * @return mixed
	 */
	private function getCache() {
		return $this->cache ?: $this->cache = new FilesystemCache(sys_get_temp_dir());
	}

	/**
	 * @param Cache $cache
	 */
	public function setCache(Cache $cache) {
		$this->cache = $cache;
	}

	/**
	 * @return mixed
	 */
	public function callback() {
		if (empty($this->callback)) {
			$this->callback = new Callback($this['Auth']);
		}

		return $this->callback;
	}

	/**
	 * @param $key
	 * @return mixed
	 */
	public function privateDownloadUrl($key) {
		$url = $this->key_to_url($key);

		return $this->run('Auth', 'privateDownloadUrl', [$url]);
	}
	/**
	 * @param $string
	 * @param $key
	 * @return mixed
	 */
	public function put($string, $key = null) {
		$key = $this->keyFilter($key);
		return $this->run('UploadManager', 'put', [$this->uploadToken(), $key, $string]);
	}

	/**
	 * @param $key
	 */
	private function keyFilter($key) {
		return empty($key) ? null : $key;
	}

	/**
	 * @param $key
	 * @return mixed
	 */
	public function stat($key) {
		return $this->run('BucketManager', 'stat', [$this->bucket, $key]);
	}

	/**
	 * @param $key
	 */
	public function has($key) {
		return (bool) $this->run('BucketManager', 'stat', [$this->bucket, $key]);
	}

	/**
	 * copy, 两个或四个参数，空间可选
	 * @param string $bucket 源空间
	 * @param string $key 源键
	 * @param string $bucket 目标空间
	 * @param string $key 目标键
	 * @return
	 */
	public function copy() {
		$param = $this->copyAndMoveParams(func_get_args());
		return $this->run('BucketManager', 'copy', $param);
	}

	/**
	 * move, 两个或四个参数，空间可选
	 * @param string $bucket 源空间
	 * @param string $key 源键
	 * @param string $bucket 目标空间
	 * @param string $key 目标键
	 * @return
	 */
	public function move() {
		$param = $this->copyAndMoveParams(func_get_args());
		return $this->run('BucketManager', 'move', $param);
	}

	/**
	 * fetch
	 * @param  string $url 目标url
	 * @param  stirng $key 保存key
	 * @return
	 */
	public function fetch($url, $key = '') {
		return $this->run('BucketManager', 'fetch', [$url, $this->bucket, $key]);
	}

	/**
	 * delete
	 * @param  string $key
	 * @return
	 */
	public function delete($key) {
		return $this->run('BucketManager', 'delete', [$this->bucket, $key]);
	}

	/**
	 * rename
	 * @param  string $oldname
	 * @param  string $newname
	 * @return
	 */
	public function rename($oldname, $newname) {
		return $this->run('BucketManager', 'rename', [$this->bucket, $oldname, $newname]);
	}

	/**
	 * 修改mime
	 * @param  string $key
	 * @param  string $mime
	 * @return
	 */
	public function changeMime($key, $mime) {
		return $this->run('BucketManager', 'changeMime', [$this->bucket, $key, $mime]);
	}

	/**
	 * prefetch
	 * @param  string $key
	 * @return
	 */
	public function prefetch($key) {
		return $this->run('BucketManager', 'prefetch', [$this->bucket, $key]);
	}

	/**
	 * 批量查询，当处于build模式时，返回指令
	 * @param  array $keys
	 * @return
	 */
	public function batchStat($keys) {

		$op = call_user_func_array(['\Qiniu\Storage\BucketManager', 'buildBatchStat'], [$this->bucket, $keys]);

		if ($this->build) {
			return $this->pushStack($op);
		}

		return $this->batch($op);
	}

	/**
	 * 批量复制
	 * @return
	 */
	public function batchCopy() {

		$input = $this->batchCopyAndMoveParams(func_get_args());

		$op = call_user_func_array(['\Qiniu\Storage\BucketManager', 'buildBatchCopy'], $input);

		if ($this->build) {
			return $this->pushStack($op);
		}

		return $this->batch($op);
	}

	/**
	 * 批量删除
	 * @param  string or array $keys 可以删除目录
	 * @return
	 */
	public function batchDelete($keys) {

		if (is_string($keys) && strrev($keys)[0] == '/') {

			$keys = $this->listKeys($keys);

		}

		$op = $this['BucketManager']->buildBatchDelete($this->bucket, $keys);

		if ($this->build) {
			return $this->pushStack($op);
		}

		return $this->batch($op);
	}

	/**
	 * 向上同步
	 * @param  int $level 同步类型, 1(默认)增量，2覆盖，3清空
	 * @return bool
	 */
	public function upSync($source, $prefix = '', $level = 1, $ignores = []) {

		if (!$this->uploader instanceof UploadManager) {
			throw new InvalidArgumentsException('You need an Uploader first which is instance of Cloudsthere\QiniuHelper\UploadManager');
		}

		set_time_limit(0);

		if (is_string($ignores)) {
			$ignores = [$ignores];
		}

		$stat = [];

		$local_files = filesystem()->allFiles($source);

		$ignore_file = $source . '/.qiniuignore';
		if (filesystem()->exists($ignore_file) && !empty($file_ignores = filesystem()->get($ignore_file))) {

			eval('$file_ignores = \'' . $file_ignores . '\';');

			if (!empty($file_ignores)) {

				$file_ignores = json_decode($file_ignores, true);

				if (!$file_ignores) {
					throw new InvalidArgumentsException('Invalid json in .qiniuignore');
				}

				$ignores = array_merge($ignores, $file_ignores);
			}
		}

		if (!empty($local_files)) {

			$local_files = array_reduce($local_files, function ($v, $w) {

				$v[$w->getRelativePathname()] = $w;
				return $v;

			});
		}

		$local_keys = array_keys($local_files);

		$bucket_keys = $this->listKeys($prefix);

		list($local_keys, $stat['ignore']) = $this->ignore($local_keys, $ignores);

		// 清空空间
		if ($level == 3) {
			$stat['delete'] = $bucket_keys;
			$this->batchDelete($bucket_keys);
			$bucket_keys = [];
		}

		foreach ($local_keys as $key) {

			$filename = $prefix . $local_files[$key]->getRelativePathname();

			//delete
			if (in_array($filename, $bucket_keys) && $level > 1) {

				$res = $this->delete($filename);

				if ($res) {
					$stat['delete'][] = $filename;
				} else {
					$stat['error'][] = $this->getError();
				}

			}

			// upload
			if (!in_array($filename, $bucket_keys) || $level > 1) {

				$res = $this->uploader->putFile($filename, $local_files[$key]->getRealPath());

				if ($res) {
					$stat['add'][] = $filename;
				} else {
					$stat['error'][] = $this->uploader->getError();
				}

			}
		}

		return $stat;
	}

	/**
	 * 向下同步
	 * @param  int $level 同步类型, 1(默认)增量，2覆盖，3清空
	 * @return bool
	 */
	public function downSync($dest, $prefix = '', $level = 1) {

		if (!$this->uploader instanceof UploadManager) {
			throw new InvalidArgumentsException('You need an Downloader first which is instance of Cloudsthere\QiniuHelper\DownloadManager');
		}

		set_time_limit(0);

		$stat = [];

		$local_files = (array) filesystem()->allFiles($dest);

		if (!empty($local_files)) {

			$local_files = array_reduce($local_files, function ($v, $w) {

				$v[$w->getRelativePathname()] = $w;
				return $v;

			});
		}

		$bucket_keys = $this->listKeys($prefix);

		// 清空本地
		if ($level == 3) {
			$stat['delete'] = array_keys($local_files);
			$res = filesystem()->deleteDirectory($dest, true);
			if (!$res) {
				throw new RuntimeException('Failed to clear directory ' . $dest);
			}

		}

		foreach ($bucket_keys as $key) {

			if (array_key_exists($key, $local_files) && $level > 1) {

				$res = filesystem()->delete($local_files[$key]->getRealPath());

				if ($res) {
					$stat['delete'][] = $key;
				} else {
					$stat['error'][] = 'Failed to delete file $key';
				}

			}

			// download
			if (!array_key_exists($key, $local_files) || $level > 1) {

				$filename = realpath($dest) . '/' . $key;

				$res = $this->downloader->download($key, $filename);

				if ($res) {
					$stat['add'][] = $key;
				} else {
					$stat['error'][] = 'Failed to build file ' . $key;
				}

			}
		}

		return $stat;
	}

	/**
	 * 刷新七牛缓存， 目录传值有问题
	 * @param  array  $keys
	 * @param  array  $dirs
	 * @return
	 */
	public function refresh($keys = [], $dirs = []) {
		if (is_string($keys)) {
			$keys = [$keys];
		}

		if (is_string($dirs)) {
			$dirs = [$dirs];
		}

		$keys = array_map([$this, 'key_to_url'], $keys);
		$dirs = array_map([$this, 'key_to_url'], $dirs);

		$data = [
			'urls' => $keys,
			'dirs' => $dirs,
		];

		$authorization = $this->auth->signRequest(self::REFRESH_API, null);
		$headers = [
			'Authorization' => 'QBox ' . $authorization,
			'Content-Type' => 'application/json',
		];

		$ret = Client::post(self::REFRESH_API, json_encode($data), $headers);

		if (!$ret->ok()) {

			$retArr = array(null, new Error(self::REFRESH_API, $ret));

		} else {

			if ($ret->json()['code'] == 200) {
				$r = ($ret->body === null) ? array() : $ret->json();
				$retArr = array($r, null);
			} else {
				$ret->error = $ret->json()['error'];
				$retArr = array(null, new Error(self::REFRESH_API, $ret));
			}
		}

		return $this->response($retArr);

	}

	/**
	 * 处理批量复制和移动的参数
	 * @param     $input
	 * @return
	 */
	private function batchCopyAndMoveParams($input) {
		$num = count($input);

		if ($num == 1) {
			return [$this->bucket, $input[0], $this->bucket];
		} elseif ($num == 3) {
			return $input;
		} else {
			throw new InvalidArgumentsException(sprintf('This method expects 1 or 3 parameters, %s given', is_null($num) ? 'null' : $num));
		}

	}

	/**
	 * 执行批量操作命令
	 * @param  array  $ops 命令集
	 * @return
	 */
	public function batch($ops = []) {

		if (is_string($ops)) {
			$ops = [$ops];
		}

		if ($this->build) {
			$ops = array_merge($this->opStack, $ops);
			$this->flushBuild();
		}

		return $this->run('BucketManager', 'batch', [$ops]);
	}

	/**
	 * 将命令压入栈
	 * @param  array $op
	 * @return
	 */
	private function pushStack($op) {
		$this->opStack = array_merge($this->opStack, $op);
		return $op;
	}

	/**
	 *
	 * @param  string $prefix
	 * @param  string $marker
	 * @param  string $limit  默认1000
	 * @return array
	 */
	public function listFiles($prefix = '', $marker = '', $limit = 1000) {
		return $this->run('BucketManager', 'listFiles', [$this->bucket, $prefix, $marker, $limit]);
	}

	/**
	 * @param $prefix
	 * @param $marker
	 * @param $limit
	 * @return mixed
	 */
	public function listKeys($prefix = '', $marker = '', $limit = 1000) {
		$files = $this->run('BucketManager', 'listFiles', [$this->bucket, $prefix, $marker, $limit]);
		if (!$files) {
			return $files;
		}

		return array_column($files, 'key');
	}

	private function flushBuild() {
		$this->opStack = [];
		$this->build = false;
	}

	/**
	 * @param $build
	 * @return mixed
	 */
	public function build($build = true) {
		$this->build = $build;
		return $this;
	}

	/**
	 * 处理copy与move时的参数
	 * @param  array $input
	 * @return array
	 */
	/**
	 * @param $id
	 * @return mixed
	 */
	private function copyAndMoveParams($input) {
		$num = count($input);

		if ($num == 2) {
			return [
				$this->bucket,
				$input[0],
				$this->bucket,
				$input[1],
			];
		} elseif ($num == 4) {
			return $input;
		} else {
			throw new InvalidArgumentsException(sprintf('This method expects 2 or 4 parameters, %s given', is_null($num) ? 'null' : $num));
		}

	}

	function __get($id) {
		return $this->offsetGet($id);
	}

	/**
	 * @param $id
	 * @param $value
	 */
	function __set($id, $value) {
		$this->offsetSet($id, $value);
	}

	private function registerProviders() {
		foreach ($this->providers as $provider) {
			$this->register(new $provider);
		}
	}

	/**
	 * @param $raw
	 * @return mixed
	 */
	private function response($raw) {
		if (is_array($raw)) {
			list($ret, $err) = $raw;
		}

		if (is_object($raw)) {
			$err = $raw;
		}

		if (is_null($raw) || $raw == [null, null]) {
			$ret = true;
		}
		if (is_string($raw)) {
			$ret = $raw;
		}

		if (!empty($err)) {
			$this->error = $err;
			return false;
		}

		$this->error = null;
		return $ret;

	}

	/**
	 * @param $provider
	 * @param $method
	 * @param array $args
	 * @return mixed
	 */
	private function run($provider, $method, $args = []) {
		return $this->response(call_user_func_array([$this[$provider], $method], $args));

	}

	/**
	 * @param array $keys
	 * @param array $paterns
	 * @return mixed
	 */
	public function ignore($keys = [], $paterns = []) {

		$paterns = array_reduce($paterns, function ($v, $w) {
			if (strrev($w)[0] == '/') {
				$w .= '.*';
			}

			if (strpos($w, '.') === 0) {
				$w = '.*\\' . $w;
			}

			$v .= $w . '|';
			return $v;
		});

		$paterns = '#^' . rtrim($paterns, '|') . '$#';
		$ignores = [];

		$keys = array_filter($keys, function ($var) use ($paterns, &$ignores) {

			$res = preg_match($paterns, $var);

			if ($res) {
				array_push($ignores, $var);
			}

			return !$res;
		});

		return [$keys, $ignores];
	}

}