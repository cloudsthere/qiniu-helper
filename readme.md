# qiniu-helper

七牛非官方SDK，基于官方SDK[[qiniu/php-sdk]](https://github.com/qiniu/php-sdk), 适用Laravel, 包含基本操作功能，以及新增功能：

* 刷新缓存
* 同步目录
* 忽略规则

## 安装

```
composer require "cloudthere/qiniu-helper"
```

## 配置

### Laravel 应用

1.注册`ServiceProvider`

```
// 在根目录下config/app.php中添加
QiniuHelper\ServiceProvider::class,
```

2.创建配置文件

```
php artisan vendor:publish
```

3.修改根目录下`config/qiniu.php`中相关配置


### 其他应用

可忽略ServiceProvider, 在初始化QiniuHelper对象时传入相应配置则可


## 初始化
```
// Laravel 
$qiniu = app('qiniu');

// 其他应用
include path_to_vendor/autoload.php
$qiniu = new \QiniuHelper\QiniuHelper($config) // 相应配置

```

## 使用

* 以下代码的参数名代表参数的具体含义，除另外说明。
* 接口可能有多个默认参数，一般情况下不用手动设置
* 参数名说明：
	* key 在七牛空间中的文件名，如`style.css, js/common.js`, keys表示数组，也可以传字符串，会被自动转为数组
	* prefix 前缀，如`images/`
	* saveAs 生成的新文件名
	* pipeline 多媒体处理管道

### 上传操作
```
// 上传字符串
$qiniu->put($string, $key = null); // 不传$key或$key为空，七牛将随机生成文件名，下同

// 上传文件
$qiniu->putFile($filePath, $key = null, $params = null, $mime = 'application/octet-stream', $checkCrc = false); 

// 获取uploadToken, 前端上传可能用到
$qiniu->uploadToken($forceRefresh = false); // token会有3600秒缓存，可以令其强制刷新

// 上传策略, 具体策略可参考 [七牛API上传策略](http://developer.qiniu.com/article/developer/security/put-policy.html) 
$qiniu->withPolicy($policies)->putFile($filePath);

```

### 空间操作
```
// 查询文件
$qiniu->stat($key);

// 是否包含此文件
$qiniu->has($key);

// 获取文件信息列表, 默认$limit = 1000, 所以后两个参数基本用不上
$qiniu->listFiles($prefix = '', $marker = '', $limit = 1000);

// 获取文件名列表, 从文件信息列表提取文件名
$qiniu->listKeys($prefix = '', $marker = '', $limit = 1000);

// 复制, 可传2个或4个参数
$qiniu->copy($source_key, $target_key); // 默认空间内复制
$qiniu->copy($source_bucket, $source_key, $target_bucket, $target_key); // 跨空间复制

// 移动文件, 传参机制与copy相同
$qiniu->move();

// 重命名
$qiniu->rename($key, $newname);

// 抓取远程资源
$qiniu->fetch($url, $key = null);

// 删除文件
$qiniu->delete($key);

// 修改mime
$qiniu->changeMime($key, $mime);

// 从镜像源站抓取资源到空间中，如果空间中已经存在，则覆盖该资源
$qiniu->prefetch($key);

// 私有下载地址
$qiniu->privateDownloadUrl($key);

// ---批量操作----
// 批量查询
$qiniu->batchStat(array $keys);

// 批量复制, 可传1个或3个参数
// $keys的键为源文件名，值为复制所得文件名，如：
// ['source1' => 'target1', 'source2' => 'target2']
$qiniu->batchCopy($keys);
$qiniu->batchCopy($source_bucket, $keys, $target_bucket); // 跨空间复制

// 批量移动, 参数机制与batchCopy相同
$qiniu->batchMove();

// 批量删除, $keys是string or array。若传入目录，如'images/', 将删除目录下所有文件
$qiniu->batchDelete($keys); 

// 延迟操作, 只对批量操作有效;指令不会被马上执行，而是缓存在对象的私有属性
$qiniu->build($build = true)->batchstat($keys);
$qiniu->batchDelete($keys); 
// 开启延迟后，后面的批量操作指令都会缓存起来，直到执行操作或手动关闭，如$qiniu->build(false)
$qiniu->batch($ops = []); // 执行指令
```
### 文件同步操作

```
// 刷新七牛缓存, 七牛限制每天刷新文件数为100个
// 关于第二个参数$dirs, 七牛虽然开放了刷新目录这个接口，但刷新目录的权限没有对外开放（客服说的），所以不建议使用
$qiniu->refresh($keys = [], $dirs = []);

// 向上同步
$qiniu->upSync($source, $prefix = '', $level = 1, $ignores = [])

// 向下同步
$qiniu->downSync($dest, $prefix = '', $level = 1)
```
* `$source`和`$dest`均指本地目录
* `$level`,同步程度。1（默认),只新增文件，不删除，不覆盖； 2，相同文件名将覆盖，同时新增；3，清空目标目录，再传输。
* `$ignores`, 忽略规则，只对向上同步生效。有另一种使忽略规则生效的方法，可以在当前操作目录下创建带有忽略规则的文件.qiniuignore。
* .qiniuignore采用一维json数组格式，如下

```
[
    "test.xml", // 忽略单个文件
    ".md", // 忽略所有以md结尾的文件
    "doc/", // 忽略doc目录下所有文件
]
```
### 持久化操作

* 持久化操作执行完后，七牛会向指定的notify_url(位于配置文件), 发送执行结果通知
* 具体操作命令可参考[七牛API数据处理](http://developer.qiniu.com/article/index.html#dora-api-handbook)


```
// $key, 将要操作的资源；$fops，stirng|array, 将要进行的操作;$force, 是否覆盖已有相同文件
$qiniu->fop($key, $fops, $pipeline = null, $force = false);

// 查询, $id, fop方法返回的id
$qiniu->fopStatus($id); 

// 生成压缩包
$qiniu->zip($keys, $saveAs = '', $pipeline = null);

```

### 图片处理
```
// 获取图片信息
$qiniu->imageInfo($key);

// exif信息
$qiniu->imageExif($key);

// 预览地址, $ops指相关操作，如限制图片宽高
$qiniu->imagePreviewUrl($key, $ops = []);
```
### 回调
* 七牛的回调有两种，用于上传策略的`callback`, 用于持久化操作的`notify`。本SDK都用`QiniuHelper\Callback`类处理。
* `Callback`类的`verify`方法只对`callback`有效。因为notify请求头没有`HTTP_AUTHORIZATION` (可能七牛认为notify不需要验证吧)
* `notify_url`在配置中设定，每次持久化操作都会发送回调（当然，你可以删除此配置，取消回调通知）。`callbackUrl`需要在上传策略中手动设定。

```
// 获取Callback对象
$callback = $qiniu->callback();

// 验证
$callback->verify();

// 获取原始body
$callback->getBody();

// 转换为数组的body
$callback->read();

// 获取contentType
$callback->getContentType();
```


### 公共方法
```
// 修改默认空间；空间名设置在配置文件，采用['空间名'=>'域名']的格式，可以设置多对，第一个对为默认。
// 如果$bucket已设置在配置文件，$domain可为空；否则，$domain必须
$qiniu->setBucket($bucket, $domain = '');

// 获取错误信息
$qiniu->getError(); // 返回Qiniu\Http\Error的实例

// 更换缓存驱动，$cache必须是Doctrine\Common\Cache\Cache的实现
$qiniu->setCache(Cache $cache);
```