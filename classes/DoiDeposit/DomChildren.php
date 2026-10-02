<?php

/**
 * @file classes/DoiDeposit/DomChildren.php
 *
 * Copyright (c) 2026 CODECHECK Initiative
 * Distributed under the Apache License, Version 2.0. For full terms see the file LICENSE.
 *
 * @trait DomChildren
 *
 * @brief The DOM reading the Crossref and DataCite writers share (#19).
 */

namespace APP\plugins\generic\codecheck\classes\DoiDeposit;

use DOMElement;

trait DomChildren
{
    /**
     * Child elements in the parent's namespace with the given name.
     *
     * @return array<int, DOMElement>
     */
    private static function children(?DOMElement $parent, string $localName): array
    {
        $children = [];
        foreach ($parent?->childNodes ?? [] as $child) {
            if ($child instanceof DOMElement && $child->namespaceURI === $parent->namespaceURI && $child->localName === $localName) {
                $children[] = $child;
            }
        }

        return $children;
    }

    /**
     * How a relation is told apart from another: its type and its identifier,
     * ignoring case, as DOIs and host names do.
     */
    private static function relationKey(string $type, string $identifier): string
    {
        return $type . ' ' . strtolower(trim($identifier));
    }
}
