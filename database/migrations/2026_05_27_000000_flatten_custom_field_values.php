<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Flatten EAV custom-field storage (v2.0).
 *
 * Backfill: last CFV per CFD wins (CFV.id DESC, first-seen).
 * Integer field with non-numeric value → value_text + warning log.
 * down() is dev/test only — CFV.id is not preserved.
 */
class FlattenCustomFieldValues extends Migration
{
    public function up(): void
    {
        Schema::table('custom_field_device', function (Blueprint $table) {
            $table->text('value_text')->nullable()->after('custom_field_id');
            $table->bigInteger('value_int')->nullable()->after('value_text');
        });

        $dups = DB::table('custom_fields')
            ->select('name', DB::raw('MIN(id) AS keep_id'))
            ->groupBy('name')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($dups as $row) {
            $loserIds = DB::table('custom_fields')
                ->where('name', $row->name)
                ->where('id', '!=', $row->keep_id)
                ->pluck('id');

            DB::table('custom_field_device')
                ->whereIn('custom_field_id', $loserIds)
                ->update(['custom_field_id' => $row->keep_id]);

            DB::table('custom_fields')->whereIn('id', $loserIds)->delete();
        }

        // CFV rows attached to loser CFDs cascade out via FK.
        $cfdDups = DB::table('custom_field_device')
            ->select('device_id', 'custom_field_id', DB::raw('MIN(id) AS keep_id'))
            ->groupBy('device_id', 'custom_field_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($cfdDups as $row) {
            DB::table('custom_field_device')
                ->where('device_id', $row->device_id)
                ->where('custom_field_id', $row->custom_field_id)
                ->where('id', '!=', $row->keep_id)
                ->delete();
        }

        // Order by CFV.id DESC + first-seen: autoincrement is the only monotonic signal (no created_at index).
        $rows = DB::table('custom_field_values as cfv')
            ->join('custom_field_device as cfd', 'cfv.custom_field_device_id', '=', 'cfd.id')
            ->join('custom_fields as cf', 'cfd.custom_field_id', '=', 'cf.id')
            ->orderBy('cfv.id', 'desc')
            ->select('cfd.id as cfd_id', 'cf.name as field_name', 'cf.type as field_type', 'cfd.device_id', 'cfv.value as raw_value')
            ->get();

        $seen = [];
        foreach ($rows as $r) {
            if (isset($seen[$r->cfd_id])) {
                continue;
            }
            $seen[$r->cfd_id] = true;

            $update = ['value_text' => null, 'value_int' => null];

            if ($r->field_type === 'integer') {
                $trimmed = trim((string) $r->raw_value);
                if (preg_match('/^-?\d+$/', $trimmed)) {
                    $update['value_int'] = (int) $trimmed;
                } else {
                    Log::warning('flatten-eav: integer field has non-integer value; storing in value_text', [
                        'field'     => $r->field_name,
                        'device_id' => $r->device_id,
                        'value'     => $r->raw_value,
                    ]);
                    $update['value_text'] = $r->raw_value;
                }
            } else {
                $update['value_text'] = $r->raw_value;
            }

            DB::table('custom_field_device')->where('id', $r->cfd_id)->update($update);
        }

        Schema::table('custom_fields', function (Blueprint $table) {
            $table->unique('name', 'custom_fields_name_unique');
        });

        Schema::table('custom_field_device', function (Blueprint $table) {
            $table->unique(['device_id', 'custom_field_id'], 'cfd_device_field_unique');
            $table->index(['custom_field_id', 'value_int'], 'cfd_field_value_int_idx');
            // MySQL requires a prefix length when indexing TEXT.
            DB::statement('CREATE INDEX cfd_field_value_text_idx ON custom_field_device (custom_field_id, value_text(64))');
        });

        Schema::dropIfExists('custom_field_values');
    }

    public function down(): void
    {
        Schema::create('custom_field_values', function (Blueprint $table) {
            $table->increments('id')->unsigned()->index();
            $table->unsignedInteger('custom_field_device_id')->index();
            $table->text('value');
            $table->timestamps();
            $table->foreign('custom_field_device_id')->references('id')->on('custom_field_device')->onDelete('cascade');
        });

        // chunkById, not each() — the latter is Eloquent-only.
        DB::table('custom_field_device')
            ->where(function ($q) {
                $q->whereNotNull('value_text')->orWhereNotNull('value_int');
            })
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $cfd) {
                    $value = $cfd->value_text ?? (string) $cfd->value_int;
                    DB::table('custom_field_values')->insert([
                        'custom_field_device_id' => $cfd->id,
                        'value'                  => $value,
                        'created_at'             => $cfd->created_at,
                        'updated_at'             => $cfd->updated_at,
                    ]);
                }
            });

        Schema::table('custom_field_device', function (Blueprint $table) {
            $table->dropUnique('cfd_device_field_unique');
            $table->dropIndex('cfd_field_value_int_idx');
            $table->dropIndex('cfd_field_value_text_idx');
        });

        Schema::table('custom_fields', function (Blueprint $table) {
            $table->dropUnique('custom_fields_name_unique');
        });

        Schema::table('custom_field_device', function (Blueprint $table) {
            $table->dropColumn(['value_text', 'value_int']);
        });
    }
}
