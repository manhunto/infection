<?php
/**
 * This code is licensed under the BSD 3-Clause License.
 *
 * Copyright (c) 2017, Maks Rafalko
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * * Redistributions of source code must retain the above copyright notice, this
 *   list of conditions and the following disclaimer.
 *
 * * Redistributions in binary form must reproduce the above copyright notice,
 *   this list of conditions and the following disclaimer in the documentation
 *   and/or other materials provided with the distribution.
 *
 * * Neither the name of the copyright holder nor the names of its
 *   contributors may be used to endorse or promote products derived from
 *   this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE
 * FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
 * DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
 * SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY,
 * OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

declare(strict_types=1);

namespace Infection\Mutator\Boolean;

use Infection\Mutator\Definition;
use Infection\Mutator\GetMutatorName;
use Infection\Mutator\Mutator;
use Infection\Mutator\MutatorCategory;
use Infection\PhpParser\Visitor\ParentConnector;
use LogicException;
use PhpParser\Node;

/**
 * @internal
 *
 * @implements Mutator<Node\Expr\BinaryOp\BooleanOr>
 */
final class LogicalOrNegateAllSubExpr implements Mutator
{
    use GetMutatorName;

    public static function getDefinition(): ?Definition
    {
        return new Definition(
            <<<'TXT'
Negates all sub-expressions at once in OR (`||`).
TXT
            ,
            MutatorCategory::ORTHOGONAL_REPLACEMENT,
            null,
            <<<'DIFF'
- $a = $b || $c;
+ $a = !$b || !$c;
DIFF
        );
    }

    /**
     * @psalm-mutation-free
     *
     * @return iterable<Node>
     */
    public function mutate(Node $node): iterable
    {
        yield $this->negateEverySubExpression($node);
    }

    public function canMutate(Node $node): bool
    {
        if (!$node instanceof Node\Expr\BinaryOp\BooleanOr) {
            return false;
        }

        $parent = ParentConnector::findParent($node);

        return $parent !== null && !$parent instanceof Node\Expr\BinaryOp\BooleanOr; // only grandparent
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function negateEverySubExpression(Node $node, array $attributes = []): Node\Expr
    {
        if ($node instanceof Node\Expr\BinaryOp\BooleanOr) {
            return new Node\Expr\BinaryOp\BooleanOr(
                $this->negateEverySubExpression($node->left),
                $this->negateEverySubExpression($node->right),
                $attributes
            );
        }

        if ($node instanceof Node\Expr\BooleanNot) {
            return $node->expr;
        }

        if ($node instanceof Node\Expr) {
            return new Node\Expr\BooleanNot($node);
        }

        throw new LogicException('Unexpected value type ' . $node->getType());
    }
}
