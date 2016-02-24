<?php
/**
 * @name DemoController
 * @author chenming
 * @desc 默认控制器
 * @see http://www.php.net/manual/en/class.yaf-controller-abstract.php
 */
class DemoController extends Yaf_Controller_Abstract {

	public function IndexAction() {
		
	    //echo "hello, world";
        //echo json_encode(array('first' => 'one', 'second' => 'two'));
        $response = array('status' => 200,
            'headers' => array('Content-Type' => 'application/json;charset=UTF-8',
                'X-RateLimit-Limit' => 20,
                'X-RateLimit-Remaining' => 5,
            ),
            'cookies' => array(),
            'gzip' => 0,
            'content' => json_encode(array('first' => 'one', 'second' => 'two')),
        );
        
        echo json_encode($response);
        return false;
	}
}
