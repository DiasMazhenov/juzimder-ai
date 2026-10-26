<?php
declare(strict_types=1);
namespace Juzimder;

final class MigrationRunner {
    public const CURRENT_VERSION = 17;
    private const MIGRATIONS = [
        1 => '002_project_settings.sql', 2 => '003_ai_credentials.sql',
        3 => '004_publications.sql', 4 => '005_ai_profiles.sql',
        5 => '006_project_assets.sql', 6 => '007_ai_fallback_model.sql',
        7 => '008_ai_skills.sql', 8 => '009_ai_generation_jobs.sql',
        9 => '010_ai_job_cancellation.sql', 10 => '011_ai_job_diagnostics.sql',
        11 => '012_form_submissions.sql', 12 => '013_project_slugs.sql',
        13 => '014_form_mail.sql', 14 => '015_form_mail_test_limits.sql',
        15 => '016_account_recovery_codes.sql', 16 => '017_ai_request_limits.sql',
    ];

    public function __construct(private readonly \PDO $db) {}

    public function run(): int {
        while (true) {
            $version = $this->version();
            if ($version > self::CURRENT_VERSION) throw new \RuntimeException('Database schema version is newer than this application.');
            if ($version === self::CURRENT_VERSION) return $version;
            if ($version === 0) {
                $this->apply(0, fn() => $this->executeMigration('001_initial.sql'));
                continue;
            }
            if (!isset(self::MIGRATIONS[$version])) throw new \RuntimeException('No migration is registered for the current database schema.');
            $this->apply($version, function () use ($version): void {
                $this->executeMigration(self::MIGRATIONS[$version]);
                if ($version === 1) $this->backfillProjectSettings();
            });
        }
    }

    private function apply(int $expectedVersion, callable $migration): void {
        $this->db->exec('BEGIN IMMEDIATE');
        try {
            if ($this->version() !== $expectedVersion) { $this->db->rollBack(); return; }
            $migration();
            if ($this->version() !== $expectedVersion + 1) throw new \RuntimeException('Migration did not advance the database schema by one version.');
            $this->db->commit();
        } catch (\Throwable $error) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $error;
        }
    }

    private function version(): int { return (int)$this->db->query('PRAGMA user_version')->fetchColumn(); }

    private function executeMigration(string $filename): void {
        $sql = file_get_contents(__DIR__ . '/migrations/' . $filename);
        if (!is_string($sql) || $sql === '') throw new \RuntimeException('Database migration file is unavailable.');
        $this->db->exec($sql);
    }

    private function backfillProjectSettings(): void {
        $projects = $this->db->query('SELECT p.id,d.payload FROM projects p JOIN documents d ON d.project_id=p.id')->fetchAll();
        $updateProject = $this->db->prepare('UPDATE projects SET settings_payload=? WHERE id=?');
        foreach ($projects as $row) {
            $settings = ProjectSettings::initial(json_decode($row['payload'], false, 64, JSON_THROW_ON_ERROR));
            $updateProject->execute([json_encode($settings, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), $row['id']]);
        }
        $revisions = $this->db->query('SELECT id,payload FROM revisions')->fetchAll();
        $updateRevision = $this->db->prepare('UPDATE revisions SET settings_payload=? WHERE id=?');
        foreach ($revisions as $row) {
            $settings = ProjectSettings::initial(json_decode($row['payload'], false, 64, JSON_THROW_ON_ERROR));
            $updateRevision->execute([json_encode($settings, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), $row['id']]);
        }
    }
}
