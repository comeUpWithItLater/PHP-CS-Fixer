<?php

/*
 * This file is part of PHP CS Fixer.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *     Dariusz Rumiński <dariusz.ruminski@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace PhpCsFixer\Fixer\Comment;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\Tokenizer\Tokens;

/**
 * @author SpacePossum
 */
final class NoEmptyCommentFixer extends AbstractFixer
{
    const TYPE_HASH = 1;
    const TYPE_DOUBLE_SLASH = 2;
    const TYPE_SLASH_ASTERISK = 3;

    /**
     * {@inheritdoc}
     */
    public function fix(\SplFileInfo $file, Tokens $tokens)
    {
        for ($index = 1, $count = count($tokens); $index < $count; ++$index) {
            if (!$tokens[$index]->isGivenKind(T_COMMENT)) {
                continue;
            }

            list($blockStart, $index, $isEmpty) = $this->getCommentBlock($tokens, $index);
            if (false === $isEmpty) {
                continue;
            }

            for ($i = $blockStart; $i <= $index; ++$i) {
                $tokens->clearTokenAndMergeSurroundingWhitespace($i);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getPriority()
    {
        // should be run after PhpdocToCommentFixer and before NoExtraConsecutiveBlankLinesFixer, NoTrailingWhitespaceFixer and NoWhitespaceInBlankLineFixer.
        return 2;
    }

    /**
     * {@inheritdoc}
     */
    public function getDefinition()
    {
        return new FixerDefinition(
            'There should not be any empty comments.',
            array(new CodeSample("<?php\n//\n#\n/* */\n"))
        );
    }

    /**
     * {@inheritdoc}
     */
    public function isCandidate(Tokens $tokens)
    {
        return $tokens->isTokenKindFound(T_COMMENT);
    }

    /**
     * Return the start index, end index and a flag stating if the comment block is empty.
     *
     * @param Tokens $tokens
     * @param int    $index  T_COMMENT index
     *
     * @return array
     */
    private function getCommentBlock(Tokens $tokens, $index)
    {
        $commentType = $this->getCommentType($tokens[$index]->getContent());
        $empty = $this->isEmptyComment($tokens[$index]->getContent());
        $start = $index;
        $count = count($tokens);
        ++$index;

        for (; $index < $count; ++$index) {
            if ($tokens[$index]->isComment()) {
                if ($commentType !== $this->getCommentType($tokens[$index]->getContent())) {
                    break;
                }

                if ($empty) { // don't retest if already known the block not being empty
                    $empty = $this->isEmptyComment($tokens[$index]->getContent());
                }

                continue;
            }

            if (!$tokens[$index]->isWhitespace() || $this->getLineBreakCount($tokens, $index, $index + 1) > 1) {
                break;
            }
        }

        return array($start, $index - 1, $empty);
    }

    /**
     * @param string $content
     *
     * @return int
     */
    private function getCommentType($content)
    {
        if ('#' === $content[0]) {
            return self::TYPE_HASH;
        }

        if ('*' === $content[1]) {
            return self::TYPE_SLASH_ASTERISK;
        }

        return self::TYPE_DOUBLE_SLASH;
    }

    /**
     * @param Tokens $tokens
     * @param int    $whiteStart
     * @param int    $whiteEnd
     *
     * @return int
     */
    private function getLineBreakCount(Tokens $tokens, $whiteStart, $whiteEnd)
    {
        $lineCount = 0;
        for ($i = $whiteStart; $i < $whiteEnd; ++$i) {
            $lineCount += substr_count($tokens[$i]->getContent(), "\n");
        }

        return $lineCount;
    }

    /**
     * @param string $content
     *
     * @return bool
     */
    private function isEmptyComment($content)
    {
        $type = $this->getCommentType($content);
        switch ($type) {
            case self::TYPE_HASH:
                // single line comment starting with '#'
                return 1 === preg_match('|^#\s*$|', $content);
            case self::TYPE_SLASH_ASTERISK:
                // comment starting with '/*' and ending with '*/' (but not a PHPDoc)
                return 1 === preg_match('|^/\*\s*\*/$|', $content);
            case self::TYPE_DOUBLE_SLASH:
                // single line comment starting with '//'
                return 1 === preg_match('|^//\s*$|', $content);
        }
    }
}
