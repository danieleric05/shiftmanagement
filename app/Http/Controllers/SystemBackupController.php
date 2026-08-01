<?php

namespace App\Http\Controllers;

use Ifsnop\Mysqldump\Mysqldump;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Throwable;

class SystemBackupController extends Controller
{
    public function download(Request $request)
    {
        $token = config('services.backup.token');

        abort_if(
            ! $token || ! hash_equals($token, (string) $request->header('X-Backup-Token')),
            403
        );

        $connection = Config::get('database.connections.mysql');

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $connection['host'],
            $connection['port'],
            $connection['database'],
            $connection['charset'] ?? 'utf8mb4'
        );

        $path = tempnam(sys_get_temp_dir(), 'backup_');

        try {
            (new Mysqldump($dsn, $connection['username'], $connection['password']))->start($path);
        } catch (Throwable $e) {
            @unlink($path);

            Log::error('Échec du backup de la base de données.', ['exception' => $e->getMessage()]);

            abort(500, 'Le backup a échoué.');
        }

        $filename = 'shiftmanagement-'.now()->format('Y-m-d_His').'.sql';

        return response()->download($path, $filename)->deleteFileAfterSend();
    }
}
