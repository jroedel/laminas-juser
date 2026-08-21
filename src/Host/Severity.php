<?php

declare(strict_types=1);

namespace JUser\Host;

/**
 * How loudly a message is meant to read.
 *
 * A message needs a severity and 2.x expressed it with
 * `Laminas\Mvc\Plugin\FlashMessenger::NAMESPACE_ERROR` and friends — five string
 * constants on a class this module is dropping. An enum is the replacement rather
 * than five constants of our own because the value is one of a closed set and
 * nothing else about it is a namespace: `setNamespace('error')` was laminas naming
 * a *storage bucket*, and every caller in this module used it to mean severity.
 *
 * ## The values are the laminas strings, deliberately
 *
 * `Success->value === 'success'`, and so on for all five. That is what makes the
 * adapter on a laminas host a one-liner — `setNamespace($severity->value)` — and,
 * more importantly, what makes a flash message written by *this* release readable by
 * a layout that has not been ported yet. A flash survives in the session across a
 * redirect, so during a migration one request writes it and a differently-rendered
 * page reads it; changing the strings would lose messages across exactly that hop,
 * silently and only for visitors mid-flow.
 *
 * `test/Unit/JUserSeverityTest` in the consuming application pins the five values
 * against the laminas constants for as long as that package is installed.
 */
enum Severity: string
{
    case Success = 'success';
    case Error   = 'error';
    case Warning = 'warning';
    case Info    = 'info';
    /** Nothing in this module uses it; it exists because a host layout may render it. */
    case Default = 'default';
}
