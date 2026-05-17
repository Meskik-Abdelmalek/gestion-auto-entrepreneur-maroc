<?php
// ── CSV Bank Statement Import ─────────────────────────────────
require_once __DIR__ . '/includes/functions.php';
$pageName = 'Import Relevé Bancaire';
$db = getDB();

// ── Bank CSV format definitions ───────────────────────────────
// Each format describes how to parse a specific bank's CSV export.
// 'detect' is a regex run against the raw first 3 lines of the file.
const BANK_FORMATS = [
    'attijariwafa' => [
        'label'      => 'Attijariwafa Bank',
        'encoding'   => 'UTF-8',
        'delimiter'  => ';',
        'skip_rows'  => 7,           // rows before actual data
        'col_date'   => 0,
        'col_desc'   => 2,
        'col_debit'  => 3,
        'col_credit' => 4,
        'date_fmt'   => 'd/m/Y',
        'detect'     => '/Attijariwafa|ATTIJARIWAFA|CIH|AWB/i',
        'amount_clean' => true,       // remove spaces & replace comma
    ],
    'bmce' => [
        'label'      => 'BMCE Bank of Africa',
        'encoding'   => 'UTF-8',
        'delimiter'  => ';',
        'skip_rows'  => 5,
        'col_date'   => 0,
        'col_desc'   => 1,
        'col_debit'  => 3,
        'col_credit' => 4,
        'date_fmt'   => 'd/m/Y',
        'detect'     => '/BMCE|Bank.of.Africa/i',
        'amount_clean' => true,
    ],
    'ocp' => [
        'label'      => 'OCP / Barid Bank',
        'encoding'   => 'ISO-8859-1',
        'delimiter'  => ';',
        'skip_rows'  => 4,
        'col_date'   => 0,
        'col_desc'   => 2,
        'col_debit'  => 4,
        'col_credit' => 5,
        'date_fmt'   => 'd/m/Y',
        'detect'     => '/OCP|Barid|CCP/i',
        'amount_clean' => true,
    ],
    'cih' => [
        'label'      => 'CIH Bank',
        'encoding'   => 'UTF-8',
        'delimiter'  => ';',
        'skip_rows'  => 6,
        'col_date'   => 0,
        'col_desc'   => 1,
        'col_debit'  => 2,
        'col_credit' => 3,
        'date_fmt'   => 'd/m/Y',
        'detect'     => '/CIH|Credit Immobilier/i',
        'amount_clean' => true,
    ],
    'cashplus' => [
        'label'      => 'CashPlus',
        'encoding'   => 'UTF-8',
        'delimiter'  => ',',
        'skip_rows'  => 1,
        'col_date'   => 0,
        'col_desc'   => 1,
        'col_debit'  => 3,
        'col_credit' => 2,
        'date_fmt'   => 'Y-m-d',
        'detect'     => '/CashPlus|Cash.Plus/i',
        'amount_clean' => false,
    ],
    'generic' => [
        'label'      => 'CSV Générique (date;description;débit;crédit)',
        'encoding'   => 'UTF-8',
        'delimiter'  => ';',
        'skip_rows'  => 1,
        'col_date'   => 0,
        'col_desc'   => 1,
        'col_debit'  => 2,
        'col_credit' => 3,
        'date_fmt'   => 'd/m/Y',
        'detect'     => null,
        'amount_clean' => true,
    ],
];

// ── Load accounts list ────────────────────────────────────────
$accounts  = $db->query("SELECT * FROM ae_bank_accounts WHERE is_active=1 ORDER BY sort_order,id")->fetchAll();
$accountId = (int)($_GET['account'] ?? ($_POST['account_id'] ?? (count($accounts) ? $accounts[0]['id'] : 1)));

$flash   = null;
$preview = null;
$errors  = [];

// ── Handle POST ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    // ── Confirmed import ──────────────────────────────────────
    if (isset($_POST['confirm_import']) && isset($_SESSION['import_rows'])) {
        $rows      = $_SESSION['import_rows'];
        $accId     = (int)$_POST['account_id'];
        $fy        = (int)date('Y');
        $imported  = 0;
        $skipped   = 0;
        $format    = $_SESSION['import_format'] ?? 'generic';

        foreach ($rows as $row) {
            // Dedup by hash
            $hash = md5($row['date'] . '|' . $row['description'] . '|' . $row['credit'] . '|' . $row['debit']);
            $chk  = $db->prepare("SELECT COUNT(*) FROM ae_bank_transactions WHERE import_hash=? AND account_id=?");
            $chk->execute([$hash, $accId]);
            if ((int)$chk->fetchColumn() > 0) { $skipped++; continue; }

            $rowFy = $row['date'] ? (int)date('Y', strtotime($row['date'])) : $fy;
            $db->prepare("INSERT INTO ae_bank_transactions (account_id,transaction_date,description,credit,debit,fiscal_year,imported,import_hash) VALUES (?,?,?,?,?,?,1,?)")
               ->execute([$accId, $row['date'], $row['description'], $row['credit'], $row['debit'], $rowFy, $hash]);
            $imported++;
        }

        // Log import
        $db->prepare("INSERT INTO ae_csv_imports (account_id,bank_format,filename,rows_total,rows_imported,rows_skipped) VALUES (?,?,?,?,?,?)")
           ->execute([$accId, $format, $_SESSION['import_filename'] ?? '', count($rows), $imported, $skipped]);

        unset($_SESSION['import_rows'], $_SESSION['import_format'], $_SESSION['import_filename']);
        flash('message', "$imported transactions importées, $skipped doublons ignorés.");
        header("Location: /bank.php?account=$accId"); exit;
    }

    // ── Cancel / re-upload ────────────────────────────────────
    if (isset($_POST['cancel_import'])) {
        unset($_SESSION['import_rows'], $_SESSION['import_format'], $_SESSION['import_filename']);
        header("Location: /bank-import.php?account=$accountId"); exit;
    }

    // ── Parse uploaded file ───────────────────────────────────
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $accountId = (int)($_POST['account_id'] ?? $accountId);
        $tmpPath   = $_FILES['csv_file']['tmp_name'];
        $origName  = $_FILES['csv_file']['name'];
        $forceFmt  = clean($_POST['bank_format'] ?? '');

        [$rows, $detectedFmt, $parseErrors] = parseBankCsv($tmpPath, $forceFmt);

        if ($parseErrors) {
            $errors = $parseErrors;
        } elseif (empty($rows)) {
            $errors[] = 'Aucune transaction trouvée dans le fichier. Vérifiez le format sélectionné.';
        } else {
            $_SESSION['import_rows']     = $rows;
            $_SESSION['import_format']   = $detectedFmt;
            $_SESSION['import_filename'] = $origName;
            $preview = $rows;
        }
    }
}

// If session has pending preview
if (!$preview && isset($_SESSION['import_rows'])) {
    $preview = $_SESSION['import_rows'];
}

// ── CSV Parser ────────────────────────────────────────────────
function parseBankCsv(string $path, string $forceFormat = ''): array
{
    $raw = file_get_contents($path);
    if ($raw === false) return [[], 'generic', ['Impossible de lire le fichier.']];

    $formats = BANK_FORMATS;

    // Auto-detect format from file content
    $detected = $forceFormat ?: 'generic';
    if (!$forceFormat) {
        $head = implode("\n", array_slice(explode("\n", $raw), 0, 5));
        foreach ($formats as $key => $fmt) {
            if ($fmt['detect'] && preg_match($fmt['detect'], $head)) {
                $detected = $key;
                break;
            }
        }
    }

    $fmt = $formats[$detected] ?? $formats['generic'];

    // Convert encoding
    if (($fmt['encoding'] ?? 'UTF-8') !== 'UTF-8') {
        $raw = mb_convert_encoding($raw, 'UTF-8', $fmt['encoding']);
    }

    $lines = preg_split('/\r\n|\r|\n/', $raw);
    $skip  = (int)($fmt['skip_rows'] ?? 1);
    $delim = $fmt['delimiter'] ?? ';';

    $rows  = [];
    $errs  = [];
    $lineNo = 0;

    foreach ($lines as $line) {
        $lineNo++;
        if ($lineNo <= $skip) continue;
        $line = trim($line);
        if (!$line) continue;

        $cols = str_getcsv($line, $delim);

        $dateRaw   = trim($cols[$fmt['col_date']]   ?? '');
        $descRaw   = trim($cols[$fmt['col_desc']]   ?? '');
        $debitRaw  = trim($cols[$fmt['col_debit']]  ?? '');
        $creditRaw = trim($cols[$fmt['col_credit']] ?? '');

        if (!$dateRaw && !$descRaw) continue;  // blank row

        // Parse date
        $dateObj = DateTime::createFromFormat($fmt['date_fmt'], $dateRaw);
        if (!$dateObj) {
            // Try fallback formats
            foreach (['d/m/Y', 'Y-m-d', 'd-m-Y', 'm/d/Y'] as $tryFmt) {
                $dateObj = DateTime::createFromFormat($tryFmt, $dateRaw);
                if ($dateObj) break;
            }
        }
        if (!$dateObj) {
            $errs[] = "Ligne $lineNo : date non reconnue « $dateRaw »";
            if (count($errs) > 5) { $errs[] = '…et d\'autres erreurs.'; break; }
            continue;
        }
        $dateIso = $dateObj->format('Y-m-d');

        // Clean amounts
        $cleanAmt = function(string $v) use ($fmt): float {
            if ($fmt['amount_clean'] ?? true) {
                $v = str_replace([' ', "\u{00A0}", "\u{202F}"], '', $v);
                $v = str_replace(',', '.', $v);
            }
            $v = preg_replace('/[^\d.]/', '', $v);
            return (float)$v;
        };

        $debit  = $cleanAmt($debitRaw);
        $credit = $cleanAmt($creditRaw);

        if ($debit == 0 && $credit == 0 && !$descRaw) continue;

        $rows[] = [
            'date'        => $dateIso,
            'description' => $descRaw,
            'debit'       => $debit,
            'credit'      => $credit,
        ];
    }

    return [$rows, $detected, $errs];
}

require_once __DIR__ . '/includes/header.php';
$flashMsg = flash('message');
?>

<?php if ($flashMsg): ?>
<div class="mb-4 px-4 py-3 rounded-xl text-sm font-medium <?= $flashMsg['type']==='error'?'bg-red-50 text-red-700':'bg-green-50 text-green-700' ?>">
    <?= h($flashMsg['msg']) ?>
</div>
<?php endif; ?>

<div class="flex items-center gap-3 mb-5">
    <a href="/bank-accounts.php" class="btn-f px-3 py-2 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl text-fluent-n2 hover:bg-fluent-n6 bg-white dark:bg-gray-800">← Comptes</a>
    <div>
        <h1 class="text-lg font-bold text-fluent-neutral">Import Relevé Bancaire</h1>
        <p class="text-xs text-fluent-n3">Supporte OCP, Attijariwafa, BMCE, CIH, CashPlus</p>
    </div>
</div>

<?php if ($preview): ?>
<!-- ── Preview before confirming ────────────────────────────── -->
<div class="bg-white dark:bg-gray-800 rounded-2xl shadow-f p-5 mb-4">
    <div class="flex items-center justify-between mb-4">
        <div>
            <h2 class="font-semibold text-sm text-fluent-neutral">
                Aperçu — <?= count($preview) ?> transactions à importer
            </h2>
            <p class="text-xs text-fluent-n3 mt-0.5">Format détecté : <?= h(BANK_FORMATS[$_SESSION['import_format'] ?? 'generic']['label'] ?? 'Générique') ?></p>
        </div>
    </div>

    <!-- Preview table -->
    <div class="overflow-x-auto max-h-96 overflow-y-auto mb-4">
        <table class="w-full text-sm">
            <thead class="sticky top-0 bg-fluent-n7 dark:bg-gray-900">
                <tr class="border-b border-fluent-n5 dark:border-white/10">
                    <th class="text-left px-3 py-2 text-xs font-semibold text-fluent-n3">DATE</th>
                    <th class="text-left px-3 py-2 text-xs font-semibold text-fluent-n3">DESCRIPTION</th>
                    <th class="text-right px-3 py-2 text-xs font-semibold text-fluent-green">CRÉDIT</th>
                    <th class="text-right px-3 py-2 text-xs font-semibold text-fluent-red">DÉBIT</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-fluent-n6 dark:divide-white/5">
                <?php foreach (array_slice($preview, 0, 50) as $row): ?>
                <tr class="hover:bg-fluent-n7 dark:hover:bg-white/5">
                    <td class="px-3 py-2 text-xs font-mono text-fluent-n3"><?= date('d/m/Y', strtotime($row['date'])) ?></td>
                    <td class="px-3 py-2 text-sm text-fluent-neutral max-w-xs truncate"><?= h($row['description']) ?></td>
                    <td class="px-3 py-2 text-right text-sm font-semibold <?= $row['credit']>0?'text-fluent-green':'text-fluent-n4' ?>">
                        <?= $row['credit']>0 ? '+'.money($row['credit']) : '—' ?>
                    </td>
                    <td class="px-3 py-2 text-right text-sm font-semibold <?= $row['debit']>0?'text-fluent-red':'text-fluent-n4' ?>">
                        <?= $row['debit']>0 ? '-'.money($row['debit']) : '—' ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (count($preview) > 50): ?>
                <tr><td colspan="4" class="px-3 py-2 text-center text-xs text-fluent-n3">…et <?= count($preview)-50 ?> autres transactions</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Confirm / Cancel -->
    <form method="POST" class="flex gap-3">
        <input type="hidden" name="_csrf" value="<?= $csrf ?>">
        <input type="hidden" name="account_id" value="<?= $accountId ?>">
        <button type="submit" name="confirm_import" value="1"
            class="btn-f px-5 py-2.5 bg-fluent-green text-white rounded-xl text-sm font-semibold hover:bg-green-700">
            ✓ Confirmer l'import (<?= count($preview) ?> transactions)
        </button>
        <button type="submit" name="cancel_import" value="1"
            class="btn-f px-5 py-2.5 border border-fluent-n4 dark:border-white/20 rounded-xl text-sm text-fluent-n2 hover:bg-fluent-n6">
            Annuler
        </button>
    </form>
</div>

<?php else: ?>
<!-- ── Upload form ──────────────────────────────────────────── -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

    <!-- Main form -->
    <div class="lg:col-span-2">
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-f p-5">
            <h2 class="font-semibold text-sm text-fluent-neutral mb-4">Importer un relevé CSV</h2>

            <?php if ($errors): ?>
            <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700 space-y-1">
                <?php foreach ($errors as $e): ?><div>⚠️ <?= h($e) ?></div><?php endforeach; ?>
            </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" name="_csrf" value="<?= $csrf ?>">

                <!-- Account selector -->
                <div>
                    <label class="block text-xs font-medium text-fluent-n2 mb-1">Compte *</label>
                    <select name="account_id" class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl bg-white dark:bg-gray-800 inp">
                        <?php foreach ($accounts as $acc): ?>
                        <option value="<?= $acc['id'] ?>" <?= $acc['id']===$accountId?'selected':'' ?>>
                            <?= h($acc['name']) ?> — <?= h($acc['bank_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Bank format -->
                <div>
                    <label class="block text-xs font-medium text-fluent-n2 mb-1">Format (auto-détecté si non sélectionné)</label>
                    <select name="bank_format" class="w-full px-3 py-2.5 text-sm border border-fluent-n4 dark:border-white/20 rounded-xl bg-white dark:bg-gray-800 inp">
                        <option value="">Auto-détection</option>
                        <?php foreach (BANK_FORMATS as $key => $fmt): ?>
                        <option value="<?= $key ?>"><?= h($fmt['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- File upload -->
                <div>
                    <label class="block text-xs font-medium text-fluent-n2 mb-1">Fichier CSV *</label>
                    <div class="border-2 border-dashed border-fluent-n4 dark:border-white/20 rounded-xl p-6 text-center cursor-pointer hover:border-fluent-blue transition-colors"
                        onclick="document.getElementById('csv-file-input').click()">
                        <div class="text-3xl mb-2">📄</div>
                        <div class="text-sm font-medium text-fluent-n2">Cliquez ou glissez votre fichier CSV</div>
                        <div class="text-xs text-fluent-n3 mt-1">Formats acceptés : .csv, .txt — Max 5 Mo</div>
                        <div id="file-name" class="text-xs text-fluent-blue mt-2 hidden"></div>
                    </div>
                    <input type="file" id="csv-file-input" name="csv_file" accept=".csv,.txt" required class="hidden"
                        onchange="document.getElementById('file-name').textContent=this.files[0].name;document.getElementById('file-name').classList.remove('hidden')">
                </div>

                <button type="submit" class="w-full btn-f py-3 bg-fluent-blue text-white rounded-xl text-sm font-semibold hover:bg-fluent-blue-dk">
                    Analyser le fichier →
                </button>
            </form>
        </div>
    </div>

    <!-- Format guide -->
    <div class="space-y-3">
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-f p-5">
            <h3 class="font-semibold text-xs text-fluent-n2 uppercase tracking-wider mb-3">Banques supportées</h3>
            <div class="space-y-2">
                <?php foreach (BANK_FORMATS as $key => $fmt): if ($key === 'generic') continue; ?>
                <div class="flex items-center gap-2 text-sm">
                    <span class="w-2 h-2 rounded-full bg-fluent-green flex-shrink-0"></span>
                    <span class="text-fluent-neutral font-medium"><?= h($fmt['label']) ?></span>
                </div>
                <?php endforeach; ?>
                <div class="flex items-center gap-2 text-sm mt-2 pt-2 border-t border-fluent-n5 dark:border-white/10">
                    <span class="w-2 h-2 rounded-full bg-amber-400 flex-shrink-0"></span>
                    <span class="text-fluent-n2">CSV Générique (fallback)</span>
                </div>
            </div>
        </div>

        <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 rounded-2xl p-4 text-xs text-amber-800 dark:text-amber-200 space-y-1.5">
            <div class="font-semibold">💡 Comment exporter votre relevé</div>
            <div><strong>Attijariwafa / CIH :</strong> Espace Client → Relevé de compte → Exporter CSV</div>
            <div><strong>BMCE :</strong> Mes Comptes → Historique → Télécharger</div>
            <div><strong>OCP :</strong> Mon espace → Opérations → Export</div>
            <div><strong>CashPlus :</strong> Dashboard → Historique → Export CSV</div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
