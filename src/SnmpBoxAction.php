<?php
declare(strict_types=1);

namespace terr17216\snmpbox;

use Nmap\Nmap;
use Symfony\Component\Process\Process;

class SnmpBoxAction
{
    private string $snmpPath = '/usr/local/bin/';
    private int $maxOidCount = 10;
    private string $error = '';

    public function __construct(private readonly SnmpBoxRequest $boxRequest)
    {
        if ($this->boxRequest->getCommand() === 'checkTcpPort') {
            $this->maxOidCount = 3;
        }
    }

    public function execute(): SnmpBoxResult
    {
        $microTime = \microtime(true);
        if (!self::validateRequest()) {
            return new SnmpBoxResult()->setError($this->error);
        }

        $result = new SnmpBoxResult();
        $resultData = [];
        $singleResult = [];
        $success = true;
        if (\in_array($this->boxRequest->getCommand(), ['get','walk', 'checkTcpPort'])) {
            foreach ($this->boxRequest->getOid() as $oid) {
                if (\in_array($this->boxRequest->getCommand(), ['get','walk', 'set'])) {
                    $process = Process::fromShellCommandline($this->getFullSnmpCommand($oid));
                    $process->run();
                    if (!$process->isSuccessful()) {
                        $result->setError($process->getErrorOutput());
                        return $result;
                    }
                    $oidResult = $this->getResult($oid, $process->getOutput());
                    $singleResult = $oidResult[$oid];
                    $resultData += $oidResult;
                } elseif ($this->boxRequest->getCommand() === 'checkTcpPort') {
                    $oidResult = $this->getCheckTcpPortResult((string) $oid);
                    $singleResult = $oidResult[$oid];
                    $resultData += $oidResult;
                }
                $this->maxOidCount--;
                if ($this->maxOidCount === 0) {
                    break;
                }
            }
        } elseif ($this->boxRequest->getCommand() === 'set') {
            $process = Process::fromShellCommandline($this->getFullSnmpCommand($this->boxRequest->getOid()));
            $process->run();
            if (!$process->isSuccessful()) {
                $result->setError($process->getErrorOutput());
                return $result;
            }

            $resultData = $this->getSnmpSetResult($this->boxRequest->getOid(), $process->getOutput());
            if (\count($resultData) != \count($this->boxRequest->getOid())) {
                $success = false;
            } else {
                $result->setIntResult(1);
            }
        }

        $result->setFullResult($resultData)->setResult($singleResult)->setSuccess($success);

        if (\count($this->boxRequest->getOid()) === 1) {    // Requested only one oid
            if ($this->boxRequest->getCommand() === 'get') {    // Requested 'get' command
                if (\array_key_exists($this->boxRequest->getOid()[0], $resultData)) {   // Value is exist
                    $result->setStrResult((string) $singleResult[$this->boxRequest->getOid()[0]]);
                    if (\is_int($singleResult[$this->boxRequest->getOid()[0]])) {
                        $result->setIntResult($singleResult[$this->boxRequest->getOid()[0]]);
                    }
                }
            } elseif ($this->boxRequest->getCommand() === 'checkTcpPort') {
                if (\array_key_exists($this->boxRequest->getOid()[0], $resultData)) {
                    $intResult = $resultData[$this->boxRequest->getOid()[0]][$this->boxRequest->getOid()[0]] === 'up' ? 1 : 0;
                    $result->setIntResult($intResult);
                    $result->setStrResult($resultData[$this->boxRequest->getOid()[0]][$this->boxRequest->getOid()[0]]);
                }
            }
        }
        $result->setExecutionTime(\round((\microtime(true) - $microTime), 2));

        return $result;
    }

    private function getResult(string $requestOid, string $snmpResultSrc): array
    {
        $returnArray = [];

        foreach (\explode("\n", $snmpResultSrc) as $row) {
            $arrayResult = \explode(' = ', $row);
            if (\array_key_exists(0, $arrayResult) && \array_key_exists(1, $arrayResult)) {
                $returnArray[$requestOid][$arrayResult[0]] = $this->formatValue($arrayResult[1]);
            }
        }

        return $returnArray;
    }

    private function getSnmpSetResult(array $snmpSetOidList, string $snmpResultSrc): array
    {
        $returnArray = [];
        $oidList = [];
        foreach ($snmpSetOidList as $r) {
            $oidList[] = $r['oid'];
        }

        foreach (\explode("\n", $snmpResultSrc) as $row) {
            $arrayResult = \explode(' = ', $row);
            if (\array_key_exists(0, $arrayResult) && \array_key_exists(1, $arrayResult)) {
                if (in_array($arrayResult[0], $oidList)) {
                    $returnArray[$arrayResult[0]] = $this->formatValue($arrayResult[1]);
                }
            }
        }

        return $returnArray;
    }

    private function getCheckTcpPortResult(string $port): array
    {
        $nmap = Nmap::create();
        $nmap->setTimeout(3);
        $hosts = $nmap->scan([$this->boxRequest->getIpAddress()], [$port]);
        foreach ($hosts as $host) {
            if ($host->getState() !== 'up') {
                return [$port => [$port => 'hostDown']];
            }
            $ports = $host->getPorts();
            foreach ($ports as $thisPort) {
                if ($thisPort->getState() !== 'open') {
                    return [$port => [$port => 'down']];
                }
            }
            return [$port => [$port => 'up']];
        }
        return [];
    }

    private function formatValue (null|string $value): string|int
    {
        if (empty($value)) {
            return '';
        }

        if (\preg_match('/^INTEGER: /', $value)) {
            return (int) \str_replace('INTEGER: ', '', \preg_replace('/[^0-9-]/', '', $value));
        } elseif (\preg_match('/^Gauge32: /', $value)) {
            return (int) \str_replace('Gauge32: ', '', $value);
        } elseif (\preg_match('/^Counter32: /', $value)) {
            return (int) \str_replace('Counter32: ', '', $value);
        } elseif (\preg_match('/^IpAddress: /', $value)) {
            return \str_replace('IpAddress: ', '', $value);
        } elseif (\preg_match('/^STRING: /', $value)) {
            return \str_replace('STRING: ', '', $value);
        } elseif (\preg_match('/^Timeticks: /', $value)) {
            return \str_replace('Timeticks: ', '', $value);
        } elseif (\preg_match('/^OID: /', $value)) {
            return \str_replace('OID: ', '', $value);
        } elseif (\preg_match('/^Hex-STRING: /', $value)) {
            return \str_replace(' ', '', \str_replace('Hex-STRING: ', '', $value));
        } else {
            return \str_replace('"', '', $value);
        }
    }

    private function validateRequest(): bool
    {
        if (\in_array($this->boxRequest->getCommand(), ['get','walk', 'set'])) {
            if (\in_array($this->boxRequest->getSnmpVersion(), [1, 2])) {
                if (empty($this->boxRequest->getSnmpCommunity())) {
                    $this->error = 'The snmp community is not specified.';
                    return false;
                }
            } else {
                if (empty($this->boxRequest->getSnmpV3Login())) {
                    $this->error = 'The snmp v3 login is not specified.';
                    return false;
                }
                if (empty($this->boxRequest->getSnmpV3Password())) {
                    $this->error = 'The snmp v3 password is not specified.';
                    return false;
                }
            }

            if (\in_array($this->boxRequest->getCommand(), ['get', 'walk'])) {
                foreach ($this->boxRequest->getOid() as $oid) {
                    $pattern = '/^\.(?:\d+\.)*\d+$/';
                    if (is_string($oid)) {
                        $targetOid = $oid;
                    } elseif (\is_array($oid)) {
                        if (!\array_key_exists('oid', $oid)) {
                            $this->error = 'The snmp oid set as array, but oid key is not exists';
                            return false;
                        }
                        $targetOid = $oid['oid'];
                    }
                    if (!preg_match($pattern, $targetOid)) {
                        $this->error = 'The snmp oid ' . $targetOid . ' is not valid.';
                        return false;
                    }
                }
            } else {
                foreach ($this->boxRequest->getOid() as $oid) {
                    $pattern = '/^\.(?:\d+\.)*\d+$/';
                    if (!\is_array($oid)) {
                        $this->error = 'The snmpset oid is not array.';
                        return false;
                    }

                    if (!\array_key_exists('oid', $oid)) {
                        $this->error = 'The snmp oid ' . $oid . ' is not exists.';
                        return false;
                    }
                    if (!preg_match($pattern, $oid['oid'])) {
                        $this->error = 'The snmp set oid ' . $oid . ' is not valid.';
                        return false;
                    }

                    if (!\array_key_exists('type', $oid)) {
                        $this->error = 'The snmp set type is not exists.';
                        return false;
                    }
                    if (!\in_array($oid['type'], ['a', 's', 'i', 'x'])) {
                        $this->error = 'for snmp set value type "' . $oid['type'] . '" is not valid, use: a, s, i or x';
                        return false;
                    }

                    if (!\array_key_exists('value', $oid)) {
                        $this->error = 'The snmp set value is not exists.';
                        return false;
                    }
                }
            }
        } elseif ($this->boxRequest->getCommand() === 'checkTcpPort') {
            if (\count($this->boxRequest->getOid()) === 0) {
                $this->error = 'for command checkTcpPort variable PortList (oid) is empty';
                return false;
            }

            if (\count($this->boxRequest->getOid()) > 3) {
                $this->error = 'For the checkTcpPort command, the maximum number of ports is 3 ('
                    . \count($this->boxRequest->getOid()).' were specified)';
                return false;
            }

            foreach ($this->boxRequest->getOid() as $oid) {
                if (!is_int($oid)) {
                    $this->error = 'For the checkTcpPort command, the specified port number ('
                        . var_export($oid, true).') is not an integer';
                    return false;
                }

                if (($oid < 0) || ($oid > 65535)) {
                    $this->error = 'The specified TCP port must be greater than zero and less than 65535 ('.$oid.' was specified)';
                    return false;
                }
            }
        }
        return true;
    }

    public function getFirstOidCommand(): string
    {
        return $this->getFullSnmpCommand($this->boxRequest->getOid()[0]);
    }

    public function getFullSnmpCommand(string|array $oid): string
    {
        if (!\in_array($this->boxRequest->getCommand(), ['get','walk', 'set'])) {
            return '';
        }

        if ($this->boxRequest->getSnmpVersion() != 2) {
            $version = $this->boxRequest->getSnmpVersion();
        } else {
            $version = '2c';
        }

        if (\is_string($oid)) {
            $runOid = $oid;
        } else {
            $runOid = '';
            foreach ($oid as $oidUnit) {
                if ($this->boxRequest->getCommand() === 'set') {
                    $runOid .= $oidUnit['oid'] . ' ' . $oidUnit['type'] . ' ';
                    if (\preg_match('/^\d+$/', (string) $oidUnit['value'])) {
                        $runOid .= $oidUnit['value'];
                    } else {
                        $runOid .= '"' . $oidUnit['value'] . '"';
                    }
                    $runOid .= ' ';
                }
            }
        }

        // TODO: Add SNMP V3 Command Support
        return $this->snmpPath . 'snmp' . $this->boxRequest->getCommand() .' -v ' . $version
            . ' -Onx'
            . ' -c ' . $this->boxRequest->getSnmpCommunity()
            . ' ' . $this->boxRequest->getIpAddress()
            . ' ' . $runOid;
    }
}