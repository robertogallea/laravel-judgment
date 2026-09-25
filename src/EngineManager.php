<?php

namespace RobertoGallea\Judgment;

use Closure;
use Illuminate\Contracts\Container\Container;
use RobertoGallea\Judgment\Contracts\Engine;
use RobertoGallea\Judgment\Exceptions\EngineNotConfigured;

/**
 * Builds the Engine of each named connection in judgment.engines, once per
 * connection. A connection's driver is a registered driver name or a class
 * implementing the Engine contract, resolved from the container.
 */
class EngineManager
{
    /** @var array<string, Engine> */
    private array $engines = [];

    /** @var array<string, Closure(Container, array<string, mixed>, string): Engine> */
    private array $drivers = [];

    private ?Engine $prevented = null;

    public function __construct(private readonly Container $container) {}

    /**
     * Answer every connection with the given Engine from now on, so no real Engine is reached.
     *
     * @internal used by Judge::fake()
     */
    public function prevent(Engine $engine): void
    {
        $this->prevented = $engine;
    }

    /** The Engine of the named connection, or of the default connection when none is named. */
    public function engine(?string $connection = null): Engine
    {
        $connection ??= $this->container->make('config')->get('judgment.engine') ?? throw EngineNotConfigured::noDefault();

        return $this->prevented ?? $this->engines[$connection] ??= $this->build($connection);
    }

    /**
     * Register a driver that connections can name.
     *
     * @param  Closure(Container, array<string, mixed>, string): Engine  $factory  given the container, the connection's configuration and its name
     */
    public function extend(string $driver, Closure $factory): static
    {
        $this->drivers[$driver] = $factory;

        return $this;
    }

    private function build(string $connection): Engine
    {
        /** @var array<string, mixed>|null $config */
        $config = $this->container->make('config')->get("judgment.engines.$connection");
        if (! is_array($config) || ! is_string($config['driver'] ?? null)) {
            throw EngineNotConfigured::connection($connection);
        }

        $driver = $config['driver'];
        if (isset($this->drivers[$driver])) {
            return ($this->drivers[$driver])($this->container, $config, $connection);
        }

        $engine = $this->container->make($driver);
        if (! $engine instanceof Engine) {
            throw EngineNotConfigured::notAnEngine($connection, $driver);
        }

        return $engine;
    }
}
