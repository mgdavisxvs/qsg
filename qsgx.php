<?php
declare(strict_types=1);
session_start();

/**
 * --------------------------------------------------------------------------
 * Utility functions – analysis core (syntax, logic, Kant, ruliad bits)
 * --------------------------------------------------------------------------
 */

function normalize_clause(string $text): string {
    $text = preg_replace('/\s+/u', ' ', $text);
    return trim($text ?? '');
}

/**
 * Token representation:
 * [
 *   'index' => int,
 *   'raw'   => string,
 *   'clean' => string,
 *   'lower' => string,
 *   'tag'   => string,  // PREP | VERB | QUANT | DET | CONJ | NUM | NEG | WORD
 *   'role'  => string
 * ]
 */
function tokenize_clause(string $text): array {
    $norm = normalize_clause($text);
    if ($norm === '') {
        return [];
    }

    $chunks = explode(' ', $norm);
    $tokens = [];

    foreach ($chunks as $idx => $raw) {
        $clean = preg_replace('/^[^\p{L}\p{N}]+|[^\p{L}\p{N}]+$/u', '', $raw);
        $lower = mb_strtolower($clean, 'UTF-8');

        $tokens[] = [
            'index' => $idx,
            'raw'   => $raw,
            'clean' => $clean,
            'lower' => $lower,
            'tag'   => 'WORD',
            'role'  => 'content',
        ];
    }

    return $tokens;
}

function classify_tokens(array $tokens): array {
    $preps = [
        "of", "with", "by", "within", "under", "over", "into", "onto", "from",
        "to", "for", "in", "on", "at", "through", "between", "before", "after",
        "against", "toward", "inside", "outside", "beyond", "without", "around"
    ];
    $preps = array_fill_keys($preps, true);

    $verbs = [
        "is", "are", "shall", "must", "may", "will", "can", "protect", "protects",
        "respect", "respects", "harm", "harms", "kill", "kills", "exploit", "exploits",
        "use", "uses", "manipulate", "manipulates", "help", "helps", "aid", "aids",
        "support", "supports"
    ];
    $verbs = array_fill_keys($verbs, true);

    $quantifiers = [
        "every", "all", "any", "no", "none", "some", "each"
    ];
    $quantifiers = array_fill_keys($quantifiers, true);

    $negations = [
        "not", "never", "no", "none", "without"
    ];
    $negations = array_fill_keys($negations, true);

    foreach ($tokens as &$t) {
        $tag  = "WORD";
        $role = "content";

        if (isset($negations[$t['lower']])) {
            $tag  = "NEG";
            $role = "negation";
        } elseif (isset($preps[$t['lower']])) {
            $tag  = "PREP";
            $role = "relational connector";
        } elseif (isset($verbs[$t['lower']])) {
            $tag  = "VERB";
            $role = "predicate / copula";
        } elseif (isset($quantifiers[$t['lower']])) {
            $tag  = "QUANT";
            $role = "quantifier";
        } elseif (preg_match('/^(the|a|an)$/u', $t['lower'])) {
            $tag  = "DET";
            $role = "determiner";
        } elseif (preg_match('/^(and|or|but)$/u', $t['lower'])) {
            $tag  = "CONJ";
            $role = "logical connector";
        } elseif (preg_match('/^[0-9]+$/u', $t['lower'])) {
            $tag  = "NUM";
            $role = "numeric literal";
        }

        $t['tag']  = $tag;
        $t['role'] = $role;
    }
    unset($t);

    return $tokens;
}

/**
 * QSG score (0–1) + label & notes.
 */
function compute_qsg(array $tokens): array {
    $count = count($tokens);
    if ($count === 0) {
        return [
            'score' => 0.0,
            'label' => 'Empty',
            'notes' => 'No text provided.',
        ];
    }

    $verbs    = 0;
    $preps    = 0;
    $garbage  = 0;

    foreach ($tokens as $t) {
        if ($t['tag'] === 'VERB') {
            $verbs++;
        }
        if ($t['tag'] === 'PREP') {
            $preps++;
        }
        if ($t['clean'] === '') {
            $garbage++;
        }
    }

    $score = 0.0;
    if ($verbs > 0) {
        $score += 0.4;
    }
    if ($count >= 6) {
        $score += 0.3;
    }
    if ($preps > 0) {
        $score += 0.2;
    }
    if ($garbage === 0) {
        $score += 0.1;
    }

    if ($score >= 0.8) {
        $label = "Strong sentence-like structure";
    } elseif ($score >= 0.5) {
        $label = "Reasonably structured clause";
    } elseif ($score > 0.0) {
        $label = "Fragmentary or weakly structured";
    } else {
        $label = "Non-sentential text";
    }

    $notes = sprintf(
        "Tokens: %d · Verbs: %d · Prepositions: %d · %s",
        $count,
        $verbs,
        $preps,
        $garbage ? "Garbage tokens: $garbage" : "No obvious noise tokens"
    );

    return [
        'score' => $score,
        'label' => $label,
        'notes' => $notes,
    ];
}

/**
 * Extract Agent–Action–Patient (AAP) heuristically.
 */
function extract_aap(array $tokens): array {
    if (!$tokens) {
        return [
            'agentPhrase'  => null,
            'actionWord'   => null,
            'patientPhrase'=> null,
        ];
    }

    $verbIndex = null;
    foreach ($tokens as $i => $t) {
        if ($t['tag'] === 'VERB') {
            $verbIndex = $i;
            break;
        }
    }

    if ($verbIndex === null) {
        return [
            'agentPhrase'  => null,
            'actionWord'   => null,
            'patientPhrase'=> null,
        ];
    }

    $agentTokens  = array_slice($tokens, 0, $verbIndex);
    $patientTokens= array_slice($tokens, $verbIndex + 1);

    $cleanPhrase = function (array $ts): ?string {
        $parts = [];
        foreach ($ts as $t) {
            if ($t['clean'] === '') continue;
            $parts[] = $t['clean'];
        }
        if (!$parts) {
            return null;
        }
        return implode(' ', $parts);
    };

    return [
        'agentPhrase'   => $cleanPhrase($agentTokens),
        'actionWord'    => $tokens[$verbIndex]['lower'],
        'patientPhrase' => $cleanPhrase($patientTokens),
    ];
}

/**
 * Build a rough FOL-like skeleton from preposition-based phrase segments.
 */
function build_fol(array $tokens): array {
    if (count($tokens) === 0) {
        return [
            'formula' => '—',
            'notes'   => 'No tokens to analyse.',
        ];
    }

    $prepsList = [
        "of", "with", "by", "within", "under", "over", "into", "onto", "from",
        "to", "for", "in", "on", "at", "through", "between", "before", "after",
        "against", "toward", "inside", "outside", "beyond", "without", "around"
    ];
    $preps = array_fill_keys($prepsList, true);

    $rawTextParts = [];
    $negations = [];
    foreach ($tokens as $t) {
        if ($t['tag'] === 'NEG') {
            $negations[] = $t['lower'];
        }
        $rawTextParts[] = $t['clean'] !== '' ? $t['clean'] : $t['lower'];
    }
    $rawText = implode(' ', $rawTextParts);

    $hasPrep = false;
    foreach ($tokens as $t) {
        if (isset($preps[$t['lower']])) {
            $hasPrep = true;
            break;
        }
    }

    if (!$hasPrep) {
        $noteNeg = $negations ? ' Negations detected: ' . implode(', ', $negations) . '.' : '';
        return [
            'formula' => 'ClauseAsPredicate(c): "' . $rawText . '"',
            'notes'   => 'No explicit prepositions detected – treating the entire clause as a single predicate.' . $noteNeg,
        ];
    }

    // Split into segments: phrase / prep / phrase ...
    $segments = [];
    $current  = [];

    foreach ($tokens as $t) {
        if (isset($preps[$t['lower']])) {
            if (!empty($current)) {
                $segments[] = ['type' => 'phrase', 'tokens' => $current];
                $current    = [];
            }
            $segments[] = ['type' => 'prep', 'tokens' => [$t]];
        } else {
            $current[] = $t;
        }
    }
    if (!empty($current)) {
        $segments[] = ['type' => 'phrase', 'tokens' => $current];
    }

    $entities  = [];
    $relations = [];

    $entityIdx = 0;
    $lastEnt   = null;

    $phraseToEntity = function (array $phraseTokens, int $index): string {
        $core = [];
        foreach ($phraseTokens as $t) {
            if (preg_match('/^(the|a|an|and|or|but)$/u', $t['lower'])) {
                continue;
            }
            $core[] = mb_strtolower($t['clean'], 'UTF-8');
        }
        if (empty($core)) {
            return 'x' . $index;
        }
        $head = end($core);
        $name = preg_replace('/[^a-z0-9]/u', '', $head);
        return $name !== '' ? $name : ('x' . $index);
    };

    $countSegments = count($segments);
    for ($i = 0; $i < $countSegments; $i++) {
        $seg = $segments[$i];

        if ($seg['type'] === 'phrase') {
            $ent = $phraseToEntity($seg['tokens'], $entityIdx++);
            $entities[] = $ent;
            if ($lastEnt === null) {
                $lastEnt = $ent;
            }
        } elseif ($seg['type'] === 'prep') {
            $prepWord = $seg['tokens'][0]['lower'];
            $next     = $segments[$i + 1] ?? null;
            if ($next && $next['type'] === 'phrase') {
                $ent = $phraseToEntity($next['tokens'], $entityIdx++);
                $entities[] = $ent;
                $relationName = ucfirst($prepWord);
                $relations[]  = $relationName . '(' . $lastEnt . ', ' . $ent . ')';
                $lastEnt      = $ent;
                $i++; // skip phrase we just used
            }
        }
    }

    $uniqueEntities = array_values(array_unique($entities));
    $varList        = implode(', ', $uniqueEntities);
    $entityPreds    = [];
    foreach ($uniqueEntities as $e) {
        $entityPreds[] = 'E(' . $e . ')';
    }

    $allPreds = array_merge($entityPreds, $relations);
    $formula  = '∃ ' . $varList . ' · ' . implode(' ∧ ', $allPreds);

    $notes = sprintf(
        "Derived %d entity symbol(s): %s · Relations (%d): %s",
        count($uniqueEntities),
        $varList ?: 'none',
        count($relations),
        $relations ? implode(', ', $relations) : 'none'
    );
    if ($negations) {
        $notes .= ' · Negations detected: ' . implode(', ', $negations) . '.';
    }

    return [
        'formula' => $formula,
        'notes'   => $notes,
    ];
}

/**
 * Logic coherence score based on verbs, preps, quantifiers, modals, negations.
 */
function compute_logic(array $tokens): array {
    if (count($tokens) === 0) {
        return [
            'score' => 0.0,
            'label' => 'No logical structure',
            'notes' => 'Empty clause.',
        ];
    }

    $verbs  = 0;
    $preps  = 0;
    $quant  = 0;
    $modals = 0;
    $negs   = 0;
    $modalWords = ['must', 'shall', 'may', 'can', 'should'];

    foreach ($tokens as $t) {
        if ($t['tag'] === 'VERB') {
            $verbs++;
        }
        if ($t['tag'] === 'PREP') {
            $preps++;
        }
        if ($t['tag'] === 'QUANT') {
            $quant++;
        }
        if ($t['tag'] === 'NEG') {
            $negs++;
        }
        if (in_array($t['lower'], $modalWords, true)) {
            $modals++;
        }
    }

    $score = 0.0;
    if ($verbs > 0) {
        $score += 0.4;
    }
    if ($preps > 0) {
        $score += 0.3;
    }
    if (($quant + $modals) > 0) {
        $score += 0.2;
    }
    if ($negs > 0 && $verbs > 0) {
        $score += 0.1; // negation often implies more structured logic
    }

    if ($score >= 0.8) {
        $label = "Strong logical form";
    } elseif ($score >= 0.5) {
        $label = "Moderate logical form";
    } elseif ($score > 0.0) {
        $label = "Weak or implicit logical form";
    } else {
        $label = "No visible logical commitment";
    }

    $notes = sprintf(
        "Verbs: %d · Preposition-based relations: %d · Quantifiers: %d · Modals: %d · Negations: %d",
        $verbs,
        $preps,
        $quant,
        $modals,
        $negs
    );

    return [
        'score' => $score,
        'label' => $label,
        'notes' => $notes,
    ];
}

/**
 * Kantian CI heuristic with "ends vs means" hint.
 */
function compute_kant(array $tokens): array {
    if (count($tokens) === 0) {
        return [
            'score' => 0.0,
            'label' => 'Unknown',
            'notes' => 'No content for moral assessment.',
        ];
    }

    $badWords = [
        "kill", "kills", "harm", "harms", "exploit", "exploits", "deceive", "deceives",
        "lie", "lies", "steal", "steals", "coerce", "coerces", "abuse", "abuses",
        "manipulate", "manipulates", "dominate", "dominates", "enslave", "enslaves",
        "torture", "tortures", "use", "uses"
    ];
    $badWords = array_fill_keys($badWords, true);

    $goodWords = [
        "protect", "protects", "respect", "respects", "help", "helps", "aid", "aids",
        "support", "supports", "care", "cares", "defend", "defends", "preserve", "preserves",
        "honor", "honors", "safeguard", "safeguards", "benefit", "benefits"
    ];
    $goodWords = array_fill_keys($goodWords, true);

    // Person-like nouns
    $personWords = [
        "person", "persons", "people", "citizen", "citizens",
        "worker", "workers", "human", "humans", "individual", "individuals"
    ];
    $personWords = array_fill_keys($personWords, true);

    $good = 0;
    $bad  = 0;
    $hits = [];
    $personsPresent = false;
    $instrumentalBadVerb = false;

    foreach ($tokens as $t) {
        if (isset($goodWords[$t['lower']])) {
            $good++;
            $hits[] = '+' . $t['clean'];
        } elseif (isset($badWords[$t['lower']])) {
            $bad++;
            $hits[] = '-' . $t['clean'];
            // treat these bad verbs as potential "means" verbs
            $instrumentalBadVerb = true;
        }
        if (isset($personWords[$t['lower']])) {
            $personsPresent = true;
        }
    }

    $score = 0.5 + ($good - $bad) * 0.1;
    if ($score < 0.0) {
        $score = 0.0;
    } elseif ($score > 1.0) {
        $score = 1.0;
    }

    if ($score >= 0.8) {
        $label = "Likely CI-aligned (protective / respectful orientation)";
    } elseif ($score >= 0.6) {
        $label = "Weakly CI-aligned (mildly protective / neutral)";
    } elseif ($score >= 0.4) {
        $label = "Ambiguous / mixed in moral orientation";
    } elseif ($score > 0.0) {
        $label = "Likely CI-violating (tendency toward instrumentalizing others)";
    } else {
        $label = "No explicit moral polarity detected";
    }

    $notesParts = [];
    $notesParts[] = sprintf("Good indicators: %d · Bad indicators: %d", $good, $bad);
    $notesParts[] = $hits ? 'Polarity hits: ' . implode(', ', $hits) : 'No explicit moral polarity tokens found.';

    // Ends vs means hint
    if ($personsPresent && $instrumentalBadVerb) {
        $notesParts[] = "CI warning: persons appear in the clause combined with harmful/instrumental verbs; this suggests a risk of treating persons as mere means.";
    } elseif ($personsPresent && $good > 0 && !$instrumentalBadVerb) {
        $notesParts[] = "CI hint: persons appear together with protective/respectful language; this leans toward treating persons as ends in themselves.";
    }

    $notesParts[] = "Heuristic: CI focuses on universalizability and treating persons as ends; this proxy only inspects local wording.";

    return [
        'score' => $score,
        'label' => $label,
        'notes' => implode(' · ', $notesParts),
    ];
}

function build_tone_summary(array $tokens): string {
    $freq = [];
    foreach ($tokens as $t) {
        if ($t['tag'] === 'PREP') {
            $p = $t['lower'];
            $freq[$p] = ($freq[$p] ?? 0) + 1;
        }
    }

    if (!$freq) {
        return "No preposition wiring detected.";
    }

    arsort($freq);
    $parts = [];
    foreach ($freq as $p => $c) {
        $parts[] = $p . '×' . $c;
    }
    return 'Preposition wiring: ' . implode(', ', $parts);
}

/**
 * Ambiguity / vagueness heuristic.
 * High score ≈ clearer; low score ≈ more vague terms.
 */
function compute_ambiguity(array $tokens): array {
    if (count($tokens) === 0) {
        return [
            'score' => 0.0,
            'label' => 'Unknown',
            'notes' => 'No content to assess for vagueness.',
            'hits'  => [],
        ];
    }

    $vagueWords = [
        "reasonable", "appropriate", "adequate", "significant", "material",
        "substantial", "generally", "normally", "as needed", "if necessary",
        "from time to time", "where possible", "to the extent possible"
    ];
    $vagueSet = [];
    foreach ($vagueWords as $w) {
        $vagueSet[mb_strtolower($w, 'UTF-8')] = true;
    }

    $hits = [];
    foreach ($tokens as $t) {
        $lower = $t['lower'];
        if (isset($vagueSet[$lower])) {
            $hits[] = $t['clean'] !== '' ? $t['clean'] : $lower;
        }
    }

    $count = count($hits);
    // 1.0 = no vague terms, down to 0 as vague terms accumulate
    $score = $count === 0 ? 1.0 : max(0.0, 1.0 - min($count, 10) * 0.08);

    if ($count === 0) {
        $label = "Low linguistic vagueness";
        $notes = "No obvious vague or open-textured terms detected.";
    } else {
        $label = "Contains vague / open-textured terms";
        $notes = "Vague terms: " . implode(', ', $hits) . ".";
    }

    return [
        'score' => $score,
        'label' => $label,
        'notes' => $notes,
        'hits'  => $hits,
    ];
}

/**
 * Modal profile: obligation vs permission vs recommendation.
 */
function compute_modal_profile(array $tokens): array {
    $obligation = ['must', 'shall', 'have to'];
    $permission = ['may', 'can', 'could'];
    $recommend  = ['should', 'ought'];

    $obSet = array_fill_keys($obligation, true);
    $permSet = array_fill_keys($permission, true);
    $recSet = array_fill_keys($recommend, true);

    $counts = [
        'obligation'   => 0,
        'permission'   => 0,
        'recommendation' => 0,
    ];

    foreach ($tokens as $t) {
        $w = $t['lower'];
        if (isset($obSet[$w])) {
            $counts['obligation']++;
        } elseif (isset($permSet[$w])) {
            $counts['permission']++;
        } elseif (isset($recSet[$w])) {
            $counts['recommendation']++;
        }
    }

    $summaryParts = [];
    $summaryParts[] = "Obligation: " . $counts['obligation'];
    $summaryParts[] = "Permission: " . $counts['permission'];
    $summaryParts[] = "Recommendation: " . $counts['recommendation'];

    return [
        'counts'  => $counts,
        'summary' => implode(' · ', $summaryParts),
    ];
}

/**
 * Simple rewrite: normalize and lightly clarify some legalese-ish clause.
 */
function rewrite_clause(string $text): string {
    $norm = normalize_clause($text);
    if ($norm === '') {
        return '—';
    }

    $rewritten = $norm;

    $pattern = '/for the claim of the treaty is with the protection of the lands by the council within this venue under the natural law/i';
    $replacement = 'the treaty claim provides for the protection of the lands by the council at this venue under natural law';
    $rewritten = preg_replace($pattern, $replacement, $rewritten);

    $first = mb_substr($rewritten, 0, 1, 'UTF-8');
    $rest  = mb_substr($rewritten, 1, null, 'UTF-8');
    $rewritten = mb_strtoupper($first, 'UTF-8') . $rest;

    if (!preg_match('/[.!?]$/u', $rewritten)) {
        $rewritten .= '.';
    }

    return $rewritten;
}

function tokenize_for_diff(string $text): array {
    $norm = normalize_clause($text);
    if ($norm === '') {
        return [];
    }
    return explode(' ', $norm);
}

/**
 * Word-level diff using LCS. Returns HTML string with <del> and <ins>.
 */
function diff_clauses(string $original, string $rewritten): string {
    $origTokens = tokenize_for_diff($original);
    $newTokens  = tokenize_for_diff($rewritten);

    $m = count($origTokens);
    $n = count($newTokens);

    $dp = array_fill(0, $m + 1, array_fill(0, $n + 1, 0));

    for ($i = 1; $i <= $m; $i++) {
        for ($j = 1; $j <= $n; $j++) {
            if ($origTokens[$i - 1] === $newTokens[$j - 1]) {
                $dp[$i][$j] = $dp[$i - 1][$j - 1] + 1;
            } else {
                $dp[$i][$j] = max($dp[$i - 1][$j], $dp[$i][$j - 1]);
            }
        }
    }

    $result = [];
    $i = $m;
    $j = $n;

    while ($i > 0 && $j > 0) {
        if ($origTokens[$i - 1] === $newTokens[$j - 1]) {
            array_unshift($result, htmlspecialchars($origTokens[$i - 1], ENT_QUOTES, 'UTF-8'));
            $i--;
            $j--;
        } elseif ($dp[$i - 1][$j] >= $dp[$i][$j - 1]) {
            array_unshift(
                $result,
                '<del>' . htmlspecialchars($origTokens[$i - 1], ENT_QUOTES, 'UTF-8') . '</del>'
            );
            $i--;
        } else {
            array_unshift(
                $result,
                '<ins>' . htmlspecialchars($newTokens[$j - 1], ENT_QUOTES, 'UTF-8') . '</ins>'
            );
            $j--;
        }
    }

    while ($i > 0) {
        array_unshift(
            $result,
            '<del>' . htmlspecialchars($origTokens[$i - 1], ENT_QUOTES, 'UTF-8') . '</del>'
        );
        $i--;
    }
    while ($j > 0) {
        array_unshift(
            $result,
            '<ins>' . htmlspecialchars($newTokens[$j - 1], ENT_QUOTES, 'UTF-8') . '</ins>'
        );
        $j--;
    }

    return implode(' ', $result);
}

function scores_to_bits(float $q, float $l, float $k): array {
    return [
        'q' => ($q >= 0.6) ? 1 : 0,
        'l' => ($l >= 0.6) ? 1 : 0,
        'k' => ($k >= 0.6) ? 1 : 0,
    ];
}

function explain_bits(array $bits, ?array $labels = null): string {
    $q = $bits['q'];
    $l = $bits['l'];
    $k = $bits['k'];

    $active = [];
    if ($q) $active[] = "syntax/structure (QSG)";
    if ($l) $active[] = "logical coherence (FOL-ish)";
    if ($k) $active[] = "Kantian alignment (CI heuristic)";

    if (!$active) {
        return "The clause does not register as syntactically clear, logically structured, or morally oriented under the current heuristics.";
    }

    $base  = "The clause is positively classified on " . implode(", ", $active) . ".";
    $extra = [];

    if ($q && $l && $k) {
        $extra[] = "It occupies the highest triad state: well-formed, inferentially meaningful, and ethically protective.";
    } elseif ($q && $l && !$k) {
        $extra[] = "Form and inference are strong, but the wording does not clearly express an ethical commitment respecting persons as ends.";
    } elseif ($q && !$l && $k) {
        $extra[] = "The sentence reads clearly and is morally oriented, but the logical structure (quantifiers, conditionals) is weak or implicit.";
    } elseif (!$q && $l && $k) {
        $extra[] = "The moral and logical signals are present, but the surface sentence is noisy or structurally fragile.";
    }

    if ($labels !== null) {
        $extra[] = sprintf(
            "QSG: %s. Logic: %s. Kant CI: %s.",
            $labels['qsgLabel'],
            $labels['logicLabel'],
            $labels['kantLabel']
        );
    }

    return $base . ' ' . implode(' ', $extra);
}

function format_score(?float $score): string {
    if ($score === null || !is_finite($score)) {
        return 'score: —';
    }
    return 'score: ' . (string)round($score * 100) . '/100';
}

/**
 * Wrapper: analyze clause end-to-end.
 */
function analyze_clause(string $clause, bool $withRewrite = true): array {
    $norm    = normalize_clause($clause);
    $tokens  = classify_tokens(tokenize_clause($norm));
    $qsg     = compute_qsg($tokens);
    $logic   = compute_logic($tokens);
    $kant    = compute_kant($tokens);
    $fol     = build_fol($tokens);
    $tone    = build_tone_summary($tokens);
    $ambiguity = compute_ambiguity($tokens);
    $modalProfile = compute_modal_profile($tokens);

    $bits    = scores_to_bits($qsg['score'], $logic['score'], $kant['score']);
    $bitStr  = $bits['q'] . $bits['l'] . $bits['k'];

    $labels  = [
        'qsgLabel'   => $qsg['label'],
        'logicLabel' => $logic['label'],
        'kantLabel'  => $kant['label'],
    ];
    $stateExplanation = explain_bits($bits, $labels);

    $aap = extract_aap($tokens);

    $rewritten = null;
    $diffHtml  = null;
    if ($withRewrite) {
        $rewritten = rewrite_clause($clause);
        $diffHtml  = diff_clauses($clause, $rewritten);
    }

    return [
        'normalized'       => $norm,
        'tokens'           => $tokens,
        'qsg'              => $qsg,
        'logic'            => $logic,
        'kant'             => $kant,
        'fol'              => $fol,
        'toneSummary'      => $tone,
        'ambiguity'        => $ambiguity,
        'modalProfile'     => $modalProfile,
        'bits'             => $bits,
        'bitString'        => $bitStr,
        'stateExplanation' => $stateExplanation,
        'rewritten'        => $rewritten,
        'diffHtml'         => $diffHtml,
        'aap'              => $aap,
    ];
}

/**
 * --------------------------------------------------------------------------
 * Request handling (AJAX JSON + HTML shell)
 * --------------------------------------------------------------------------
 */

$defaultClause = "for the claim of the treaty is with the protection of the lands by the council within this venue under the natural law";

if (!isset($_SESSION['history'])) {
    $_SESSION['history'] = [];
}

$isAjax = ($_SERVER['REQUEST_METHOD'] === 'POST')
    && isset($_POST['mode'])
    && $_POST['mode'] === 'analyze_json';

if ($isAjax) {
    header('Content-Type: application/json; charset=utf-8');

    $action = $_POST['action'] ?? 'scan';

    if ($action === 'clear_history') {
        $_SESSION['history'] = [];
        $statePill = ['text' => 'History cleared', 'tone' => 'warn'];
        echo json_encode([
            'ok'        => true,
            'statePill' => $statePill,
            'analysis'  => null,
            'history'   => [],
        ]);
        exit;
    }

    if ($action === 'export_history') {
        $export = [
            'exported_at' => time(),
            'history'     => $_SESSION['history'],
        ];
        echo json_encode([
            'ok'     => true,
            'export' => [
                'filename' => 'qsg_ruliad_history_' . date('Ymd_His') . '.json',
                'payload'  => $export,
            ],
            'history' => array_reverse($_SESSION['history']),
        ]);
        exit;
    }

    // Default: scan / scan_rewrite
    $clause = $_POST['clause'] ?? $defaultClause;
    $withRewrite = ($action === 'scan_rewrite');

    $analysis = analyze_clause($clause, $withRewrite);

    if ($analysis['kant']['score'] >= 0.6 && $analysis['qsg']['score'] >= 0.6 && $analysis['logic']['score'] >= 0.6) {
        $statePill = ['text' => 'Aligned triad (Q+L+K)', 'tone' => 'ok'];
    } elseif ($analysis['kant']['score'] < 0.4) {
        $statePill = ['text' => 'Possible CI issue', 'tone' => 'bad'];
    } else {
        $statePill = ['text' => 'Partial alignment', 'tone' => 'warn'];
    }

    $entry = [
        'timestamp' => time(),
        'clause'    => $analysis['normalized'],
        'bits'      => $analysis['bits'],
        'qsg'       => $analysis['qsg']['score'],
        'logic'     => $analysis['logic']['score'],
        'kant'      => $analysis['kant']['score'],
    ];
    $_SESSION['history'][] = $entry;

    $history = array_reverse($_SESSION['history']);

    echo json_encode([
        'ok'        => true,
        'statePill' => $statePill,
        'analysis'  => $analysis,
        'history'   => $history,
    ]);
    exit;
}

/**
 * Helper to render state pill classes
 */
function state_pill_class(?string $tone): string {
    $base = "inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium border ";
    return match ($tone) {
        'work' => $base . "border-indigo-400 bg-indigo-50 text-indigo-700",
        'ok'   => $base . "border-emerald-400 bg-emerald-50 text-emerald-700",
        'warn' => $base . "border-amber-400 bg-amber-50 text-amber-700",
        'bad'  => $base . "border-rose-400 bg-rose-50 text-rose-700",
        default => $base . "border-slate-300 bg-slate-50 text-slate-700",
    };
}

function chip_class(float $score): string {
    $base = "inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[11px] ";
    if ($score >= 0.8) {
        return $base . "bg-emerald-50 border-emerald-400 text-emerald-700";
    } elseif ($score >= 0.5) {
        return $base . "bg-amber-50 border-amber-400 text-amber-700";
    } elseif ($score > 0.0) {
        return $base . "bg-rose-50 border-rose-400 text-rose-700";
    }
    return $base . "bg-slate-50 border-slate-300 text-slate-700";
}

function bits_index(array $bits): int {
    return $bits['q'] + $bits['l'] * 2 + $bits['k'] * 4;
}

// Initial view: no server-side analysis; UI is populated via AJAX.
$initialClause = $defaultClause;
$analysis = null;
$statePill = ['text' => 'Idle', 'tone' => null];

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>QSG Logic &amp; Kant Lab – Ruliad Console v2.0 (PHP + AJAX)</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    .diff del {
      background: #fee2e2;
      color: #991b1b;
      text-decoration: line-through;
      padding: 1px 2px;
      border-radius: 4px;
    }
    .diff ins {
      background: #dcfce7;
      color: #166534;
      text-decoration: none;
      padding: 1px 2px;
      border-radius: 4px;
    }
    .state-node {
      transition: all 0.2s ease;
    }
    .state-node:hover {
      transform: scale(1.05);
    }
  </style>
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 antialiased">
  <div class="max-w-6xl mx-auto px-4 py-6 space-y-4">
    <!-- Header -->
    <header class="space-y-1">
      <h1 class="text-2xl font-semibold tracking-tight text-slate-900">
        Quantum Syntax Grammar · Logic · Kant · Ruliad Console v2.0
      </h1>
      <p class="text-sm text-slate-600">
        Wolfram-inspired rule evolution · Knuth-style small total functions · PHP session-backed history
        <span class="font-mono text-xs text-slate-500 block md:inline">
          QSG (grammar) · FOL (logic) · CI (Kant) · Ambiguity · Modals · 2³ ruliad state space
        </span>
      </p>
    </header>

    <!-- Input + Controls -->
    <section class="bg-white border border-slate-200 rounded-xl shadow-sm p-4 space-y-3">
      <form class="space-y-3" id="analysisForm" autocomplete="off">
        <div class="flex flex-wrap items-center gap-2 justify-between">
          <div class="flex items-center gap-2">
            <span class="text-sm font-medium text-slate-900">Clause Input</span>
            <span id="statePill"
              class="<?php echo state_pill_class($statePill['tone']); ?>">
              <?php echo htmlspecialchars($statePill['text'], ENT_QUOTES, 'UTF-8'); ?>
            </span>
          </div>
          <span id="toneSummary" class="text-[11px] text-slate-500">
            No preposition wiring detected.
          </span>
        </div>

        <textarea
          id="clause"
          name="clause"
          class="w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm leading-relaxed text-slate-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500"
          rows="4"
          placeholder="Enter clause for analysis..."
          aria-label="Clause input for analysis"
        ><?php echo htmlspecialchars($initialClause, ENT_QUOTES, 'UTF-8'); ?></textarea>

        <div class="flex flex-wrap items-center gap-3 text-xs text-slate-700">
          <label class="inline-flex items-center gap-1">
            <span class="font-medium">BPM</span>
            <input
              type="number"
              name="bpm"
              id="bpm"
              value="84"
              min="30"
              max="240"
              class="w-16 rounded-md border border-slate-300 bg-white px-1.5 py-1 text-xs focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
              aria-label="Beats per minute"
            />
          </label>

          <label class="inline-flex items-center gap-1">
            <input
              type="checkbox"
              name="met"
              id="met"
              class="h-3 w-3 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
              aria-label="Enable metronome (conceptual)"
            />
            <span>Metronome</span>
          </label>

          <label class="inline-flex items-center gap-1">
            <input
              type="checkbox"
              name="tones"
              id="tones"
              class="h-3 w-3 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
              aria-label="Play tones from prepositions (conceptual)"
            />
            <span>Play tones from prepositions</span>
          </label>

          <label class="inline-flex items-center gap-1">
            <input
              type="checkbox"
              id="autoScan"
              class="h-3 w-3 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
            />
            <span>Auto-scan while typing</span>
          </label>

          <div class="flex flex-wrap gap-2 ml-auto">
            <button
              type="button"
              data-action="scan"
              class="btn-scan inline-flex items-center gap-1 rounded-full border border-slate-800 bg-slate-900 px-3 py-1 text-xs font-medium text-white shadow-sm hover:bg-slate-800 active:translate-y-[1px]"
              aria-label="Scan clause (Ctrl/⌘+Enter)"
            >
              Scan (Ctrl/⌘+Enter)
            </button>
            <button
              type="button"
              data-action="scan_rewrite"
              class="btn-scan inline-flex items-center gap-1 rounded-full border border-indigo-600 bg-indigo-600 px-3 py-1 text-xs font-medium text-white shadow-sm hover:bg-indigo-500 active:translate-y-[1px]"
              aria-label="Scan, rewrite and diff clause (Ctrl/⌘+Shift+Enter)"
            >
              Scan + Rewrite &amp; Diff
            </button>
            <button
              type="button"
              data-action="clear"
              class="inline-flex items-center gap-1 rounded-full border border-slate-300 bg-white px-3 py-1 text-xs font-medium text-slate-700 shadow-sm hover:bg-slate-50 active:translate-y-[1px]"
              aria-label="Clear analysis"
              id="btnClearAnalysis"
            >
              Clear
            </button>
          </div>
        </div>

        <div id="report" class="mt-2 flex flex-wrap gap-2 text-[11px] text-slate-800 items-center">
          <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5">
            Tokens: —
          </span>
          <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5">
            Preps: —
          </span>
          <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5">
            Verbs: —
          </span>
          <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5">
            Negations: —
          </span>
        </div>
      </form>
    </section>

    <!-- Main analysis grid -->
    <section class="grid gap-4 lg:grid-cols-3">
      <!-- Left 2 columns -->
      <div class="lg:col-span-2 space-y-4">
        <!-- Analysis Layers -->
        <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-4 space-y-3">
          <div class="flex flex-wrap gap-2 items-center justify-between">
            <h2 class="text-sm font-semibold text-slate-900 flex items-center gap-2">
              Analysis Layers
              <span class="inline-flex items-center gap-1 text-[10px] font-normal text-slate-500">
                <span class="w-2 h-2 rounded-full bg-blue-500"></span>QSG
                <span class="w-2 h-2 rounded-full bg-purple-500"></span>Logic
                <span class="w-2 h-2 rounded-full bg-amber-500"></span>Kant
                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>Clarity
              </span>
            </h2>
          </div>

          <div class="flex flex-wrap gap-2 text-[11px]" id="layerChips">
            <span class="inline-flex items-center gap-1 rounded-full border border-slate-300 bg-slate-50 px-2 py-0.5">
              QSG: —
            </span>
            <span class="inline-flex items-center gap-1 rounded-full border border-slate-300 bg-slate-50 px-2 py-0.5">
              Logic: —
            </span>
            <span class="inline-flex items-center gap-1 rounded-full border border-slate-300 bg-slate-50 px-2 py-0.5">
              Kant CI: —
            </span>
            <span class="inline-flex items-center gap-1 rounded-full border border-slate-300 bg-slate-50 px-2 py-0.5">
              Clarity: —
            </span>
            <span class="inline-flex items-center gap-1 rounded-full border border-slate-300 bg-slate-50 px-2 py-0.5 font-mono">
              State LFK: —
            </span>
          </div>

          <div class="grid md:grid-cols-2 gap-3 text-[11px]">
            <!-- FOL Formula -->
            <div class="space-y-1">
              <div class="flex items-center justify-between">
                <span class="font-semibold text-slate-900">First-Order Logic Skeleton</span>
                <span class="font-mono text-[10px] text-slate-400">φ(x,…)</span>
              </div>
              <pre
                id="formulaBox"
                class="min-h-[96px] rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-2 text-[11px] leading-snug overflow-x-auto whitespace-pre-wrap"
              >—</pre>
            </div>

            <!-- Notes -->
            <div class="space-y-2">
              <div>
                <div class="flex items-center justify-between">
                  <span class="font-semibold text-slate-900">QSG · Structural Notes</span>
                  <span id="qsgScore" class="font-mono text-[10px] text-slate-500">
                    score: —
                  </span>
                </div>
                <p id="qsgNotes" class="mt-1 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-2 leading-snug">
                  —
                </p>
              </div>

              <div>
                <div class="flex items-center justify-between">
                  <span class="font-semibold text-slate-900">Logic · Inference Notes</span>
                  <span id="logicScore" class="font-mono text-[10px] text-slate-500">
                    score: —
                  </span>
                </div>
                <p id="logicNotes" class="mt-1 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-2 leading-snug">
                  —
                </p>
              </div>

              <div>
                <div class="flex items-center justify-between">
                  <span class="font-semibold text-slate-900">Kant CI · Moral Evaluation</span>
                  <span id="kantScore" class="font-mono text-[10px] text-slate-500">
                    score: —
                  </span>
                </div>
                <p id="kantNotes" class="mt-1 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-2 leading-snug">
                  —
                </p>
              </div>

              <div>
                <div class="flex items-center justify-between">
                  <span class="font-semibold text-slate-900">Ambiguity · Clarity</span>
                  <span id="ambigScore" class="font-mono text-[10px] text-slate-500">
                    score: —
                  </span>
                </div>
                <p id="ambigNotes" class="mt-1 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-2 leading-snug">
                  —
                </p>
              </div>

              <div>
                <div class="flex items-center justify-between">
                  <span class="font-semibold text-slate-900">Modal Logic Profile</span>
                </div>
                <p id="modalSummary" class="mt-1 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-2 leading-snug">
                  Obligation: 0 · Permission: 0 · Recommendation: 0
                </p>
              </div>
            </div>
          </div>
        </div>

        <!-- Agent–Action–Patient -->
        <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-4 space-y-2">
          <div class="flex items-center justify-between gap-2">
            <h2 class="text-sm font-semibold text-slate-900">Agent–Action–Patient (AAP)</h2>
            <span class="text-[10px] text-slate-500">Heuristic extraction</span>
          </div>
          <dl class="grid grid-cols-1 sm:grid-cols-3 gap-2 text-[11px]">
            <div>
              <dt class="font-semibold text-slate-800">Agent</dt>
              <dd id="aapAgent" class="text-slate-700">—</dd>
            </div>
            <div>
              <dt class="font-semibold text-slate-800">Action</dt>
              <dd id="aapAction" class="text-slate-700 font-mono">—</dd>
            </div>
            <div>
              <dt class="font-semibold text-slate-800">Patient</dt>
              <dd id="aapPatient" class="text-slate-700">—</dd>
            </div>
          </dl>
          <p class="text-[10px] text-slate-500">
            The AAP view approximates “who does what to whom” to support CI evaluation and FOL templating.
          </p>
        </div>

        <!-- Clause Decomposition -->
        <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-4 space-y-3">
          <div class="flex items-center justify-between gap-2">
            <h2 class="text-sm font-semibold text-slate-900">Clause Decomposition</h2>
            <span class="text-[10px] text-slate-500">Token-level view · prepositions and negations as “wires”</span>
          </div>

          <div class="overflow-x-auto rounded-lg border border-slate-200 bg-slate-50">
            <table class="min-w-full text-[11px]">
              <thead class="bg-slate-100 text-slate-600">
                <tr>
                  <th class="px-2 py-1 text-left font-medium">#</th>
                  <th class="px-2 py-1 text-left font-medium">Token</th>
                  <th class="px-2 py-1 text-left font-medium">Role</th>
                  <th class="px-2 py-1 text-left font-medium">Tag</th>
                  <th class="px-2 py-1 text-left font-medium">Notes</th>
                </tr>
              </thead>
              <tbody id="tokenTableBody" class="divide-y divide-slate-200">
                <tr>
                  <td colspan="5" class="px-2 py-2 text-center text-slate-500">
                    No tokens – nothing to decompose.
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Rewrite & Diff -->
        <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-4 space-y-3">
          <div class="flex items-center justify-between gap-2">
            <h2 class="text-sm font-semibold text-slate-900">Rewrite &amp; Diff</h2>
            <span class="text-[10px] text-slate-500">Knuth-style clarity pass (PHP)</span>
          </div>

          <div class="grid md:grid-cols-2 gap-3 text-[11px]">
            <div class="space-y-1">
              <span class="font-medium text-slate-800">Rewritten Clause</span>
              <textarea
                id="rewrittenClause"
                class="w-full min-h-[80px] rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-2 leading-snug"
                readonly
              >—</textarea>
            </div>
            <div class="space-y-1">
              <span class="font-medium text-slate-800">Diff (original → rewritten)</span>
              <div
                id="diffView"
                class="diff min-h-[80px] rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-2 leading-snug text-[11px]"
              >—</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Right column: Ruliad state + history -->
      <div class="space-y-4">
        <!-- Ruliad State Console -->
        <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-4 space-y-3">
          <div class="flex items-center justify-between gap-2">
            <h2 class="text-sm font-semibold text-slate-900">Ruliad State Console</h2>
            <span class="text-[10px] text-slate-500 font-mono">2³ states over (QSG, Logic, CI)</span>
          </div>

          <p class="text-[11px] text-slate-600">
            Bits are ordered as <span class="font-mono">[Q, L, K]</span>.
            Each scan maps the clause into one of eight discrete states in the local ruliad.
          </p>

          <div class="grid grid-cols-4 gap-2 text-[10px]" id="ruliadGrid">
            <?php
            $stateDefs = [
              ['bits' => '000', 'title' => 'Incoherent',      'desc' => 'No stable grammar, logic, or CI.'],
              ['bits' => '001', 'title' => 'Moral Only',      'desc' => 'Ethos present, form unstable.'],
              ['bits' => '010', 'title' => 'Logic Only',      'desc' => 'Abstractly coherent, linguistically rough.'],
              ['bits' => '011', 'title' => 'Logic + Moral',   'desc' => 'Ethical principle with clear inference.'],
              ['bits' => '100', 'title' => 'QSG Only',        'desc' => 'Well-formed text, weak semantics.'],
              ['bits' => '101', 'title' => 'QSG + Moral',     'desc' => 'Readable and ethically oriented.'],
              ['bits' => '110', 'title' => 'QSG + Logic',     'desc' => 'Clear sentence with sound inference.'],
              ['bits' => '111', 'title' => 'Full Alignment',  'desc' => 'Grammar, logic, and CI all coherent.'],
            ];
            foreach ($stateDefs as $st):
            ?>
              <div class="state-node rounded-lg border border-slate-200 bg-slate-50 px-1.5 py-1 text-left leading-tight"
                   data-bits="<?php echo htmlspecialchars($st['bits'], ENT_QUOTES, 'UTF-8'); ?>">
                <div class="font-mono text-[10px] text-slate-500">
                  <?php echo htmlspecialchars($st['bits'], ENT_QUOTES, 'UTF-8'); ?>
                </div>
                <div class="font-semibold text-slate-800">
                  <?php echo htmlspecialchars($st['title'], ENT_QUOTES, 'UTF-8'); ?>
                </div>
                <div class="text-[10px] text-slate-500">
                  <?php echo htmlspecialchars($st['desc'], ENT_QUOTES, 'UTF-8'); ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>

          <div class="mt-2 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-2 text-[11px]">
            <div class="flex items-center justify-between gap-2">
              <span class="font-semibold text-slate-900">Current State Explanation</span>
              <span id="stateBitsLabel" class="font-mono text-[10px] text-slate-500">
                [Q,L,K] = —
              </span>
            </div>
            <p id="stateExplanation" class="mt-1 text-[11px] text-slate-700 leading-snug">
              Run a scan to project the clause into the local 2³ ruliad state space.
            </p>
          </div>
        </div>

        <!-- History -->
        <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-4 space-y-3">
          <div class="flex items-center justify-between gap-2">
            <h2 class="text-sm font-semibold text-slate-900">Scan History (session)</h2>
            <div class="flex items-center gap-2">
              <button
                type="button"
                id="btnClearHistory"
                class="text-[10px] rounded-full border border-slate-300 bg-white px-2 py-0.5 text-slate-600 hover:bg-slate-50"
              >
                Clear history
              </button>
              <button
                type="button"
                id="btnExportHistory"
                class="text-[10px] rounded-full border border-indigo-400 bg-indigo-50 px-2 py-0.5 text-indigo-700 hover:bg-indigo-100"
              >
                Export JSON
              </button>
            </div>
          </div>
          <div id="historyList" class="space-y-1 max-h-64 overflow-y-auto text-[11px]">
            <p class="text-slate-500 text-[11px]">
              No scans yet. Your runs will appear here.
            </p>
          </div>
        </div>
      </div>
    </section>

    <!-- Footer / Help -->
    <footer class="text-[11px] text-slate-500 border-t border-slate-200 pt-3 mt-3">
      <div class="flex flex-wrap items-center justify-between gap-2">
        <span>
          Keyboard:
          <span class="font-mono">Ctrl/⌘+Enter</span> = Scan,
          <span class="font-mono">Ctrl/⌘+Shift+Enter</span> = Scan + Rewrite
        </span>
        <span class="font-mono">
          Heuristics only – QSG / FOL / CI / Ambiguity are approximations, not oracles.
        </span>
      </div>
    </footer>
  </div>

  <!-- JS: AJAX engine + live UI updates -->
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const form         = document.getElementById('analysisForm');
      const clauseInput  = document.getElementById('clause');
      const bpmInput     = document.getElementById('bpm');
      const metInput     = document.getElementById('met');
      const tonesInput   = document.getElementById('tones');
      const autoScan     = document.getElementById('autoScan');

      const statePill    = document.getElementById('statePill');
      const toneSummary  = document.getElementById('toneSummary');
      const report       = document.getElementById('report');
      const layerChips   = document.getElementById('layerChips');
      const formulaBox   = document.getElementById('formulaBox');
      const qsgScoreEl   = document.getElementById('qsgScore');
      const qsgNotesEl   = document.getElementById('qsgNotes');
      const logicScoreEl = document.getElementById('logicScore');
      const logicNotesEl = document.getElementById('logicNotes');
      const kantScoreEl  = document.getElementById('kantScore');
      const kantNotesEl  = document.getElementById('kantNotes');
      const ambigScoreEl = document.getElementById('ambigScore');
      const ambigNotesEl = document.getElementById('ambigNotes');
      const modalSummaryEl = document.getElementById('modalSummary');

      const aapAgentEl   = document.getElementById('aapAgent');
      const aapActionEl  = document.getElementById('aapAction');
      const aapPatientEl = document.getElementById('aapPatient');

      const tokenTableBody = document.getElementById('tokenTableBody');
      const rewrittenClauseEl = document.getElementById('rewrittenClause');
      const diffViewEl        = document.getElementById('diffView');

      const ruliadGrid    = document.getElementById('ruliadGrid');
      const stateBitsLabel = document.getElementById('stateBitsLabel');
      const stateExplanationEl = document.getElementById('stateExplanation');

      const historyList   = document.getElementById('historyList');
      const btnClearHistory = document.getElementById('btnClearHistory');
      const btnExportHistory = document.getElementById('btnExportHistory');
      const btnClearAnalysis = document.getElementById('btnClearAnalysis');

      const scanButtons   = document.querySelectorAll('.btn-scan');

      function jsStatePillClass(tone) {
        const base = "inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium border ";
        switch (tone) {
          case 'work': return base + "border-indigo-400 bg-indigo-50 text-indigo-700";
          case 'ok':   return base + "border-emerald-400 bg-emerald-50 text-emerald-700";
          case 'warn': return base + "border-amber-400 bg-amber-50 text-amber-700";
          case 'bad':  return base + "border-rose-400 bg-rose-50 text-rose-700";
          default:     return base + "border-slate-300 bg-slate-50 text-slate-700";
        }
      }

      function formatScore(score) {
        if (score === null || score === undefined || !isFinite(score)) return 'score: —';
        return 'score: ' + Math.round(score * 100) + '/100';
      }

      async function runAction(action) {
        if (action === 'clear') {
          clearAnalysisUI();
          return;
        }

        const fd = new FormData();
        fd.append('mode', 'analyze_json');
        fd.append('action', action);
        fd.append('clause', clauseInput.value || '');
        fd.append('bpm', bpmInput.value || '');
        if (metInput.checked)   fd.append('met', '1');
        if (tonesInput.checked) fd.append('tones', '1');

        try {
          statePill.className = jsStatePillClass('work');
          statePill.textContent = 'Working…';

          const res = await fetch(window.location.href, {
            method: 'POST',
            body: fd
          });
          const data = await res.json();

          if (action === 'export_history') {
            if (data.export && data.export.payload) {
              triggerExport(data.export);
            }
            if (data.history) {
              renderHistory(data.history);
            }
            // do not overwrite analysis UI in export-only mode
            statePill.className = jsStatePillClass(null);
            statePill.textContent = 'History exported';
            return;
          }

          applyAnalysis(data);
        } catch (err) {
          console.error(err);
          statePill.className = jsStatePillClass('bad');
          statePill.textContent = 'Error';
        }
      }

      function triggerExport(exp) {
        const payload = exp.payload || {};
        const jsonStr = JSON.stringify(payload, null, 2);
        const blob = new Blob([jsonStr], { type: 'application/json' });
        const url  = URL.createObjectURL(blob);
        const a    = document.createElement('a');
        a.href = url;
        a.download = exp.filename || 'qsg_ruliad_history.json';
        document.body.appendChild(a);
        a.click();
        a.remove();
        URL.revokeObjectURL(url);
      }

      function applyAnalysis(data) {
        const state = data.statePill || { text: 'Idle', tone: null };
        const analysis = data.analysis || null;
        const history = data.history || [];

        statePill.className = jsStatePillClass(state.tone || null);
        statePill.textContent = state.text || 'Idle';

        if (!analysis) {
          clearAnalysisUI();
          renderHistory(history);
          return;
        }

        toneSummary.textContent = analysis.toneSummary || 'No preposition wiring detected.';

        // Chips
        renderChips(analysis);

        // FOL
        const formula = (analysis.fol && analysis.fol.formula) ? analysis.fol.formula : '—';
        const notes   = (analysis.fol && analysis.fol.notes)   ? analysis.fol.notes   : '';
        formulaBox.textContent = formula + (notes ? "\n\n// " + notes : "");

        // Scores + notes
        qsgScoreEl.textContent   = formatScore(analysis.qsg && analysis.qsg.score);
        qsgNotesEl.textContent   = analysis.qsg && analysis.qsg.notes ? analysis.qsg.notes : '—';
        logicScoreEl.textContent = formatScore(analysis.logic && analysis.logic.score);
        logicNotesEl.textContent = analysis.logic && analysis.logic.notes ? analysis.logic.notes : '—';
        kantScoreEl.textContent  = formatScore(analysis.kant && analysis.kant.score);
        kantNotesEl.textContent  = analysis.kant && analysis.kant.notes ? analysis.kant.notes : '—';

        ambigScoreEl.textContent = formatScore(analysis.ambiguity && analysis.ambiguity.score);
        ambigNotesEl.textContent = analysis.ambiguity && analysis.ambiguity.notes ? analysis.ambiguity.notes : '—';

        modalSummaryEl.textContent = analysis.modalProfile && analysis.modalProfile.summary
          ? analysis.modalProfile.summary
          : 'Obligation: 0 · Permission: 0 · Recommendation: 0';

        // AAP
        aapAgentEl.textContent   = (analysis.aap && analysis.aap.agentPhrase)   || '—';
        aapActionEl.textContent  = (analysis.aap && analysis.aap.actionWord)    || '—';
        aapPatientEl.textContent = (analysis.aap && analysis.aap.patientPhrase) || '—';

        // Tokens
        renderTokenTable(analysis.tokens || []);

        // Rewrite + diff
        rewrittenClauseEl.value = analysis.rewritten || '—';
        diffViewEl.innerHTML    = analysis.diffHtml || '—';

        // Ruliad state
        const bits = analysis.bitString || '000';
        stateBitsLabel.textContent = `[Q,L,K] = [${analysis.bits.q},${analysis.bits.l},${analysis.bits.k}]`;
        stateExplanationEl.textContent = analysis.stateExplanation || '';
        highlightRuliad(bits);

        // History
        renderHistory(history);

        // Report row
        renderReport(analysis);
      }

      function renderChips(analysis) {
        const qsgScore   = analysis.qsg && analysis.qsg.score || 0;
        const logicScore = analysis.logic && analysis.logic.score || 0;
        const kantScore  = analysis.kant && analysis.kant.score || 0;
        const ambigScore = analysis.ambiguity && analysis.ambiguity.score || 0;

        const bitString  = analysis.bitString || '---';

        const chips = [
          { label: 'QSG: '   + (analysis.qsg && analysis.qsg.label   || '—'), score: qsgScore },
          { label: 'Logic: ' + (analysis.logic && analysis.logic.label || '—'), score: logicScore },
          { label: 'Kant CI: ' + (analysis.kant && analysis.kant.label || '—'), score: kantScore },
          { label: 'Clarity: ' + (analysis.ambiguity && analysis.ambiguity.label || '—'), score: ambigScore },
        ];

        const stateChip = 'State LFK: ' + bitString;

        let html = '';
        chips.forEach((c) => {
          const cls = jsChipClass(c.score);
          html += `<span class="${cls}">${escapeHtml(c.label)}</span>`;
        });
        html += `<span class="inline-flex items-center gap-1 rounded-full border border-indigo-400 bg-indigo-50 px-2 py-0.5 font-mono text-indigo-700">${escapeHtml(stateChip)}</span>`;
        layerChips.innerHTML = html;
      }

      function jsChipClass(score) {
        const base = "inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[11px] ";
        if (score >= 0.8) {
          return base + "bg-emerald-50 border-emerald-400 text-emerald-700";
        } else if (score >= 0.5) {
          return base + "bg-amber-50 border-amber-400 text-amber-700";
        } else if (score > 0.0) {
          return base + "bg-rose-50 border-rose-400 text-rose-700";
        }
        return base + "bg-slate-50 border-slate-300 text-slate-700";
      }

      function renderTokenTable(tokens) {
        if (!tokens || !tokens.length) {
          tokenTableBody.innerHTML = `
            <tr>
              <td colspan="5" class="px-2 py-2 text-center text-slate-500">
                No tokens – nothing to decompose.
              </td>
            </tr>
          `;
          return;
        }

        let html = '';
        tokens.forEach((t) => {
          let note = "Lexical content token.";
          switch (t.tag) {
            case 'PREP': note = "Defines a relation / edge in the FOL graph."; break;
            case 'VERB': note = "Carries predicate / action semantics."; break;
            case 'QUANT': note = "Introduces a quantifier (∀/∃)."; break;
            case 'CONJ': note = "Connects subclauses (∧ / ∨)."; break;
            case 'DET': note = "Grammatical determiner."; break;
            case 'NEG': note = "Introduces logical negation (¬)."; break;
          }
          html += `
            <tr class="bg-white">
              <td class="px-2 py-1 text-slate-500 font-mono">${t.index}</td>
              <td class="px-2 py-1 font-mono text-slate-900">${escapeHtml(t.raw)}</td>
              <td class="px-2 py-1 text-slate-700">${escapeHtml(t.role)}</td>
              <td class="px-2 py-1 text-slate-700 font-mono">${escapeHtml(t.tag)}</td>
              <td class="px-2 py-1 text-slate-500">${escapeHtml(note)}</td>
            </tr>
          `;
        });
        tokenTableBody.innerHTML = html;
      }

      function highlightRuliad(bitString) {
        if (!ruliadGrid) return;
        const nodes = ruliadGrid.querySelectorAll('.state-node');
        nodes.forEach((node) => {
          node.classList.remove('ring-2', 'ring-indigo-500', 'bg-indigo-100');
        });
        const active = ruliadGrid.querySelector(`.state-node[data-bits="${bitString}"]`);
        if (active) {
          active.classList.add('ring-2', 'ring-indigo-500', 'bg-indigo-100');
        }
      }

      function renderHistory(history) {
        if (!history || !history.length) {
          historyList.innerHTML = `
            <p class="text-slate-500 text-[11px]">
              No scans yet. Your runs will appear here.
            </p>
          `;
          return;
        }

        let html = '';
        const totalHistory = history.length;
        history.forEach((h, idx) => {
          const badgeNum = totalHistory - idx;
          const timeStr = new Date(h.timestamp * 1000).toLocaleTimeString();
          const bits = h.bits || { q:0, l:0, k:0 };
          html += `
            <div class="flex flex-col gap-0.5 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-2">
              <div class="flex items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                  <span class="inline-flex items-center justify-center rounded-full bg-slate-800 px-1.5 py-0.5 text-[9px] font-mono text-white">
                    #${badgeNum}
                  </span>
                  <span class="text-[10px] text-slate-500">${escapeHtml(timeStr)}</span>
                </div>
                <span class="font-mono text-[10px] text-slate-600">
                  [${bits.q},${bits.l},${bits.k}]
                </span>
              </div>
              <div class="text-[11px] text-slate-800">
                ${escapeHtml(h.clause || '')}
              </div>
              <div class="text-[10px] text-slate-500">
                QSG ${Math.round((h.qsg || 0) * 100)} · Logic ${Math.round((h.logic || 0) * 100)} · CI ${Math.round((h.kant || 0) * 100)}
              </div>
            </div>
          `;
        });
        historyList.innerHTML = html;
      }

      function renderReport(analysis) {
        if (!analysis || !analysis.tokens) {
          report.innerHTML = `
            <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5">Tokens: —</span>
            <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5">Preps: —</span>
            <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5">Verbs: —</span>
            <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5">Negations: —</span>
          `;
          return;
        }

        const tokens = analysis.tokens;
        let preps = 0, verbs = 0, negs = 0;
        tokens.forEach((t) => {
          if (t.tag === 'PREP') preps++;
          if (t.tag === 'VERB') verbs++;
          if (t.tag === 'NEG')  negs++;
        });
        const chips = [
          `Tokens: ${tokens.length}`,
          `Preps: ${preps}`,
          `Verbs: ${verbs}`,
          `Negations: ${negs}`,
          `QSG: ${Math.round((analysis.qsg && analysis.qsg.score || 0) * 100)}`,
          `Logic: ${Math.round((analysis.logic && analysis.logic.score || 0) * 100)}`,
          `CI: ${Math.round((analysis.kant && analysis.kant.score || 0) * 100)}`
        ];
        let html = '';
        chips.forEach((txt) => {
          html += `
            <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5">
              ${escapeHtml(txt)}
            </span>
          `;
        });
        report.innerHTML = html;
      }

      function clearAnalysisUI() {
        toneSummary.textContent = 'No preposition wiring detected.';
        formulaBox.textContent = '—';

        qsgScoreEl.textContent   = 'score: —';
        qsgNotesEl.textContent   = '—';
        logicScoreEl.textContent = 'score: —';
        logicNotesEl.textContent = '—';
        kantScoreEl.textContent  = 'score: —';
        kantNotesEl.textContent  = '—';
        ambigScoreEl.textContent = 'score: —';
        ambigNotesEl.textContent = '—';
        modalSummaryEl.textContent = 'Obligation: 0 · Permission: 0 · Recommendation: 0';

        aapAgentEl.textContent   = '—';
        aapActionEl.textContent  = '—';
        aapPatientEl.textContent = '—';

        tokenTableBody.innerHTML = `
          <tr>
            <td colspan="5" class="px-2 py-2 text-center text-slate-500">
              No tokens – nothing to decompose.
            </td>
          </tr>
        `;

        rewrittenClauseEl.value = '—';
        diffViewEl.innerHTML    = '—';

        stateBitsLabel.textContent = '[Q,L,K] = —';
        stateExplanationEl.textContent = 'Run a scan to project the clause into the local 2³ ruliad state space.';
        highlightRuliad('000');

        renderReport(null);
      }

      function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
          .replace(/&/g, '&amp;')
          .replace(/</g, '&lt;')
          .replace(/>/g, '&gt;')
          .replace(/"/g, '&quot;')
          .replace(/'/g, '&#039;');
      }

      // Button wiring
      scanButtons.forEach((btn) => {
        btn.addEventListener('click', () => {
          const action = btn.getAttribute('data-action') || 'scan';
          runAction(action);
        });
      });

      btnClearHistory.addEventListener('click', () => {
        runAction('clear_history');
      });

      btnExportHistory.addEventListener('click', () => {
        runAction('export_history');
      });

      btnClearAnalysis.addEventListener('click', () => {
        clearAnalysisUI();
        statePill.className = jsStatePillClass(null);
        statePill.textContent = 'Analysis cleared';
      });

      // Keyboard shortcuts
      document.addEventListener('keydown', (ev) => {
        const isMac = navigator.platform.toUpperCase().indexOf('MAC') >= 0;
        const meta  = isMac ? ev.metaKey : ev.ctrlKey;
        if (!meta || ev.key !== 'Enter') return;

        ev.preventDefault();
        if (ev.shiftKey) {
          runAction('scan_rewrite');
        } else {
          runAction('scan');
        }
      });

      // Auto-scan while typing with debounce
      let typingTimer = null;
      clauseInput.addEventListener('input', () => {
        if (!autoScan.checked) return;
        if (typingTimer) clearTimeout(typingTimer);
        typingTimer = setTimeout(() => {
          runAction('scan');
        }, 600);
      });
    });
  </script>
</body>
</html>