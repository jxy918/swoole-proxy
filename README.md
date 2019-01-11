# swoole-proxy

* websocket协议转tcp，swoole实现前端代理服务器(websocket), 后端服务器是TCP

## 一，概述

* 用swoole实现一个websocket代理服务器，代理到后端c++的tcp服务器上， 方便h5游戏开发，主要实现转换协议， 把后端c++的tcp协议转换成swoole的websocket协议。
 
## 二，示例图

![示例demo](demo.png)

 
## 三，使用

* 1，目录说明：

```
./c++server c++服务器目录，可跑起来
./client 客户端交互测试工具
./ProxyServer.php  代理服务器代码，此代理服务器可以用户代理游戏服务器
./ProxyServerByCo.php  协程客户端版，代理服务器代码，注意由于此处c++服务器是一问一答，所以可以用

``` 

>PS:协程代理服务器，只能用户同步模式服务器，因为协程客户端需要发送请求，能获取到返回结果，如果不返回，会超时报错

* 2，启动服务器 ：

先进c++server目录， 点击可执行文件运行c++服务器， c++服务器（windows版）是一个简单echo服务器， 你发送什么样的数据会原样返回，跑起来后会到c++服务器端口：6080

代理服务器启动：配置代理服务器要代理的后端c++服务器的ip和端口, 如果是想跑协程版， 请自行修改一下gameproxy.sh里的代码，把ProxyServer.php替换成ProxyServerByCo.php

```
gameproxy.sh start         启动代理服务器
gameproxy.sh stop          停止代理服务器

```

客户端：进入client目录，浏览器运行index.html文件，
       

## 四，联系方式

* qq：251413215, 加qq请输入验证消息：swoole-proxy
