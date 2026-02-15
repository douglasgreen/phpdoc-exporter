<?php

declare(strict_types=1);

namespace DouglasGreen\PhpDocExporter\Error;

/**
 * POSIX-compliant exit codes for CLI operations.
 *
 * @package DouglasGreen\PhpDocExporter\Error
 *
 * @api
 *
 * @since 1.0.0
 */
enum ExitCodes: int
{
    case SUCCESS = 0;

    case GENERAL_ERROR = 1;

    case USAGE_ERROR = 2;

    case PERMISSION_DENIED = 126;

    case COMMAND_NOT_FOUND = 127;

    case SIGINT = 130;
}
