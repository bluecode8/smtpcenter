<?php
namespace Protocols;

use \GatewayWorker\Lib\Gateway;
use Workerman\Connection\ConnectionInterface;

class Ftcp implements \Workerman\Protocols\ProtocolInterface
{
    // 协议头长度
    const PACKAGE_HEAD_LEN = 8;

    public static function packByArr($arr)  {
        $atArr=array();
        foreach ($arr as $k=>$v) {
            $atArr[]=pack($v[0],$v[1]);
        }
        return $atArr;
    }

    /**
     * 检查包的完整性
     * 如果能够得到包长，则返回包的在buffer中的长度，否则返回0继续等待数据
     * 如果协议有问题，则可以返回false，当前客户端连接会因此断开
     * @param string $buffer
     * @return int
     */
    public static function input($buffer, ConnectionInterface $connection)
    {
        // echo "ftcp::input\r\n";
        // 如果不够一个协议头的长度，则继续等待
        if (strlen($buffer) < self::PACKAGE_HEAD_LEN) {
            return 0;
        }

        // 解包
        $header         = unpack('Lhead_size/Ldata_size', $buffer);
        // var_dump($header);
        $total_size     = $header['head_size'] + $header['data_size'] + self::PACKAGE_HEAD_LEN;

        // 返回包长
        return $total_size;
    }

    /**
     * 打包，当向客户端发送数据的时候会自动调用
     * @param string $buffer
     * @return string
     */
    public static function encode($data, ConnectionInterface $connection)
    {
        // echo "ftcp::encode\r\n";

        // 数据载荷
        $payload        = isset($data['data']) ? $data['data'] : false;

        // 消息头数据
        $message        = $data;
        unset($message['remote_ip']);      // 删除敏感数据
        unset($message['data']);
        $head_data      = json_encode($message);

        // 打包消息头和数据载荷
        if ($payload) {
            // 有数据载荷
            $size_data      = array(
                'head_size' => array('L', strlen($head_data)),
                'data_size' => array('L', strlen($payload)), // ??????
            );

            // if ($data['type']==201) {
            //     var_dump($size_data);
            // }

            $send_data      = join("", self::packByArr($size_data)) . $head_data . $payload;
        } else {
            // 无数据载荷
            $size_data      = array(
                'head_size' => array('L', strlen($head_data)),
                'data_size' => array('L', 0),
            );
            $send_data      = join("", self::packByArr($size_data)) . $head_data;
        }

        // var_dump($send_data);
        return $send_data;
    }

    /**
     * 解包，当接收到的数据字节数等于input返回的值（大于0的值）自动调用
     * 并传递给onMessage回调函数的$data参数
     * @param string $buffer
     * @return string
     */
    public static function decode($buffer, ConnectionInterface $connection)
    {
        // echo "ftcp::decode\r\n";

        $header         = unpack('Lhead_size/Ldata_size', $buffer);

        // 消息头
        $head_data      = substr($buffer, self::PACKAGE_HEAD_LEN, $header['head_size']);
        $message        = json_decode($head_data, true);

        // IP信息
        $message['remote_ip']   = $connection->getRemoteIp();

        // 数据载荷
        if ($header['data_size']>0) {
            $message['data']        = substr($buffer, self::PACKAGE_HEAD_LEN + $header['head_size']);
        }

        // if ($message['type']==203) {
        //     var_dump($message);
        // }

        return $message;
    }
}