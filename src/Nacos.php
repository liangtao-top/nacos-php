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
// | Version: 2.0 2022/5/19 17:40
// +----------------------------------------------------------------------
namespace V2dmIM\Nacos;

use Swoole\NameResolver;
use Swoole\NameResolver\Cluster;
use Swoole\NameResolver\Exception;
use function Swoole\Coroutine\Http\get;
use function Swoole\Coroutine\Http\post;
use function Swoole\Coroutine\Http\request;

class Nacos extends NameResolver
{

    /**
     * @throws \Swoole\Coroutine\Http\Client\Exception|Exception
     */
    public function join(string $name, string $ip, int $port, array $options = []): bool
    {
        $params['port']        = $port;
        $params['ip']          = $ip;
        $params['healthy']     = 'true';
        $params['weight']      = $options['weight'] ?? 100;
        $params['encoding']    = $options['encoding'] ?? 'utf-8';
        $params['namespaceId'] = $options['namespaceId'] ?? 'public';
        $params['serviceName'] = $this->prefix . $name;

        $url = $this->baseUrl . '/nacos/v1/ns/instance?' . http_build_query($params);
        $r   = post($url, []);
        return $this->checkResponse($r, $url);
    }

    /**
     * 维持心跳
     * @param string $name 服务名称
     * @param string $ip   服务IP
     * @param int    $port 服务端口
     * @param int    $ms   心跳间隔
     * @author TaoGe <liangtao.gz@foxmail.com>
     * @date   2022/5/16 11:15
     * @throws \Swoole\Coroutine\Http\Client\Exception|Exception
     */
    public function beat(string $name, string $ip, int $port, array $options = [], int $ms = 3000): bool
    {
        $params['serviceName'] = $this->prefix . $name;
        $params['beat']        = json_encode([
                                                 'namespaceId' => $options['namespaceId'] ?? 'public',
                                                 "cluster"     => '',
                                                 "ip"          => $ip,
                                                 "port"        => $port,
                                                 "scheduled"   => true,
                                                 "serviceName" => $this->prefix . $name,
                                                 "weight"      => $options['weight'] ?? 100,
                                             ]);
        $url                   = $this->baseUrl . '/nacos/v1/ns/instance/beat?' . http_build_query($params);
        $r                     = request($url, 'PUT');
        return $this->checkResponse($r, $url);
    }


    /**
     * @throws \Swoole\Coroutine\Http\Client\Exception|Exception
     */
    public function leave(string $name, string $ip, int $port): bool
    {
        $params['port']        = $port;
        $params['ip']          = $ip;
        $params['serviceName'] = $this->prefix . $name;

        $url = $this->baseUrl . '/nacos/v1/ns/instance?' . http_build_query($params);
        $r   = request($this->baseUrl . '/nacos/v1/ns/instance?' . http_build_query($params), 'DELETE');
        return $this->checkResponse($r, $url);
    }

    /**
     * @throws \Swoole\Coroutine\Http\Client\Exception|Exception|\Swoole\Exception
     */
    public function getCluster(string $name): ?Cluster
    {
        $params['serviceName'] = $this->prefix . $name;
        $params['healthyOnly'] = true;
        $url                   = $this->baseUrl . '/nacos/v1/ns/instance/list?' . http_build_query($params);
        $r                     = get($url);
        if (!$this->checkResponse($r, $url)) {
            return null;
        }
        $result = json_decode($r->getBody());
        if (empty($result)) {
            return null;
        }
        $cluster = new Cluster();
        foreach ($result->hosts as $node) {
            $cluster->add($node->ip, $node->port, (int)$node->weight);
        }
        return $cluster;
    }

    /**
     * getPrefix
     * @return string
     * @author TaoGe <liangtao.gz@foxmail.com>
     * @date   2022/5/20 12:29
     */
    public function getPrefix(): string
    {
        return $this->prefix;
    }
}
