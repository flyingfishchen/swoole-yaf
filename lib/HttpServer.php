<?php

class HttpServer
{
    public static $instance;

    public $http;

    public static $get;

    public static $post;

    public static $header;

    public static $server;

    private $application;
    
    public $processName = 'swoole-server';
    
    protected $runUser;
    
    protected $host = '0.0.0.0';
    
    protected $port = 9501;
    
    protected $listen = [];
    
    protected $setting = [];
    
    protected $masterPidFile;
    
    protected $managerPidFile;
    
    protected $runPath = '/tmp';
    
    protected $mode = SWOOLE_PROCESS;
    
    protected $sockType = SWOOLE_SOCK_TCP;

    public function __construct($config = [])
    {
        $this->setting = array_merge([
            'worker_num' => 4,
            'daemonize' => 1,
            'dispatch_mode' => 1,
            'log_path' => '/tmp/swoole',
        ], $config);

        if (isset($this->setting['processName']) && $this->setting['processName']) {
            $this->processName =  $this->setting['processName']; 
        }
        
        if (isset($this->setting['runUser']) && $this->setting['runUser']) {
            $this->runUser =  $this->setting['runUser'];
        }
        
        if (isset($this->setting['mode']) && $this->setting['mode']) {
            $this->mode =  $this->setting['mode'];
        }
        
        if (isset($this->setting['sock_type']) && $this->setting['sock_type']) {
            $this->sock_type =  $this->setting['sock_type'];
        }
        
        $this->masterPidFile = $this->runPath . '/' . $this->processName . '.master.pid';
        $this->managerPidFile = $this->runPath . '/' . $this->processName . '.manager.pid';
        
        $this->setHost();
        
        if (isset($this->setting['listen']) && $this->setting['listen']) {
            $this->transListener($this->setting['listen']);
        }
        
        if ($this->listen[0]) {
            $this->host = $this->listen[0]['host'] ? $this->listen[0]['host'] : $this->host;
            $this->port = $this->listen[0]['port'] ? $this->listen[0]['port'] : $this->port;
            unset($this->listen[0]);
        }
    }

    protected function setHost()
    {
        $ipList = swoole_get_local_ip();
        if (isset($ipList['eth1'])) {
            $this->host = $ipList['eth1'];
        } elseif (isset($ipList['eth0'])) {
            $this->host = $ipList['eth0'];
        } else {
            $this->host = '0.0.0.0';
        }
    }
    
    protected function transListener($listen)
    {
        if (is_string($listen)) {
            $tmpArr = explode(":", $listen);
            $host = isset($tmpArr[1]) ? $tmpArr[0] : $this->host;
            $port = isset($tmpArr[1]) ? $tmpArr[1] : $tmpArr[0];
    
            $this->listen[] = array(
                'host' => $host,
                'port' => $port,
            );
            return true;
        }
        foreach ($listen as $v) {
            $this->transListener($v);
        }
    }
    
    public function onMasterStart($server)
    {
        // rename master process
        Console::setProcessName($this->processName . ': master process');
        file_put_contents($this->masterPidFile, $server->master_pid);
        file_put_contents($this->managerPidFile, $server->manager_pid);
        
        if ($this->runUser) {
            Console::changeUser($this->runUser);
        }
    }
    
    public function onManagerStart($server)
    {
        // rename manager process
        Console::setProcessName($this->processName . ': manager process');
        if ($this->runUser) {
            Console::changeUser($this->runUser);
        }
    }
    
    public function onWorkerStart()
    {
        define('APPLICATION_PATH', dirname(__DIR__));
        $this->application = new Yaf_Application(APPLICATION_PATH . "/conf/application.ini");
        ob_start();
        $this->application->bootstrap()->run();
        ob_end_clean();
        
        Console::setProcessName($this->processName . ': event worker process');
        if ($this->runUser) {
            Console::changeUser($this->runUser);
        }
    }

    public function onRequest($request, $response)
    {
        HttpServer::$server = isset($request->server) ? $request->server : [];
        HttpServer::$header = isset($request->header) ? $request->header : [];
        HttpServer::$get = isset($request->get) ? $request->get : [];
        HttpServer::$post = isset($request->post) ? $request->post : [];
        // TODO handle img
    
        $exception = null;
        ob_start();
        try {
            $yaf_request = new Yaf_Request_Http(HttpServer::$server['request_uri']);
            $this->application->getDispatcher()->dispatch($yaf_request);
            // unset(Yaf_Application::app());
        } catch (Yaf_Exception $e) {
            $exception = $e;
        }
        $result = ob_get_contents();
        ob_end_clean();
        
        if (is_object($exception) && $exception) {
            //log the exception
            $this->log("[exception]: " . $exception);//Exception::__toString()
            
            // set status
            $response->status(500);//'Internal Server Error'
            //send content
            $response->end('Internal Server Error');
        } else {
            $result = json_decode($result, true);
    
            // set status
            $response->status($result['status']);
    
            // add Headers
            foreach ($result['headers'] as $key => $value) {
                $response->header($key, $value);
            }
    
            // add cookies
            foreach ($result['cookies'] as $key => $value) {
                $response->cookie($key, $value);
            }
    
            // set gzip level
            if (isset($result['gzip']) && $result['gzip']) {
                $response->gzip($result['gzip']);
            }
    
            //send content
            $response->end($result['content']);
        }
    }
    
    public static function getInstance()
    {
        if (! self::$instance) {
            self::$instance = new HttpServer();
        }
        return self::$instance;
    }
    
    protected function initServer()
    {
         $http = new swoole_http_server($this->host, $this->port, $this->mode, $this->sockType);
         
         $http->set($this->setting);
        
         // Set Event Server callback function
         $http->on('Start', array($this, 'onMasterStart'));
         $http->on('ManagerStart', array($this, 'onManagerStart'));
         $http->on('WorkerStart', array($this, 'onWorkerStart'));
         $http->on('Request', array($this, 'onRequest'));
          
         // add listener
         if (is_array($this->listen)) {
             foreach ($this->listen as $v) {
                 if (!$v['host'] || !$v['port']) {
                     continue;
                 }
                 $http->addlistener($v['host'], $v['port'], $this->sockType);
             }
         }
         $this->http = $http;
    }
    
    protected function start()
    {
        $this->log($this->processName . ": start [OK]");
        $this->http->start();
    }
    
    protected function shutdown()
    {
        $masterId = $this->getPidFromFile($this->masterPidFile);
        if (!$masterId) {
            $this->log("[warning] " . $this->processName . ": can not find master pid file");
            $this->log($this->processName . ": stop [FAIL]");
            return false;
        } elseif (!posix_kill($masterId, 15)) {
            $this->log("[warning] " . $this->processName . ": send signal to master failed");
            $this->log($this->processName . ": stop [FAIL]");
            return false;
        }
        unlink($this->masterPidFile);
        unlink($this->managerPidFile);
        usleep(50000);
        $this->log($this->processName . ": stop [OK]"); 
        
        return true;
    }
    
    protected function getPidFromFile($file)
    {
        $pid = false;
        if (file_exists($file)) {
            $pid = file_get_contents($file);
        }
        return $pid;
    }
    
    protected function reload()
    {
        $managerId = $this->getPidFromFile($this->managerPidFile);
        if (!$managerId) {
            $this->log("[warning] " . $this->processName . ": can not find manager pid file");
            $this->log($this->processName . ": reload [FAIL]");
            return false;
        } elseif (!posix_kill($managerId, 10))//USR1
        {
            $this->log("[warning] " . $this->processName . ": send signal to manager failed");
            $this->log($this->processName . ": stop [FAIL]");
            return false;
        }
        $this->log($this->processName . ": reload [OK]");
        return true;
    }
    
    protected function status()
    {
        $this->log('*****************************************************************');
        $this->log('Summary: ');
        $this->log('Swoole Version: ' . SWOOLE_VERSION);
        if (!$this->checkServerIsRunning()) {
            $this->log($this->processName . ': is running [FAIL]');
            $this->log("*****************************************************************");
            return false;
        }
        $this->log($this->processName . ': is running [OK]');
        $this->log('master pid : is ' . $this->getPidFromFile($this->masterPidFile));
        $this->log('manager pid : is ' . $this->getPidFromFile($this->managerPidFile));
        $this->log("*****************************************************************");
    }
    
    protected function checkServerIsRunning()
    {
        $pid = $this->getPidFromFile($this->masterPidFile);
        return $pid && $this->checkPidIsRunning($pid);
    }
    
    protected function checkPidIsRunning($pid)
    {
        return posix_kill($pid, 0);
    }
    
    public function run()
    {
        echo __METHOD__ . PHP_EOL;
        $cmd = isset($_SERVER['argv'][1]) ? strtolower($_SERVER['argv'][1]) : 'help';
        switch ($cmd) {
            case 'stop':
                $this->shutdown();
                break;
            case 'start':
                $this->initServer();
                $this->start();
                break;
            case 'reload':
                $this->reload();
                break;
            case 'restart':
                $this->shutdown();
                sleep(2);
                $this->initServer();
                $this->start();
                break;
            case 'status':
                $this->status();
                break;
            default:
                echo 'Usage:php swoole.php start | stop | reload | restart | status | help' . PHP_EOL;
                break;
        }
    }
    
    public function log($msg)
    {
        if ($this->setting['log_path'] && is_writable($this->setting['log_path'])) {
            error_log(date('Y-m-d H:i:s') . '  ' . $msg . PHP_EOL, 3, $this->setting['log_path'] . '/' . date('ym') . '.log');
            echo $msg . PHP_EOL;
        } else {
            echo '[warning] log path (' . $this->setting['log_path'] . ') is unwritable !!!' . PHP_EOL;
        }
    }
}