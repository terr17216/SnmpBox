<?php
declare(strict_types=1);

namespace terr17216\snmpbox;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;

class SnmpBoxHandler
{
    public function __construct(
        private \Redis $redis,
    ) {}

    public function __invoke(SnmpBoxRequest $snmpRequest): void
    {
        $action = new SnmpBoxAction($snmpRequest);
        $result = $action->execute();

        // 2. Записываем ответ в Redis
        $responseKey = 'rpc_res:' . $snmpRequest->getRequestId();

        // Помещаем ответ в список и задаем время жизни ключа (на всякий случай)
        $this->redis->lPush($responseKey, json_encode($result));
        $this->redis->expire($responseKey, 60);
    }
}