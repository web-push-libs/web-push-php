<?php

namespace Minishlink\WebPush;

class Queue
{
    /**
     * @var array
     */
    private $array = [];

    public function push($item): void
    {
        $this->array[] = $item;
    }

    public function pop()
    {
        return array_shift($this->array);
    }

    public function count(): int
    {
        return count($this->array);
    }

    public function isEmpty(): bool
    {
        return empty($this->array);
    }

    public function isNotEmpty(): bool
    {
        return $this->isEmpty() === false;
    }
}
