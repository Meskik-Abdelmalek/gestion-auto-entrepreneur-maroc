<?php
// ── includes/multi_activity.php ───────────────────────────────
// Improved multi-activity support for v2.1.
//
// An "activity" in Moroccan AE law maps directly to fiscal category:
//   - Service   (IR 1%, plafond 200 000 MAD/an)
//   - Commerce  (IR 0.5%, plafond 500 000 MAD/an)
//   - Industrie (IR 0.5%, plafond 500 000 MAD/an — assimilé commerce)
//
// Additionally, the user can set up to 3 free-text activity labels
// (e.g. "Développement Web", "Formation", "Vente de matériel").
// This module provides helpers to:
//   1. Get the activity→category mapping
//   2. Compute per-activity revenue breakdown
//   3. Render the activity selector for invoice/quote forms
//   4. Compute IR per activity
//   5. Return a consolidated plafond warning per activity bucket

// ── getActivityMap ────────────────────────────────────────────
// Returns array of ['label'=>…, 'category'=>…] for all configured activities.
function getActivityMap(): array
{
    $cfg    = getConfig();
    $result = [];
    for ($i = 1; $i <= 3; $i++) {
        $label = trim($cfg["activity_$i"] ?? '');
        if (!$label) continue;
        // Heuristic: if the label contains "commerce" or "vente", map to Commerce.
        // Otherwise default to Service. User can override in invoice form.
        $cat = preg_match('/commerce|vente|négoce|revente|boutique/i', $label) ? 'Commerce' : 'Service';
        $result[] = ['label' => $label, 'category' => $cat];
    }
    if (empty($result)) {
        $result[] = ['label' => 'Activité principale', 'category' => 'Service'];
    }
    return $result;
}

// ── getActivityRevenue ────────────────────────────────────────
// Returns per-activity revenue breakdown for a given fiscal year.
// Result: array keyed by activity label → ['paid', 'pending', 'category', 'ir', 'count']
function getActivityRevenue(int $fiscalYear): array
{
    $db  = getDB();
    $cfg = getConfig();

    // Fetch all invoices for the year, grouped by activity + category
    $st = $db->prepare("
        SELECT
            COALESCE(NULLIF(activity, ''), category) AS activity_key,
            category,
            COALESCE(SUM(CASE WHEN status='Payé'       THEN amount_ttc ELSE 0 END), 0) AS paid,
            COALESCE(SUM(CASE WHEN status='En attente' THEN amount_ttc ELSE 0 END), 0) AS pending,
            COUNT(*)                                                                    AS invoice_count
        FROM ae_invoices
        WHERE fiscal_year = ?
        GROUP BY activity_key, category
        ORDER BY paid DESC
    ");
    $st->execute([$fiscalYear]);
    $rows = $st->fetchAll();

    $irRateSvc = (float)($cfg['ir_rate_services'] ?? 0.01);
    $irRateCom = (float)($cfg['ir_rate_commerce'] ?? 0.005);

    $result = [];
    foreach ($rows as $row) {
        $irRate = ($row['category'] === 'Service') ? $irRateSvc : $irRateCom;
        $result[$row['activity_key']] = [
            'label'    => $row['activity_key'],
            'category' => $row['category'],
            'paid'     => (float)$row['paid'],
            'pending'  => (float)$row['pending'],
            'ir'       => (float)$row['paid'] * $irRate,
            'ir_rate'  => $irRate,
            'count'    => (int)$row['invoice_count'],
        ];
    }
    return $result;
}

// ── getPlafondStatus ──────────────────────────────────────────
// Returns ceiling usage per fiscal category.
// Result: ['Service' => ['paid'=>…,'ceiling'=>…,'pct'=>…,'alert'=>…], 'Commerce'=>…]
function getPlafondStatus(int $fiscalYear): array
{
    $db  = getDB();
    $cfg = getConfig();

    $ceilSvc = (float)($cfg['ceiling_services'] ?? 200000);
    $ceilCom = (float)($cfg['ceiling_commerce'] ?? 500000);

    $st = $db->prepare("
        SELECT category,
               COALESCE(SUM(CASE WHEN status='Payé' THEN amount_ttc ELSE 0 END),0) AS paid
        FROM ae_invoices
        WHERE fiscal_year=?
        GROUP BY category
    ");
    $st->execute([$fiscalYear]);

    $data = ['Service' => 0, 'Commerce' => 0, 'Industrie' => 0];
    foreach ($st->fetchAll() as $r) {
        $data[$r['category']] = (float)$r['paid'];
    }

    $alertLevels = [
        (float)($cfg['alert_red']    ?? 0.95) => 'red',
        (float)($cfg['alert_orange'] ?? 0.85) => 'orange',
        (float)($cfg['alert_yellow'] ?? 0.75) => 'yellow',
    ];
    krsort($alertLevels);

    $result = [];
    foreach ([
        'Service'   => $ceilSvc,
        'Commerce'  => $ceilCom,
        'Industrie' => $ceilCom,
    ] as $cat => $ceiling) {
        $paid = $data[$cat] ?? 0;
        $pct  = $ceiling > 0 ? $paid / $ceiling : 0;

        $alert = 'normal';
        foreach ($alertLevels as $threshold => $level) {
            if ($pct >= $threshold) { $alert = $level; break; }
        }

        $result[$cat] = [
            'paid'    => $paid,
            'ceiling' => $ceiling,
            'pct'     => $pct,
            'alert'   => $alert,
            'remaining' => max(0, $ceiling - $paid),
        ];
    }
    return $result;
}

// ── renderActivitySelector ────────────────────────────────────
// Outputs an HTML <select> for activity selection in invoice/quote forms.
// $name: input name attribute
// $selected: current value
// $showCategories: if true, show category in option text
function renderActivitySelector(string $name, string $selected = '', bool $showCategories = true): string
{
    $activities = getActivityMap();
    $cfg        = getConfig();
    $html       = '<select name="' . htmlspecialchars($name, ENT_QUOTES) . '" ';
    $html      .= 'class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl bg-white dark:bg-gray-800 inp" ';
    $html      .= 'onchange="updateCategoryFromActivity(this)">';
    $html      .= '<option value="">— Sélectionner —</option>';

    foreach ($activities as $act) {
        $val  = htmlspecialchars($act['label'], ENT_QUOTES);
        $label = $showCategories
            ? htmlspecialchars($act['label'] . ' (' . $act['category'] . ')', ENT_QUOTES)
            : htmlspecialchars($act['label'], ENT_QUOTES);
        $sel  = ($selected === $act['label']) ? ' selected' : '';
        $html .= "<option value=\"$val\" data-category=\"{$act['category']}\"$sel>$label</option>";
    }
    $html .= '</select>';
    $html .= '<script>
function updateCategoryFromActivity(sel) {
    var opt = sel.options[sel.selectedIndex];
    var cat = opt.getAttribute("data-category");
    if (cat) {
        var catSel = document.querySelector("[name=\'category\']");
        if (catSel) catSel.value = cat;
    }
}
</script>';
    return $html;
}

// ── getMultiActivityDashboardData ─────────────────────────────
// Returns a rich array for the dashboard's multi-activity card.
function getMultiActivityDashboardData(int $fiscalYear): array
{
    $activities = getActivityRevenue($fiscalYear);
    $plafonds   = getPlafondStatus($fiscalYear);
    $map        = getActivityMap();

    // Total IR due across all activities
    $totalIR    = array_sum(array_column($activities, 'ir'));
    $totalPaid  = array_sum(array_column($activities, 'paid'));

    return [
        'activities'  => $activities,
        'plafonds'    => $plafonds,
        'activity_map'=> $map,
        'total_ir'    => $totalIR,
        'total_paid'  => $totalPaid,
        'has_multi'   => count($map) > 1,
    ];
}
