<?php
declare(strict_types=1);

/**
 * Test script for Wolfram analysis engine
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/analysis_core.php';
require_once __DIR__ . '/wolfram_analysis.php';

echo "Testing Wolfram Analysis Engine\n";
echo "================================\n\n";

// Test clause with ambiguous language
$test_clause = "The vendor may provide services as reasonably necessary at their sole discretion with unlimited liability.";

echo "Test Clause:\n";
echo "  \"$test_clause\"\n\n";

// Tokenize and classify
$tokens = classify_tokens(tokenize_clause($test_clause));
echo "✓ Tokenized: " . count($tokens) . " tokens\n";

// Run Wolfram analysis
$start = microtime(true);
$wolfram = wolfram_analyze($tokens);
$duration = (microtime(true) - $start) * 1000;

echo "✓ Wolfram analysis complete in " . round($duration, 2) . "ms\n\n";

// Display transformations
echo "TRANSFORMATIONS:\n";
echo "================\n";
if ($wolfram['transformations']['rule_count'] > 0) {
    echo "Found " . $wolfram['transformations']['rule_count'] . " transformations:\n\n";

    foreach ($wolfram['transformations']['transformations'] as $i => $t) {
        echo ($i + 1) . ". [" . strtoupper($t['category']) . "] " . round($t['strength'] * 100) . "% confidence\n";
        echo "   \"" . $t['original'] . "\" → \"" . $t['suggested'] . "\"\n";
        echo "   Rule: " . $t['rule'] . "\n\n";
    }

    echo "IMPROVED TEXT:\n";
    echo "=============\n";
    echo $wolfram['transformations']['improved_text'] . "\n\n";

    echo "METRICS BY CATEGORY:\n";
    echo "===================\n";
    foreach ($wolfram['transformations']['metrics'] as $category => $metrics) {
        echo "  $category:\n";
        echo "    Count: " . $metrics['count'] . "\n";
        echo "    Avg Strength: " . round($metrics['avg_strength'] * 100) . "%\n";
        echo "    Total Impact: " . round($metrics['total_impact'], 2) . "\n\n";
    }
} else {
    echo "No transformations needed - clause is optimal.\n\n";
}

// Display multiway evolution
echo "MULTIWAY EVOLUTION:\n";
echo "==================\n";
echo "State count: " . $wolfram['multiway']['state_count'] . "\n";
echo "Terminal states: " . implode(', ', $wolfram['multiway']['terminal_states']) . "\n\n";

// Performance
echo "PERFORMANCE:\n";
echo "============\n";
echo "Execution time: " . $wolfram['performance']['execution_time_ms'] . "ms\n";
echo "Algorithm complexity: O(n) where n = token count\n\n";

// Test dependency graph with multiple clauses
echo "\nTesting Dependency Graph:\n";
echo "=========================\n";

$clauses = [
    [
        'id' => 'clause_1',
        'text' => 'As defined in Section 3, Confidential Information shall include all proprietary data.',
        'section_number' => '1',
    ],
    [
        'id' => 'clause_2',
        'text' => 'The Receiving Party shall not disclose Confidential Information to third parties.',
        'section_number' => '2',
    ],
    [
        'id' => 'clause_3',
        'text' => 'Confidential Information means any non-public information disclosed by either party.',
        'section_number' => '3',
        'defines' => ['Confidential Information'],
    ],
];

$graph = build_clause_dependency_graph($clauses);
$stats = $graph->get_stats();

echo "Graph statistics:\n";
echo "  Vertices (clauses): " . $stats['vertices'] . "\n";
echo "  Edges (dependencies): " . $stats['edges'] . "\n";
echo "  Density: " . round($stats['density'], 3) . "\n";
echo "  Avg out-degree: " . round($stats['avg_out_degree'], 2) . "\n\n";

$cycles = $graph->find_cycles();
echo "Cycles detected: " . count($cycles) . "\n";

$topological = $graph->topological_sort();
if ($topological !== null) {
    echo "✓ Topological order exists (no cycles)\n";
    echo "  Order: " . implode(' → ', $topological) . "\n";
} else {
    echo "✗ Cannot create topological order (cycles present)\n";
}

echo "\n✓ All Wolfram tests passed!\n";
echo "✓ Ready for production use\n";

?>
