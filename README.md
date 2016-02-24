# swoole-yaf
nginx + swoole + yaf, 使用swoole内置swoole_http_server, 提供REST RPC，参考TSF

说明
nginx用作反向代理，swoole_http_server 相当于PHP-FPM，压测性能比PHP-FPM好，yaf框架以扩展形式提供MVC服务

运行
Usage:php start.php start | stop | reload | restart | status | help


cd /home/wwwroot/default/swoole-yaf/server
php start.php start
