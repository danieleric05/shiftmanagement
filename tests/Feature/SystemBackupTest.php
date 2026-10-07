<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemBackupTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'jeton-de-sauvegarde-de-test-0123456789abcdef';

    public function test_refuse_sans_jeton(): void
    {
        config(['services.backup.token' => self::TOKEN]);

        $this->get('/system/backup')->assertForbidden();
    }

    public function test_refuse_avec_un_mauvais_jeton(): void
    {
        config(['services.backup.token' => self::TOKEN]);

        $this->get('/system/backup', ['X-Backup-Token' => 'mauvais'])->assertForbidden();
    }

    public function test_desactive_si_aucun_jeton_configure(): void
    {
        config(['services.backup.token' => null]);

        $this->get('/system/backup', ['X-Backup-Token' => ''])->assertForbidden();
    }

    public function test_renvoie_un_dump_sql_complet_avec_un_jeton_valide(): void
    {
        config(['services.backup.token' => self::TOKEN]);

        $response = $this->get('/system/backup', ['X-Backup-Token' => self::TOKEN]);

        $response->assertOk();
        $sql = file_get_contents($response->baseResponse->getFile()->getPathname());

        $this->assertStringContainsString('CREATE TABLE `users`', $sql);
        $this->assertStringContainsString('CREATE TABLE `servants`', $sql);
        $this->assertStringContainsString('Dump completed', $sql);
    }
}
