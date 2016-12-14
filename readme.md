## 注意事项

1. 使用composer进行依赖项目的安装

2. 配置文件的设置

3. 修改文件 `vendor/doctrine/cache/lib/Doctrine/Common/Cache/CacheProvider.php` 

   ```php
   # line:183
   private function getNamespacedId($id)
   {
     $namespaceVersion  = $this->getNamespaceVersion();

     # Todo 版本更新记得要改...
     //        return sprintf('%s[%s][%s]', $this->namespace, $id, $namespaceVersion);
     return $id;
   }
   ```

4. 修改文件`vendor/doctrine/cache/lib/Doctrine/Common/Cache/RedisCache.php`

   ```php
   # line:45
   public function setRedis(Redis $redis)
   {
     //        $redis->setOption(Redis::OPT_SERIALIZER, $this->getSerializerValue());
     $this->redis = $redis;
   }
   ```

   ​

