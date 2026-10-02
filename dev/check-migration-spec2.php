<?php

/**
 * Run the #185 upgrade migration against the development database and check
 * what it did.
 *
 * Both datasets start on 2.0, so nothing automated ever sees a `latest` or
 * `1.0` row or a `latest` column default, and the migration's database half —
 * the rows it moves, the column it alters, the second run that must change
 * nothing — is covered by nothing else. `I185MoveRecordsToConfigSpec2UnitTest`
 * covers the one rule that needs no database.
 *
 * It seeds three rows of its own (submission ids 9990001-9990003), puts the
 * column default back to `latest`, runs the migration twice and asserts. It
 * removes its rows afterwards, but **the migration itself moves every `latest`
 * and `1.0` row in the table**, which is what it is for, so a real legacy
 * record in the database it is pointed at is moved as well.
 *
 * It refuses to run unless the OJS install's plugin symlink points at this
 * checkout: the migration class is autoloaded through that symlink, and the
 * check would otherwise test somebody else's branch (see CLAUDE.md).
 *
 * Usage (see the check-migration target in the Makefile):
 *
 *   php dev/check-migration-spec2.php OJS_ROOT
 */

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$ojsRoot = $argv[1] ?? getenv('OJS_ROOT') ?: '';
if ($ojsRoot === '' || !is_file("{$ojsRoot}/tools/bootstrap.php")) {
    fwrite(STDERR, "No OJS installation at '{$ojsRoot}'; pass OJS_ROOT.\n");
    exit(2);
}

$linked = realpath("{$ojsRoot}/plugins/generic/codecheck");
$here = realpath(__DIR__ . '/..');
if ($linked !== $here) {
    fwrite(STDERR, "The OJS plugin symlink points at {$linked}, not at {$here}; it would test that code.\n");
    exit(2);
}

chdir($ojsRoot);
require "{$ojsRoot}/tools/bootstrap.php";

use APP\plugins\generic\codecheck\classes\migration\upgrade\I185_MoveRecordsToConfigSpec2 as Migration;

// What the script seeds, by submission id; `2.1` is a version the migration
// must leave alone.
$seed = [9990001 => 'latest', 9990002 => '1.0', 9990003 => '2.1'];
$failures = 0;
$check = function (string $what, bool $ok) use (&$failures): void {
    echo ($ok ? '  ok   ' : '  FAIL ') . $what . "\n";
    $failures += $ok ? 0 : 1;
};
$versions = fn () => DB::table('codecheck_metadata')
    ->whereIn('submission_id', array_keys($seed))
    ->orderBy('submission_id')
    ->pluck('spec_version', 'submission_id')
    ->all();
$columnDefault = function (): ?string {
    $column = collect(Schema::getColumns('codecheck_metadata'))->firstWhere('name', 'spec_version');

    return $column['default'] ?? null;
};

$now = date('Y-m-d H:i:s');
try {
    foreach ($seed as $id => $version) {
        DB::table('codecheck_metadata')->where('submission_id', $id)->delete();
        DB::table('codecheck_metadata')->insert([
            'submission_id' => $id, 'spec_version' => $version, 'created_at' => $now, 'updated_at' => $now,
        ]);
    }
    DB::statement("ALTER TABLE codecheck_metadata ALTER spec_version SET DEFAULT 'latest'");

    echo 'Before: ' . json_encode($versions()) . ', default ' . var_export($columnDefault(), true) . "\n";
    $check('seeded the legacy default', Migration::defaultIs($columnDefault(), 'latest'));

    echo "First run\n";
    (new Migration())->up();
    $check('`latest` moved to 2.0', ($versions()[9990001] ?? null) === '2.0');
    $check('`1.0` moved to 2.0', ($versions()[9990002] ?? null) === '2.0');
    $check('a version the migration does not retire is left alone', ($versions()[9990003] ?? null) === '2.1');
    $check('the column default is 2.0', Migration::defaultIs($columnDefault(), '2.0'));

    $afterFirst = $versions();
    echo "Second run\n";
    (new Migration())->up();
    $check('changes nothing', $versions() === $afterFirst && Migration::defaultIs($columnDefault(), '2.0'));
} finally {
    DB::table('codecheck_metadata')->whereIn('submission_id', array_keys($seed))->delete();
}

echo $failures === 0 ? "All checks passed.\n" : "{$failures} check(s) failed.\n";
exit($failures === 0 ? 0 : 1);
