### redis扩展得一个简单封装，提供了主从库得支持。提供了Yii框架得组件封装

使用方法

~~~
Yii::app()->redis->set($k, $v, $exprty);
Yii::app()->redis->get($k);
Yii::app()->redis->hSet($k, $hk, $v);
Yii::app()->redis->hGet($k, $hk);
~~~
