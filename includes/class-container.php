<?php

namespace YourShare;

if (!defined('ABSPATH')) {
    exit;
}

class Container
{
    /** @var array<string, callable> */
    private $factories = [];

    /** @var array<string, mixed> */
    private $instances = [];

    public function set(string $id, callable $factory): void
    {
        $this->factories[$id] = $factory;
    }

    /**
     * @template T
     * @param string $id
     * @return T
     */
    public function get(string $id)
    {
        if (!array_key_exists($id, $this->instances)) {
            if (!isset($this->factories[$id])) {
                throw new \InvalidArgumentException(sprintf('Service "%s" is not registered in the container.', $id));
            }

            $this->instances[$id] = call_user_func($this->factories[$id], $this);
        }

        return $this->instances[$id];
    }
}
