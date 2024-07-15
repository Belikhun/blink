<?php

namespace Blink;

/**
 * DataPipe.php
 * 
 * Class providing real-time data streaming to client.
 * 
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class DataPipe {
    const OKAY = 0;
    const INFO = 1;
    const WARN = 2;
    const ERROR = 3;

    public int $index = 0;

    public function start() {
        // Discard all output buffer to avoid garbage html.
        while (ob_get_level())
            @ob_end_clean();

        @set_time_limit(0);
        @ini_set("zlib.output_compression", "Off");
        @ini_set("implicit_flush", 1);
        if (function_exists("apache_setenv"))
            apache_setenv("no-gzip", 1);
        
        header("Content-Type: text/plain;charset=utf-8");
        header("X-Accel-Buffering: no");
        ob_implicit_flush(1);

        $instance = $this;

        set_exception_handler(function (\Throwable $e) use ($instance) {
            $instance -> error(1, $e -> getMessage(), array(
                "code" => $e -> getCode(),
                "file" => $e -> getFile(),
                "line" => $e -> getLine()
            ));

            $instance -> end();
        });

	    set_error_handler(function (int $code, string $text, string $file, int $line) use ($instance) {
            $instance -> error(1, $text, array(
                "code" => $code,
                "file" => $file,
                "line" => $line
            ));

            $instance -> end();
        });

        echo ">>>START<<<" . str_repeat(" ", 1024 * 64) . "\n";
        flush();

        return $this;
    }

    public function end($data = array()) {
        $data = json_encode($data);
        echo ">>>END|||{$data}<<<\n";
        flush();
        die();
    }

    public function send(int $level, float $progress, string $message, array $data = []) {
        $this -> index += 1;

        $tokens = array(
            $this -> index,
            $level,
            $progress,
            $message,
            json_encode($data)
        );

        $data = ">>>" . implode("|||", $tokens) . "<<<";
        echo ">>>" . implode("|||", $tokens) . "<<<\n";
        flush();

        return $this;
    }

    public function success(int $progress, string $message, array $data = []) {
        return $this -> send(static::OKAY, $progress, $message, $data);
    }

    public function info(int $progress, string $message, array $data = []) {
        return $this -> send(static::INFO, $progress, $message, $data);
    }

    public function warning(int $progress, string $message, array $data = []) {
        return $this -> send(static::WARN, $progress, $message, $data);
    }

    public function error(int $progress, string $message, array $data = []) {
        return $this -> send(static::ERROR, $progress, $message, $data);
    }
}
