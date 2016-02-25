# swoole-yaf
nginx + swoole + yaf, 使用swoole内置swoole_http_server, 提供REST RPC，参考TSF

说明

nginx用作反向代理，swoole_http_server 相当于PHP-FPM，压测性能比PHP-FPM好，yaf框架以扩展形式提供MVC服务

运行

Usage:php start.php start | stop | reload | restart | status | help


cd /home/wwwroot/default/swoole-yaf/server

php start.php start

测试

apt-get install httpie

http http://127.0.0.1:9501/demo

输出

HTTP/1.1 200 OK
Connection: keep-alive
Content-Length: 30
Content-Type: application/json;charset=UTF-8
Date: Thu, 25 Feb 2016 02:27:04 GMT
Server: swoole-http-server
X-RateLimit-Limit: 20
X-RateLimit-Remaining: 5

{"first": "one", "second": "two"}
