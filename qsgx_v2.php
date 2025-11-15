<?php
/**
 * QSG Ruliad Console v2.0
 * Improved Edition - Integrating Knuth, Wolfram, and Torvalds Perspectives
 *
 * This version integrates:
 * - config.php: Centralized constants with mathematical justification
 * - security.php: CSRF, session security, rate limiting, input validation
 * - analysis_core.php: Optimized core analysis functions
 *
 * IMPROVEMENTS:
 * - KNUTH: Single-pass algorithms, documented constants, no duplication
 * - WOLFRAM: 2³ ruliad state space, computational transformations
 * - TORVALDS: Security hardening, error handling, performance optimization
 *
 * @version 2.0
 * @author  QSG Ruliad Team
 */

declare(strict_types=1);

// Load all improved modules
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/analysis_core.php';
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

// Default clause for initial load
$default_clause = "for the claim of the treaty is with the protection of the lands by the council within this venue under the natural law";

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

        $logger->debug('Cache MISS - computing analysis', ['clause_length' => strlen($clause)]);

        // KNUTH: Optimized single-pass analysis
        $tokens = classify_tokens(tokenize_clause($clause));
        $qsg    = compute_qsg($tokens);
        $logic  = compute_logic($tokens);
        $kant   = compute_kant($tokens);
        $fol    = build_fol($tokens);
        $tone   = build_tone_summary($tokens);
        $ambiguity = compute_ambiguity($tokens);
        $modal_profile = compute_modal_profile($tokens);
        $aap = extract_aap($tokens);

        // WOLFRAM: Compute ruliad state
        $bits = scores_to_bits($qsg['score'], $logic['score'], $kant['score']);
        $bit_string = $bits['q'] . $bits['l'] . $bits['k'];

        $labels = [
            'qsgLabel'   => $qsg['label'],
            'logicLabel' => $logic['label'],
            'kantLabel'  => $kant['label'],
        ];
        $state_explanation = explain_bits($bits, $labels);

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
            'qsg'              => $qsg,
            'logic'            => $logic,
            'kant'             => $kant,
            'fol'              => $fol,
            'toneSummary'      => $tone,
            'ambiguity'        => $ambiguity,
            'modalProfile'     => $modal_profile,
            'aap'              => $aap,
            'bits'             => $bits,
            'bitString'        => $bit_string,
            'stateExplanation' => $state_explanation,
            'rewritten'        => $rewritten,
            'diffHtml'         => $diff_html,
        ];

        $logger->debug('Analysis complete', [
            'qsg_score' => $qsg['score'],
            'logic_score' => $logic['score'],
            'kant_score' => $kant['score'],
            'state' => $bit_string,
        ]);

        // Determine state pill
        if ($qsg['score'] >= 0.6 && $logic['score'] >= 0.6 && $kant['score'] >= 0.6) {
            $state_pill = ['text' => 'Aligned triad (Q+L+K)', 'tone' => 'ok'];
        } elseif ($kant['score'] < 0.4) {
            $state_pill = ['text' => 'Possible CI issue', 'tone' => 'bad'];
        } else {
            $state_pill = ['text' => 'Partial alignment', 'tone' => 'warn'];
        }

        // TORVALDS: Add to bounded history
        $entry = [
            'timestamp' => time(),
            'clause'    => $analysis['normalized'],
            'bits'      => $bits,
            'qsg'       => $qsg['score'],
            'logic'     => $logic['score'],
            'kant'      => $kant['score'],
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
  <title>QSG Logic &amp; Kant Lab – Ruliad Console v2.0 (Improved Edition)</title>
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
        <strong>Improved Edition</strong> – Knuth (algorithms) · Wolfram (ruliad) · Torvalds (security)
        <span class="font-mono text-xs text-slate-500 block md:inline">
          QSG (grammar) · FOL (logic) · CI (Kant) · 2³ ruliad state space · CSRF protected
        </span>
      </p>
    </header>

    <!-- Input + Controls -->
    <section class="bg-white border border-slate-200 rounded-xl shadow-sm p-4 space-y-3">
      <form class="space-y-3" id="analysisForm" autocomplete="off">
        <!-- CSRF Token -->
        <input type="hidden" name="csrf_token" id="csrfToken" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>" />

        <div class="flex flex-wrap items-center gap-2 justify-between">
          <div class="flex items-center gap-2">
            <span class="text-sm font-medium text-slate-900">Clause Input</span>
            <span id="statePill"
              class="<?php echo state_pill_class($state_pill['tone']); ?>">
              <?php echo htmlspecialchars($state_pill['text'], ENT_QUOTES, 'UTF-8'); ?>
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
        ><?php echo htmlspecialchars($initial_clause, ENT_QUOTES, 'UTF-8'); ?></textarea>

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

        <!-- QSG Score -->
        <div class="mt-4 space-y-2">
          <div>
            <span class="text-xs font-semibold text-slate-900">QSG Score</span>
            <span id="qsgScore" class="text-xs text-slate-500 ml-2">—</span>
          </div>
          <p id="qsgNotes" class="text-xs text-slate-600 border rounded p-2 bg-slate-50">—</p>
        </div>

        <!-- Logic Score -->
        <div class="mt-4 space-y-2">
          <div>
            <span class="text-xs font-semibold text-slate-900">Logic Score</span>
            <span id="logicScore" class="text-xs text-slate-500 ml-2">—</span>
          </div>
          <p id="logicNotes" class="text-xs text-slate-600 border rounded p-2 bg-slate-50">—</p>
        </div>

        <!-- Kant CI Score -->
        <div class="mt-4 space-y-2">
          <div>
            <span class="text-xs font-semibold text-slate-900">Kant CI Score</span>
            <span id="kantScore" class="text-xs text-slate-500 ml-2">—</span>
          </div>
          <p id="kantNotes" class="text-xs text-slate-600 border rounded p-2 bg-slate-50">—</p>
        </div>

        <!-- FOL Formula -->
        <div class="mt-4 space-y-2">
          <span class="text-xs font-semibold text-slate-900">First-Order Logic Formula</span>
          <pre id="formulaBox" class="text-xs text-slate-800 border rounded p-2 bg-slate-50 overflow-x-auto">—</pre>
        </div>

        <!-- State Explanation -->
        <div class="mt-4 space-y-2">
          <span class="text-xs font-semibold text-slate-900">Ruliad State Explanation</span>
          <p id="stateExplanation" class="text-xs text-slate-700 border rounded p-2 bg-slate-50">
            Run a scan to project the clause into the 2³ ruliad state space.
          </p>
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
          <span class="font-mono">Ctrl/⌘+Enter</span> = Scan,
          <span class="font-mono">Ctrl/⌘+Shift+Enter</span> = Scan + Rewrite
        </span>
        <span class="font-mono">
          v2.0 (Improved Edition) – CSRF Protected · Rate Limited · Optimized
        </span>
      </div>
    </footer>
  </div>

  <!-- JavaScript -->
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const clauseInput = document.getElementById('clause');
      const csrfToken = document.getElementById('csrfToken');
      const statePill = document.getElementById('statePill');
      const toneSummary = document.getElementById('toneSummary');

      const qsgScoreEl = document.getElementById('qsgScore');
      const qsgNotesEl = document.getElementById('qsgNotes');
      const logicScoreEl = document.getElementById('logicScore');
      const logicNotesEl = document.getElementById('logicNotes');
      const kantScoreEl = document.getElementById('kantScore');
      const kantNotesEl = document.getElementById('kantNotes');
      const formulaBox = document.getElementById('formulaBox');
      const stateExplanationEl = document.getElementById('stateExplanation');
      const historyList = document.getElementById('historyList');

      const btnClearHistory = document.getElementById('btnClearHistory');
      const btnExportHistory = document.getElementById('btnExportHistory');
      const scanButtons = document.querySelectorAll('.btn-scan');

      function formatScore(score) {
        if (score === null || score === undefined || !isFinite(score)) return '—';
        return Math.round(score * 100) + '/100';
      }

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
          statePill.textContent = 'Working…';
          statePill.className = 'inline-flex items-center rounded-full border border-indigo-400 bg-indigo-50 text-indigo-700 px-2 py-0.5 text-[11px] font-medium';

          const res = await fetch(window.location.href, {
            method: 'POST',
            body: fd
          });

          if (!res.ok) {
            const err = await res.json();
            throw new Error(err.error || 'Request failed');
          }

          const data = await res.json();

          if (action === 'export_history') {
            triggerExport(data.export);
            renderHistory(data.history);
            statePill.textContent = 'History exported';
            statePill.className = 'inline-flex items-center rounded-full border border-emerald-400 bg-emerald-50 text-emerald-700 px-2 py-0.5 text-[11px] font-medium';
            return;
          }

          applyAnalysis(data);
        } catch (err) {
          console.error(err);
          statePill.textContent = 'Error: ' + err.message;
          statePill.className = 'inline-flex items-center rounded-full border border-rose-400 bg-rose-50 text-rose-700 px-2 py-0.5 text-[11px] font-medium';
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

        toneSummary.textContent = analysis.toneSummary || '';

        qsgScoreEl.textContent = formatScore(analysis.qsg?.score);
        qsgNotesEl.textContent = analysis.qsg?.notes || '—';
        logicScoreEl.textContent = formatScore(analysis.logic?.score);
        logicNotesEl.textContent = analysis.logic?.notes || '—';
        kantScoreEl.textContent = formatScore(analysis.kant?.score);
        kantNotesEl.textContent = analysis.kant?.notes || '—';

        formulaBox.textContent = (analysis.fol?.formula || '—') + '\n\n// ' + (analysis.fol?.notes || '');
        stateExplanationEl.textContent = analysis.stateExplanation || '';

        renderHistory(data.history);
      }

      function renderHistory(history) {
        if (!history || !history.length) {
          historyList.innerHTML = '<p class="text-xs text-slate-500">No scans yet.</p>';
          return;
        }

        let html = '<div class="space-y-2">';
        history.forEach((h, idx) => {
          html += `
            <div class="border rounded p-2 bg-slate-50 text-xs">
              <div class="font-mono text-[10px] text-slate-500">#${history.length - idx}</div>
              <div class="text-slate-800 mt-1">${escapeHtml(h.clause)}</div>
              <div class="text-slate-500 mt-1">Q:${Math.round(h.qsg * 100)} L:${Math.round(h.logic * 100)} K:${Math.round(h.kant * 100)}</div>
            </div>
          `;
        });
        html += '</div>';
        historyList.innerHTML = html;
      }

      function clearAnalysis() {
        qsgScoreEl.textContent = '—';
        qsgNotesEl.textContent = '—';
        logicScoreEl.textContent = '—';
        logicNotesEl.textContent = '—';
        kantScoreEl.textContent = '—';
        kantNotesEl.textContent = '—';
        formulaBox.textContent = '—';
        stateExplanationEl.textContent = 'Run a scan to project the clause into the 2³ ruliad state space.';
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
