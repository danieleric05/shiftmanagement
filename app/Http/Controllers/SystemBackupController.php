<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Ifsnop\Mysqldump\Mysqldump;

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

        $path = tempnam(sys_get_temp_dir(), 'backup_').'.sql';

        (new Mysqldump($dsn, $connection['username'], $connection['password']))->start($path);

        $filename = 'shiftmanagement-'.now()->format('Y-m-d_His').'.sql';

        return response()->download($path, $filename)->deleteFileAfterSend();
    }
}
