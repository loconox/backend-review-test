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

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<InClassNode>
 */
class NamespaceRule implements Rule
{
    use RuleTrait;

    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    /**
     * @param InClassNode $node
     *
     * @return RuleError[]
     *
     * @throws \PHPStan\ShouldNotHappenException
     */
    public function processNode(Node $node, Scope $scope): array
    {
        // skip if not in src dir
        if (!$this->inSrcDir($scope->getFile())) {
            return [];
        }
        $namespace = $scope->getNamespace();
        if (!$namespace) {
            return [];
        }
        $layer = $this->getLayer($namespace);
        $message = 'Namespace is not valid. Allowed is \'' . $this->getNamespaceRegex() . '\'.';
        if (!$layer) {
            return [
                RuleErrorBuilder::message($message)->build(),
            ];
        }

        return [];
    }

    private function inSrcDir(string $filename): bool
    {
        $srcDir = $_ENV['SRC_DIR'] ?? null;
        if (!$srcDir) {
            return false;
        }

        return str_starts_with($filename, $srcDir);
    }
}
