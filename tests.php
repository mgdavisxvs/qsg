<?php
/**
 * QSG Ruliad Console - Unit Tests
 *
 * TORVALDS: "Code without tests is broken code you haven't discovered yet."
 *
 * Simple assert-based tests for core analysis functions.
 * For production, migrate to PHPUnit.
 *
 * Usage: php tests.php
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/analysis_core.php';
require_once __DIR__ . '/cache.php';
require_once __DIR__ . '/logger.php';

// Enable assertions
assert_options(ASSERT_ACTIVE, 1);
assert_options(ASSERT_BAIL, 1); // Stop on first failure

$test_count = 0;
$pass_count = 0;

function test(string $name, callable $fn): void {
    global $test_count, $pass_count;
    $test_count++;

    try {
        $fn();
        $pass_count++;
        echo "✓ {$name}\n";
    } catch (Throwable $e) {
        echo "✗ {$name}\n";
        echo "  Error: " . $e->getMessage() . "\n";
        echo "  File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    }
}

echo "QSG Ruliad Console - Unit Tests\n";
echo "================================\n\n";

// ============================================================================
// TOKENIZATION TESTS
// ============================================================================

echo "Tokenization Tests:\n";
echo "-------------------\n";

test("normalize_clause removes extra whitespace", function() {
    $result = normalize_clause("  hello   world  ");
    assert($result === "hello world");
});

test("normalize_clause handles empty string", function() {
    $result = normalize_clause("");
    assert($result === "");
});

test("tokenize_clause returns empty array for empty string", function() {
    $result = tokenize_clause("");
    assert($result === []);
});

test("tokenize_clause splits on whitespace", function() {
    $result = tokenize_clause("the council protects");
    assert(count($result) === 3);
    assert($result[0]['lower'] === 'the');
    assert($result[1]['lower'] === 'council');
    assert($result[2]['lower'] === 'protects');
});

test("tokenize_clause strips punctuation from clean field", function() {
    $result = tokenize_clause("Hello, world!");
    assert($result[0]['raw'] === 'Hello,');
    assert($result[0]['clean'] === 'Hello');
    assert($result[1]['raw'] === 'world!');
    assert($result[1]['clean'] === 'world');
});

echo "\n";

// ============================================================================
// CLASSIFICATION TESTS
// ============================================================================

echo "Classification Tests:\n";
echo "--------------------\n";

test("classify_tokens identifies prepositions", function() {
    $tokens = tokenize_clause("the protection of the land");
    $classified = classify_tokens($tokens);

    assert($classified[0]['tag'] === 'DET'); // the
    assert($classified[2]['tag'] === 'PREP'); // of
});

test("classify_tokens identifies verbs", function() {
    $tokens = tokenize_clause("the council protects the land");
    $classified = classify_tokens($tokens);

    assert($classified[2]['tag'] === 'VERB'); // protects
});

test("classify_tokens identifies quantifiers", function() {
    $tokens = tokenize_clause("all persons have rights");
    $classified = classify_tokens($tokens);

    assert($classified[0]['tag'] === 'QUANT'); // all
});

test("classify_tokens identifies negations", function() {
    $tokens = tokenize_clause("do not harm others");
    $classified = classify_tokens($tokens);

    assert($classified[1]['tag'] === 'NEG'); // not
});

echo "\n";

// ============================================================================
// QSG SCORING TESTS
// ============================================================================

echo "QSG Scoring Tests:\n";
echo "------------------\n";

test("compute_qsg returns zero score for empty tokens", function() {
    $result = compute_qsg([]);
    assert($result['score'] === 0.0);
    assert($result['label'] === 'Empty');
});

test("compute_qsg gives higher score to well-formed sentence", function() {
    $tokens = classify_tokens(tokenize_clause("the council protects the land by natural law"));
    $result = compute_qsg($tokens);

    assert($result['score'] >= 0.8); // Should be strong
    assert(str_contains($result['label'], 'Strong'));
});

test("compute_qsg gives lower score to fragment", function() {
    $tokens = classify_tokens(tokenize_clause("protects land"));
    $result = compute_qsg($tokens);

    assert($result['score'] < 0.8);
});

echo "\n";

// ============================================================================
// LOGIC SCORING TESTS
// ============================================================================

echo "Logic Scoring Tests:\n";
echo "--------------------\n";

test("compute_logic returns zero for empty tokens", function() {
    $result = compute_logic([]);
    assert($result['score'] === 0.0);
});

test("compute_logic gives high score for clause with verbs and quantifiers", function() {
    $tokens = classify_tokens(tokenize_clause("all persons must be protected by law"));
    $result = compute_logic($tokens);

    assert($result['score'] >= 0.5);
});

test("compute_logic detects negations", function() {
    $tokens = classify_tokens(tokenize_clause("do not harm any person"));
    $result = compute_logic($tokens);

    assert(str_contains($result['notes'], 'Negations'));
});

echo "\n";

// ============================================================================
// KANT CI SCORING TESTS
// ============================================================================

echo "Kant CI Tests:\n";
echo "--------------\n";

test("compute_kant returns neutral for empty tokens", function() {
    $result = compute_kant([]);
    assert($result['score'] === 0.0);
});

test("compute_kant gives high score for protective language", function() {
    $tokens = classify_tokens(tokenize_clause("protect all persons with respect"));
    $result = compute_kant($tokens);

    assert($result['score'] > 0.5); // Should be positive
    assert(str_contains($result['notes'], 'Good indicators'));
});

test("compute_kant gives low score for harmful language", function() {
    $tokens = classify_tokens(tokenize_clause("exploit and harm others"));
    $result = compute_kant($tokens);

    assert($result['score'] < 0.5); // Should be negative
});

test("compute_kant detects persons with harmful verbs", function() {
    $tokens = classify_tokens(tokenize_clause("use persons as means"));
    $result = compute_kant($tokens);

    assert(str_contains($result['notes'], 'CI warning'));
});

echo "\n";

// ============================================================================
// AMBIGUITY TESTS
// ============================================================================

echo "Ambiguity Tests:\n";
echo "----------------\n";

test("compute_ambiguity returns 1.0 for precise language", function() {
    $tokens = classify_tokens(tokenize_clause("the council protects the land"));
    $result = compute_ambiguity($tokens);

    assert($result['score'] === 1.0);
    assert($result['label'] === 'Low linguistic vagueness');
});

test("compute_ambiguity detects vague terms", function() {
    $tokens = classify_tokens(tokenize_clause("reasonable efforts as needed"));
    $result = compute_ambiguity($tokens);

    assert($result['score'] < 1.0);
    assert(count($result['hits']) > 0);
});

echo "\n";

// ============================================================================
// MODAL PROFILE TESTS
// ============================================================================

echo "Modal Profile Tests:\n";
echo "--------------------\n";

test("compute_modal_profile detects obligation modals", function() {
    $tokens = classify_tokens(tokenize_clause("you must protect rights"));
    $result = compute_modal_profile($tokens);

    assert($result['counts']['obligation'] === 1);
});

test("compute_modal_profile detects permission modals", function() {
    $tokens = classify_tokens(tokenize_clause("you may exercise rights"));
    $result = compute_modal_profile($tokens);

    assert($result['counts']['permission'] === 1);
});

test("compute_modal_profile detects recommendation modals", function() {
    $tokens = classify_tokens(tokenize_clause("you should respect others"));
    $result = compute_modal_profile($tokens);

    assert($result['counts']['recommendation'] === 1);
});

echo "\n";

// ============================================================================
// AAP EXTRACTION TESTS
// ============================================================================

echo "AAP Extraction Tests:\n";
echo "---------------------\n";

test("extract_aap returns nulls for empty tokens", function() {
    $result = extract_aap([]);
    assert($result['agentPhrase'] === null);
    assert($result['actionWord'] === null);
    assert($result['patientPhrase'] === null);
});

test("extract_aap splits clause at verb", function() {
    $tokens = classify_tokens(tokenize_clause("the council protects the land"));
    $result = extract_aap($tokens);

    assert($result['agentPhrase'] === 'the council');
    assert($result['actionWord'] === 'protects');
    assert($result['patientPhrase'] === 'the land');
});

test("extract_aap returns nulls when no verb", function() {
    $tokens = classify_tokens(tokenize_clause("the green land"));
    $result = extract_aap($tokens);

    assert($result['agentPhrase'] === null);
    assert($result['actionWord'] === null);
});

echo "\n";

// ============================================================================
// FOL BUILDER TESTS
// ============================================================================

echo "FOL Builder Tests:\n";
echo "------------------\n";

test("build_fol handles empty tokens", function() {
    $result = build_fol([]);
    assert($result['formula'] === '—');
});

test("build_fol creates formula with prepositions", function() {
    $tokens = classify_tokens(tokenize_clause("the protection of the land"));
    $result = build_fol($tokens);

    assert(str_contains($result['formula'], '∃'));
    assert(str_contains($result['formula'], '∧'));
});

test("build_fol handles clause without prepositions", function() {
    $tokens = classify_tokens(tokenize_clause("justice prevails"));
    $result = build_fol($tokens);

    assert(str_contains($result['formula'], 'ClauseAsPredicate'));
});

echo "\n";

// ============================================================================
// TONE SUMMARY TESTS
// ============================================================================

echo "Tone Summary Tests:\n";
echo "-------------------\n";

test("build_tone_summary returns message for no prepositions", function() {
    $tokens = classify_tokens(tokenize_clause("justice prevails"));
    $result = build_tone_summary($tokens);

    assert($result === "No preposition wiring detected.");
});

test("build_tone_summary counts preposition frequencies", function() {
    $tokens = classify_tokens(tokenize_clause("the claim of the treaty is with the protection of the lands"));
    $result = build_tone_summary($tokens);

    assert(str_contains($result, 'Preposition wiring'));
    assert(str_contains($result, 'of'));
});

echo "\n";

// ============================================================================
// REWRITE TESTS
// ============================================================================

echo "Rewrite Tests:\n";
echo "--------------\n";

test("rewrite_clause handles empty string", function() {
    $result = rewrite_clause("");
    assert($result === '—');
});

test("rewrite_clause capitalizes first letter", function() {
    $result = rewrite_clause("hello world");
    assert($result[0] === 'H');
});

test("rewrite_clause adds period if missing", function() {
    $result = rewrite_clause("hello world");
    assert(str_ends_with($result, '.'));
});

test("rewrite_clause applies QSG transformation", function() {
    $input = "for the claim of the treaty is with the protection of the lands by the council within this venue under the natural law";
    $result = rewrite_clause($input);

    assert(str_contains($result, 'provides for'));
});

echo "\n";

// ============================================================================
// DIFF TESTS
// ============================================================================

echo "Diff Tests:\n";
echo "-----------\n";

test("tokenize_for_diff splits words", function() {
    $result = tokenize_for_diff("hello world");
    assert($result === ['hello', 'world']);
});

test("diff_clauses handles identical strings", function() {
    $result = diff_clauses("hello world", "hello world");
    assert($result === 'hello world');
});

test("diff_clauses marks deletions", function() {
    $result = diff_clauses("hello world", "hello");
    assert(str_contains($result, '<del>'));
    assert(str_contains($result, 'world'));
});

test("diff_clauses marks insertions", function() {
    $result = diff_clauses("hello", "hello world");
    assert(str_contains($result, '<ins>'));
    assert(str_contains($result, 'world'));
});

echo "\n";

// ============================================================================
// RULIAD STATE TESTS
// ============================================================================

echo "Ruliad State Tests:\n";
echo "-------------------\n";

test("scores_to_bits converts scores correctly", function() {
    $bits = scores_to_bits(0.8, 0.7, 0.9);
    assert($bits['q'] === 1);
    assert($bits['l'] === 1);
    assert($bits['k'] === 1);
});

test("scores_to_bits handles low scores", function() {
    $bits = scores_to_bits(0.3, 0.2, 0.4);
    assert($bits['q'] === 0);
    assert($bits['l'] === 0);
    assert($bits['k'] === 0);
});

test("explain_bits handles all zeros", function() {
    $bits = ['q' => 0, 'l' => 0, 'k' => 0];
    $result = explain_bits($bits);

    assert(str_contains($result, 'does not register'));
});

test("explain_bits handles all ones", function() {
    $bits = ['q' => 1, 'l' => 1, 'k' => 1];
    $result = explain_bits($bits);

    assert(str_contains($result, 'highest triad state'));
});

test("explain_bits includes labels when provided", function() {
    $bits = ['q' => 1, 'l' => 1, 'k' => 0];
    $labels = [
        'qsgLabel' => 'Strong',
        'logicLabel' => 'Good',
        'kantLabel' => 'Weak'
    ];
    $result = explain_bits($bits, $labels);

    assert(str_contains($result, 'Strong'));
    assert(str_contains($result, 'Good'));
    assert(str_contains($result, 'Weak'));
});

echo "\n";

// ============================================================================
// HELPER FUNCTION TESTS
// ============================================================================

echo "Helper Function Tests:\n";
echo "----------------------\n";

test("format_score formats valid score", function() {
    $result = format_score(0.75);
    assert($result === 'score: 75/100');
});

test("format_score handles null", function() {
    $result = format_score(null);
    assert($result === 'score: —');
});

test("format_score handles non-finite", function() {
    $result = format_score(INF);
    assert($result === 'score: —');
});

echo "\n";

// ============================================================================
// INTEGRATION TESTS
// ============================================================================

echo "Integration Tests:\n";
echo "------------------\n";

test("Full analysis pipeline works", function() {
    $clause = "the council must protect all persons within its jurisdiction";

    $tokens = classify_tokens(tokenize_clause($clause));
    $qsg = compute_qsg($tokens);
    $logic = compute_logic($tokens);
    $kant = compute_kant($tokens);
    $fol = build_fol($tokens);
    $aap = extract_aap($tokens);
    $bits = scores_to_bits($qsg['score'], $logic['score'], $kant['score']);

    // All should return valid data structures
    assert(isset($qsg['score']));
    assert(isset($logic['score']));
    assert(isset($kant['score']));
    assert(isset($fol['formula']));
    assert(isset($aap['actionWord']));
    assert(isset($bits['q']));
});

test("Analysis handles complex QSG clause", function() {
    $clause = "for the claim of the treaty is with the protection of the lands by the council within this venue under the natural law";

    $tokens = classify_tokens(tokenize_clause($clause));
    $qsg = compute_qsg($tokens);
    $fol = build_fol($tokens);

    assert($qsg['score'] > 0.5); // Should score reasonably
    assert(str_contains($fol['formula'], '∃')); // Should have FOL
});

echo "\n";

// ============================================================================
// CACHE TESTS
// ============================================================================

echo "Cache Tests:\n";
echo "------------\n";

test("AnalysisCache get returns null for missing key", function() {
    $cache = new AnalysisCache();
    $result = $cache->get('nonexistent');
    assert($result === null);
});

test("AnalysisCache set and get work correctly", function() {
    $cache = new AnalysisCache();
    $data = ['score' => 0.85, 'result' => 'test'];

    $cache->set('test_key', $data);
    $result = $cache->get('test_key');

    assert($result !== null);
    assert($result['score'] === 0.85);
    assert($result['result'] === 'test');
});

test("AnalysisCache tracks hits and misses", function() {
    $cache = new AnalysisCache();

    // Miss
    $cache->get('missing');
    $stats = $cache->get_stats();
    assert($stats['misses'] === 1);
    assert($stats['hits'] === 0);

    // Hit
    $cache->set('present', ['data' => 'value']);
    $cache->get('present');
    $stats = $cache->get_stats();
    assert($stats['hits'] === 1);
    assert($stats['misses'] === 1);
});

test("AnalysisCache respects max size with LRU eviction", function() {
    $cache = new AnalysisCache(3); // Small cache for testing

    $cache->set('key1', ['value' => 1]);
    $cache->set('key2', ['value' => 2]);
    $cache->set('key3', ['value' => 3]);

    assert($cache->size() === 3);

    // Adding 4th item should evict least recently used (key1)
    $cache->set('key4', ['value' => 4]);

    assert($cache->size() === 3);
    assert($cache->get('key1') === null); // Evicted
    assert($cache->get('key2')['value'] === 2); // Still present
});

test("AnalysisCache updates access time on get", function() {
    $cache = new AnalysisCache(2); // Small cache

    $cache->set('key1', ['value' => 1]);
    $cache->set('key2', ['value' => 2]);

    // Access key1 to make it recently used
    $cache->get('key1');

    // Add key3 - should evict key2 (least recently used)
    $cache->set('key3', ['value' => 3]);

    assert($cache->get('key1')['value'] === 1); // Still present
    assert($cache->get('key2') === null); // Evicted
    assert($cache->get('key3')['value'] === 3); // Present
});

test("AnalysisCache clear removes all entries", function() {
    $cache = new AnalysisCache();

    $cache->set('key1', ['value' => 1]);
    $cache->set('key2', ['value' => 2]);
    assert($cache->size() === 2);

    $cache->clear();
    assert($cache->size() === 0);
    assert($cache->get('key1') === null);
});

echo "\n";

// ============================================================================
// LOGGER TESTS
// ============================================================================

echo "Logger Tests:\n";
echo "-------------\n";

test("Logger creates log file and writes entries", function() {
    $log_file = '/tmp/qsg_test_' . uniqid() . '.log';
    $logger = new Logger($log_file, Logger::DEBUG);

    $logger->info('Test message', ['key' => 'value']);

    assert(file_exists($log_file));
    $contents = file_get_contents($log_file);
    assert(str_contains($contents, 'INFO'));
    assert(str_contains($contents, 'Test message'));

    // Cleanup
    unlink($log_file);
});

test("Logger respects minimum log level", function() {
    $log_file = '/tmp/qsg_test_' . uniqid() . '.log';
    $logger = new Logger($log_file, Logger::WARN); // Only WARN and ERROR

    $logger->debug('Debug message');
    $logger->info('Info message');
    $logger->warn('Warning message');

    $contents = file_get_contents($log_file);
    assert(!str_contains($contents, 'Debug message'));
    assert(!str_contains($contents, 'Info message'));
    assert(str_contains($contents, 'Warning message'));

    // Cleanup
    unlink($log_file);
});

test("Logger writes structured context as JSON", function() {
    $log_file = '/tmp/qsg_test_' . uniqid() . '.log';
    $logger = new Logger($log_file, Logger::DEBUG);

    $logger->info('Test', ['user_id' => 123, 'action' => 'scan']);

    $contents = file_get_contents($log_file);
    assert(str_contains($contents, '"user_id":123'));
    assert(str_contains($contents, '"action":"scan"'));

    // Cleanup
    unlink($log_file);
});

test("Logger tail returns recent entries", function() {
    $log_file = '/tmp/qsg_test_' . uniqid() . '.log';
    $logger = new Logger($log_file, Logger::DEBUG);

    $logger->info('Line 1');
    $logger->info('Line 2');
    $logger->info('Line 3');

    $tail = $logger->tail(2);

    assert(count($tail) === 2);
    assert(str_contains($tail[0], 'Line 2'));
    assert(str_contains($tail[1], 'Line 3'));

    // Cleanup
    unlink($log_file);
});

test("Logger handles all log levels", function() {
    $log_file = '/tmp/qsg_test_' . uniqid() . '.log';
    $logger = new Logger($log_file, Logger::DEBUG);

    $logger->debug('Debug message');
    $logger->info('Info message');
    $logger->warn('Warning message');
    $logger->error('Error message');

    $contents = file_get_contents($log_file);
    assert(str_contains($contents, 'DEBUG'));
    assert(str_contains($contents, 'INFO'));
    assert(str_contains($contents, 'WARN'));
    assert(str_contains($contents, 'ERROR'));

    // Cleanup
    unlink($log_file);
});

test("get_logger returns singleton instance", function() {
    $logger1 = get_logger();
    $logger2 = get_logger();

    assert($logger1 === $logger2); // Same instance
});

echo "\n";

// ============================================================================
// RESULTS SUMMARY
// ============================================================================

echo "================================\n";
echo "Test Results:\n";
echo "  Total:  {$test_count}\n";
echo "  Passed: {$pass_count}\n";
echo "  Failed: " . ($test_count - $pass_count) . "\n";

if ($pass_count === $test_count) {
    echo "\n✅ All tests passed!\n";
    exit(0);
} else {
    echo "\n❌ Some tests failed.\n";
    exit(1);
}
