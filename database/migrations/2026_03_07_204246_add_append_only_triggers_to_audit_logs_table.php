<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            DB::unprepared("
                CREATE TRIGGER IF NOT EXISTS audit_logs_no_update
                BEFORE UPDATE ON audit_logs
                BEGIN
                    SELECT RAISE(ABORT, 'audit_logs are append-only');
                END;
            ");

            DB::unprepared("
                CREATE TRIGGER IF NOT EXISTS audit_logs_no_delete
                BEFORE DELETE ON audit_logs
                BEGIN
                    SELECT RAISE(ABORT, 'audit_logs are append-only');
                END;
            ");
        }

        if ($driver === 'pgsql') {
            DB::unprepared("
                CREATE OR REPLACE FUNCTION prevent_audit_logs_modifications()
                RETURNS trigger AS $$
                BEGIN
                    RAISE EXCEPTION 'audit_logs are append-only';
                END;
                $$ LANGUAGE plpgsql;
            ");

            DB::unprepared("
                DROP TRIGGER IF EXISTS audit_logs_no_update ON audit_logs;
                CREATE TRIGGER audit_logs_no_update
                BEFORE UPDATE ON audit_logs
                FOR EACH ROW EXECUTE FUNCTION prevent_audit_logs_modifications();
            ");

            DB::unprepared("
                DROP TRIGGER IF EXISTS audit_logs_no_delete ON audit_logs;
                CREATE TRIGGER audit_logs_no_delete
                BEFORE DELETE ON audit_logs
                FOR EACH ROW EXECUTE FUNCTION prevent_audit_logs_modifications();
            ");
        }

        if ($driver === 'mysql') {
            DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_no_update;');
            DB::unprepared("
                CREATE TRIGGER audit_logs_no_update
                BEFORE UPDATE ON audit_logs
                FOR EACH ROW
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit_logs are append-only';
            ");

            DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_no_delete;');
            DB::unprepared("
                CREATE TRIGGER audit_logs_no_delete
                BEFORE DELETE ON audit_logs
                FOR EACH ROW
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit_logs are append-only';
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_no_update;');
            DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_no_delete;');
        }

        if ($driver === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_no_update ON audit_logs;');
            DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_no_delete ON audit_logs;');
            DB::unprepared('DROP FUNCTION IF EXISTS prevent_audit_logs_modifications();');
        }

        if ($driver === 'mysql') {
            DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_no_update;');
            DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_no_delete;');
        }
    }
};
