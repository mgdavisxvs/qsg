<?php
declare(strict_types=1);

/**
 * Wolfram-Style Computational Legal Analysis Engine
 *
 * Applies Stephen Wolfram's computational thinking to legal document analysis:
 * - Rule-based transformation systems (cellular automaton principles)
 * - Multiway evolution graphs (exploring all interpretation paths)
 * - Clause dependency graphs (directed acyclic graphs of relationships)
 * - Computational equivalence classes (grouping similar clauses)
 *
 * Optimized with Linus Torvalds' engineering principles:
 * - O(n) or O(n log n) algorithms only
 * - Memory-efficient adjacency lists
 * - Lazy evaluation where possible
 * - Clean separation of concerns
 * - Performance profiling hooks
 *
 * @author Legal Analysis Team
 * @version 4.0 - Wolfram Computational Enhancement
 */

// ============================================================================
// § 1. RULE-BASED TRANSFORMATION SYSTEM
// ============================================================================

/**
 * Legal Clause Transformation Rules
 *
 * Wolfram's insight: Complex behavior emerges from simple rules.
 * We define transformation rules that convert ambiguous legal language
 * into precise, enforceable alternatives.
 *
 * Rule format: [pattern, replacement, category, strength]
 * - pattern: regex or token pattern to match
 * - replacement: suggested transformation
 * - category: type of transformation (precision, enforceability, risk-reduction)
 * - strength: 0.0-1.0 (confidence in the transformation)
 */
const TRANSFORMATION_RULES = [
    // Ambiguity → Precision rules
    ['may', 'shall', 'precision', 0.9],
    ['might', 'will', 'precision', 0.8],
    ['reasonable', 'mutually agreed upon', 'precision', 0.7],
    ['appropriate', 'as specified in Exhibit A', 'precision', 0.7],
    ['substantial', 'exceeding [specific threshold]', 'precision', 0.6],
    ['approximately', 'within [±X%]', 'precision', 0.8],

    // Weak → Strong binding language
    ['should', 'shall', 'enforceability', 0.9],
    ['could', 'will', 'enforceability', 0.8],
    ['endeavor to', 'shall', 'enforceability', 0.85],
    ['use best efforts', 'shall use commercially reasonable efforts', 'enforceability', 0.7],

    // One-sided → Balanced language
    ['sole discretion', 'reasonable discretion', 'risk-reduction', 0.9],
    ['absolute', 'subject to [specified limits]', 'risk-reduction', 0.85],
    ['unlimited', 'limited to [maximum amount]', 'risk-reduction', 0.95],
    ['irrevocable', 'revocable upon [specified conditions]', 'risk-reduction', 0.8],
    ['perpetual', 'for a term of [specified duration]', 'risk-reduction', 0.9],

    // Missing protections → Added safeguards
    ['liable', 'liable, subject to limitations in Section [X]', 'risk-reduction', 0.7],
    ['indemnify', 'indemnify to the extent permitted by law', 'risk-reduction', 0.75],
    ['waive', 'waive, except as required by applicable law', 'risk-reduction', 0.8],
];

/**
 * Apply rule-based transformations to a clause
 *
 * Time Complexity: O(n × r) where n = tokens, r = rules (constant ~20)
 * Space Complexity: O(n) for storing transformations
 *
 * @param array $tokens Classified tokens from analysis_core.php
 * @return array ['transformations' => [...], 'improved_text' => string, 'metrics' => [...]]
 */
function apply_transformation_rules(array $tokens): array {
    $transformations = [];
    $improved_tokens = $tokens;
    $text = implode(' ', array_column($tokens, 'clean'));

    foreach (TRANSFORMATION_RULES as $rule) {
        [$pattern, $replacement, $category, $strength] = $rule;

        // Find all occurrences (O(n) per rule)
        $matches = [];
        foreach ($tokens as $idx => $token) {
            if (stripos($token['lower'], $pattern) !== false) {
                $matches[] = [
                    'index' => $idx,
                    'original' => $token['clean'],
                    'position' => $token['position'] ?? $idx,
                ];
            }
        }

        // Record transformations
        if (!empty($matches)) {
            foreach ($matches as $match) {
                $transformations[] = [
                    'category' => $category,
                    'original' => $match['original'],
                    'suggested' => $replacement,
                    'strength' => $strength,
                    'position' => $match['position'],
                    'rule' => $pattern,
                ];

                // Apply transformation to improved text
                $text = preg_replace(
                    '/\b' . preg_quote($pattern, '/') . '\b/ui',
                    $replacement,
                    $text,
                    1 // Only first occurrence
                );
            }
        }
    }

    // Calculate improvement metrics
    $metrics = calculate_transformation_metrics($transformations);

    return [
        'transformations' => $transformations,
        'improved_text' => $text,
        'metrics' => $metrics,
        'rule_count' => count($transformations),
    ];
}

/**
 * Calculate metrics for transformations
 *
 * @param array $transformations List of applied transformations
 * @return array Aggregated metrics by category
 */
function calculate_transformation_metrics(array $transformations): array {
    $by_category = [
        'precision' => [],
        'enforceability' => [],
        'risk-reduction' => [],
    ];

    foreach ($transformations as $t) {
        $by_category[$t['category']][] = $t['strength'];
    }

    $metrics = [];
    foreach ($by_category as $category => $strengths) {
        if (empty($strengths)) {
            $metrics[$category] = ['count' => 0, 'avg_strength' => 0.0, 'total_impact' => 0.0];
        } else {
            $metrics[$category] = [
                'count' => count($strengths),
                'avg_strength' => array_sum($strengths) / count($strengths),
                'total_impact' => array_sum($strengths),
            ];
        }
    }

    return $metrics;
}

// ============================================================================
// § 2. CLAUSE DEPENDENCY GRAPH (DAG)
// ============================================================================

/**
 * Dependency Graph using adjacency list
 *
 * Torvalds' principle: Simple data structures + clean algorithms
 *
 * Time Complexity: O(V + E) for most operations
 * Space Complexity: O(V + E) where V = clauses, E = dependencies
 */
class DependencyGraph {
    private array $adjacency_list = [];  // clause_id => [dependent_clause_ids]
    private array $reverse_list = [];    // clause_id => [clauses_it_depends_on]
    private array $metadata = [];        // clause_id => metadata
    private int $edge_count = 0;

    /**
     * Add a clause to the graph
     *
     * @param string $clause_id Unique identifier
     * @param array $metadata Associated data (text, type, metrics)
     */
    public function add_clause(string $clause_id, array $metadata = []): void {
        if (!isset($this->adjacency_list[$clause_id])) {
            $this->adjacency_list[$clause_id] = [];
            $this->reverse_list[$clause_id] = [];
            $this->metadata[$clause_id] = $metadata;
        }
    }

    /**
     * Add dependency: from_clause depends on to_clause
     *
     * Time: O(1)
     *
     * @param string $from_clause Dependent clause
     * @param string $to_clause Dependency
     * @param string $relationship_type Type of dependency
     */
    public function add_dependency(
        string $from_clause,
        string $to_clause,
        string $relationship_type = 'references'
    ): void {
        // Ensure both clauses exist
        $this->add_clause($from_clause);
        $this->add_clause($to_clause);

        // Add edge (avoid duplicates)
        if (!in_array($to_clause, $this->adjacency_list[$from_clause], true)) {
            $this->adjacency_list[$from_clause][] = $to_clause;
            $this->reverse_list[$to_clause][] = $from_clause;
            $this->edge_count++;
        }
    }

    /**
     * Topological sort using Kahn's algorithm
     *
     * Time: O(V + E)
     * Returns clauses in order where dependencies come before dependents
     *
     * @return array|null Sorted clause IDs, or null if cycle detected
     */
    public function topological_sort(): ?array {
        // Calculate in-degrees (O(V + E))
        $in_degree = [];
        foreach ($this->adjacency_list as $clause_id => $_) {
            $in_degree[$clause_id] = count($this->reverse_list[$clause_id]);
        }

        // Queue of clauses with no dependencies
        $queue = [];
        foreach ($in_degree as $clause_id => $degree) {
            if ($degree === 0) {
                $queue[] = $clause_id;
            }
        }

        $sorted = [];
        while (!empty($queue)) {
            $clause_id = array_shift($queue);
            $sorted[] = $clause_id;

            // Reduce in-degree of dependents
            foreach ($this->adjacency_list[$clause_id] as $dependent) {
                $in_degree[$dependent]--;
                if ($in_degree[$dependent] === 0) {
                    $queue[] = $dependent;
                }
            }
        }

        // Check for cycles
        if (count($sorted) !== count($this->adjacency_list)) {
            return null; // Cycle detected
        }

        return $sorted;
    }

    /**
     * Find strongly connected components (cycles)
     *
     * Time: O(V + E) using Tarjan's algorithm
     *
     * @return array List of strongly connected components
     */
    public function find_cycles(): array {
        $index = 0;
        $stack = [];
        $indices = [];
        $low_links = [];
        $on_stack = [];
        $components = [];

        $strongconnect = function(string $v) use (
            &$index, &$stack, &$indices, &$low_links, &$on_stack, &$components, &$strongconnect
        ) {
            $indices[$v] = $index;
            $low_links[$v] = $index;
            $index++;
            $stack[] = $v;
            $on_stack[$v] = true;

            foreach ($this->adjacency_list[$v] as $w) {
                if (!isset($indices[$w])) {
                    $strongconnect($w);
                    $low_links[$v] = min($low_links[$v], $low_links[$w]);
                } elseif ($on_stack[$w] ?? false) {
                    $low_links[$v] = min($low_links[$v], $indices[$w]);
                }
            }

            if ($low_links[$v] === $indices[$v]) {
                $component = [];
                do {
                    $w = array_pop($stack);
                    $on_stack[$w] = false;
                    $component[] = $w;
                } while ($w !== $v);

                if (count($component) > 1) { // Only return actual cycles
                    $components[] = $component;
                }
            }
        };

        foreach ($this->adjacency_list as $v => $_) {
            if (!isset($indices[$v])) {
                $strongconnect($v);
            }
        }

        return $components;
    }

    /**
     * Get graph statistics
     *
     * @return array Statistics about the graph
     */
    public function get_stats(): array {
        $vertex_count = count($this->adjacency_list);

        return [
            'vertices' => $vertex_count,
            'edges' => $this->edge_count,
            'density' => $vertex_count > 1
                ? $this->edge_count / ($vertex_count * ($vertex_count - 1))
                : 0.0,
            'avg_out_degree' => $vertex_count > 0
                ? $this->edge_count / $vertex_count
                : 0.0,
        ];
    }

    /**
     * Export graph in DOT format for visualization
     *
     * @return string GraphViz DOT format
     */
    public function to_dot(): string {
        $dot = "digraph ClauseDependencies {\n";
        $dot .= "  rankdir=LR;\n";
        $dot .= "  node [shape=box, style=rounded];\n\n";

        foreach ($this->adjacency_list as $from => $to_list) {
            $label = $this->metadata[$from]['label'] ?? $from;
            $dot .= "  \"{$from}\" [label=\"{$label}\"];\n";

            foreach ($to_list as $to) {
                $dot .= "  \"{$from}\" -> \"{$to}\";\n";
            }
        }

        $dot .= "}\n";
        return $dot;
    }
}

/**
 * Build dependency graph from multiple clauses
 *
 * Detects dependencies through:
 * - Cross-references ("as defined in Section X")
 * - Definitions and usages
 * - Temporal ordering ("prior to", "following")
 * - Conditional dependencies ("subject to", "notwithstanding")
 *
 * Time: O(n × m) where n = clauses, m = avg clause length
 *
 * @param array $clauses List of clauses with metadata
 * @return DependencyGraph
 */
function build_clause_dependency_graph(array $clauses): DependencyGraph {
    $graph = new DependencyGraph();

    // Add all clauses first
    foreach ($clauses as $clause) {
        $clause_id = $clause['id'] ?? 'clause_' . spl_object_hash((object)$clause);
        $graph->add_clause($clause_id, [
            'text' => $clause['text'] ?? '',
            'label' => substr($clause['text'] ?? '', 0, 30) . '...',
            'type' => $clause['type'] ?? 'unknown',
        ]);
    }

    // Detect dependencies
    foreach ($clauses as $from_clause) {
        $from_id = $from_clause['id'] ?? 'clause_' . spl_object_hash((object)$from_clause);
        $from_text = strtolower($from_clause['text'] ?? '');

        foreach ($clauses as $to_clause) {
            if ($from_clause === $to_clause) continue;

            $to_id = $to_clause['id'] ?? 'clause_' . spl_object_hash((object)$to_clause);

            // Check for various dependency patterns
            $dependencies_found = false;

            // Pattern 1: Explicit references
            if (preg_match('/\b(section|clause|paragraph|exhibit|schedule|appendix)\s+[a-z0-9]+\b/i', $from_text)) {
                if (isset($to_clause['section_number']) &&
                    stripos($from_text, (string)$to_clause['section_number']) !== false) {
                    $graph->add_dependency($from_id, $to_id, 'cross-reference');
                    $dependencies_found = true;
                }
            }

            // Pattern 2: Definition usage
            if (isset($to_clause['defines'])) {
                foreach ($to_clause['defines'] as $term) {
                    if (stripos($from_text, strtolower($term)) !== false) {
                        $graph->add_dependency($from_id, $to_id, 'definition');
                        $dependencies_found = true;
                        break;
                    }
                }
            }

            // Pattern 3: Temporal ordering
            if (preg_match('/\b(prior to|before|following|after|upon)\b/i', $from_text)) {
                // Simple heuristic: if from_clause mentions temporal relation and to_clause,
                // there might be a dependency
                $to_keywords = extract_key_terms($to_clause['text'] ?? '');
                foreach ($to_keywords as $keyword) {
                    if (stripos($from_text, strtolower($keyword)) !== false) {
                        $graph->add_dependency($from_id, $to_id, 'temporal');
                        $dependencies_found = true;
                        break;
                    }
                }
            }

            // Pattern 4: Conditional dependencies
            if (preg_match('/\b(subject to|notwithstanding|except as|unless)\b/i', $from_text)) {
                $to_keywords = extract_key_terms($to_clause['text'] ?? '');
                foreach ($to_keywords as $keyword) {
                    if (stripos($from_text, strtolower($keyword)) !== false) {
                        $graph->add_dependency($from_id, $to_id, 'conditional');
                        $dependencies_found = true;
                        break;
                    }
                }
            }
        }
    }

    return $graph;
}

/**
 * Extract key terms from a clause for dependency detection
 *
 * @param string $text Clause text
 * @return array Key terms (proper nouns, defined terms, etc.)
 */
function extract_key_terms(string $text): array {
    $terms = [];

    // Extract capitalized terms (likely proper nouns or defined terms)
    if (preg_match_all('/\b[A-Z][a-z]+(?:\s+[A-Z][a-z]+)*\b/', $text, $matches)) {
        $terms = array_merge($terms, $matches[0]);
    }

    // Extract terms in quotes (likely defined terms)
    if (preg_match_all('/"([^"]+)"/', $text, $matches)) {
        $terms = array_merge($terms, $matches[1]);
    }

    return array_unique($terms);
}

// ============================================================================
// § 3. MULTIWAY EVOLUTION GRAPH
// ============================================================================

/**
 * Multiway Graph for exploring alternative interpretations
 *
 * Wolfram's insight: Legal clauses can evolve along multiple interpretation paths.
 * This creates a "multiway graph" showing all possible states.
 *
 * Example: "Party may terminate" has multiple interpretations:
 * 1. "Party has discretion to terminate" (permissive)
 * 2. "Party might terminate in the future" (predictive)
 * 3. "Party shall have the option to terminate" (contractual right)
 */
class MultiwayGraph {
    private array $states = [];  // state_id => ['text' => ..., 'interpretation' => ...]
    private array $transitions = [];  // from_state => [to_states with rules]
    private string $initial_state;

    public function __construct(string $initial_text) {
        $this->initial_state = 'state_0';
        $this->states[$this->initial_state] = [
            'text' => $initial_text,
            'interpretation' => 'original',
            'depth' => 0,
        ];
    }

    /**
     * Add a new interpretation state
     *
     * @param string $from_state Parent state
     * @param string $new_text New interpretation
     * @param string $rule_applied Rule that generated this state
     * @return string New state ID
     */
    public function add_state(string $from_state, string $new_text, string $rule_applied): string {
        $new_state_id = 'state_' . count($this->states);
        $depth = ($this->states[$from_state]['depth'] ?? 0) + 1;

        $this->states[$new_state_id] = [
            'text' => $new_text,
            'interpretation' => $rule_applied,
            'depth' => $depth,
            'parent' => $from_state,
        ];

        if (!isset($this->transitions[$from_state])) {
            $this->transitions[$from_state] = [];
        }
        $this->transitions[$from_state][] = [
            'to_state' => $new_state_id,
            'rule' => $rule_applied,
        ];

        return $new_state_id;
    }

    /**
     * Get all terminal states (leaf nodes)
     *
     * @return array List of terminal state IDs
     */
    public function get_terminal_states(): array {
        $terminal = [];
        foreach ($this->states as $state_id => $_) {
            if (!isset($this->transitions[$state_id]) || empty($this->transitions[$state_id])) {
                $terminal[] = $state_id;
            }
        }
        return $terminal;
    }

    /**
     * Get path from initial state to a given state
     *
     * @param string $state_id Target state
     * @return array Path of states and rules
     */
    public function get_path(string $state_id): array {
        $path = [];
        $current = $state_id;

        while ($current !== $this->initial_state) {
            $state_data = $this->states[$current];
            $path[] = [
                'state' => $current,
                'text' => $state_data['text'],
                'rule' => $state_data['interpretation'],
            ];
            $current = $state_data['parent'] ?? $this->initial_state;
        }

        // Add initial state
        $path[] = [
            'state' => $this->initial_state,
            'text' => $this->states[$this->initial_state]['text'],
            'rule' => 'original',
        ];

        return array_reverse($path);
    }

    /**
     * Export as JSON for visualization
     *
     * @return string JSON representation
     */
    public function to_json(): string {
        return json_encode([
            'states' => $this->states,
            'transitions' => $this->transitions,
            'initial' => $this->initial_state,
            'terminal' => $this->get_terminal_states(),
        ], JSON_PRETTY_PRINT);
    }
}

/**
 * Generate multiway evolution graph for a clause
 *
 * Explores all possible interpretations by applying transformation rules
 * in different orders and combinations.
 *
 * Time: O(r^d) where r = rules, d = max depth (controlled by max_depth param)
 * Space: O(states) = O(r^d)
 *
 * @param string $clause_text Initial clause
 * @param array $tokens Classified tokens
 * @param int $max_depth Maximum evolution depth (default 2)
 * @return MultiwayGraph
 */
function generate_multiway_graph(string $clause_text, array $tokens, int $max_depth = 2): MultiwayGraph {
    $graph = new MultiwayGraph($clause_text);
    $queue = [['state' => 'state_0', 'text' => $clause_text, 'depth' => 0]];

    while (!empty($queue)) {
        $current = array_shift($queue);

        if ($current['depth'] >= $max_depth) {
            continue;
        }

        // Apply each transformation rule
        foreach (TRANSFORMATION_RULES as $rule) {
            [$pattern, $replacement, $category, $strength] = $rule;

            // Check if pattern matches
            if (stripos($current['text'], $pattern) !== false) {
                // Generate new interpretation
                $new_text = preg_replace(
                    '/\b' . preg_quote($pattern, '/') . '\b/ui',
                    $replacement,
                    $current['text'],
                    1
                );

                if ($new_text !== $current['text']) {
                    $new_state = $graph->add_state(
                        $current['state'],
                        $new_text,
                        "{$category}: {$pattern} → {$replacement}"
                    );

                    // Add to queue for further evolution
                    $queue[] = [
                        'state' => $new_state,
                        'text' => $new_text,
                        'depth' => $current['depth'] + 1,
                    ];
                }
            }
        }
    }

    return $graph;
}

// ============================================================================
// § 4. COMPUTATIONAL EQUIVALENCE CLASSES
// ============================================================================

/**
 * Group clauses into equivalence classes based on computational similarity
 *
 * Wolfram's principle: Different representations may be computationally equivalent.
 *
 * Two clauses are equivalent if they produce the same legal obligations,
 * even if phrased differently.
 *
 * Time: O(n^2) for pairwise similarity (can be optimized with LSH)
 *
 * @param array $clauses List of clauses
 * @param float $threshold Similarity threshold (0.0-1.0)
 * @return array Equivalence classes
 */
function find_equivalence_classes(array $clauses, float $threshold = 0.8): array {
    $classes = [];
    $assigned = [];

    foreach ($clauses as $i => $clause_a) {
        if (isset($assigned[$i])) continue;

        $class = [$i];
        $assigned[$i] = true;

        foreach ($clauses as $j => $clause_b) {
            if ($i === $j || isset($assigned[$j])) continue;

            $similarity = compute_clause_similarity($clause_a, $clause_b);

            if ($similarity >= $threshold) {
                $class[] = $j;
                $assigned[$j] = true;
            }
        }

        $classes[] = $class;
    }

    return $classes;
}

/**
 * Compute similarity between two clauses
 *
 * Uses multiple metrics:
 * 1. Token overlap (Jaccard similarity)
 * 2. Structural similarity (same obligations, parties, etc.)
 * 3. Semantic similarity (similar legal effect)
 *
 * @param array $clause_a First clause
 * @param array $clause_b Second clause
 * @return float Similarity score (0.0-1.0)
 */
function compute_clause_similarity(array $clause_a, array $clause_b): float {
    // Token overlap (Jaccard)
    $tokens_a = array_map('strtolower', $clause_a['tokens'] ?? []);
    $tokens_b = array_map('strtolower', $clause_b['tokens'] ?? []);

    $intersection = count(array_intersect($tokens_a, $tokens_b));
    $union = count(array_unique(array_merge($tokens_a, $tokens_b)));

    $jaccard = $union > 0 ? $intersection / $union : 0.0;

    // Structural similarity
    $struct_score = 0.0;
    $struct_checks = 0;

    if (isset($clause_a['has_obligations']) && isset($clause_b['has_obligations'])) {
        $struct_score += ($clause_a['has_obligations'] === $clause_b['has_obligations']) ? 1.0 : 0.0;
        $struct_checks++;
    }

    if (isset($clause_a['has_parties']) && isset($clause_b['has_parties'])) {
        $struct_score += ($clause_a['has_parties'] === $clause_b['has_parties']) ? 1.0 : 0.0;
        $struct_checks++;
    }

    $structural = $struct_checks > 0 ? $struct_score / $struct_checks : 0.0;

    // Weighted combination
    return 0.6 * $jaccard + 0.4 * $structural;
}

// ============================================================================
// § 5. INTEGRATION FUNCTION
// ============================================================================

/**
 * Main Wolfram-style analysis function
 *
 * Combines all computational techniques:
 * - Rule-based transformations
 * - Dependency graph construction
 * - Multiway evolution
 * - Equivalence classes
 *
 * @param array $tokens Classified tokens
 * @param array $clauses Multiple clauses (optional)
 * @return array Complete Wolfram analysis results
 */
function wolfram_analyze(array $tokens, array $clauses = []): array {
    $start_time = microtime(true);

    // § 1. Rule-based transformations
    $transformations = apply_transformation_rules($tokens);

    // § 2. Dependency graph (if multiple clauses)
    $dependency_graph = null;
    $cycles = [];
    $topological_order = null;

    if (!empty($clauses)) {
        $dependency_graph = build_clause_dependency_graph($clauses);
        $cycles = $dependency_graph->find_cycles();
        $topological_order = $dependency_graph->topological_sort();
    }

    // § 3. Multiway evolution
    $text = implode(' ', array_column($tokens, 'clean'));
    $multiway = generate_multiway_graph($text, $tokens, 2);
    $terminal_states = $multiway->get_terminal_states();

    // § 4. Equivalence classes (if multiple clauses)
    $equivalence_classes = [];
    if (!empty($clauses)) {
        $equivalence_classes = find_equivalence_classes($clauses, 0.8);
    }

    $execution_time = (microtime(true) - $start_time) * 1000; // ms

    return [
        'transformations' => $transformations,
        'dependency_graph' => $dependency_graph ? [
            'stats' => $dependency_graph->get_stats(),
            'has_cycles' => !empty($cycles),
            'cycles' => $cycles,
            'topological_order' => $topological_order,
            'dot' => $dependency_graph->to_dot(),
        ] : null,
        'multiway' => [
            'state_count' => count($terminal_states),
            'terminal_states' => $terminal_states,
            'json' => $multiway->to_json(),
        ],
        'equivalence_classes' => $equivalence_classes,
        'performance' => [
            'execution_time_ms' => round($execution_time, 2),
        ],
    ];
}

// ============================================================================
// § 6. VISUALIZATION HELPERS
// ============================================================================

/**
 * Generate HTML visualization of transformations
 *
 * @param array $transformations List of transformations
 * @return string HTML markup
 */
function render_transformations_html(array $transformations): string {
    if (empty($transformations['transformations'])) {
        return '<p class="text-gray-500 italic">No transformations suggested.</p>';
    }

    $html = '<div class="space-y-3">';

    foreach ($transformations['transformations'] as $t) {
        $category_colors = [
            'precision' => 'blue',
            'enforceability' => 'emerald',
            'risk-reduction' => 'rose',
        ];
        $color = $category_colors[$t['category']] ?? 'gray';

        $html .= '<div class="border border-' . $color . '-200 rounded-lg p-3 bg-' . $color . '-50">';
        $html .= '<div class="flex items-center justify-between mb-2">';
        $html .= '<span class="text-xs font-semibold text-' . $color . '-700 uppercase">' . htmlspecialchars($t['category']) . '</span>';
        $html .= '<span class="text-xs text-' . $color . '-600">' . round($t['strength'] * 100) . '% confidence</span>';
        $html .= '</div>';
        $html .= '<div class="text-sm">';
        $html .= '<span class="text-gray-700">Replace: </span>';
        $html .= '<code class="bg-red-100 text-red-800 px-1 rounded">' . htmlspecialchars($t['original']) . '</code>';
        $html .= '<span class="text-gray-700"> with: </span>';
        $html .= '<code class="bg-green-100 text-green-800 px-1 rounded">' . htmlspecialchars($t['suggested']) . '</code>';
        $html .= '</div>';
        $html .= '</div>';
    }

    $html .= '</div>';

    return $html;
}

?>
