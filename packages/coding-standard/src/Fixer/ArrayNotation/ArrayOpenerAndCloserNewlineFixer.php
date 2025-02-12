<?php

declare(strict_types=1);

namespace Symplify\CodingStandard\Fixer\ArrayNotation;

use Nette\Utils\Strings;
use PhpCsFixer\Fixer\Whitespace\ArrayIndentationFixer;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;
use PhpCsFixer\WhitespacesFixerConfig;
use SplFileInfo;
use Symplify\CodingStandard\Fixer\AbstractSymplifyFixer;
use Symplify\CodingStandard\TokenRunner\Analyzer\FixerAnalyzer\ArrayAnalyzer;
use Symplify\CodingStandard\TokenRunner\Traverser\ArrayBlockInfoFinder;
use Symplify\CodingStandard\TokenRunner\ValueObject\BlockInfo;
use Symplify\CodingStandard\TokenRunner\ValueObject\TokenKinds;
use Symplify\RuleDocGenerator\Contract\DocumentedRuleInterface;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * @see \Symplify\CodingStandard\Tests\Fixer\ArrayNotation\ArrayOpenerAndCloserNewlineFixer\ArrayOpenerAndCloserNewlineFixerTest
 */
final class ArrayOpenerAndCloserNewlineFixer extends AbstractSymplifyFixer implements DocumentedRuleInterface
{
    /**
     * @var string
     */
    private const ERROR_MESSAGE = 'Indexed PHP array opener [ and closer ] must be on own line';

    /**
     * @var ArrayBlockInfoFinder
     */
    private $arrayBlockInfoFinder;

    /**
     * @var WhitespacesFixerConfig
     */
    private $whitespacesFixerConfig;

    /**
     * @var ArrayAnalyzer
     */
    private $arrayAnalyzer;

    public function __construct(
        ArrayBlockInfoFinder $arrayBlockInfoFinder,
        WhitespacesFixerConfig $whitespacesFixerConfig,
        ArrayAnalyzer $arrayAnalyzer
    ) {
        $this->arrayBlockInfoFinder = $arrayBlockInfoFinder;
        $this->whitespacesFixerConfig = $whitespacesFixerConfig;
        $this->arrayAnalyzer = $arrayAnalyzer;
    }

    public function getDefinition(): FixerDefinitionInterface
    {
        return new FixerDefinition(self::ERROR_MESSAGE, []);
    }

    public function getPriority(): int
    {
        return $this->getPriorityBefore(ArrayIndentationFixer::class);
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(self::ERROR_MESSAGE, [
            new CodeSample(
                <<<'CODE_SAMPLE'
$items = [1 => 'Hey'];
CODE_SAMPLE
                ,
                <<<'CODE_SAMPLE'
$items = [
1 => 'Hey'
];
CODE_SAMPLE
            ),
        ]);
    }

    /**
     * @param Tokens<Token> $tokens
     */
    public function isCandidate(Tokens $tokens): bool
    {
        if (! $tokens->isAnyTokenKindsFound(TokenKinds::ARRAY_OPEN_TOKENS)) {
            return false;
        }

        return $tokens->isTokenKindFound(T_DOUBLE_ARROW);
    }

    /**
     * @param Tokens<Token> $tokens
     */
    public function fix(SplFileInfo $fileInfo, Tokens $tokens): void
    {
        $blockInfos = $this->arrayBlockInfoFinder->findArrayOpenerBlockInfos($tokens);

        foreach ($blockInfos as $blockInfo) {
            $this->fixArrayOpener($tokens, $blockInfo);
        }
    }

    /**
     * @param Tokens<Token> $tokens
     */
    private function fixArrayOpener(Tokens $tokens, BlockInfo $blockInfo): void
    {
        if ($this->isNextTokenAlsoArrayOpener($tokens, $blockInfo->getStart())) {
            return;
        }

        // no items
        $itemCount = $this->arrayAnalyzer->getItemCount($tokens, $blockInfo);
        if ($itemCount === 0) {
            return;
        }

        if (! $this->arrayAnalyzer->isIndexedList($tokens, $blockInfo)) {
            return;
        }

        // closer must run before the opener, as tokens as added by traversing up
        $this->handleArrayCloser($tokens, $blockInfo->getEnd());
        $this->handleArrayOpener($tokens, $blockInfo->getStart());
    }

    /**
     * @param Tokens<Token> $tokens
     */
    private function isNextTokenAlsoArrayOpener(Tokens $tokens, int $index): bool
    {
        $nextToken = $this->getNextMeaningfulToken($tokens, $index);
        if (! $nextToken instanceof Token) {
            return false;
        }

        return $nextToken->isGivenKind(TokenKinds::ARRAY_OPEN_TOKENS);
    }

    /**
     * @param Tokens<Token> $tokens
     */
    private function handleArrayCloser(Tokens $tokens, int $arrayCloserPosition): void
    {
        $preArrayCloserPosition = $arrayCloserPosition - 1;

        $previousCloserToken = $tokens[$preArrayCloserPosition] ?? null;
        if (! $previousCloserToken instanceof Token) {
            return;
        }

        // already whitespace
        if (Strings::contains($previousCloserToken->getContent(), "\n")) {
            return;
        }

        $tokens->ensureWhitespaceAtIndex($preArrayCloserPosition, 1, $this->whitespacesFixerConfig->getLineEnding());
    }

    /**
     * @param Tokens<Token> $tokens
     */
    private function handleArrayOpener(Tokens $tokens, int $arrayOpenerPosition): void
    {
        $postArrayOpenerPosition = $arrayOpenerPosition + 1;

        $nextToken = $tokens[$postArrayOpenerPosition] ?? null;
        if (! $nextToken instanceof Token) {
            return;
        }

        // already is whitespace
        if (Strings::contains($nextToken->getContent(), "\n")) {
            return;
        }

        $tokens->ensureWhitespaceAtIndex($postArrayOpenerPosition, 0, $this->whitespacesFixerConfig->getLineEnding());
    }
}
