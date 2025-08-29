<?php
/**
 * OrangeHRM is a comprehensive Human Resource Management (HRM) System that captures
 * all the essential functionalities required for any enterprise.
 * Copyright (C) 2006 OrangeHRM Inc., http://www.orangehrm.com
 *
 * OrangeHRM is free software: you can redistribute it and/or modify it under the terms of
 * the GNU General Public License as published by the Free Software Foundation, either
 * version 3 of the License, or (at your option) any later version.
 *
 * OrangeHRM is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
 * without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with OrangeHRM.
 * If not, see <https://www.gnu.org/licenses/>.
 */

namespace OrangeHRM\Leave\Service;

use OrangeHRM\Leave\Traits\Service\LeaveEntitlementServiceTrait;
use OrangeHRM\Leave\Traits\Service\LeaveTypeServiceTrait;
use OrangeHRM\Leave\Traits\Service\LeavePeriodServiceTrait;
use Symfony\Component\Yaml\Yaml;

/**
 * Minimal rule-based Leave assistant with optional local LLM hook
 */
class LeaveAssistantService
{
    use LeaveEntitlementServiceTrait;
    use LeaveTypeServiceTrait;
    use LeavePeriodServiceTrait;

    private array $faq;
    /** @var string[] */
    private array $domainKeywords = [
        // core terms
        'leave','holiday','holidays','workweek','work week','entitlement','entitlements','balance','balances',
        // common actions
        'apply','request','cancel','approve','reject','assign','view','status','track',
        // ui nouns
        'my leave','leave list','leave type','leave types','leave period','work week',
        // popular leave names
        'vacation','annual','sick','casual'
    ];
    /** @var string[] */
    private array $stopWords = [
        'a','an','the','and','or','but','if','then','else','when','how','to','for','of','on','in','at','is','are','was','were','be','can','i','my','me','what','which','with','do','does','did','you','your'
    ];

    public function __construct()
    {
        $this->faq = $this->loadFaq();
    }

    /**
     * Answer a user message with safe guidance only (no writes)
     * @param string $message
     * @param int|null $empNumber
     * @return array{answer:string,mode:string,sources:array}
     */
    public function answer(string $message, ?int $empNumber = null): array
    {
        $normalized = strtolower(trim($message));

    // Quick out-of-scope guard: if message has no domain keywords
    $inScope = $this->isInScope($normalized);

        // 1) Intent: leave balance
        if ($this->containsAny($normalized, ['balance', 'how much', 'how many', 'remaining']) &&
            $this->containsAny($normalized, ['leave', 'days'])) {
            $answer = $this->buildBalanceHelp($empNumber);
            return ['answer' => $answer, 'mode' => 'rule-based', 'sources' => ['internal-api']];
        }

        // 2) Intent: apply/request leave
        if ($this->containsAny($normalized, ['apply', 'request']) && $this->containsAny($normalized, ['leave'])) {
            return [
                'answer' => $this->faq['how_to_apply'] ?? 'Go to Leave > Apply. Select type, dates, duration, add comments, then Submit.',
                'mode' => 'rule-based',
                'sources' => ['faq']
            ];
        }

        // 3) Intent: cancel leave
        if ($this->containsAny($normalized, ['cancel']) && $this->containsAny($normalized, ['leave', 'request'])) {
            return [
                'answer' => $this->faq['how_to_cancel'] ?? 'Go to Leave > My Leave. Open the request and use the cancel action if available.',
                'mode' => 'rule-based',
                'sources' => ['faq']
            ];
        }

        // 4) Intent: view leave
        if ($this->containsAny($normalized, ['view', 'status', 'track']) && $this->containsAny($normalized, ['leave'])) {
            return [
                'answer' => $this->faq['how_to_view'] ?? 'Go to Leave > My Leave to see status, dates, and comments for your requests.',
                'mode' => 'rule-based',
                'sources' => ['faq']
            ];
        }

        // 5) Policy questions (fallback to FAQ match)
        $policy = $this->bestFaq($normalized, $inScope);
        if ($policy) {
            return ['answer' => $policy, 'mode' => 'faq', 'sources' => ['faq']];
        }

        // 6) Generic fallback (out-of-scope)
        return [
            'answer' => 'That inquiry is out of scope for the Leave Assistant. I can only provide read-only guidance for the Leave module, like leave balances and how to apply, cancel, or view your leave. Try: "What is my leave balance?" or "How do I apply for leave?"',
            'mode' => 'fallback',
            'sources' => []
        ];
    }

    private function containsAny(string $text, array $keywords): bool
    {
        foreach ($keywords as $kw) {
            if (strpos($text, $kw) !== false) {
                return true;
            }
        }
        return false;
    }

    private function loadFaq(): array
    {
        $path = realpath(dirname(__FILE__, 2)) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'assistant-faq.yaml';
        if ($path && file_exists($path)) {
            $data = Yaml::parseFile($path);
            return is_array($data) ? $data : [];
        }
        return [];
    }

    private function bestFaq(string $normalizedQuestion, bool $inScope): ?string
    {
        // If not in scope, do not match FAQ
        if (!$inScope) {
            return null;
        }
        // Tokenize question (remove stopwords)
        $qTokens = $this->filterStopWords($this->tokenize($normalizedQuestion));

        $best = null;
        $bestScore = 0;
        foreach ($this->faq as $key => $answer) {
            $k = str_replace(['_', '-'], ' ', strtolower($key));
            $kTokens = $this->filterStopWords($this->tokenize($k));
            if (empty($kTokens)) {
                continue;
            }
            $score = $this->overlapScore($qTokens, $kTokens);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = is_string($answer) ? $answer : null;
            }
        }
        // Require a reasonable overlap to avoid false positives
        return $bestScore >= 2 ? $best : null;
    }

    private function buildBalanceHelp(?int $empNumber): string
    {
        if (!$empNumber) {
            return 'Go to Leave > My Leave Entitlements to view your balances.';
        }
        // Provide generic steps and point to UI. Avoid exposing specific numbers directly here to keep scope read-only
        return 'To check your leave balance: Open Leave > My Leave Entitlements. You can also open Leave > Apply and pick a leave type to see your available balance before submitting.';
    }

    private function isInScope(string $text): bool
    {
        $lt = strtolower($text);
        foreach ($this->domainKeywords as $kw) {
            if (strpos($lt, $kw) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Split text into lowercase word tokens
     * @return string[]
     */
    private function tokenize(string $text): array
    {
        $tokens = preg_split('/[^a-z0-9]+/i', strtolower($text)) ?: [];
        return array_values(array_filter($tokens, fn($t) => $t !== ''));
    }

    /**
     * Remove common stop words
     * @param string[] $tokens
     * @return string[]
     */
    private function filterStopWords(array $tokens): array
    {
        $sw = array_flip($this->stopWords);
        return array_values(array_filter($tokens, fn($t) => !isset($sw[$t])));
    }

    /**
     * Count of overlapping tokens between two arrays
     * @param string[] $a
     * @param string[] $b
     */
    private function overlapScore(array $a, array $b): int
    {
        if (empty($a) || empty($b)) {
            return 0;
        }
        $setB = array_flip($b);
        $score = 0;
        foreach (array_unique($a) as $t) {
            if (isset($setB[$t])) {
                $score++;
            }
        }
        return $score;
    }
}
