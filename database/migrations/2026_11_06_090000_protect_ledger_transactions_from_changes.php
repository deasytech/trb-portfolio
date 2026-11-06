<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MESSAGE = 'Posted ledger transactions are immutable.';

    /**
     * Reject updates and deletes at the database level, covering bulk queries and event-less writes.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        foreach (['update', 'delete'] as $action) {
            $name = "ledger_transactions_no_{$action}";

            if ($driver === 'sqlite') {
                DB::unprepared("CREATE TRIGGER {$name} BEFORE ".strtoupper($action).' ON ledger_transactions BEGIN SELECT RAISE(ABORT, \''.self::MESSAGE.'\'); END');
            } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
                DB::unprepared("CREATE TRIGGER {$name} BEFORE ".strtoupper($action).' ON ledger_transactions FOR EACH ROW SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT = \''.self::MESSAGE.'\'');
            }
        }
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS ledger_transactions_no_update');
        DB::unprepared('DROP TRIGGER IF EXISTS ledger_transactions_no_delete');
    }
};
