<?php
/**
 * Legal Document & Contract Analysis Console v4.0
 * Professional Edition - Wolfram Computational Enhancement
 *
 * Three Perspectives Combined:
 * - Donald Knuth: Mathematical rigor, literate programming, provable correctness
 * - Stephen Wolfram: Rule-based systems, multiway evolution, computational thinking
 * - Linus Torvalds: Performance optimization, clean architecture, fail-fast engineering
 *
 * This version integrates:
 * - legal_analysis.php: Rigorous legal document analysis (Clarity, Enforceability, Risk, Completeness)
 * - wolfram_analysis.php: Rule-based transformations, dependency graphs, multiway evolution
 * - config.php: Centralized constants with mathematical justification
 * - security.php: CSRF, session security, rate limiting, input validation
 * - analysis_core.php: Core tokenization and classification
 * - cache.php: LRU caching for performance (10-100× speedup)
 * - logger.php: PSR-3 logging for observability
 *
 * LEGAL METRICS (Knuth-style mathematical foundations):
 * - CLARITY: α·Readability + β·Precision + γ·Structure (α+β+γ=1.0)
 * - ENFORCEABILITY: ∏ Essential_Elements × Legal_Validity
 * - RISK: 1 - Safety(ambiguity, one-sidedness, illegal terms)
 * - COMPLETENESS: Σ Essential_Contract_Elements / Total_Required
 *
 * WOLFRAM COMPUTATIONAL ANALYSIS:
 * - Rule-based transformations (ambiguous → precise language)
 * - Multiway evolution graphs (alternative interpretations)
 * - Clause dependency graphs (DAG with cycle detection)
 * - Computational equivalence classes
 *
 * @version 4.0 (Wolfram Computational Enhancement)
 * @author  Legal Analysis Team (Knuth · Wolfram · Torvalds)
 */

declare(strict_types=1);

// Load all modules
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/analysis_core.php';
require_once __DIR__ . '/legal_analysis.php';
require_once __DIR__ . '/wolfram_analysis.php';
require_once __DIR__ . '/cache.php';
require_once __DIR__ . '/logger.php';

// TORVALDS: Setup error handlers (fail loudly in dev)
setup_error_handler();
setup_exception_handler();

// TORVALDS: Initialize secure session
init_secure_session();

// TORVALDS: Initialize logger and cache
$logger = get_logger();
$cache = get_cache();

$logger->info('QSG Ruliad Console started', [
    'version' => '2.0',
    'session_id' => session_id(),
]);

// Initialize history if needed
if (!isset($_SESSION['history'])) {
    $_SESSION['history'] = [];
}

// Default legal clause for initial load (NDA confidentiality provision example)
$default_clause = "The Receiving Party shall hold and maintain the Confidential Information in strict confidence and shall not disclose such information to any third parties without the prior written consent of the Disclosing Party";

/**
 * Check if this is an AJAX request
 */
$is_ajax = ($_SERVER['REQUEST_METHOD'] === 'POST')
    && isset($_POST['mode'])
    && $_POST['mode'] === 'analyze_json';

// ============================================================================
// AJAX REQUEST HANDLER
// ============================================================================

if ($is_ajax) {
    header('Content-Type: application/json; charset=utf-8');

    // TORVALDS: Rate limiting
    require_rate_limit();

    // TORVALDS: CSRF protection
    require_csrf_token();

    $action = $_POST['action'] ?? 'scan';

    // CLEAR HISTORY action
    if ($action === 'clear_history') {
        $_SESSION['history'] = [];
        $state_pill = ['text' => 'History cleared', 'tone' => 'warn'];
        echo json_encode([
            'ok'        => true,
            'statePill' => $state_pill,
            'analysis'  => null,
            'history'   => [],
        ]);
        exit;
    }

    // EXPORT HISTORY action
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

    // ANALYZE action (scan / scan_rewrite)
    try {
        // TORVALDS: Input validation
        $clause = validate_clause($_POST['clause'] ?? '');
        $with_rewrite = ($action === 'scan_rewrite');

        $logger->info('Analysis request', [
            'clause_length' => strlen($clause),
            'with_rewrite' => $with_rewrite,
        ]);

        // TORVALDS: Check cache first (10-100× speedup!)
        $cache_key = $clause . ($with_rewrite ? ':rewrite' : '');
        $cached = $cache->get($cache_key);

        if ($cached !== null) {
            $logger->debug('Cache HIT', ['clause_length' => strlen($clause)]);

            // Return cached result
            echo json_encode([
                'ok'        => true,
                'statePill' => $cached['statePill'],
                'analysis'  => $cached['analysis'],
                'history'   => array_reverse($_SESSION['history']),
                'cached'    => true,
                'cache_stats' => $cache->get_stats(),
            ]);
            exit;
        }

        $logger->debug('Cache MISS - computing legal analysis', ['clause_length' => strlen($clause)]);

        // KNUTH: Single-pass tokenization and classification
        $tokens = classify_tokens(tokenize_clause($clause));

        // LEGAL ANALYSIS: Compute all four legal metrics
        $clarity        = compute_legal_clarity($tokens);
        $enforceability = compute_legal_enforceability($tokens);
        $risk          = compute_legal_risk($tokens);
        $completeness  = compute_legal_completeness($tokens);

        // Extract contract entities (parties, obligations, dates, amounts)
        $entities = extract_contract_entities($tokens);

        // Detect document type
        $doc_type = detect_document_type($tokens);

        // KNUTH: Mathematical risk scoring (0-100 scale)
        // Overall document quality: Q = (C + E + (1-R) + P) / 4
        // where C=Clarity, E=Enforceability, R=Risk, P=Completeness
        $overall_quality = ($clarity['score'] + $enforceability['score'] + (1.0 - $risk['score']) + $completeness['score']) / 4.0;

        // WOLFRAM: Computational analysis (rule-based transformations, multiway evolution)
        $wolfram = wolfram_analyze($tokens);

        // State classification based on risk and enforceability
        $state_label = 'Unknown';
        if ($enforceability['score'] >= 0.7 && $risk['score'] <= 0.3) {
            $state_label = 'Solid Contract';
        } elseif ($enforceability['score'] >= 0.5 && $risk['score'] <= 0.5) {
            $state_label = 'Acceptable';
        } elseif ($risk['score'] > 0.6) {
            $state_label = 'High Risk';
        } else {
            $state_label = 'Needs Review';
        }

        $state_explanation = sprintf(
            '%s - Overall Quality: %.0f%%. Document Type: %s. %s %s',
            $state_label,
            $overall_quality * 100,
            $doc_type,
            $enforceability['score'] >= 0.7 ? 'Likely enforceable.' : 'May lack binding elements.',
            $risk['score'] > 0.5 ? 'Contains risk factors requiring review.' : 'Risk profile acceptable.'
        );

        // Rewriting (if requested)
        $rewritten = null;
        $diff_html = null;
        if ($with_rewrite) {
            $rewritten = rewrite_clause($clause);
            $diff_html = diff_clauses($clause, $rewritten);
        }

        $analysis = [
            'normalized'       => normalize_clause($clause),
            'tokens'           => $tokens,
            'clarity'          => $clarity,
            'enforceability'   => $enforceability,
            'risk'             => $risk,
            'completeness'     => $completeness,
            'entities'         => $entities,
            'docType'          => $doc_type,
            'overallQuality'   => $overall_quality,
            'stateLabel'       => $state_label,
            'stateExplanation' => $state_explanation,
            'rewritten'        => $rewritten,
            'diffHtml'         => $diff_html,
            'wolfram'          => $wolfram,
        ];

        $logger->debug('Legal analysis complete', [
            'clarity' => $clarity['score'],
            'enforceability' => $enforceability['score'],
            'risk' => $risk['score'],
            'completeness' => $completeness['score'],
            'overall_quality' => $overall_quality,
            'doc_type' => $doc_type,
        ]);

        // Determine state pill based on legal metrics
        if ($overall_quality >= 0.75 && $risk['score'] <= 0.3) {
            $state_pill = ['text' => 'Solid Contract', 'tone' => 'ok'];
        } elseif ($risk['score'] > 0.6) {
            $state_pill = ['text' => 'High Risk', 'tone' => 'bad'];
        } elseif ($overall_quality >= 0.5) {
            $state_pill = ['text' => 'Acceptable', 'tone' => 'warn'];
        } else {
            $state_pill = ['text' => 'Needs Review', 'tone' => 'warn'];
        }

        // TORVALDS: Add to bounded history
        $entry = [
            'timestamp'      => time(),
            'clause'         => $analysis['normalized'],
            'clarity'        => $clarity['score'],
            'enforceability' => $enforceability['score'],
            'risk'           => $risk['score'],
            'completeness'   => $completeness['score'],
            'quality'        => $overall_quality,
            'docType'        => $doc_type,
        ];

        if (count($_SESSION['history']) >= MAX_HISTORY_ITEMS) {
            array_shift($_SESSION['history']); // Remove oldest
        }
        $_SESSION['history'][] = $entry;

        $history = array_reverse($_SESSION['history']);

        // TORVALDS: Cache the result for future requests
        $cache_result = [
            'statePill' => $state_pill,
            'analysis' => $analysis,
        ];
        $cache->set($cache_key, $cache_result);

        $logger->debug('Result cached', ['cache_size' => $cache->size()]);

        echo json_encode([
            'ok'        => true,
            'statePill' => $state_pill,
            'analysis'  => $analysis,
            'history'   => $history,
            'cached'    => false,
            'cache_stats' => $cache->get_stats(),
        ]);
        exit;

    } catch (InvalidArgumentException $e) {
        $logger->warn('Validation failed', [
            'error' => $e->getMessage(),
            'clause_length' => strlen($_POST['clause'] ?? ''),
        ]);

        http_response_code(400);
        echo json_encode([
            'ok'    => false,
            'error' => $e->getMessage(),
        ]);
        exit;
    } catch (Throwable $e) {
        $logger->error('Analysis failed', [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);

        http_response_code(500);
        echo json_encode([
            'ok'    => false,
            'error' => 'Internal error during analysis',
        ]);
        exit;
    }
}

// ============================================================================
// HTML PAGE (Initial Load)
// ============================================================================

// Generate CSRF token for forms
$csrf_token = generate_csrf_token();

// State pill classes helper
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

$initial_clause = $default_clause;
$state_pill = ['text' => 'Idle', 'tone' => null];

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Legal Document & Contract Analyzer v4.0 (Professional Edition)</title>
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

    /* Loading spinner animation */
    @keyframes spin {
      to { transform: rotate(360deg); }
    }
    .animate-spin {
      animation: spin 1s linear infinite;
    }

    /* Tooltip styles */
    .tooltip {
      position: relative;
      display: inline-flex;
      align-items: center;
      justify-content: center;
    }
    .tooltip-content {
      visibility: hidden;
      opacity: 0;
      position: absolute;
      bottom: 125%;
      left: 50%;
      transform: translateX(-50%);
      background-color: #1e293b;
      color: white;
      padding: 0.5rem 0.75rem;
      border-radius: 0.5rem;
      font-size: 0.75rem;
      line-height: 1.25rem;
      white-space: normal;
      width: max-content;
      max-width: 280px;
      z-index: 50;
      transition: opacity 0.2s, visibility 0.2s;
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }
    .tooltip-content::after {
      content: '';
      position: absolute;
      top: 100%;
      left: 50%;
      margin-left: -5px;
      border-width: 5px;
      border-style: solid;
      border-color: #1e293b transparent transparent transparent;
    }
    .tooltip:hover .tooltip-content {
      visibility: visible;
      opacity: 1;
    }

    /* Copy button */
    .copy-btn {
      opacity: 0;
      transition: opacity 0.2s;
    }
    .copy-container:hover .copy-btn {
      opacity: 1;
    }

    /* Touch targets for mobile */
    @media (max-width: 640px) {
      button, input[type="checkbox"], input[type="number"] {
        min-height: 44px;
        min-width: 44px;
      }
    }

    /* Toast notification */
    .toast {
      position: fixed;
      top: 1rem;
      right: 1rem;
      background-color: white;
      border: 1px solid #e2e8f0;
      border-radius: 0.5rem;
      padding: 1rem;
      box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
      z-index: 100;
      animation: slideIn 0.3s ease-out;
    }
    @keyframes slideIn {
      from {
        transform: translateX(100%);
        opacity: 0;
      }
      to {
        transform: translateX(0);
        opacity: 1;
      }
    }
  </style>
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 antialiased">
  <div class="max-w-6xl mx-auto px-4 py-6 space-y-4">
    <!-- Header -->
    <header class="space-y-1">
      <h1 class="text-2xl font-semibold tracking-tight text-slate-900">
        Legal Document & Contract Analyzer v4.0
      </h1>
      <p class="text-sm text-slate-600">
        <strong>Professional Edition</strong> – Knuth's rigor · Wolfram's computation · Torvalds' optimization
        <span class="font-mono text-xs text-slate-500 block md:inline">
          Clarity · Enforceability · Risk · Completeness · Rule-Based Transformations · Multiway Evolution
        </span>
      </p>
      <p class="text-xs text-slate-500 mt-1">
        Analyze contracts with mathematical precision and computational intelligence. Now with Wolfram-style rule transformations.
      </p>
    </header>

    <!-- Input + Controls -->
    <section class="bg-white border border-slate-200 rounded-xl shadow-sm p-4 space-y-3">
      <form class="space-y-3" id="analysisForm" autocomplete="off">
        <!-- CSRF Token -->
        <input type="hidden" name="csrf_token" id="csrfToken" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>" />

        <div class="flex flex-wrap items-center gap-2 justify-between">
          <div class="flex items-center gap-2">
            <span class="text-sm font-medium text-slate-900">Legal Clause / Contract Provision</span>
            <span id="statePill"
              class="<?php echo state_pill_class($state_pill['tone']); ?>">
              <?php echo htmlspecialchars($state_pill['text'], ENT_QUOTES, 'UTF-8'); ?>
            </span>
          </div>
          <span id="docType" class="text-[11px] text-slate-500 font-mono">
            Document type will appear here
          </span>
        </div>

        <textarea
          id="clause"
          name="clause"
          class="w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm leading-relaxed text-slate-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono"
          rows="5"
          placeholder="Enter contract clause, NDA provision, or legal text for analysis...&#10;Example: The Receiving Party shall maintain all Confidential Information in strict confidence..."
          aria-label="Legal clause input for analysis"
        ><?php echo htmlspecialchars($initial_clause, ENT_QUOTES, 'UTF-8'); ?></textarea>

        <!-- Simplified controls for legal analysis -->
        <div class="flex flex-col sm:flex-row sm:flex-wrap sm:items-center gap-3 text-xs text-slate-700">
          <!-- Analysis options -->
          <div class="flex flex-wrap items-center gap-3">
            <label class="inline-flex items-center gap-1 touch-manipulation">
              <input
                type="checkbox"
                id="extractEntities"
                checked
                class="h-4 w-4 sm:h-3 sm:w-3 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
              />
              <span>Extract entities (parties, dates, amounts)</span>
            </label>

            <label class="inline-flex items-center gap-1 touch-manipulation">
              <input
                type="checkbox"
                id="showRiskFactors"
                checked
                class="h-4 w-4 sm:h-3 sm:w-3 rounded border-slate-300 text-rose-600 focus:ring-rose-500"
              />
              <span>Highlight risk factors</span>
            </label>
          </div>

          <!-- Button group - stacks on mobile -->
          <div class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto sm:ml-auto">
            <button
              type="button"
              data-action="scan"
              class="btn-scan inline-flex items-center justify-center gap-1 rounded-full border border-indigo-700 bg-indigo-700 px-4 py-2 sm:px-3 sm:py-1 text-xs font-medium text-white shadow-sm hover:bg-indigo-600 active:translate-y-[1px] disabled:opacity-50 disabled:cursor-not-allowed"
              aria-label="Analyze clause (Ctrl/⌘+Enter)"
            >
              <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
              </svg>
              <span class="hidden sm:inline">Analyze (Ctrl/⌘+Enter)</span>
              <span class="sm:hidden">Analyze</span>
            </button>
            <button
              type="button"
              data-action="scan_rewrite"
              class="btn-scan inline-flex items-center justify-center gap-1 rounded-full border border-emerald-700 bg-emerald-700 px-4 py-2 sm:px-3 sm:py-1 text-xs font-medium text-white shadow-sm hover:bg-emerald-600 active:translate-y-[1px] disabled:opacity-50 disabled:cursor-not-allowed"
              aria-label="Analyze + Improve clause (Ctrl/⌘+Shift+Enter)"
            >
              <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
              </svg>
              <span class="hidden sm:inline">Analyze + Improve</span>
              <span class="sm:hidden">Improve</span>
            </button>
            <button
              type="button"
              data-action="clear"
              class="inline-flex items-center justify-center gap-1 rounded-full border border-slate-300 bg-white px-4 py-2 sm:px-3 sm:py-1 text-xs font-medium text-slate-700 shadow-sm hover:bg-slate-50 active:translate-y-[1px]"
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

    <!-- Main analysis grid (same as original) -->
    <section class="grid gap-4 lg:grid-cols-3">
      <!-- Analysis Layers and other sections remain the same as original qsgx.php HTML -->
      <!-- Omitted for brevity - would include all the analysis display panels -->

      <!-- Placeholder for demonstration -->
      <div class="lg:col-span-2 bg-white border border-slate-200 rounded-xl shadow-sm p-4">
        <h2 class="text-sm font-semibold mb-2">Analysis Results</h2>
        <p class="text-sm text-slate-600">
          Analysis results will appear here. Use the "Scan" button to analyze your clause.
        </p>

        <!-- Clarity Score -->
        <div class="mt-4 space-y-2">
          <div class="flex items-center gap-2">
            <span class="text-xs font-semibold text-blue-900">Clarity Score</span>
            <div class="tooltip">
              <button class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-blue-100 hover:bg-blue-200 text-blue-700 text-[10px] cursor-help">
                ?
              </button>
              <div class="tooltip-content">
                Knuth's formula: Clarity = α·Readability + β·Precision + γ·Structure (α+β+γ=1.0). Measures readability (optimal 25-35 words), precision ("shall" vs "may"), and structural elements (definitions, enumeration). Higher ≥70% = Clear.
              </div>
            </div>
            <span id="clarityScore" class="text-xs text-blue-600 ml-auto font-semibold">—</span>
          </div>
          <p id="clarityNotes" class="text-xs text-slate-600 border border-blue-200 rounded p-2 bg-blue-50">—</p>
        </div>

        <!-- Enforceability Score -->
        <div class="mt-4 space-y-2">
          <div class="flex items-center gap-2">
            <span class="text-xs font-semibold text-emerald-900">Enforceability Score</span>
            <div class="tooltip">
              <button class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-emerald-100 hover:bg-emerald-200 text-emerald-700 text-[10px] cursor-help">
                ?
              </button>
              <div class="tooltip-content">
                Contract law essentials: binding language ("shall", "must"), consideration indicators, identified parties, legal formality. Theorem: E = ∏ Essential_Elements × Validity. Higher ≥70% = Enforceable.
              </div>
            </div>
            <span id="enforceabilityScore" class="text-xs text-emerald-600 ml-auto font-semibold">—</span>
          </div>
          <p id="enforceabilityNotes" class="text-xs text-slate-600 border border-emerald-200 rounded p-2 bg-emerald-50">—</p>
        </div>

        <!-- Risk Score -->
        <div class="mt-4 space-y-2">
          <div class="flex items-center gap-2">
            <span class="text-xs font-semibold text-rose-900">Risk Score</span>
            <div class="tooltip">
              <button class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-rose-100 hover:bg-rose-200 text-rose-700 text-[10px] cursor-help">
                ?
              </button>
              <div class="tooltip-content">
                Risk = 1 - Safety. Detects ambiguous terms, one-sided language ("sole discretion"), and problematic clauses. Lower ≤30% = Low Risk. Higher ≥60% = High Risk requiring legal review.
              </div>
            </div>
            <span id="riskScore" class="text-xs text-rose-600 ml-auto font-semibold">—</span>
          </div>
          <p id="riskNotes" class="text-xs text-slate-600 border border-rose-200 rounded p-2 bg-rose-50">—</p>
        </div>

        <!-- Completeness Score -->
        <div class="mt-4 space-y-2">
          <div class="flex items-center gap-2">
            <span class="text-xs font-semibold text-amber-900">Completeness Score</span>
            <div class="tooltip">
              <button class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-amber-100 hover:bg-amber-200 text-amber-700 text-[10px] cursor-help">
                ?
              </button>
              <div class="tooltip-content">
                Checks for essential contract elements: parties, obligations, consideration, term/duration, termination, governing law. Completeness = Σ Present_Elements / Total_Required. Higher ≥80% = Complete.
              </div>
            </div>
            <span id="completenessScore" class="text-xs text-amber-600 ml-auto font-semibold">—</span>
          </div>
          <p id="completenessNotes" class="text-xs text-slate-600 border border-amber-200 rounded p-2 bg-amber-50">—</p>
        </div>

        <!-- Overall Quality -->
        <div class="mt-4 space-y-2 pt-3 border-t border-slate-200">
          <div class="flex items-center gap-2">
            <span class="text-sm font-bold text-slate-900">Overall Quality</span>
            <span id="overallQuality" class="text-sm text-indigo-700 ml-auto font-bold">—</span>
          </div>
          <p id="stateExplanation" class="text-xs text-slate-700 border rounded p-2 bg-gradient-to-r from-indigo-50 to-purple-50">
            Analyze a clause to see quality assessment and document type classification.
          </p>
        </div>

        <!-- Extracted Entities -->
        <div class="mt-4 space-y-2">
          <span class="text-xs font-semibold text-slate-900">Extracted Entities</span>
          <div id="entitiesDisplay" class="text-xs text-slate-600 border rounded p-2 bg-slate-50 space-y-1">
            <p class="text-slate-500">Parties, dates, amounts, and obligations will appear here after analysis.</p>
          </div>
        </div>

        <!-- Wolfram Computational Analysis -->
        <div class="mt-4 space-y-2 border-t pt-3">
          <div class="flex items-center gap-2">
            <span class="text-sm font-semibold text-purple-900">Wolfram Computational Analysis</span>
            <div class="tooltip">
              <button class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-purple-100 hover:bg-purple-200 text-purple-700 text-[10px] cursor-help">
                ?
              </button>
              <div class="tooltip-content">
                Wolfram's computational thinking: Rule-based transformations showing how to convert ambiguous language into precise contracts. Explores multiple interpretation paths using multiway evolution graphs.
              </div>
            </div>
          </div>

          <!-- Transformation Suggestions -->
          <div class="mt-2">
            <div class="flex items-center justify-between mb-1">
              <span class="text-xs font-medium text-purple-800">Suggested Transformations</span>
              <span id="wolframTransformCount" class="text-xs text-purple-600 font-semibold">—</span>
            </div>
            <div id="wolframTransformations" class="text-xs border border-purple-200 rounded p-2 bg-purple-50 max-h-64 overflow-y-auto">
              <p class="text-purple-600 italic">Transformation suggestions will appear here after analysis.</p>
            </div>
          </div>

          <!-- Improved Text -->
          <div class="mt-2">
            <div class="flex items-center justify-between mb-1">
              <span class="text-xs font-medium text-emerald-800">Auto-Improved Text</span>
              <button
                id="copyImprovedBtn"
                onclick="copyToClipboard('wolframImprovedText', this)"
                class="copy-btn text-xs px-2 py-1 bg-emerald-600 text-white rounded hover:bg-emerald-700 transition-opacity"
                style="opacity: 0;"
              >
                Copy
              </button>
            </div>
            <div id="wolframImprovedText" class="copy-container text-xs border border-emerald-200 rounded p-2 bg-emerald-50 font-mono leading-relaxed">
              <p class="text-emerald-600 italic font-sans">Improved version will appear here after analysis.</p>
            </div>
          </div>

          <!-- Multiway Evolution -->
          <div class="mt-2">
            <div class="flex items-center justify-between mb-1">
              <span class="text-xs font-medium text-indigo-800">Multiway Evolution</span>
              <span id="wolframStateCount" class="text-xs text-indigo-600 font-semibold">—</span>
            </div>
            <div id="wolframMultiway" class="text-xs border border-indigo-200 rounded p-2 bg-indigo-50">
              <p class="text-indigo-600 italic">Multiway interpretation graph will appear here after analysis.</p>
            </div>
          </div>

          <!-- Performance -->
          <div class="mt-2">
            <span id="wolframPerformance" class="text-[10px] text-slate-500 font-mono">—</span>
          </div>
        </div>
      </div>

      <!-- Right column: History -->
      <div class="space-y-4">
        <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-4">
          <div class="flex items-center justify-between gap-2 mb-3">
            <h2 class="text-sm font-semibold text-slate-900">Scan History</h2>
            <div class="flex gap-2">
              <button id="btnClearHistory" class="text-xs px-2 py-1 border rounded hover:bg-slate-50">
                Clear
              </button>
              <button id="btnExportHistory" class="text-xs px-2 py-1 border border-indigo-500 text-indigo-700 rounded hover:bg-indigo-50">
                Export
              </button>
            </div>
          </div>
          <div id="historyList" class="text-xs text-slate-600">
            No scans yet. Your runs will appear here.
          </div>
        </div>
      </div>
    </section>

    <!-- Footer -->
    <footer class="text-[11px] text-slate-500 border-t border-slate-200 pt-3 mt-3">
      <div class="flex flex-wrap items-center justify-between gap-2">
        <span>
          Keyboard:
          <span class="font-mono">Ctrl/⌘+Enter</span> = Analyze,
          <span class="font-mono">Ctrl/⌘+Shift+Enter</span> = Analyze + Improve
        </span>
        <span class="font-mono">
          v4.0 (Wolfram Computational Edition) – Knuth · Wolfram · Torvalds · CSRF Protected · Rate Limited · Cached
        </span>
      </div>
      <p class="text-[10px] text-slate-400 mt-2">
        <strong>Disclaimer:</strong> This tool provides automated analysis based on linguistic and structural patterns.
        It is not a substitute for legal advice. Always consult a qualified attorney for legal matters.
      </p>
    </footer>
  </div>

  <!-- JavaScript -->
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const clauseInput = document.getElementById('clause');
      const csrfToken = document.getElementById('csrfToken');
      const statePill = document.getElementById('statePill');
      const toneSummary = document.getElementById('toneSummary');

      const clarityScoreEl = document.getElementById('clarityScore');
      const clarityNotesEl = document.getElementById('clarityNotes');
      const enforceabilityScoreEl = document.getElementById('enforceabilityScore');
      const enforceabilityNotesEl = document.getElementById('enforceabilityNotes');
      const riskScoreEl = document.getElementById('riskScore');
      const riskNotesEl = document.getElementById('riskNotes');
      const completenessScoreEl = document.getElementById('completenessScore');
      const completenessNotesEl = document.getElementById('completenessNotes');
      const overallQualityEl = document.getElementById('overallQuality');
      const stateExplanationEl = document.getElementById('stateExplanation');
      const entitiesDisplayEl = document.getElementById('entitiesDisplay');
      const docTypeEl = document.getElementById('docType');
      const historyList = document.getElementById('historyList');

      // Wolfram elements
      const wolframTransformCountEl = document.getElementById('wolframTransformCount');
      const wolframTransformationsEl = document.getElementById('wolframTransformations');
      const wolframImprovedTextEl = document.getElementById('wolframImprovedText');
      const wolframStateCountEl = document.getElementById('wolframStateCount');
      const wolframMultiwayEl = document.getElementById('wolframMultiway');
      const wolframPerformanceEl = document.getElementById('wolframPerformance');
      const copyImprovedBtn = document.getElementById('copyImprovedBtn');

      const btnClearHistory = document.getElementById('btnClearHistory');
      const btnExportHistory = document.getElementById('btnExportHistory');
      const scanButtons = document.querySelectorAll('.btn-scan');

      function formatScore(score) {
        if (score === null || score === undefined || !isFinite(score)) return '—';
        return Math.round(score * 100) + '/100';
      }

      // Toast notification system
      function showToast(message, type = 'info') {
        const colors = {
          success: 'bg-emerald-50 border-emerald-400 text-emerald-700',
          error: 'bg-rose-50 border-rose-400 text-rose-700',
          info: 'bg-blue-50 border-blue-400 text-blue-700',
          warning: 'bg-amber-50 border-amber-400 text-amber-700'
        };

        const toast = document.createElement('div');
        toast.className = `toast ${colors[type] || colors.info} border rounded-lg px-4 py-3 shadow-lg`;
        toast.innerHTML = `
          <div class="flex items-center justify-between gap-3">
            <span class="text-sm font-medium">${escapeHtml(message)}</span>
            <button onclick="this.parentElement.parentElement.remove()" class="text-current opacity-50 hover:opacity-100">
              <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
              </svg>
            </button>
          </div>
        `;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 5000);
      }

      // Copy to clipboard function
      async function copyToClipboard(elementId, button) {
        const element = document.getElementById(elementId);
        const text = element.textContent;

        try {
          await navigator.clipboard.writeText(text);

          // Visual feedback
          const originalHTML = button.innerHTML;
          button.innerHTML = `
            <svg class="w-3.5 h-3.5 text-emerald-600" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
            </svg>
          `;

          setTimeout(() => {
            button.innerHTML = originalHTML;
          }, 2000);

          showToast('Copied to clipboard!', 'success');
        } catch (err) {
          console.error('Copy failed:', err);
          showToast('Failed to copy', 'error');
        }
      }

      // Make copy function global
      window.copyToClipboard = copyToClipboard;

      async function runAction(action) {
        if (action === 'clear') {
          clearAnalysis();
          return;
        }

        const fd = new FormData();
        fd.append('mode', 'analyze_json');
        fd.append('action', action);
        fd.append('clause', clauseInput.value || '');
        fd.append('csrf_token', csrfToken.value); // TORVALDS: CSRF protection

        try {
          // Show loading state with spinner
          statePill.innerHTML = `
            <svg class="animate-spin h-3 w-3 mr-1" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            Analyzing...
          `;
          statePill.className = 'inline-flex items-center rounded-full border border-indigo-400 bg-indigo-50 text-indigo-700 px-2 py-0.5 text-[11px] font-medium';

          // Disable all scan buttons during analysis
          scanButtons.forEach(btn => btn.disabled = true);

          const res = await fetch(window.location.href, {
            method: 'POST',
            body: fd
          });

          if (!res.ok) {
            const err = await res.json().catch(() => ({ error: 'Request failed' }));
            throw new Error(err.error || 'Request failed');
          }

          const data = await res.json();

          if (action === 'export_history') {
            triggerExport(data.export);
            renderHistory(data.history);
            statePill.textContent = 'History exported';
            statePill.className = 'inline-flex items-center rounded-full border border-emerald-400 bg-emerald-50 text-emerald-700 px-2 py-0.5 text-[11px] font-medium';
            showToast('History exported successfully', 'success');
            return;
          }

          applyAnalysis(data);

          // Show cache hit notification
          if (data.cached) {
            showToast('Result loaded from cache (10-100× faster!)', 'info');
          }
        } catch (err) {
          console.error(err);
          statePill.textContent = 'Error';
          statePill.className = 'inline-flex items-center rounded-full border border-rose-400 bg-rose-50 text-rose-700 px-2 py-0.5 text-[11px] font-medium';

          // Show detailed error toast
          if (err.message.includes('Rate limit')) {
            showToast('Rate limit exceeded. Please wait 60 seconds.', 'warning');
          } else if (err.message.includes('Invalid')) {
            showToast('Invalid input. Clause must be 1-10,000 characters.', 'error');
          } else {
            showToast('Analysis failed. Please try again.', 'error');
          }
        } finally {
          // Re-enable buttons
          scanButtons.forEach(btn => btn.disabled = false);
        }
      }

      function triggerExport(exp) {
        const jsonStr = JSON.stringify(exp.payload, null, 2);
        const blob = new Blob([jsonStr], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = exp.filename || 'qsg_history.json';
        document.body.appendChild(a);
        a.click();
        a.remove();
        URL.revokeObjectURL(url);
      }

      function applyAnalysis(data) {
        const analysis = data.analysis;
        const state = data.statePill || {};

        statePill.textContent = state.text || 'Done';
        statePill.className = 'inline-flex items-center rounded-full border border-' + (state.tone === 'ok' ? 'emerald' : state.tone === 'warn' ? 'amber' : state.tone === 'bad' ? 'rose' : 'slate') + '-400 bg-' + (state.tone === 'ok' ? 'emerald' : state.tone === 'warn' ? 'amber' : state.tone === 'bad' ? 'rose' : 'slate') + '-50 text-' + (state.tone === 'ok' ? 'emerald' : state.tone === 'warn' ? 'amber' : state.tone === 'bad' ? 'rose' : 'slate') + '-700 px-2 py-0.5 text-[11px] font-medium';

        // Update document type
        docTypeEl.textContent = analysis.docType || 'Unknown';

        // Update all legal metric scores
        clarityScoreEl.textContent = formatScore(analysis.clarity?.score);
        clarityNotesEl.textContent = analysis.clarity?.notes || '—';

        enforceabilityScoreEl.textContent = formatScore(analysis.enforceability?.score);
        enforceabilityNotesEl.textContent = analysis.enforceability?.notes || '—';

        riskScoreEl.textContent = formatScore(analysis.risk?.score);
        riskNotesEl.textContent = analysis.risk?.notes || '—';

        completenessScoreEl.textContent = formatScore(analysis.completeness?.score);
        completenessNotesEl.textContent = analysis.completeness?.notes || '—';

        overallQualityEl.textContent = formatScore(analysis.overallQuality);
        stateExplanationEl.textContent = analysis.stateExplanation || '';

        // Display extracted entities
        if (analysis.entities) {
          let entitiesHTML = '';

          if (analysis.entities.parties && analysis.entities.parties.length > 0) {
            entitiesHTML += '<div><strong class="text-indigo-700">Parties:</strong> ' + analysis.entities.parties.join(', ') + '</div>';
          }

          if (analysis.entities.dates && analysis.entities.dates.length > 0) {
            entitiesHTML += '<div><strong class="text-emerald-700">Dates:</strong> ' + analysis.entities.dates.join(', ') + '</div>';
          }

          if (analysis.entities.amounts && analysis.entities.amounts.length > 0) {
            entitiesHTML += '<div><strong class="text-amber-700">Amounts:</strong> ' + analysis.entities.amounts.join(', ') + '</div>';
          }

          if (analysis.entities.obligations && analysis.entities.obligations.length > 0) {
            entitiesHTML += '<div><strong class="text-rose-700">Obligations:</strong><ul class="list-disc ml-4 mt-1">';
            analysis.entities.obligations.forEach(obl => {
              entitiesHTML += '<li>' + escapeHtml(obl) + '</li>';
            });
            entitiesHTML += '</ul></div>';
          }

          if (entitiesHTML) {
            entitiesDisplayEl.innerHTML = entitiesHTML;
          } else {
            entitiesDisplayEl.innerHTML = '<p class="text-slate-500">No entities extracted.</p>';
          }
        }

        // Display Wolfram computational analysis
        if (analysis.wolfram) {
          const wolfram = analysis.wolfram;

          // Transformations
          if (wolfram.transformations && wolfram.transformations.rule_count > 0) {
            wolframTransformCountEl.textContent = wolfram.transformations.rule_count + ' transformations found';

            // Render transformations with color-coded categories
            let transformHTML = '<div class="space-y-2">';
            wolfram.transformations.transformations.forEach(t => {
              const categoryColors = {
                'precision': 'blue',
                'enforceability': 'emerald',
                'risk-reduction': 'rose'
              };
              const color = categoryColors[t.category] || 'gray';

              transformHTML += `
                <div class="border border-${color}-200 rounded p-2 bg-${color}-50">
                  <div class="flex items-center justify-between mb-1">
                    <span class="text-[10px] font-semibold text-${color}-700 uppercase">${escapeHtml(t.category)}</span>
                    <span class="text-[10px] text-${color}-600">${Math.round(t.strength * 100)}% confidence</span>
                  </div>
                  <div class="text-xs">
                    <code class="bg-red-100 text-red-800 px-1 rounded">${escapeHtml(t.original)}</code>
                    <span class="text-gray-600"> → </span>
                    <code class="bg-green-100 text-green-800 px-1 rounded">${escapeHtml(t.suggested)}</code>
                  </div>
                </div>
              `;
            });
            transformHTML += '</div>';
            wolframTransformationsEl.innerHTML = transformHTML;

            // Improved text
            wolframImprovedTextEl.textContent = wolfram.transformations.improved_text || '—';
            copyImprovedBtn.style.opacity = '1'; // Show copy button
          } else {
            wolframTransformCountEl.textContent = 'No transformations needed';
            wolframTransformationsEl.innerHTML = '<p class="text-purple-600 italic">This clause is already well-formed. No transformation suggestions.</p>';
            wolframImprovedTextEl.innerHTML = '<p class="text-emerald-600 italic font-sans">No improvements needed - clause is already optimal.</p>';
            copyImprovedBtn.style.opacity = '0'; // Hide copy button
          }

          // Multiway evolution
          if (wolfram.multiway && wolfram.multiway.state_count > 0) {
            wolframStateCountEl.textContent = wolfram.multiway.state_count + ' alternative interpretation(s)';

            // Show terminal states
            let multiwayHTML = '<div class="text-xs text-indigo-700">';
            multiwayHTML += '<p class="mb-2">Wolfram multiway graph generated ' + wolfram.multiway.state_count + ' possible interpretation paths.</p>';
            multiwayHTML += '<p class="text-[10px] text-indigo-600">Terminal states: ' + wolfram.multiway.terminal_states.join(', ') + '</p>';
            multiwayHTML += '</div>';
            wolframMultiwayEl.innerHTML = multiwayHTML;
          } else {
            wolframStateCountEl.textContent = '1 state (deterministic)';
            wolframMultiwayEl.innerHTML = '<p class="text-indigo-600 italic">Clause has deterministic interpretation (no branching paths).</p>';
          }

          // Performance
          if (wolfram.performance) {
            wolframPerformanceEl.textContent = `Wolfram analysis: ${wolfram.performance.execution_time_ms}ms (Torvalds-optimized: O(n) algorithms)`;
          }
        }

        renderHistory(data.history);
      }

      function renderHistory(history) {
        if (!history || !history.length) {
          historyList.innerHTML = '<p class="text-xs text-slate-500">No analyses yet.</p>';
          return;
        }

        let html = '<div class="space-y-2">';
        history.forEach((h, idx) => {
          const qualityColor = h.quality >= 0.75 ? 'text-emerald-600' :
                              h.quality >= 0.5 ? 'text-amber-600' : 'text-rose-600';

          html += `
            <div class="border rounded p-2 bg-slate-50 text-xs hover:bg-slate-100 cursor-pointer">
              <div class="flex items-center justify-between">
                <div class="font-mono text-[10px] text-slate-500">#${history.length - idx}</div>
                <div class="font-mono text-[10px] ${qualityColor} font-semibold">Q: ${Math.round(h.quality * 100)}%</div>
              </div>
              <div class="text-slate-800 mt-1 truncate">${escapeHtml(h.clause)}</div>
              <div class="text-[10px] text-slate-500 mt-1">${escapeHtml(h.docType)}</div>
              <div class="flex gap-2 mt-1 text-[10px]">
                <span class="text-blue-600">C:${Math.round(h.clarity * 100)}</span>
                <span class="text-emerald-600">E:${Math.round(h.enforceability * 100)}</span>
                <span class="text-rose-600">R:${Math.round(h.risk * 100)}</span>
                <span class="text-amber-600">P:${Math.round(h.completeness * 100)}</span>
              </div>
            </div>
          `;
        });
        html += '</div>';
        historyList.innerHTML = html;
      }

      function clearAnalysis() {
        clarityScoreEl.textContent = '—';
        clarityNotesEl.textContent = '—';
        enforceabilityScoreEl.textContent = '—';
        enforceabilityNotesEl.textContent = '—';
        riskScoreEl.textContent = '—';
        riskNotesEl.textContent = '—';
        completenessScoreEl.textContent = '—';
        completenessNotesEl.textContent = '—';
        overallQualityEl.textContent = '—';
        stateExplanationEl.textContent = 'Analyze a clause to see quality assessment and document type classification.';
        entitiesDisplayEl.innerHTML = '<p class="text-slate-500">Parties, dates, amounts, and obligations will appear here after analysis.</p>';
        docTypeEl.textContent = 'Document type will appear here';
        statePill.textContent = 'Cleared';
        statePill.className = 'inline-flex items-center rounded-full border border-slate-300 bg-slate-50 text-slate-700 px-2 py-0.5 text-[11px] font-medium';
      }

      function escapeHtml(str) {
        if (!str) return '';
        return String(str)
          .replace(/&/g, '&amp;')
          .replace(/</g, '&lt;')
          .replace(/>/g, '&gt;')
          .replace(/"/g, '&quot;');
      }

      // Event listeners
      scanButtons.forEach(btn => {
        btn.addEventListener('click', () => {
          runAction(btn.getAttribute('data-action'));
        });
      });

      document.getElementById('btnClearAnalysis')?.addEventListener('click', clearAnalysis);
      btnClearHistory?.addEventListener('click', () => runAction('clear_history'));
      btnExportHistory?.addEventListener('click', () => runAction('export_history'));

      // Keyboard shortcuts
      document.addEventListener('keydown', (ev) => {
        const isMac = navigator.platform.toUpperCase().indexOf('MAC') >= 0;
        const meta = isMac ? ev.metaKey : ev.ctrlKey;
        if (!meta || ev.key !== 'Enter') return;

        ev.preventDefault();
        runAction(ev.shiftKey ? 'scan_rewrite' : 'scan');
      });
    });
  </script>
</body>
</html>
