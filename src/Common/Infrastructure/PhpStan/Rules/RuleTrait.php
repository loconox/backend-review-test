<?php

declare(strict_types=1);

/*
 * Copyright (c) egerie.eu - All Rights Reserved.
 *
 * This file is proprietary and confidential and a part of the Egerie API.
 * It may not be copied, distributed, or modified without explicit permission from Egerie.
 *
 * Unauthorized access or usage is strictly prohibited.
 */

namespace App\Common\Infrastructure\PhpStan\Rules;

trait RuleTrait
{
    public static string $appNamespace = 'App';
    protected const LAYER_INFRASTRUCTURE = 'Infrastructure';
    protected const LAYER_APPLICATION = 'Application';
    protected const LAYER_DOMAIN = 'Domain';

    protected function getLayer(string $namespace): ?string
    {
        $regex = $this->getNamespaceRegex();
        if (false === preg_match('/' . $regex . '/', $namespace, $matches) || !isset($matches[1])) {
            return null;
        }

        return $matches[1];
    }

    protected function getNamespaceRegex(): string
    {
        return self::$appNamespace . '\\\\.+\\\\(' . self::LAYER_INFRASTRUCTURE . '|' . self::LAYER_APPLICATION . '|' . self::LAYER_DOMAIN . ')';
    }
}
