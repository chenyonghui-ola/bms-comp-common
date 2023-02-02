# 使用说明
## 依赖"guzzlehttp/guzzle": "6.5.2"，使用composer安装此包
## 新增文件app/model/rpc/DemoRpc.php文件
```PHP
<?php

namespace Imee\Models\Rpc;

use GuzzleHttp\Psr7\Response;
use Imee\Libs\Rpc\BaseRpc;

class DemoRpc extends BaseRpc
{
    const API_PUSH_INDEX = 'pushIndex';
    
    protected $apiDevConfig = [
        'domain' => 'http://127.0.0.1',//ip
        'host'   => 'pt-dev.iambanban.com'//域名
    ];

    protected $apiConfig = [
        'domain' => 'https://admin.partying.sg',
        'host'   => 'admin.partying.sg'
    ];

    public $apiList = [
        self::API_PUSH_INDEX => [
            'path'   => '/api/xxx',
            'method' => 'post',
        ]
    ];

    protected function serviceConfig(): array
    {
        $config = ENV == 'dev' ? $this->apiDevConfig : $this->apiConfig;
        $config['options'] = [
            'headers'         => [
                'Content-Type'  => 'application/json',
            ],
            'connect_timeout' => 5,
            'timeout'         => 10,
        ];

        $config['retry'] = [
            'max'   => 2,
            'delay' => 100,
        ];

        return $config;
    }

    protected function decode(Response $response = null, $code = 200): array
    {
        if ($response) {
            return [json_decode($response->getBody(), true), $response->getStatusCode()];
        }

        return [null, 500];
    }
}
```

## 新增app/service/rpc/DemoService.php调用接口组装业务逻辑
```PHP
<?php

namespace Imee\Service\Rpc;

use Imee\Models\Rpc\DemoRpc;

class PtAdminService
{
    /** @var DemoRpc $demoRpc */
    private $demoRpc;

    public function __construct()
    {
        $this->demoRpc = new DemoRpc();
    }

    public function pushIndex($params): array
    {
        list($result, $code) = $this->demoRpc->call(DemoRpc::API_PUSH_INDEX, ['form_params' => $params]);

        if ($code != 200 || !$result['success']) {
            return [false, ['msg' => $result['msg'] ?? '接口异常']];
        }

        return [true, $result];
    }
}
```
