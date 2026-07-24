<?php
declare(strict_types=1);

namespace terr17216\snmpbox;

use JsonSerializable;

class SnmpBoxResult implements JsonSerializable
{
    private bool $success = false;

    private string $error = '';

    private array $result = [];
    private array $fullResult = [];

    private int $intResult = 0;
    private string $strResult = '';
    private float $executionTime = 0;

    public function setSuccess(bool $success): static
    {
        $this->success = $success;
        return $this;
    }

    public function getSuccess(): bool
    {
        return $this->success;
    }

    public function setError(string $error): static
    {
        $this->error = $error;
        return $this;
    }

    public function getError(): string
    {
        return $this->error;
    }

    public function setFullResult(array $result): static
    {
        $this->fullResult = $result;
        return $this;
    }

    public function getFullResult(): array
    {
        return $this->fullResult;
    }

    public function setResult(array $result): static
    {
        $this->result = $result;
        return $this;
    }

    public function getResult(): array
    {
        return $this->result;
    }

    public function setIntResult(int $intResult): static
    {
        $this->intResult = $intResult;
        return $this;
    }

    public function getIntResult(): int
    {
        return $this->intResult;
    }

    public function setStrResult(string $strResult): static
    {
        $this->strResult = $strResult;
        return $this;
    }

    public function getStrResult(): string
    {
        return $this->strResult;
    }

    public function setExecutionTime(float $executionTime): static
    {
        $this->executionTime = $executionTime;
        return $this;
    }

    public function getExecutionTime(): float
    {
        return $this->executionTime;
    }

    // Этот метод автоматически вызовется при json_encode($this)
    public function jsonSerialize(): array {
        return [
            'success'   => $this->success,
            'error' => $this->error,
            'result' => $this->result,
            'fullResult' => $this->fullResult,
            'intResult' => $this->intResult,
            'strResult' => $this->strResult,
            'executionTime' => $this->executionTime,
        ];
    }

    // Изящное восстановление из JSON (после Redis)
    public static function fromJson(string $jsonString): self {
        $data = json_decode($jsonString, true);

        // Передаем данные в конструктор
        return new self()
            ->setSuccess($data['success'])
            ->setError($data['error'])
            ->setResult($data['result'])
            ->setFullResult($data['fullResult'])
            ->setIntResult($data['intResult'])
            ->setStrResult($data['strResult'])
            ->setExecutionTime($data['executionTime']);
    }
}