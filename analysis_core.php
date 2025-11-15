<?php
declare(strict_types=1);

/**
 * QSG Ruliad Console - Core Analysis Functions (Optimized)
 *
 * KNUTH: "Premature optimization is the root of all evil. But when you optimize,
 *         do it right: reduce complexity, eliminate redundant work, choose
 *         the right data structures."
 *
 * OPTIMIZATIONS APPLIED:
 * - Single-pass token statistics (O(n) instead of O(3n))
 * - Eliminated duplicate wordlist definitions
 * - Documented algorithmic complexity
 * - Memoization for repeated calculations
 *
 * @author  QSG Ruliad Team (Knuth/Wolfram/Torvalds edition)
 */

require_once __DIR__ . '/config.php';

// ============================================================================
// TOKENIZATION & CLASSIFICATION
// ============================================================================

/**
 * Normalize clause text.
 *
 * COMPLEXITY: O(n) where n = text length
 *
 * @param string $text Raw clause text
 * @return string Normalized text
 */
function normalize_clause(string $text): string {
    // TORVALDS: Fail loudly on errors
    $normalized = preg_replace('/\s+/u', ' ', $text);
    if ($normalized === null) {
        throw new RuntimeException('Regex execution failed in normalize_clause');
    }
    return trim($normalized);
}

/**
 * Tokenize clause into structured tokens.
 *
 * COMPLEXITY: O(n) where n = number of words
 *
 * Token structure:
 * [
 *   'index' => int,     // Position in sequence
 *   'raw'   => string,  // Original text including punctuation
 *   'clean' => string,  // Stripped of leading/trailing punctuation
 *   'lower' => string,  // Lowercase for matching
 *   'tag'   => string,  // PREP | VERB | QUANT | DET | CONJ | NUM | NEG | WORD
 *   'role'  => string   // Human-readable role description
 * ]
 *
 * @param string $text Normalized clause
 * @return array<int, array> Array of token structures
 */
function tokenize_clause(string $text): array {
    $norm = normalize_clause($text);
    if ($norm === '') {
        return [];
    }

    $chunks = explode(' ', $norm);
    $tokens = [];

    foreach ($chunks as $idx => $raw) {
        // Strip leading/trailing non-alphanumeric (but preserve Unicode)
        $clean = preg_replace('/^[^\p{L}\p{N}]+|[^\p{L}\p{N}]+$/u', '', $raw);
        if ($clean === null) {
            throw new RuntimeException('Regex execution failed in tokenize_clause');
        }

        $lower = mb_strtolower($clean, 'UTF-8');

        $tokens[] = [
            'index' => $idx,
            'raw'   => $raw,
            'clean' => $clean,
            'lower' => $lower,
            'tag'   => 'WORD',      // Default, will be classified
            'role'  => 'content',
        ];
    }

    return $tokens;
}

/**
 * Classify tokens with grammatical/logical tags.
 *
 * KNUTH OPTIMIZATION: Build lookup sets once, not per-token.
 * COMPLEXITY: O(n) where n = token count
 *
 * @param array $tokens Unclassified tokens
 * @return array Classified tokens
 */
function classify_tokens(array $tokens): array {
    // Build lookup sets (O(1) membership test)
    // KNUTH: "Choose data structures wisely - they determine your algorithm's fate"
    static $preps = null;
    static $verbs = null;
    static $quantifiers = null;
    static $negations = null;

    if ($preps === null) {
        $preps = array_fill_keys(PREPOSITIONS, true);
        $verbs = array_fill_keys(VERBS, true);
        $quantifiers = array_fill_keys(QUANTIFIERS, true);
        $negations = array_fill_keys(NEGATIONS, true);
    }

    foreach ($tokens as &$t) {
        $tag  = "WORD";
        $role = "content";

        // Priority order: negation > prep > verb > quant > det > conj > num
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
    unset($t); // Break reference

    return $tokens;
}

// ============================================================================
// SINGLE-PASS TOKEN STATISTICS (Knuth optimization)
// ============================================================================

/**
 * Compute token statistics in SINGLE PASS.
 *
 * KNUTH: "Why traverse the array three times when once suffices?"
 * BEFORE: O(3n) - three separate loops
 * AFTER:  O(n)  - one loop
 *
 * @param array $tokens Classified tokens
 * @return array Statistics: verbs, preps, quant, modals, negs, garbage, total
 */
function compute_token_stats(array $tokens): array {
    static $modal_words = null;
    if ($modal_words === null) {
        $modal_words = array_fill_keys(['must', 'shall', 'may', 'can', 'should'], true);
    }

    $stats = [
        'total'   => count($tokens),
        'verbs'   => 0,
        'preps'   => 0,
        'quant'   => 0,
        'modals'  => 0,
        'negs'    => 0,
        'garbage' => 0,
    ];

    foreach ($tokens as $t) {
        if ($t['tag'] === 'VERB') {
            $stats['verbs']++;
        }
        if ($t['tag'] === 'PREP') {
            $stats['preps']++;
        }
        if ($t['tag'] === 'QUANT') {
            $stats['quant']++;
        }
        if ($t['tag'] === 'NEG') {
            $stats['negs']++;
        }
        if (isset($modal_words[$t['lower']])) {
            $stats['modals']++;
        }
        if ($t['clean'] === '') {
            $stats['garbage']++;
        }
    }

    return $stats;
}

// ============================================================================
// QSG SCORING
// ============================================================================

/**
 * Compute QSG (Quantum Syntax Grammar) score.
 *
 * KNUTH: Scoring model documented in config.php with mathematical justification.
 * Uses single-pass statistics for efficiency.
 *
 * @param array $tokens Classified tokens
 * @return array ['score' => float, 'label' => string, 'notes' => string]
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

    $stats = compute_token_stats($tokens);

    $score = 0.0;
    if ($stats['verbs'] > 0) {
        $score += QSG_VERB_WEIGHT;
    }
    if ($count >= QSG_MIN_TOKENS) {
        $score += QSG_LENGTH_WEIGHT;
    }
    if ($stats['preps'] > 0) {
        $score += QSG_PREP_WEIGHT;
    }
    if ($stats['garbage'] === 0) {
        $score += QSG_CLEAN_WEIGHT;
    }

    // Classify by threshold
    if ($score >= SCORE_THRESHOLD_STRONG) {
        $label = "Strong sentence-like structure";
    } elseif ($score >= SCORE_THRESHOLD_MODERATE) {
        $label = "Reasonably structured clause";
    } elseif ($score > SCORE_THRESHOLD_WEAK) {
        $label = "Fragmentary or weakly structured";
    } else {
        $label = "Non-sentential text";
    }

    $notes = sprintf(
        "Tokens: %d · Verbs: %d · Prepositions: %d · %s",
        $count,
        $stats['verbs'],
        $stats['preps'],
        $stats['garbage'] ? "Garbage tokens: {$stats['garbage']}" : "No obvious noise tokens"
    );

    return [
        'score' => $score,
        'label' => $label,
        'notes' => $notes,
    ];
}

// ============================================================================
// LOGIC COHERENCE SCORING
// ============================================================================

/**
 * Compute logical coherence score.
 *
 * Based on presence of verbs, prepositions, quantifiers, modals, and negations.
 * Uses single-pass statistics.
 *
 * @param array $tokens Classified tokens
 * @return array ['score' => float, 'label' => string, 'notes' => string]
 */
function compute_logic(array $tokens): array {
    if (count($tokens) === 0) {
        return [
            'score' => 0.0,
            'label' => 'No logical structure',
            'notes' => 'Empty clause.',
        ];
    }

    $stats = compute_token_stats($tokens);

    $score = 0.0;
    if ($stats['verbs'] > 0) {
        $score += LOGIC_VERB_WEIGHT;
    }
    if ($stats['preps'] > 0) {
        $score += LOGIC_PREP_WEIGHT;
    }
    if (($stats['quant'] + $stats['modals']) > 0) {
        $score += LOGIC_QUANT_MODAL_WEIGHT;
    }
    if ($stats['negs'] > 0 && $stats['verbs'] > 0) {
        $score += LOGIC_NEGATION_WEIGHT; // Negation often implies more structured logic
    }

    if ($score >= SCORE_THRESHOLD_STRONG) {
        $label = "Strong logical form";
    } elseif ($score >= SCORE_THRESHOLD_MODERATE) {
        $label = "Moderate logical form";
    } elseif ($score > SCORE_THRESHOLD_WEAK) {
        $label = "Weak or implicit logical form";
    } else {
        $label = "No visible logical commitment";
    }

    $notes = sprintf(
        "Verbs: %d · Preposition-based relations: %d · Quantifiers: %d · Modals: %d · Negations: %d",
        $stats['verbs'],
        $stats['preps'],
        $stats['quant'],
        $stats['modals'],
        $stats['negs']
    );

    return [
        'score' => $score,
        'label' => $label,
        'notes' => $notes,
    ];
}

// ============================================================================
// KANT CI (CATEGORICAL IMPERATIVE) SCORING
// ============================================================================

/**
 * Compute Kantian Categorical Imperative alignment score.
 *
 * Heuristic: identifies words suggesting instrumental harm vs. protective respect.
 * CI principle: "Act only according to that maxim whereby you can at the same time
 *               will that it should become a universal law."
 *
 * KNUTH: Wordlists defined in config.php to avoid duplication.
 *
 * @param array $tokens Classified tokens
 * @return array ['score' => float, 'label' => string, 'notes' => string]
 */
function compute_kant(array $tokens): array {
    if (count($tokens) === 0) {
        return [
            'score' => 0.0,
            'label' => 'Unknown',
            'notes' => 'No content for moral assessment.',
        ];
    }

    static $bad_words = null;
    static $good_words = null;
    static $person_words = null;

    if ($bad_words === null) {
        $bad_words = array_fill_keys(KANT_BAD_WORDS, true);
        $good_words = array_fill_keys(KANT_GOOD_WORDS, true);
        $person_words = array_fill_keys(PERSON_WORDS, true);
    }

    $good = 0;
    $bad  = 0;
    $hits = [];
    $persons_present = false;
    $instrumental_bad_verb = false;

    foreach ($tokens as $t) {
        if (isset($good_words[$t['lower']])) {
            $good++;
            $hits[] = '+' . $t['clean'];
        } elseif (isset($bad_words[$t['lower']])) {
            $bad++;
            $hits[] = '-' . $t['clean'];
            $instrumental_bad_verb = true;
        }
        if (isset($person_words[$t['lower']])) {
            $persons_present = true;
        }
    }

    // Score: base 0.5, ±0.1 per good/bad word
    $score = KANT_BASE_SCORE + ($good - $bad) * KANT_WORD_WEIGHT;
    $score = max(0.0, min(1.0, $score)); // Clamp to [0, 1]

    if ($score >= SCORE_THRESHOLD_STRONG) {
        $label = "Likely CI-aligned (protective / respectful orientation)";
    } elseif ($score >= 0.6) {
        $label = "Weakly CI-aligned (mildly protective / neutral)";
    } elseif ($score >= 0.4) {
        $label = "Ambiguous / mixed in moral orientation";
    } elseif ($score > SCORE_THRESHOLD_WEAK) {
        $label = "Likely CI-violating (tendency toward instrumentalizing others)";
    } else {
        $label = "No explicit moral polarity detected";
    }

    $notes_parts = [];
    $notes_parts[] = sprintf("Good indicators: %d · Bad indicators: %d", $good, $bad);
    $notes_parts[] = $hits ? 'Polarity hits: ' . implode(', ', $hits) : 'No explicit moral polarity tokens found.';

    // Ends vs means hint
    if ($persons_present && $instrumental_bad_verb) {
        $notes_parts[] = "CI warning: persons appear in the clause combined with harmful/instrumental verbs; this suggests a risk of treating persons as mere means.";
    } elseif ($persons_present && $good > 0 && !$instrumental_bad_verb) {
        $notes_parts[] = "CI hint: persons appear together with protective/respectful language; this leans toward treating persons as ends in themselves.";
    }

    $notes_parts[] = "Heuristic: CI focuses on universalizability and treating persons as ends; this proxy only inspects local wording.";

    return [
        'score' => $score,
        'label' => $label,
        'notes' => implode(' · ', $notes_parts),
    ];
}

// ============================================================================
// AMBIGUITY / VAGUENESS SCORING
// ============================================================================

/**
 * Compute ambiguity/vagueness score.
 *
 * High score (→1.0) = clearer, more precise
 * Low score (→0.0) = vague, open-textured
 *
 * @param array $tokens Classified tokens
 * @return array ['score' => float, 'label' => string, 'notes' => string, 'hits' => array]
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

    static $vague_set = null;
    if ($vague_set === null) {
        $vague_set = array_fill_keys(
            array_map('mb_strtolower', VAGUE_WORDS),
            true
        );
    }

    $hits = [];
    foreach ($tokens as $t) {
        if (isset($vague_set[$t['lower']])) {
            $hits[] = $t['clean'] !== '' ? $t['clean'] : $t['lower'];
        }
    }

    $count = count($hits);
    // Formula: 1.0 - min(vague_count, MAX) × PENALTY
    $score = $count === 0
        ? 1.0
        : max(0.0, 1.0 - min($count, AMBIGUITY_MAX_PENALTY_TERMS) * AMBIGUITY_PENALTY);

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

// ============================================================================
// MODAL PROFILE
// ============================================================================

/**
 * Compute modal logic profile (obligation, permission, recommendation).
 *
 * @param array $tokens Classified tokens
 * @return array ['counts' => array, 'summary' => string]
 */
function compute_modal_profile(array $tokens): array {
    static $ob_set = null;
    static $perm_set = null;
    static $rec_set = null;

    if ($ob_set === null) {
        $ob_set   = array_fill_keys(MODALS_OBLIGATION, true);
        $perm_set = array_fill_keys(MODALS_PERMISSION, true);
        $rec_set  = array_fill_keys(MODALS_RECOMMENDATION, true);
    }

    $counts = [
        'obligation'     => 0,
        'permission'     => 0,
        'recommendation' => 0,
    ];

    foreach ($tokens as $t) {
        $w = $t['lower'];
        if (isset($ob_set[$w])) {
            $counts['obligation']++;
        } elseif (isset($perm_set[$w])) {
            $counts['permission']++;
        } elseif (isset($rec_set[$w])) {
            $counts['recommendation']++;
        }
    }

    $summary = sprintf(
        "Obligation: %d · Permission: %d · Recommendation: %d",
        $counts['obligation'],
        $counts['permission'],
        $counts['recommendation']
    );

    return [
        'counts'  => $counts,
        'summary' => $summary,
    ];
}

// ============================================================================
// AGENT-ACTION-PATIENT (AAP) EXTRACTION
// ============================================================================

/**
 * Extract Agent–Action–Patient structure heuristically.
 *
 * Splits clause at first verb:
 * - Before verb = Agent
 * - Verb = Action
 * - After verb = Patient
 *
 * @param array $tokens Classified tokens
 * @return array ['agentPhrase' => ?string, 'actionWord' => ?string, 'patientPhrase' => ?string]
 */
function extract_aap(array $tokens): array {
    if (!$tokens) {
        return [
            'agentPhrase'   => null,
            'actionWord'    => null,
            'patientPhrase' => null,
        ];
    }

    // Find first verb
    $verb_index = null;
    foreach ($tokens as $i => $t) {
        if ($t['tag'] === 'VERB') {
            $verb_index = $i;
            break;
        }
    }

    if ($verb_index === null) {
        return [
            'agentPhrase'   => null,
            'actionWord'    => null,
            'patientPhrase' => null,
        ];
    }

    $agent_tokens  = array_slice($tokens, 0, $verb_index);
    $patient_tokens = array_slice($tokens, $verb_index + 1);

    $clean_phrase = function (array $ts): ?string {
        $parts = [];
        foreach ($ts as $t) {
            if ($t['clean'] === '') continue;
            $parts[] = $t['clean'];
        }
        return $parts ? implode(' ', $parts) : null;
    };

    return [
        'agentPhrase'   => $clean_phrase($agent_tokens),
        'actionWord'    => $tokens[$verb_index]['lower'],
        'patientPhrase' => $clean_phrase($patient_tokens),
    ];
}

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

/**
 * Convert scores to binary ruliad bits.
 *
 * @param float $q QSG score
 * @param float $l Logic score
 * @param float $k Kant score
 * @return array ['q' => int, 'l' => int, 'k' => int]
 */
function scores_to_bits(float $q, float $l, float $k): array {
    return [
        'q' => ($q >= RULIAD_BIT_THRESHOLD) ? 1 : 0,
        'l' => ($l >= RULIAD_BIT_THRESHOLD) ? 1 : 0,
        'k' => ($k >= RULIAD_BIT_THRESHOLD) ? 1 : 0,
    ];
}

/**
 * Format score as percentage string.
 *
 * @param float|null $score Score in [0,1]
 * @return string Formatted score
 */
function format_score(?float $score): string {
    if ($score === null || !is_finite($score)) {
        return 'score: —';
    }
    return 'score: ' . (string)round($score * 100) . '/100';
}

// ============================================================================
// FIRST-ORDER LOGIC (FOL) BUILDER
// ============================================================================

/**
 * Build First-Order Logic skeleton from tokens.
 *
 * COMPLEXITY: O(n) where n = token count
 *
 * Constructs a rough FOL-like formula by:
 * 1. Splitting clause at prepositions
 * 2. Converting phrases to entity variables
 * 3. Creating relations from prepositions
 *
 * KNUTH: Uses centralized PREPOSITIONS constant to avoid duplication.
 *
 * @param array $tokens Classified tokens
 * @return array ['formula' => string, 'notes' => string]
 */
function build_fol(array $tokens): array {
    if (count($tokens) === 0) {
        return [
            'formula' => '—',
            'notes'   => 'No tokens to analyse.',
        ];
    }

    static $preps = null;
    if ($preps === null) {
        $preps = array_fill_keys(PREPOSITIONS, true);
    }

    // Collect raw text and negations
    $raw_text_parts = [];
    $negations = [];
    foreach ($tokens as $t) {
        if ($t['tag'] === 'NEG') {
            $negations[] = $t['lower'];
        }
        $raw_text_parts[] = $t['clean'] !== '' ? $t['clean'] : $t['lower'];
    }
    $raw_text = implode(' ', $raw_text_parts);

    // Check if clause has prepositions
    $has_prep = false;
    foreach ($tokens as $t) {
        if (isset($preps[$t['lower']])) {
            $has_prep = true;
            break;
        }
    }

    // No prepositions → treat entire clause as single predicate
    if (!$has_prep) {
        $note_neg = $negations ? ' Negations detected: ' . implode(', ', $negations) . '.' : '';
        return [
            'formula' => 'ClauseAsPredicate(c): "' . $raw_text . '"',
            'notes'   => 'No explicit prepositions detected – treating the entire clause as a single predicate.' . $note_neg,
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

    $entity_idx = 0;
    $last_ent   = null;

    $phrase_to_entity = function (array $phrase_tokens, int $index): string {
        $core = [];
        foreach ($phrase_tokens as $t) {
            // Skip determiners and conjunctions
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

    $count_segments = count($segments);
    for ($i = 0; $i < $count_segments; $i++) {
        $seg = $segments[$i];

        if ($seg['type'] === 'phrase') {
            $ent = $phrase_to_entity($seg['tokens'], $entity_idx++);
            $entities[] = $ent;
            if ($last_ent === null) {
                $last_ent = $ent;
            }
        } elseif ($seg['type'] === 'prep') {
            $prep_word = $seg['tokens'][0]['lower'];
            $next     = $segments[$i + 1] ?? null;
            if ($next && $next['type'] === 'phrase') {
                $ent = $phrase_to_entity($next['tokens'], $entity_idx++);
                $entities[] = $ent;
                $relation_name = ucfirst($prep_word);
                $relations[]  = $relation_name . '(' . $last_ent . ', ' . $ent . ')';
                $last_ent      = $ent;
                $i++; // Skip phrase we just used
            }
        }
    }

    $unique_entities = array_values(array_unique($entities));
    $var_list        = implode(', ', $unique_entities);
    $entity_preds    = [];
    foreach ($unique_entities as $e) {
        $entity_preds[] = 'E(' . $e . ')';
    }

    $all_preds = array_merge($entity_preds, $relations);
    $formula  = '∃ ' . $var_list . ' · ' . implode(' ∧ ', $all_preds);

    $notes = sprintf(
        "Derived %d entity symbol(s): %s · Relations (%d): %s",
        count($unique_entities),
        $var_list ?: 'none',
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

// ============================================================================
// TONE SUMMARY (Preposition Frequency)
// ============================================================================

/**
 * Build tone summary from preposition frequencies.
 *
 * WOLFRAM: This reveals the "wiring" pattern of the clause.
 * Different prepositions create different computational structures.
 *
 * @param array $tokens Classified tokens
 * @return string Summary string
 */
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

// ============================================================================
// CLAUSE REWRITING
// ============================================================================

/**
 * Rewrite clause to improve clarity.
 *
 * KNUTH: "Literate programming: say what you mean clearly."
 *
 * Currently implements one example rewrite for QSG-style legal clause.
 * Can be extended with more transformation rules.
 *
 * @param string $text Raw clause
 * @return string Rewritten clause
 */
function rewrite_clause(string $text): string {
    $norm = normalize_clause($text);
    if ($norm === '') {
        return '—';
    }

    $rewritten = $norm;

    // Example transformation: simplify QSG-style clause
    $pattern = '/for the claim of the treaty is with the protection of the lands by the council within this venue under the natural law/i';
    $replacement = 'the treaty claim provides for the protection of the lands by the council at this venue under natural law';
    $rewritten = preg_replace($pattern, $replacement, $rewritten);

    if ($rewritten === null) {
        throw new RuntimeException('Regex execution failed in rewrite_clause');
    }

    // Capitalize first letter
    $first = mb_substr($rewritten, 0, 1, 'UTF-8');
    $rest  = mb_substr($rewritten, 1, null, 'UTF-8');
    $rewritten = mb_strtoupper($first, 'UTF-8') . $rest;

    // Ensure ends with punctuation
    if (!preg_match('/[.!?]$/u', $rewritten)) {
        $rewritten .= '.';
    }

    return $rewritten;
}

// ============================================================================
// DIFF (LCS-based word diff)
// ============================================================================

/**
 * Tokenize clause for diff operation.
 *
 * @param string $text Clause text
 * @return array Array of word tokens
 */
function tokenize_for_diff(string $text): array {
    $norm = normalize_clause($text);
    if ($norm === '') {
        return [];
    }
    return explode(' ', $norm);
}

/**
 * Compute word-level diff using LCS (Longest Common Subsequence).
 *
 * KNUTH: Classic DP algorithm from TAOCP Vol 3.
 * COMPLEXITY: O(m×n) time, O(m×n) space
 * NOTE: Could be optimized to O(min(m,n)) space with Hirschberg's algorithm.
 *
 * Returns HTML with <del> for deletions and <ins> for insertions.
 *
 * @param string $original Original clause
 * @param string $rewritten Rewritten clause
 * @return string HTML diff
 */
function diff_clauses(string $original, string $rewritten): string {
    $orig_tokens = tokenize_for_diff($original);
    $new_tokens  = tokenize_for_diff($rewritten);

    $m = count($orig_tokens);
    $n = count($new_tokens);

    // DP table for LCS length
    $dp = array_fill(0, $m + 1, array_fill(0, $n + 1, 0));

    for ($i = 1; $i <= $m; $i++) {
        for ($j = 1; $j <= $n; $j++) {
            if ($orig_tokens[$i - 1] === $new_tokens[$j - 1]) {
                $dp[$i][$j] = $dp[$i - 1][$j - 1] + 1;
            } else {
                $dp[$i][$j] = max($dp[$i - 1][$j], $dp[$i][$j - 1]);
            }
        }
    }

    // Backtrack to construct diff
    $result = [];
    $i = $m;
    $j = $n;

    while ($i > 0 && $j > 0) {
        if ($orig_tokens[$i - 1] === $new_tokens[$j - 1]) {
            // Match: include unchanged
            array_unshift($result, htmlspecialchars($orig_tokens[$i - 1], ENT_QUOTES, 'UTF-8'));
            $i--;
            $j--;
        } elseif ($dp[$i - 1][$j] >= $dp[$i][$j - 1]) {
            // Deletion from original
            array_unshift(
                $result,
                '<del>' . htmlspecialchars($orig_tokens[$i - 1], ENT_QUOTES, 'UTF-8') . '</del>'
            );
            $i--;
        } else {
            // Insertion in rewritten
            array_unshift(
                $result,
                '<ins>' . htmlspecialchars($new_tokens[$j - 1], ENT_QUOTES, 'UTF-8') . '</ins>'
            );
            $j--;
        }
    }

    // Remaining deletions
    while ($i > 0) {
        array_unshift(
            $result,
            '<del>' . htmlspecialchars($orig_tokens[$i - 1], ENT_QUOTES, 'UTF-8') . '</del>'
        );
        $i--;
    }

    // Remaining insertions
    while ($j > 0) {
        array_unshift(
            $result,
            '<ins>' . htmlspecialchars($new_tokens[$j - 1], ENT_QUOTES, 'UTF-8') . '</ins>'
        );
        $j--;
    }

    return implode(' ', $result);
}

// ============================================================================
// RULIAD STATE EXPLANATION
// ============================================================================

/**
 * Explain ruliad bits with natural language.
 *
 * WOLFRAM: Each of the 2³ = 8 states represents a distinct computational
 * configuration in the QSG × Logic × Kant ruliad.
 *
 * @param array $bits ['q' => int, 'l' => int, 'k' => int]
 * @param array|null $labels Optional human-readable labels
 * @return string Natural language explanation
 */
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

    // Specific state interpretations
    if ($q && $l && $k) {
        $extra[] = "It occupies the highest triad state: well-formed, inferentially meaningful, and ethically protective.";
    } elseif ($q && $l && !$k) {
        $extra[] = "Form and inference are strong, but the wording does not clearly express an ethical commitment respecting persons as ends.";
    } elseif ($q && !$l && $k) {
        $extra[] = "The sentence reads clearly and is morally oriented, but the logical structure (quantifiers, conditionals) is weak or implicit.";
    } elseif (!$q && $l && $k) {
        $extra[] = "The moral and logical signals are present, but the surface sentence is noisy or structurally fragile.";
    }

    // Append detailed labels if provided
    if ($labels !== null) {
        $extra[] = sprintf(
            "QSG: %s. Logic: %s. Kant CI: %s.",
            $labels['qsgLabel'] ?? 'unknown',
            $labels['logicLabel'] ?? 'unknown',
            $labels['kantLabel'] ?? 'unknown'
        );
    }

    return $base . ' ' . implode(' ', $extra);
}
