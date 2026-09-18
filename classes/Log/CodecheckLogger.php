<?php

/**
 * @file classes/Log/CodecheckLogger.php
 *
 * Copyright (c) 2025 CODECHECK
 * Distributed under the Apache License, Version 2.0. For full terms see the file LICENSE.
 *
 * @class CodecheckLogger
 *
 * @brief A simple logger wrapper for the CODECHECK plugin.
 * Provides four log levels: debug, info, warning, and error.
 * All messages are prefixed with [codecheck] and written via error_log().
 */

namespace APP\plugins\generic\codecheck\classes\Log;

class CodecheckLogger
{
    /**
     * Keep a message on one line.
     *
     * Values that reach the log come from requests — uploaded filenames, paths,
     * stored JSON — and a newline in one of them forges a log line that looks
     * like a real entry from this plugin.
     */
    private static function oneLine(string $message): string
    {
        return str_replace(["\r", "\n"], ' ', $message);
    }

    /**
     * Log a debug message.
     * Use for detailed tracing, object dumps, and frequent calls.
     */
    public static function debug(string $message): void
    {
        error_log('[codecheck][debug] ' . self::oneLine($message));
    }

    /**
     * Log an info message.
     * Use for normal operations, ideally one line only.
     */
    public static function info(string $message): void
    {
        error_log('[codecheck][info] ' . self::oneLine($message));
    }

    /**
     * Log a warning message.
     * Use when something is unexpected but recoverable, e.g. malformed stored
     * data that is skipped rather than fatal.
     */
    public static function warning(string $message): void
    {
        error_log('[codecheck][warning] ' . self::oneLine($message));
    }

    /**
     * Log an error message.
     * Use when something breaks or an exception is caught.
     */
    public static function error(string $message): void
    {
        error_log('[codecheck][error] ' . self::oneLine($message));
    }
}