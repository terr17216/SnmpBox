<?php
declare(strict_types=1);

namespace terr17216\snmpbox;

use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Cache\Adapter\RedisAdapter;

class SnmpBoxClient
{
    public function __construct(
        private \Redis $redis,
        private MessageBusInterface $messageBus,
        private readonly SnmpBoxRequest $boxRequest
    )
    {
        $this->boxRequest->setRequestId(Uuid::v4()->toString());
    }

    public function sendRequestAndGetResult(): SnmpBoxResult
    {
        $this->sendRequest();
        return $this->getResult();
    }

    public function sendRequest(): void
    {
        $this->messageBus->dispatch($this->boxRequest);
    }

    public function getResult(): SnmpBoxResult
    {
        // 2. Блокируем поток и ждем ответ в Redis (Паттерн Polling)
        $responseKey = 'rpc_res:' . $this->boxRequest->getRequestId();
        $timeout = 5; // Максимальное время ожидания ответа в секундах
        $startTime = \time();

        /**
         * 2. Элегантное блокирующее ожидание (Long Polling)
         * PHP замирает на этой строчке. Никаких циклов while!
         * Метод возвращает массив: [имя_ключа, значение] или false по таймауту.
         */
        $redisResponse = $this->redis->brPop([$responseKey], $timeout);

        // 3. Проверяем, уложился ли воркер в таймаут
        if (empty($redisResponse)) {
            return new SnmpBoxResult();
        }

        // Данные лежат во втором элементе массива
        $this->redis->del($responseKey); // Удаляем за собой временный ключ
        return SnmpBoxResult::fromJson($redisResponse[1]);
    }

    public function getRequestId(): string
    {
        return $this->boxRequest->getRequestId();
    }

    public function setRequestId(string $id): static
    {
        $this->boxRequest->setRequestId($id);
        return $this;
    }
}