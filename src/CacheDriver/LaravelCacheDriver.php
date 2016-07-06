<?php

namespace QiniuHelper\CacheDriver;

use Doctrine\Common\Cache\Cache as CacheInterface;
use Cache;

class LaravelCacheDriver implements CacheInterface
{
    public function fetch($id){
        return Cache::get($id);
    }

    public function contains($id){
        return Cache::has($id);
    }

    public function save($id, $data, $lifeTime = 0){
        return Cache::put($id, $data, $lifeTime / 60);
    }

    public function delete($id){
        return Cache::forget($id);
    }

    public function getStats(){
        return null;
    }
}