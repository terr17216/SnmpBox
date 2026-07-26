# SnmpBox

Асинхронный PHP-пакет для взаимодействия с сетевым оборудованием по протоколу SNMP и проверки доступности TCP-портов с использованием Symfony Messenger.
Пакет исключает необходимость прямого взаимодействия WEB сервера и SNMP-клиента. Отправка SNMP запросов и получение результатов проходит через Redis.
Такая схема убирает необходимость WEB сервера в запуске команд пакета snmp и расширнения системного взаимодейтсвия с ним.
Так-же плюсом является взаимодействие с оборудованием, которое систематически медленно отвечает на запросы, тем самым "подвешивая" WEB сервер.

## Требования

Для работы пакета на сервере должны быть установлены следующие системные утилиты и расширения PHP:

* **PHP** `>= 8.4`
* **Расширение PHP**: `ext-redis`, `ext-json`
* **Системные утилиты**: `snmp` (команды `snmpget`/`snmpset`) и `nmap`

### Установка утилит в Ubuntu/Debian:
```bash
sudo apt update
sudo apt install snmp nmap
```

## Установка

Установите пакет в ваш проект с помощью Composer:

```bash
composer require terr17216/snmpbox
```

Что бы транспорт обрабатывал запросы указываем в _config/packages/messenger.yaml_:

```bash
framework:
    messenger:
        routing:
            'terr17216\snmpbox\SnmpBoxRequest': snmp_transport
```

## Использование

### 1. Отправка запроса и получение результата в одном методе

Вы можете создать запрос на сканирование (`walk`, `get`, `set` или `checkTcpPort`) и отправить его в шину сообщений Symfony Messenger:

```php
use terr17216\SnmpBox\SnmpBoxClient;
use terr17216\SnmpBox\SnmpBoxRequest;

// Настройка объекта запроса
$request = (new SnmpBoxRequest())
    ->setIpAddress('192.168.1.1')
    ->setCommand('walk')
    ->setSnmpVersion(2)
    ->setSnmpCommunity('public')
    ->setOid(['.1.3.6.1.2.1.1.5', '.1.3.6.1.2.1.1.3']); // SysName and Uptime

// Передайте в конструктор клиента ваш Redis и Symfony MessageBus
$snmpClient = new SnmpBoxClient(redisInstance, messageBus, request);

// Отправка в очередь и ожидание результата
$result = snmpClient->sendRequestAndGetResult();

if (\$result->isSuccess()) {
    print_r(\$result->getFullResult());
    print_r(\$result->setResult());
} else {
    echo "Ошибка: " . \$result->getError();
}
```
Результатом работы програмы будет:

```bash
Array
(
  [.1.3.6.1.2.1.1.5] => Array
    (
      [.1.3.6.1.2.1.1.5.0] => MGMT_WS-C3750
    )
  [.1.3.6.1.2.1.1.3] => Array
    (
       [.1.3.6.1.2.1.1.3.0] => (1875642275) 217 days, 2:07:02.75
    )
)
Array
(
[.1.3.6.1.2.1.1.3.0] => (1875642275) 217 days, 2:07:02.75
)
```

### 2. Асинхронная отправка запроса и получение результата

```php
class SomeController extends AbstractController
{
    ...
    
    #[Route('/some/route/request', name: 'app_route_request')]
    public function methodRequest(): JsonResponse
    {
        return $this->json(['url' => $this->generateUrl('app_route_body',
            ['requestId' => $this->sendRequest($this->redis, $this->messageBus)])]);
    }
    
    #[Route('/some/route/{requestId}/body', name: 'app_route_body')]
    public function methodBody(string $requestId): Response
    {
        $snmpClient = new SnmpBoxClient($this->redis, $this->messageBus, new SnmpBoxRequest());
        $snmpClient->setRequestId($requestId); $result = $snmpClient->getResult();
        return new Response(\print_r(\$result->getFullResult()), Response::HTTP_OK,
        ['content-type' => 'text/html']);
    }
    
    public function sendRequest(\Redis $redis, MessageBusInterface $messageBus): string
    {
        $request = (new SnmpBoxRequest())
        ->setIpAddress('192.168.1.1')
        ->setCommand('walk')
        ->setSnmpVersion(2)
        ->setSnmpCommunity('public')
        ->setOid(['.1.3.6.1.2.1.1.5', '.1.3.6.1.2.1.1.3']); // SysName and Uptime
        $snmpClient = new SnmpBoxClient($redis, $messageBus, $this->getSnmpBoxRequest());
        $snmpClient->sendRequest();
        return $snmpClient->getRequestId();
    }
}
```

На стороне FrontEnd необходимо отправить запрос на получение маршрута, по которому загрузить HTML.

### 3. Запуск обработчика (Worker)

Убедитесь, что в вашем основном приложении Symfony настроен воркер для обработки сообщений. Пакет использует атрибут `#[AsMessageHandler]` для класса `SnmpBoxHandler`, поэтому Symfony Messenger автоматически зарегистрирует его.

Для запуска обработки выполните команду в консоли вашего основного приложения:
```bash
php bin/console messenger:consume
```

Для запуска Handler создаёте в своём приложении файл SnmpBoxRequestHandler.php со следующим содержимым:

```php
<?php
declare(strict_types=1);

namespace App\MessageHandler;

use terr17216\snmpbox\SnmpBoxHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class SnmpBoxRequestHandler extends SnmpBoxHandler
{
}
```

Рекомендуется использовать Supervisor (https://supervisord.org/) для работы Handler-а.

### 4. Использование в командах

```php
class SomeCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $request = (new SnmpBoxRequest())
        ->setIpAddress('192.168.1.1')
        ->setCommand('walk')
        ->setSnmpVersion(2)
        ->setSnmpCommunity('public')
        ->setOid(['.1.3.6.1.2.1.1.5', '.1.3.6.1.2.1.1.3']);
        
        // Взаимодействие через Redis
        $snmpClient = new SnmpBoxClient($this->redis, $this->messageBus, $snmpRequest);
        $snmpBoxResult = $snmpClient->sendRequestAndGetResult();
    }
}
```

Однако мы понимаем, что команды можно запускать с привилегиями доступа до консольных приложений, в виду чего можно сделать прямое взаимодействие, без Redis:

```php
class SomeCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $request = (new SnmpBoxRequest())
        ->setIpAddress('192.168.1.1')
        ->setCommand('walk')
        ->setSnmpVersion(2)
        ->setSnmpCommunity('public')
        ->setOid(['.1.3.6.1.2.1.1.5', '.1.3.6.1.2.1.1.3']);
        
        // Прямое взаимодействие
        $snmpBoxAction = new SnmpBoxAction($snmpRequest);
        $snmpBoxResult = $snmpBoxAction->execute();
    }
}
```

Таким образом исключается задержка прохождения запроса через Redis и MessageBus.

### 5. Команда ping

```php
$request = (new SnmpBoxRequest())
   ->setIpAddress('192.168.1.1')
   ->setCommand('ping');
```
Другие параметры запроса будут проигнорированы

## Доступные команды (SnmpBoxRequest::setCommand)
* `walk` — последовательное чтение дерева OID.
* `get` — получение конкретного значения OID.
* `set` — изменение параметров на оборудовании.
* `checkTcpPort` — проверка доступности TCP-портов (использует утилиту `nmap`).
* `ping` — пинг до устройства, 2 пакета.

## Доступные методы (SnmpBoxRequest)
* `setOid()` — Добавляем массив OID для обработки.
* `addOid()` — Добавляет 1 (один) OID для обработки, можно вызывать несколько раз.

## Доступные методы (SnmpBoxResult)
* `getSuccess()` — Возвращает статус обработки запроса (bool)
* `getError()` — Возвращает ошибку при обработке запроса, если таковая имеет место быть
* `getFullResult()` - Возвращает полный результат обработки всех запрошенных OID
* `getResult()` - Возвращает первый результат. Удобно при использовании одиночного get
* `getIntResult()` - Возвращает целочисленный результат. Предназначен при использовании одиночного get или команды set
* `getStrResult()` - Возвращает строковый результат первого OID.
* `getExecutionTime()` - Возвращает время выполнения обработки запроса в милисекундах.
* `getFullSnmpCommand()` - Возвращает строку запроса SNMP.

## Лицензия

Этот проект распространяется под лицензией MIT. Подробнее см. в файле [LICENSE](LICENSE).