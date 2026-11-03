<?php
// Read-only bridge: use the legacy app's configured driver without importing its users.
ini_set('display_errors', 'stderr');
$root = $argv[1];
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db = $app->make('db')->connection();
$driver = $db->getDriverName();
if ($driver === 'pgsql') {
    $db->beginTransaction();
    $db->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ READ ONLY');
} elseif ($driver === 'mysql') {
    $db->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $db->statement('SET TRANSACTION READ ONLY');
    $db->beginTransaction();
} elseif ($driver === 'sqlite') {
    $db->statement('PRAGMA query_only = ON');
    $db->beginTransaction();
} else {
    throw new RuntimeException('Unsupported legacy database driver');
}
try {
    $data = [];
    foreach (['wallets', 'transaction_templates', 'loans', 'loan_custom_schedules', 'payments', 'recurring_items', 'recurring_occurrences', 'holidays', 'settings'] as $table) {
        $data[$table] = $db->table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
    }
    // Older loans may have calculated schedules without persisted periods.
    $data['calculated_schedules'] = [];
    foreach ($data['loans'] as $loan) {
        $stored = array_filter($data['loan_custom_schedules'], fn ($row) => $row['loan_id'] == $loan['id']);
        if ($loan['type'] === 'bank' && !$stored) {
            if ($loan['interest_calculation_method'] === 'custom') {
                throw new RuntimeException('Custom loan has no stored schedule: '.$loan['id']);
            }
            $rows = $app->make(Wallets\Lending\Application\LoanSchedulePreviewService::class)->build($loan);
            foreach ($rows as $row) {
                $row['loan_id'] = $loan['id'];
                $data['calculated_schedules'][] = $row;
            }
        }
    }
    echo json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
} finally {
    // The bridge never commits changes to the source.
    $db->getPdo()->exec('ROLLBACK');
}
