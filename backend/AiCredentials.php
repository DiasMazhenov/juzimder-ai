<?php
declare(strict_types=1);
namespace Juzimder;

final class AiCredentialVault {
    public function __construct(private readonly Store $store) {}

    public function status(string $projectId, string $userId): array {
        $this->store->owned($projectId, $userId);
        $row = $this->row($projectId);
        return [
            'provider' => 'openrouter',
            'configured' => $row !== null && is_string($row['api_key_ciphertext']) && $row['api_key_ciphertext'] !== '',
            'model' => $row['model'] ?? null,
            'version' => $row === null ? 0 : (int)$row['version'],
        ];
    }

    public function settings(string $projectId, string $userId): ?OpenRouterSettings {
        $this->store->owned($projectId, $userId);
        $row = $this->row($projectId);
        if ($row === null || !is_string($row['api_key_ciphertext']) || $row['api_key_ciphertext'] === '' || !is_string($row['model'])) return null;
        $apiKey = $this->decrypt($row['api_key_ciphertext']);
        $settings = OpenRouterSettings::fromCredentials($apiKey, $row['model']);
        if ($settings === null) throw new Problem(503, 'Сохранённые настройки AI повреждены. Введите ключ и модель заново.');
        return $settings;
    }

    public function save(string $projectId, string $userId, int $expectedVersion, ?string $apiKey, string $model): array {
        $this->store->owned($projectId, $userId);
        $model = trim($model);
        if (!OpenRouterSettings::isValidModel($model)) throw new Problem(422, 'Укажите корректный ID модели OpenRouter.');
        $row = $this->row($projectId);
        $version = $row === null ? 0 : (int)$row['version'];
        if ($version !== $expectedVersion) throw new Problem(409, 'Настройки AI изменились в другой вкладке. Обновите страницу и повторите действие.');

        $existingCiphertext = $row['api_key_ciphertext'] ?? null;
        $newApiKey = trim((string)($apiKey ?? ''));
        if ($newApiKey !== '') {
            if (strlen($newApiKey) > 512 || preg_match('/[\x00-\x20\x7F]/', $newApiKey)) throw new Problem(422, 'Проверьте API-ключ: максимум 512 символов без пробелов и управляющих знаков.');
            $ciphertext = $this->encrypt($newApiKey);
        } else {
            if (!is_string($existingCiphertext) || $existingCiphertext === '') throw new Problem(422, 'Введите API-ключ OpenRouter.');
            $ciphertext = $existingCiphertext;
        }

        if ($row === null) {
            try {
                $this->store->query('INSERT INTO project_ai_credentials(project_id,api_key_ciphertext,model,version) VALUES(?,?,?,1)', [$projectId,$ciphertext,$model]);
            } catch (\PDOException) {
                throw new Problem(409, 'Настройки AI изменились в другой вкладке. Обновите страницу и повторите действие.');
            }
        } else {
            $updated = $this->store->query('UPDATE project_ai_credentials SET api_key_ciphertext=?,model=?,version=version+1,updated_at=CURRENT_TIMESTAMP WHERE project_id=? AND version=?', [$ciphertext,$model,$projectId,$expectedVersion]);
            if ($updated->rowCount() !== 1) throw new Problem(409, 'Настройки AI изменились в другой вкладке. Обновите страницу и повторите действие.');
        }
        return $this->status($projectId, $userId);
    }

    public function clear(string $projectId, string $userId, int $expectedVersion): array {
        $this->store->owned($projectId, $userId);
        $row = $this->row($projectId);
        $version = $row === null ? 0 : (int)$row['version'];
        if ($version !== $expectedVersion) throw new Problem(409, 'Настройки AI изменились в другой вкладке. Обновите страницу и повторите действие.');
        if ($row === null) {
            $this->store->query('INSERT INTO project_ai_credentials(project_id,api_key_ciphertext,model,version) VALUES(?,NULL,NULL,1)', [$projectId]);
        } else {
            $updated = $this->store->query('UPDATE project_ai_credentials SET api_key_ciphertext=NULL,model=NULL,version=version+1,updated_at=CURRENT_TIMESTAMP WHERE project_id=? AND version=?', [$projectId,$expectedVersion]);
            if ($updated->rowCount() !== 1) throw new Problem(409, 'Настройки AI изменились в другой вкладке. Обновите страницу и повторите действие.');
        }
        return $this->status($projectId, $userId);
    }

    private function row(string $projectId): ?array {
        $row = $this->store->query('SELECT api_key_ciphertext,model,version FROM project_ai_credentials WHERE project_id=?', [$projectId])->fetch();
        return $row ?: null;
    }

    private function encrypt(string $apiKey): string {
        if (!function_exists('sodium_crypto_secretbox')) throw new Problem(503, 'На сервере недоступно защищённое хранилище ключей AI.');
        $key = $this->encryptionKey(true);
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        return base64_encode($nonce . sodium_crypto_secretbox($apiKey, $nonce, $key));
    }

    private function decrypt(string $ciphertext): string {
        if (!function_exists('sodium_crypto_secretbox_open')) throw new Problem(503, 'На сервере недоступно защищённое хранилище ключей AI.');
        $packed = base64_decode($ciphertext, true);
        if ($packed === false || strlen($packed) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) throw new Problem(503, 'Сохранённый ключ AI недоступен. Проверьте закрытый файл ключа приложения.');
        $nonce = substr($packed, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $encrypted = substr($packed, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plain = sodium_crypto_secretbox_open($encrypted, $nonce, $this->encryptionKey(false));
        if (!is_string($plain)) throw new Problem(503, 'Сохранённый ключ AI недоступен. Проверьте закрытый файл ключа приложения.');
        return $plain;
    }

    private function encryptionKey(bool $create): string {
        if (!function_exists('sodium_crypto_secretbox')) throw new Problem(503, 'На сервере недоступно защищённое хранилище ключей AI.');
        $path = $this->store->path . '.ai-key';
        if (is_link($path)) throw new Problem(503, 'Файл защищённого хранилища AI должен быть обычным закрытым файлом.');
        if (!is_file($path) && $create) {
            $key = random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
            $oldUmask = umask(0077);
            try { $handle = @fopen($path, 'x+b'); }
            finally { umask($oldUmask); }
            if (is_resource($handle)) {
                $written = fwrite($handle, $key);
                fflush($handle);
                fclose($handle);
                @chmod($path, 0600);
                if ($written !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
                    @unlink($path);
                    throw new Problem(503, 'Не удалось создать закрытое хранилище ключей AI.');
                }
                return $key;
            }
        }
        if (!is_file($path) || is_link($path)) throw new Problem(503, 'Не найден закрытый файл ключа приложения для настроек AI. Восстановите его из резервной копии.');
        @chmod($path, 0600);
        $permissions = @fileperms($path);
        $key = @file_get_contents($path);
        if ($permissions === false || ($permissions & 0077) !== 0 || !is_string($key) || strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new Problem(503, 'Файл защищённого хранилища AI недоступен или имеет неверные права.');
        }
        return $key;
    }
}
