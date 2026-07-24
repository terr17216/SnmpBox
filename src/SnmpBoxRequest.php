<?php
declare(strict_types=1);

namespace terr17216\snmpbox;

use Symfony\Component\Validator\Constraints as Assert;

class SnmpBoxRequest
{
    private string $requestId = '';
    #[Assert\Ip]
    private string $ipAddress;

    #[Assert\Choice(
        choices: [1, 2, 3],
        message: 'The value must be exactly 1, 2, or 3.'
    )]
    private int $snmpVersion = 2;
    private string $snmpCommunity = '';
    private string $snmpV3Login = '';
    private string $snmpV3Password = '';

    #[Assert\Choice(
        choices: ['get', 'walk', 'set', 'ping', 'checkTcpPort'],
        message: 'The value must be exactly get, walk or set.'
    )]
    private string $command = 'walk';

     /*
     * for command = 'get'|'walk': set oid list: $oid = ['.1.3.6.1.4.1.171.11.63.8.2.7.8.1.2', '.1.3.6.1.2.1.2.2.1.7']
     * for command = 'set': set array: $oid = [
      *     ['oid' => '.1.3.6.1.2.1.2.2.1.7', 'type' => 's', 'value' => 'someValue'],
      *     ['oid' => '.1.3.6.1.4.1.171.11.63.8.2.1.2.2', 'type' => 'a', 'value' => '192.168.0.1'],
      * ]
      *
      * !!! Note: for the `snmpset` command, use only the following order of arguments: OID, type, and value,
      * !!! because the command execution relies on `implode(' ', array_values())`.
      *
      * for command = 'set' typeList:
      * s - string
      * a - ipAddress
      * i - integer
      * x - hex-string
      *
      * for command 'checkTcpPort: set portList to $oid array: $oid = [80, 443]
      * The command will return the check status: true/false for each port
      * The command checks only TCP ports.
     */
    private array $oid = [];

    /*public function __construct(
        private string $requestId = '' // Уникальный ID запроса (UUID)
    ) {}*/

    public function getRequestId(): string
    {
        return $this->requestId;
    }

    public function setRequestId(string $requestId): static
    {
        $this->requestId = $requestId;
        return $this;
    }

    public function getIpAddress(): string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(string $ipAddress): static
    {
        $this->ipAddress = $ipAddress;
        return $this;
    }

    public function getSnmpVersion(): int
    {
        return $this->snmpVersion;
    }

    public function setSnmpVersion(int $snmpVersion): static
    {
        $this->snmpVersion = $snmpVersion;
        return $this;
    }

    public function getSnmpCommunity(): string
    {
        return $this->snmpCommunity;
    }

    public function setSnmpCommunity(string $snmpCommunity): static
    {
        $this->snmpCommunity = $snmpCommunity;
        return $this;
    }

    public function getSnmpV3Login(): string
    {
        return $this->snmpV3Login;
    }

    public function setSnmpV3Login(string $snmpV3Login): static
    {
        $this->snmpV3Login = $snmpV3Login;
        return $this;
    }

    public function getSnmpV3Password(): string
    {
        return $this->snmpV3Password;
    }

    public function setSnmpV3Password(string $snmpV3Password): static
    {
        $this->snmpV3Password = $snmpV3Password;
        return $this;
    }

    public function getCommand(): string
    {
        return $this->command;
    }

    public function setCommand(string $command): static
    {
        $this->command = $command;
        return $this;
    }

    public function getOid(): array
    {
        return $this->oid;
    }

    public function setOid(array $oid): static
    {
        $this->oid = $oid;
        return $this;
    }

    public function addOid(string|int $oid): static
    {
        $this->oid = \array_merge($this->oid, [$oid]);
        return $this;
    }
}