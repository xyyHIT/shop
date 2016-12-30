## 注意事项

1. 使用composer进行依赖项目的安装
2. 修改配置文件
3. nginx配置
    ```json
    rewrite ^/shop/html/(.*)/(.*)\.html$ /index.php?app=wechat&act=redirectHtml&modul=$1&action=$2 break;
    ```
4. 修改文件 `vendor/doctrine/cache/lib/Doctrine/Common/Cache/CacheProvider.php` 
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
5. 修改文件`vendor/doctrine/cache/lib/Doctrine/Common/Cache/RedisCache.php`

   ```php
   # line:45
   public function setRedis(Redis $redis)
   {
     //        $redis->setOption(Redis::OPT_SERIALIZER, $this->getSerializerValue());
     $this->redis = $redis;
   }
   ```
6. 商品排序 - 综合得分排序 字段 score
   ```mysql
   -- 查看事件调度是否开启
   show variables like '%sche%'
   -- 如没有,请开启
   set @@global.event_scheduler = 1;
    
   -- 新增一个存储过程,用来更新商品统计表的分数字段
   delimiter //
   CREATE PROCEDURE statistics_goods_score()
   BEGIN
   update ecm_goods_statistics set score = sales_total * 2 + views * 0.001 + collects;
   END
   //
   delimiter ;
    
   -- 添加事件调度
   create event if not exists statistics_goods_score
   on schedule every 30 MINUTE
   on completion preserve
   do call statistics_goods_score();  
   
   ```
