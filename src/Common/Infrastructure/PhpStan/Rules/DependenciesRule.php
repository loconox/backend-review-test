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
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\ShouldNotHappenException;

/**
 * @implements Rule<Node\Stmt\Use_>
 */
class DependenciesRule implements Rule
{
    use RuleTrait;

    private const ALLOWED_ANNOT = '@AllowedVendor';

    /** @var array<string, array<string>> */
    private array $allowedNamespaces = [
        self::LAYER_DOMAIN => [],
        self::LAYER_APPLICATION => [],
    ];

    public function getNodeType(): string
    {
        return Node\Stmt\Use_::class;
    }

    /**
     * @param Node\Stmt\Use_ $node
     *
     * @return array|\PHPStan\Rules\IdentifierRuleError[]|RuleError[]
     *
     * @throws ShouldNotHappenException
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $namespace = $scope->getNamespace();
        if (!$namespace) {
            return [];
        }
        $layer = $this->getLayer($namespace);
        // class outside of app namespace
        if (!$layer) {
            return [];
        }

        switch ($layer) {
            case self::LAYER_APPLICATION:
                $allowedLayers = [
                    self::LAYER_DOMAIN,
                    self::LAYER_APPLICATION,
                ];
                $allowedNamespaces = $this->allowedNamespaces[self::LAYER_APPLICATION];

                return $this->checkAllowedUse($node, $allowedLayers, $allowedNamespaces);
            case self::LAYER_DOMAIN:
                $allowedLayers = [
                    self::LAYER_DOMAIN,
                ];
                $allowedNamespaces = $this->allowedNamespaces[self::LAYER_APPLICATION];

                return $this->checkAllowedUse($node, $allowedLayers, $allowedNamespaces);
            default:
                return [];

        }
    }

    /**
     * @param array<string> $allowedLayers
     * @param array<string> $allowedNamespaces
     *
     * @return array<RuleError>
     *
     * @throws ShouldNotHappenException
     */
    private function checkAllowedUse(Node\Stmt\Use_ $node, array $allowedLayers, array $allowedNamespaces): array
    {
        // if use outside of app namespace, check allowed namespaces
        if ($node->uses[0]->name->getParts()[0] !== self::$appNamespace) {
            return $this->checkAllowedNamespaces($node, $allowedNamespaces);
        }

        return $this->checkAllowedLayers($node, $allowedLayers);
    }

    /**
     * @param array<string> $allowedLayers
     *
     * @return array<RuleError>
     *
     * @throws ShouldNotHappenException
     */
    private function checkAllowedLayers(Node\Stmt\Use_ $node, array $allowedLayers): array
    {
        $message = sprintf('You cannot include class from \'%s\' layer. Allowed %s [%s].', '%s', count($allowedLayers) > 1 ? 'layers are' : 'layer is', implode(', ', $allowedLayers));
        $layer = $node->uses[0]->name->getParts()[2];
        if (!in_array($layer, $allowedLayers, true)) {
            return [
                RuleErrorBuilder::message(sprintf($message, $layer))->build(),
            ];
        }

        return [];
    }

    /**
     * @param array<string> $allowedNamespaces
     *
     * @return array<RuleError>
     *
     * @throws ShouldNotHappenException
     */
    private function checkAllowedNamespaces(Node\Stmt\Use_ $node, array $allowedNamespaces): array
    {
        $message = 'You cannot include class \'%s\'.';
        $message .= match (true) {
            (empty($allowedNamespaces)) => ' No allowed vendor namespaces.',
            (1 === count($allowedNamespaces)) => ' Allowed namespace is ' . $allowedNamespaces[0],
            default => ' Allowed namespaces are ' . implode(', ', $allowedNamespaces[0]),
        };
        $namespace = $node->uses[0]->name->toString();

        if (!$this->isAllowedNamespace($allowedNamespaces, $namespace) && !$this->hasAllowedAnnotation($node)) {
            return [
                RuleErrorBuilder::message(sprintf($message, $namespace))->build(),
            ];
        }

        return [];
    }

    private function hasAllowedAnnotation(Node\Stmt\Use_ $node): bool
    {
        foreach ($node->getComments() as $comment) {
            if (str_contains($comment->getText(), self::ALLOWED_ANNOT)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string> $allowedNamespaces
     */
    private function isAllowedNamespace(array $allowedNamespaces, string $namespace): bool
    {
        foreach ($allowedNamespaces as $allowedNamespace) {
            if (!str_starts_with($namespace, $allowedNamespace)) {
                return true;
            }
        }

        return false;
    }
}
