<?php
/**
 * 代理服务器，php版本，游戏服务器，用来代理c++的tcp服务器，采用异步非阻塞的方式实现
 */ 
class ProxyServer {
    /**
     * 客户端数据，websocket客户端
     */         
    protected $clients = array();
    
    /**
     * 后端异步客户端，指的是链接tcp服务器的客户端
     */         
    protected $backends = array();
    
    /**
     * websocket服务器
     */         
    protected $serv = null;
    
    /**
     * 进程名名前缀
     */         
    const PROCESS_NAME_PREFIX = 'gameproxy';
    
    /**
     * 服务器ip
     */         
    protected $serv_ip = '0.0.0.0';
    
    /**
     * 服务器端口
     */         
    protected $serv_port = 9503;
    
    /**
     * 后端服务器配置
     */         
    protected $back_serv = array('ip'=>'192.168.1.34', 'port'=>9089, 'timeout'=>0.5);
    
    /**
     * 服务器配置,这里设置很关键， 要了解c++服务器的包头+包体
     */         
    protected $serv_conf = array(
		'dispatch_mode' => 2,
		'open_length_check'     => true,
		'package_length_type'   => 'N',
		'package_length_offset' => 0,          		
		'package_body_offset'   => 0,  		
		'package_max_length'    => 1024 * 1024,
		'socket_buffer_size'    => 1024 * 1024,
        
        'worker_num' => 2, //设置启动的worker进程数。
        'max_conn' => 10000, //服务器程序，最大允许的连接数 
        'backlog' => 128, //Listen队列长度，如backlog => 128，此参数将决定最多同时有多少个等待accept的连接
        'daemonize'=>0,  //守护进程化。设置daemonize => 1时，程序将转入后台作为守护进程运行
        'log_file' => './ProxyServer.log', //swoole error log
        'log_level' => 5, //设置swoole_server错误日志打印的等级，范围是0-5, 0 =>DEBUG, 1 =>TRACE, 2 =>INFO, 3 =>NOTICE, 4 =>WARNING, 5 =>ERROR
	);

    /**
     * 运行服务器
     */         
    public function run() {
        $this->serv = new Swoole\Websocket\Server($this->serv_ip, $this->serv_port);				
		$this->serv->set($this->serv_conf);		
		$this->serv->on('Start', array($this, 'onStart'));
		$this->serv->on('ManagerStart', array($this, 'onManagerStart'));
		$this->serv->on('WorkerStart', array($this, 'onWorkerStart'));
		$this->serv->on('Open', array($this, 'onOpen'));
		$this->serv->on('Message', array($this, 'onMessage'));
		$this->serv->on('Close', array($this, 'onClose'));
		$this->serv->start();
    }

    public function onStart($serv) {
		swoole_set_process_name(self::PROCESS_NAME_PREFIX.": master: ".get_called_class());
		$this->log("MasterPid={$serv->master_pid}");
        $this->log("ManagerPid={$serv->manager_pid}");
        $this->log("Server: start.Swoole version is [" . SWOOLE_VERSION . "]");
        $this->log("IP: \e[0;32m{$this->serv_ip}\e[0m, PORT:\e[0;32m{$this->serv_port}\e[0m PROXY_IP:\e[0;32m{$this->back_serv['ip']}\e[0m, PROXY_PORT:\e[0;32m{$this->back_serv['port']}\e[0m, PROXY_TIMEOUT:\e[0;32m{$this->back_serv['timeout']}\e[0m");
    }

	public function onManagerStart($serv) {
		swoole_set_process_name(self::PROCESS_NAME_PREFIX.": manager: ".get_called_class());
		$this->log("onManagerStart:");	
	}

	public function onWorkerStart($serv, $worker_id) {
		swoole_set_process_name(self::PROCESS_NAME_PREFIX.": worker {$worker_id}: ".get_called_class());
		$this->log("onWorkerStart:");	
	}

    /**
     * 处理websocket关闭回调
     */         
    public function onClose($serv, $fd) {
        //处理关闭链接，  网关服务器=>关闭异步客户端=》通知到代理服务器=》代理服务器关闭websocket前端
        if (isset($this->clients[$fd])) {
            $backend_client = $this->clients[$fd]['socket'];
            unset($this->clients[$fd]);
            if($backend_client->isConnected()) {
                $backend_client->close();
            }
            
            unset($this->backends[$backend_client->sock]);
            $this->log("client close");
        }
    }

    /**
     * 处理websocket连接成功后回调
     */         
    public function onOpen($server, $frame) {
        //启动异步客户端
        $socket = new swoole_client(SWOOLE_SOCK_TCP, SWOOLE_SOCK_ASYNC);
        $socket->on('connect', function ($socket) use($frame) {
            //初始化变量
            $this->backends[$socket->sock] = array(
                'client_fd' => $frame->fd,
            );
            $this->clients[$frame->fd] = array(
                'socket' => $socket,
            );
        
            $this->log("connect to backend server success");
            $this->log(": Client[$frame->fd] backend-sock[{$socket->sock}]: Connect.");
        });
        $socket->on('error', function ($socket) {
            $this->log("connect to backend server fail");
        });
		$socket->on('close', function ($socket) {
            //处理服务器断线
            $fd = isset($this->backends[$socket->sock]['client_fd']) ? $this->backends[$socket->sock]['client_fd'] : NULL ;
            if(!empty($fd) && $this->serv->connection_info($fd)) {                              
                $this->serv->close($fd);
                $this->log("server close websocket");
            }
        });
        $socket->on('receive', function ($socket, $data){			
            $fd = isset($this->backends[$socket->sock]['client_fd']) ? $this->backends[$socket->sock]['client_fd'] : NULL ;
            if(!empty($fd) && $this->serv->connection_info($fd)) {
                $this->log('websocket: Recv <<<<< :  client_fd='.$fd.'  len='.strlen($data)); 
    			$this->serv->push($fd, $data, WEBSOCKET_OPCODE_BINARY);
            }
        });
		
        $socket->connect($this->back_serv['ip'], $this->back_serv['port'], $this->back_serv['timeout']);
    }

    /**
     * 处理接受消息回调
     */         
    public function onMessage($server, $frame) {
        if(!empty($frame)) {
            $begin_time =  $this->_getMicTime();
            $backend_socket = isset($this->clients[$frame->fd]['socket']) ? $this->clients[$frame->fd]['socket'] : NULL;
            if(!empty($backend_socket) && $backend_socket->isConnected()) {
                $ret = $backend_socket->send($frame->data);
                $end_time = $this->_getMicTime();
                $this->log('websocket: Send >>>>> :  client_fd='.$frame->fd.'  len='.$ret.'  speed_time='.($end_time-$begin_time));
            }	
        }
    }
    
    /**
     * 打印输出
     */         
    public function log($content, $level = 'DEBUG') {
        $content = '['.date('Y-m-d H:i:s)').']   '.$level.'   '.$content."\n";
        echo $content;
    }

    /**
     * 获取微秒时间
     * @return number
     */
    private function _getMicTime(){
        $mictime = microtime();
        list($usec, $sec) = explode(" ", $mictime);
        return (float)$usec + (float)$sec;
    }
}

header("Content-type: text/html; charset=utf-8");
$serv = new ProxyServer();
$serv->run();