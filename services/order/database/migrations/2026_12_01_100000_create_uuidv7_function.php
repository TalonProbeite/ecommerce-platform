<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION uuidv7() RETURNS uuid
            LANGUAGE plpgsql VOLATILE AS $$
            DECLARE
                ts_ms bigint := floor(extract(epoch FROM clock_timestamp()) * 1000)::bigint;
                b bytea;
            BEGIN
                b := substring(int8send(ts_ms) FROM 3);
                b := b || substring(uuid_send(gen_random_uuid()) FROM 7);
                b := set_byte(b, 6, (get_byte(b, 6) & 15) | 112);
                b := set_byte(b, 8, (get_byte(b, 8) & 63) | 128);
                RETURN encode(b, 'hex')::uuid;
            END;
            $$;
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS uuidv7();');
    }
};
