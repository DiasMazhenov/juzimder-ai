<?php
declare(strict_types=1);
namespace Juzimder;

final class AiCredentialVault {
    public function __construct(private readonly Store $store) {}

    public function status(string $projectId, string $userId): array {
        $this->store->owned($projectId, $userId);
        $state = $this->state($projectId);
        $profiles = $this->store->query(
            'SELECT id,name,model,fallback_model,created_at,updated_at FROM project_ai_profiles WHERE project_id=? ORDER BY created_at,id',
            [$projectId]
        )->fetchAll();
        $activeId = is_string($state['active_profile_id'] ?? null) ? $state['active_profile_id'] : null;
        $active = null;
        foreach ($profiles as &$profile) {
            $profile['active'] = $profile['id'] === $activeId;
            $profile['fallbackModel'] = $profile['fallback_model'];
            unset($profile['fallback_model']);
            unset($profile['created_at'], $profile['updated_at']);
            if ($profile['active']) $active = $profile;
        }
        unset($profile);
        return [
            'provider' => 'openrouter',
            'configured' => $active !== null,
            'model' => $active['model'] ?? null,
            'fallbackModel' => $active['fallbackModel'] ?? null,
            'activeProfileId' => $activeId,
            'profiles' => $profiles,
            'version' => $state === null ? 0 : (int)$state['version'],
        ];
    }

    public function settings(string $projectId, string $userId): ?OpenRouterSettings {
        $this->store->owned($projectId, $userId);
        $state = $this->state($projectId);
        $profileId = $state['active_profile_id'] ?? null;
        if (!is_string($profileId) || $profileId === '') return null;
        $row = $this->store->query(
            'SELECT api_key_ciphertext,model,fallback_model FROM project_ai_profiles WHERE project_id=? AND id=?',
            [$projectId, $profileId]
        )->fetch();
        if (!$row || !is_string($row['api_key_ciphertext']) || $row['api_key_ciphertext'] === '' || !is_string($row['model'])) {
            throw new Problem(503, 'Активный профиль AI повреждён. Выберите или сохраните профиль заново.');
        }
        $settings = OpenRouterSettings::fromCredentials($this->decrypt($row['api_key_ciphertext']), $row['model'], $row['fallback_model']);
        if ($settings === null) throw new Problem(503, 'Активный профиль AI повреждён. Проверьте ключ и модель.');
        return $settings;
    }

    /** Server-only credentials, active profile first; never return this through an API. */
    public function failoverSettings(string $projectId, string $userId): array {
        $active = $this->settings($projectId, $userId);
        if (!$active) return [];
        $status = $this->status($projectId, $userId);
        $entries = [];
        foreach ($status['profiles'] as $profile) {
            $settings = $active;
            if (!$profile['active']) {
                $row = $this->store->query('SELECT api_key_ciphertext FROM project_ai_profiles WHERE project_id=? AND id=?', [$projectId,$profile['id']])->fetch();
                $settings = OpenRouterSettings::fromCredentials($this->decrypt($row['api_key_ciphertext']),$profile['model'],$profile['fallbackModel']);
            }
            if ($settings) $entries[] = ['id'=>$profile['id'],'name'=>$profile['name'],'settings'=>$settings,'active'=>$profile['active']];
        }
        usort($entries,static fn($a,$b)=>(int)$b['active']<=>(int)$a['active']);
        return $entries;
    }

    public function createProfile(string $projectId, string $userId, int $expectedVersion, string $name, ?string $apiKey, string $model, ?string $fallbackModel = null): array {
        $this->store->owned($projectId, $userId);
        $name = self::validateName($name);
        $model = self::validateModel($model);
        $fallbackModel = self::validateFallbackModel($fallbackModel, $model);
        $apiKey = self::validateApiKey($apiKey, true);
        return $this->mutate($projectId, $userId, $expectedVersion, function () use ($projectId, $name, $apiKey, $model, $fallbackModel): ?string {
            $profileId = bin2hex(random_bytes(12));
            $this->store->query(
                'INSERT INTO project_ai_profiles(id,project_id,name,api_key_ciphertext,model,fallback_model) VALUES(?,?,?,?,?,?)',
                [$profileId, $projectId, $name, $this->encrypt($apiKey), $model, $fallbackModel]
            );
            return $profileId;
        });
    }

    public function updateProfile(string $projectId, string $userId, int $expectedVersion, string $profileId, string $name, ?string $apiKey, string $model, ?string $fallbackModel = null): array {
        $this->store->owned($projectId, $userId);
        $name = self::validateName($name);
        $model = self::validateModel($model);
        $apiKey = self::validateApiKey($apiKey, false);
        if ($fallbackModel !== null) $fallbackModel = self::validateFallbackModel($fallbackModel, $model);
        return $this->mutate($projectId, $userId, $expectedVersion, function (?array $state) use ($projectId, $profileId, $name, $apiKey, $model, $fallbackModel): ?string {
            $profile = $this->store->query(
                'SELECT id,api_key_ciphertext,fallback_model FROM project_ai_profiles WHERE project_id=? AND id=?',
                [$projectId, $profileId]
            )->fetch();
            if (!$profile) throw new Problem(404, 'Профиль AI не найден.');
            $key = $apiKey === '' ? $profile['api_key_ciphertext'] : $this->encrypt($apiKey);
            $effectiveFallbackModel = $fallbackModel === null ? $profile['fallback_model'] : $fallbackModel;
            if ($effectiveFallbackModel !== null) $effectiveFallbackModel = self::validateFallbackModel($effectiveFallbackModel, $model);
            $this->store->query(
                'UPDATE project_ai_profiles SET name=?,api_key_ciphertext=?,model=?,fallback_model=?,updated_at=CURRENT_TIMESTAMP WHERE project_id=? AND id=?',
                [$name, $key, $model, $effectiveFallbackModel, $projectId, $profileId]
            );
            return $state['active_profile_id'] ?? null;
        });
    }

    public function activateProfile(string $projectId, string $userId, int $expectedVersion, string $profileId): array {
        return $this->mutate($projectId, $userId, $expectedVersion, function () use ($projectId, $profileId): ?string {
            $exists = $this->store->query('SELECT 1 FROM project_ai_profiles WHERE project_id=? AND id=?', [$projectId, $profileId])->fetchColumn();
            if ($exists === false) throw new Problem(404, 'Профиль AI не найден.');
            return $profileId;
        });
    }

    public function deleteProfile(string $projectId, string $userId, int $expectedVersion, string $profileId): array {
        return $this->mutate($projectId, $userId, $expectedVersion, function (?array $state) use ($projectId, $profileId): ?string {
            $exists = $this->store->query('SELECT 1 FROM project_ai_profiles WHERE project_id=? AND id=?', [$projectId, $profileId])->fetchColumn();
            if ($exists === false) throw new Problem(404, 'Профиль AI не найден.');
            $this->store->query('DELETE FROM project_ai_profiles WHERE project_id=? AND id=?', [$projectId, $profileId]);
            $activeId = $state['active_profile_id'] ?? null;
            if ($activeId !== $profileId) return is_string($activeId) ? $activeId : null;
            $next = $this->store->query(
                'SELECT id FROM project_ai_profiles WHERE project_id=? ORDER BY updated_at DESC,created_at DESC,id LIMIT 1',
                [$projectId]
            )->fetchColumn();
            return is_string($next) ? $next : null;
        });
    }

    /** Compatibility for clients using the original single-profile settings endpoint. */
    public function save(string $projectId, string $userId, int $expectedVersion, ?string $apiKey, string $model, ?string $fallbackModel = null): array {
        $status = $this->status($projectId, $userId);
        if (is_string($status['activeProfileId'])) {
            $active = null;
            foreach ($status['profiles'] as $profile) if ($profile['id'] === $status['activeProfileId']) { $active = $profile; break; }
            if ($active === null) throw new Problem(503, 'Активный профиль AI недоступен.');
            return $this->updateProfile($projectId, $userId, $expectedVersion, $active['id'], $active['name'], $apiKey, $model, $fallbackModel);
        }
        return $this->createProfile($projectId, $userId, $expectedVersion, 'Основной профиль', $apiKey, $model, $fallbackModel);
    }

    /** Compatibility for the original single-profile delete action: remove all project profiles. */
    public function clear(string $projectId, string $userId, int $expectedVersion): array {
        return $this->mutate($projectId, $userId, $expectedVersion, function () use ($projectId): ?string {
            $this->store->query('DELETE FROM project_ai_profiles WHERE project_id=?', [$projectId]);
            return null;
        });
    }

    private function mutate(string $projectId, string $userId, int $expectedVersion, callable $changeActiveProfile): array {
        $this->store->owned($projectId, $userId);
        try {
            $this->store->db->beginTransaction();
            $state = $this->state($projectId);
            $version = $state === null ? 0 : (int)$state['version'];
            if ($version !== $expectedVersion) throw new Problem(409, 'Профили AI изменились в другой вкладке. Обновите страницу и повторите действие.');
            if ($state === null) {
                $this->store->query(
                    'INSERT INTO project_ai_profile_state(project_id,active_profile_id,version) VALUES(?,NULL,1)',
                    [$projectId]
                );
            } else {
                $updated = $this->store->query(
                    'UPDATE project_ai_profile_state SET version=version+1,updated_at=CURRENT_TIMESTAMP WHERE project_id=? AND version=?',
                    [$projectId, $expectedVersion]
                );
                if ($updated->rowCount() !== 1) throw new Problem(409, 'Профили AI изменились в другой вкладке. Обновите страницу и повторите действие.');
            }
            $activeProfileId = $changeActiveProfile($state);
            $this->store->query(
                'UPDATE project_ai_profile_state SET active_profile_id=?,updated_at=CURRENT_TIMESTAMP WHERE project_id=?',
                [$activeProfileId, $projectId]
            );
            $this->store->db->commit();
        } catch (\Throwable $error) {
            if ($this->store->db->inTransaction()) $this->store->db->rollBack();
            if ($error instanceof \PDOException && preg_match('/locked|busy/i', $error->getMessage())) {
                throw new Problem(409, 'Профили AI изменились в другой вкладке. Обновите страницу и повторите действие.');
            }
            throw $error;
        }
        return $this->status($projectId, $userId);
    }

    private function state(string $projectId): ?array {
        $state = $this->store->query(
            'SELECT active_profile_id,version FROM project_ai_profile_state WHERE project_id=?',
            [$projectId]
        )->fetch();
        return $state ?: null;
    }

    private static function validateName(string $name): string {
        $name = trim($name);
        if ($name === '' || mb_strlen($name, 'UTF-8') > 64 || preg_match('/[\x00-\x1F\x7F]/u', $name)) {
            throw new Problem(422, 'Название профиля должно содержать от 1 до 64 символов без управляющих знаков.');
        }
        return $name;
    }

    private static function validateModel(string $model): string {
        $model = trim($model);
        if (!OpenRouterSettings::isValidModel($model)) throw new Problem(422, 'Укажите корректный ID модели OpenRouter.');
        return $model;
    }

    private static function validateFallbackModel(?string $fallbackModel, string $model): ?string {
        $fallbackModel = trim((string)$fallbackModel);
        if ($fallbackModel === '') return null;
        if (!OpenRouterSettings::isValidModel($fallbackModel) || $fallbackModel === $model) {
            throw new Problem(422, 'Укажите корректную резервную модель, отличную от основной, или оставьте поле пустым.');
        }
        return $fallbackModel;
    }

    private static function validateApiKey(?string $apiKey, bool $required): string {
        $apiKey = trim((string)($apiKey ?? ''));
        if ($apiKey === '' && $required) throw new Problem(422, 'Введите API-ключ OpenRouter для этого профиля.');
        if ($apiKey !== '' && (strlen($apiKey) > 512 || preg_match('/[\x00-\x20\x7F]/', $apiKey))) {
            throw new Problem(422, 'Проверьте API-ключ: максимум 512 символов без пробелов и управляющих знаков.');
        }
        return $apiKey;
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
