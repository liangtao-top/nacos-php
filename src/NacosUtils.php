<?php
declare(strict_types=1);
// +----------------------------------------------------------------------
// | CodeEngine
// +----------------------------------------------------------------------
// | Copyright 艾邦
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: TaoGe <liangtao.gz@foxmail.com>
// +----------------------------------------------------------------------
// | Version: 2.0 2022/5/16 11:09
// +----------------------------------------------------------------------
namespace V2dmIM\Nacos;

use Throwable;
use Swoole\Timer;
use RuntimeException;

/**
 * Nacos2.0 工具类
 */
class NacosUtils
{

    /**
     * 服务注册+维持心跳
     * @param string $host Nacos服务地址
     * @param string $name 服务名称
     * @param string $ip   服务IP
     * @param int    $port 服务端口
     * @param int    $ms   心跳间隔
     * @author TaoGe <liangtao.gz@foxmail.com>
     * @date   2022/5/16 11:15
     */
    public static function registerAndHeart(string $host, string $name, string $ip, int $port, int $ms = 5000): void
    {
        try {
            $nacos = new Nacos($host);
            $res   = $nacos->join($name, $ip, $port);
            if ($res !== true) {
                throw new RuntimeException("{$nacos->getPrefix()}$name $ip:$port registration failed.");
            }
            echo("Nacos2.0 {$nacos->getPrefix()}$name $ip:$port registry success." . PHP_EOL);
        } catch (Throwable $e) {
            echo($e->getMessage() . PHP_EOL);
            Timer::after($ms, function () use ($host, $name, $ip, $port, $ms) {
                self::registerAndHeart($host, $name, $ip, $port, $ms);
            });
            return;
        }
        Timer::tick($ms, function (int $timer_id) use ($nacos, $host, $name, $ip, $port, $ms) {
            try {
                $nacos->beat($name, $ip, $port, [], $ms);
//                echo("Timer::tick#$timer_id {$nacos->getPrefix()}$name $ip:$port client beat interval {$ms}ms." . PHP_EOL);
            } catch (Throwable $e) {
                echo($e->getMessage() . PHP_EOL);
                if (Timer::clear($timer_id)) {
                    Timer::after($ms, function () use ($host, $name, $ip, $port, $ms) {
                        self::registerAndHeart($host, $name, $ip, $port, $ms);
                    });
                }
            }
        });
    }
}
