<?php

namespace Blink;

use Closure;
use Blink\DataPipe;
use Blink\Exception\CodingError;

/**
 * Class for reporting task and subtasks progress.
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class ProgressReporter {

    const OKAY = 0;

    const INFO = 1;

    const WARN = 2;

    const ERROR = 3;

    /**
     * Parent progress reporter of this child.
     *
     * @var static
     */
    public ?ProgressReporter $parent = null;

    /**
     * Child progress reporter in this instance.
     *
     * @var static[]
     */
    public array $childs = array();

    /**
     * Target name for this progress.
     */
    public ?string $name = null;

    /**
     * Number of childs is expected to have.
     *
     * @var int
     */
    public int $expectedChildsCount = 0;

    /**
     * Number of times update was called.
     *
     * @var int
     */
    public int $updated = 0;

    /**
     * Current level of this reporter.
     *
     * @var int
     */
    public int $level = 1;

    /**
     * Progress set for this instance.
     * A number in the range [0, 1]
     *
     * @var float
     */
    public float $progress = 0;

    /**
     * Calculated progress for this instance, including childs.
     * Updated by {@see ProgressReporter::update()}.
     *
     * @var float
     */
    public float $calculated = 0;

    /**
     * Progress weight of this instance, used in parent progress reporter.
     *
     * @var float
     */
    public float $weight = 1;

    /**
     * Progress weight, used in calculating `$calculated` progress with childs.
     *
     * @var float
     */
    public float $progressWeight = 0.1;

    /**
     * Total child's weight, for fast progress calculation.
     *
     * @var float
     */
    protected float $totalWeight = 0;

    /**
     * Update listener.
     *
     * @var callable
     */
    protected ?Closure $updateListener = null;

    /**
     * Update progress.
     *
     * @param   ?string     $message        Bubbled up message.
     * @param   ?string     $status         Message status.
     * @return  static
     */
    public function update(ProgressReporter $source, int $status, string $message) {
        $this -> updated += 1;

        if (empty($this -> childs)) {
            $this -> calculated = $this -> progress;

            if (!empty($this -> parent))
                $this -> parent -> update($source, $status, $message);

            if (!empty($this -> updateListener))
                ($this -> updateListener)($source, $this -> calculated, $status, $message);

            return $this;
        }

        $childProgress = 0;
        foreach ($this -> childs as $child) {
            $childProgress += ($this -> expectedChildsCount > 0)
                ? $child -> calculated * (1 / max($this -> expectedChildsCount, count($this -> childs)))
                : $child -> calculated * ($child -> weight / $this -> totalWeight);
        }

        $calculated = ($this -> progress * $this -> progressWeight)
            + ($childProgress * (1 - $this -> progressWeight));

        $this -> calculated = max($this -> calculated, $calculated);

        if (!empty($this -> parent))
            $this -> parent -> update($source, $status, $message);

        if (!empty($this -> updateListener))
            ($this -> updateListener)($source, $this -> calculated, $status, $message);

        return $this;
    }

    /**
     * Listen for on update event.
     *
     * @param   string|callable     $callable
     * @return  static
     */
    public function onUpdate($callable) {
        if (!is_callable($callable) && !function_exists($callable))
            throw new CodingError("ProgressReporter::onUpdate(): Callable is not callable.");

        $this -> updateListener = $callable;
        return $this;
    }

    /**
     * Create a new child for this instance and return it.
     *
     * @return static
     */
    public function newChild(float $weight = 1) {
        $instance = new static();
        $instance -> level = $this -> level + 1;
        $instance -> weight = $weight;
        $instance -> parent = $this;
        $this -> totalWeight += $weight;
        $this -> childs[] = $instance;
        return $instance;
    }

    /**
     * Report progress update.
     *
     * @param   float   $progress   A number in the range [0, 1]
     * @param   int     $status
     * @param   string  $message
     * @return  static
     */
    public function report(float $progress, int $status, ?string $message) {
        $progress = min(1, max(0, $progress));
        $this -> progress = $progress;
        $this -> update($this, $status, $message);
        return $this;
    }

    public function setCompleted() {
        $this -> progress = 1;
        $this -> calculated = 1;
        $this -> progressWeight = 1;
    }

    public function task(
        Closure $callable,
        string $prepare,
        string $complete,
        string $failed,
        float $progess
    ) {
        $this -> report($this -> progress, static::INFO, $prepare);

        try {
            $callable();
            $this -> report($this -> progress + $progess, static::OKAY, $complete);
        } catch (\Throwable $e) {
            $this -> report($this -> progress + $progess, static::ERROR, $failed);
            throw $e;
        }
    }

    public function info(float $progress, ?string $message = null) {
        return $this -> report($progress, static::INFO, $message);
    }

    public function okay(float $progress, ?string $message = null) {
        return $this -> report($progress, static::OKAY, $message);
    }

    public function error(float $progress, ?string $message = null) {
        return $this -> report($progress, static::ERROR, $message);
    }

    public function complete(?string $message = null) {
        return $this -> report(1, static::OKAY, $message);
    }

    public function usePipe(DataPipe $pipe) {
        $this -> onUpdate(function (ProgressReporter $source, float $progress, int $status, string $message) use ($pipe) {
            $pipe -> send($status, $progress, $message, array(
                "level" => $source -> level
            ));
        });
    }
}
