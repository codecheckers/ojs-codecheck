<?php

/**
 * @file classes/Log/CodecheckLogger.php
 *
 * Copyright (c) 2025 CODECHECK
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CodecheckLogger
 *
 * @brief A simple logger wrapper for the CODECHECK plugin.
 * Provides three log levels: debug, info, and error.
 * All messages are prefixed with [codecheck] and written via error_log().
 */

namespace APP\plugins\generic\codecheck\classes\Log;

class CodecheckLogger
{
    /**
     * Log a debug message.
     * Use for detailed tracing, object dumps, and frequent calls.
     */
    public static function debug(string $message): void
    {
        error_log('[codecheck][debug] ' . $message);
    }

    /**
     * Log an info message.
     * Use for normal operations, ideally one line only.
     */
    public static function info(string $message): void
    {
        error_log('[codecheck][info] ' . $message);
    }

    /**
     * Log an error message.
     * Use when something breaks or an exception is caught.
     */
    public static function error(string $message): void
    {
        error_log('[codecheck][error] ' . $message);
    }
}