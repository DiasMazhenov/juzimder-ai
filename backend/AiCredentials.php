<?php
declare(strict_types=1);
namespace Juzimder;

final class AiCredentialVault {
    public function __construct(private readonly Store $store) {}

    /**
     * Return a project-backup projection. API keys stay encrypted with this
     * installation's secretbox key and are bound to the owner and source project.
     */
    public function backupSnapshot(string $projectId, string $userId): array {
        $this->store->owned($projectId, $userId);
        $state = $this->state($projectId);
        $rows = $this->store->query(
            'SELECT id,name,api_key_ciphertext,model,fallback_model FROM project_ai_profiles WHERE project_id=? ORDER BY created_at,id',
            [$projectId]
        )->fetchAll();
        $profiles = [];
        foreach ($rows as $row) {
            try {
                self::validateName((string)$row['name']);
                $model = self::validateModel((string)$row['model']);
                $fallback = self::validateFallbackModel($row['fallback_model'], $model);
                $ciphertext = (string)$row['api_key_ciphertext'];
                self::validateStoredCredential($ciphertext);
                self::validateApiKey($this->decrypt($ciphertext), true);
            } catch (Problem $error) {
                if ($error->status === 503) throw $error;
                throw new Problem(500, 'Не удалось добавить защищённые настройки AI Engine в резервную копию. Проверьте ключ хранилища AI.');
            } catch (\Throwable) {
                throw new Problem(500, 'Не удалось добавить защищённые настройки AI Engine в резервную копию. Проверьте ключ хранилища AI.');
            }
            $profiles[] = [
                'id'=>(string)$row['id'],
                'name'=>(string)$row['name'],
                'model'=>$model,
                'fallbackModel'=>$fallback,
                'credentialCiphertext'=>$ciphertext,
            ];
        }
        $activeId = $state['active_profile_id'] ?? null;
        if ($activeId !== null && (!is_string($activeId) || !in_array($activeId, array_column($profiles, 'id'), true))) {
            throw new Problem(500, 'Не удалось проверить активный профиль AI для резервной копии.');
        }
        return ['activeProfileId'=>$activeId, 'profiles'=>$profiles, 'integrity'=>$profiles === [] ? null : $this->snapshotIntegrity($projectId, $userId, $activeId, $profiles)];
    }

    /** Validate encrypted profile data from a backup without returning plaintext credentials. */
    public function validateBackupSnapshot(string $projectId, string $userId, mixed $value, bool $requireOwnedProject = true): array {
        if ($requireOwnedProject) $this->store->owned($projectId, $userId);
        $fields = $value instanceof \stdClass ? get_object_vars($value) : (is_array($value) && !array_is_list($value) ? $value : null);
        if ($fields === null || array_diff(array_keys($fields), ['activeProfileId','profiles','integrity']) || !array_key_exists('activeProfileId', $fields) || !is_array($fields['profiles'] ?? null) || !array_is_list($fields['profiles']) || !array_key_exists('integrity', $fields)) {
            throw new Problem(422, 'Некорректные настройки AI Engine в резервной копии.');
        }
        $profiles = []; $seen = [];
        foreach ($fields['profiles'] as $value) {
            $profile = $value instanceof \stdClass ? get_object_vars($value) : (is_array($value) && !array_is_list($value) ? $value : null);
            if ($profile === null || array_diff(array_keys($profile), ['id','name','model','fallbackModel','credentialCiphertext']) || !is_string($profile['id'] ?? null) || !preg_match('/^[a-f0-9]{24}$/D', $profile['id']) || isset($seen[$profile['id']]) || !is_string($profile['name'] ?? null) || !is_string($profile['model'] ?? null) || !(($profile['fallbackModel'] ?? null) === null || is_string($profile['fallbackModel'])) || !is_string($profile['credentialCiphertext'] ?? null)) {
                throw new Problem(422, 'Некорректная запись профиля AI в резервной копии.');
            }
            $name = self::validateName($profile['name']);
            $model = self::validateModel($profile['model']);
            $fallback = self::validateFallbackModel($profile['fallbackModel'], $model);
            $ciphertext = self::validateStoredCredential($profile['credentialCiphertext']);
            try {
                $plaintext = $this->decrypt($ciphertext);
                self::validateApiKey($plaintext, true);
            } catch (Problem $error) {
                if ($error->status === 503 && str_contains($error->getMessage(), 'Сохранённый ключ AI недоступен')) {
                    throw new Problem(422, 'Зашифрованные ключи AI из этого бэкапа принадлежат другой установке или повреждены. Для переноса сервера сохраните системную базу вместе с файлом ключа AI.');
                }
                throw $error;
            }
            $seen[$profile['id']] = true;
            $profiles[] = ['id'=>$profile['id'],'name'=>$name,'model'=>$model,'fallbackModel'=>$fallback,'credentialCiphertext'=>$ciphertext];
        }
        $activeId = $fields['activeProfileId'];
        if (!(($activeId === null) || (is_string($activeId) && isset($seen[$activeId])))) {
            throw new Problem(422, 'Активный AI-профиль отсутствует в резервной копии.');
        }
        if ($profiles === []) {
            if ($activeId !== null || $fields['integrity'] !== null) throw new Problem(422, 'Некорректная подпись пустых настроек AI в резервной копии.');
            $integrity = null;
        } else {
            if (!is_string($fields['integrity']) || !preg_match('/^[a-f0-9]{64}$/D', $fields['integrity'])) throw new Problem(422, 'Подпись профилей AI в резервной копии некорректна.');
            $integrity = $this->snapshotIntegrity($projectId, $userId, $activeId, $profiles);
            if (!hash_equals($integrity, $fields['integrity'])) throw new Problem(422, 'Профили AI в бэкапе не принадлежат этому аккаунту или были изменены.');
        }
        return ['activeProfileId'=>$activeId,'profiles'=>$profiles,'integrity'=>$integrity];
    }

    /** Rebind a validated snapshot while importing it as a new project for the same owner. */
    public function rebindBackupSnapshot(string $sourceProjectId, string $targetProjectId, string $userId, mixed $value, array $profileIds): array {
        $snapshot = $this->validateBackupSnapshot($sourceProjectId, $userId, $value, false);
        $oldActiveId = $snapshot['activeProfileId'];
        foreach ($snapshot['profiles'] as &$profile) {
            $oldId = $profile['id'];
            if (!isset($profileIds[$oldId]) || !is_string($profileIds[$oldId]) || !preg_match('/^[a-f0-9]{24}$/D', $profileIds[$oldId])) {
                throw new Problem(422, 'Некорректная привязка AI-профиля при восстановлении.');
            }
            $profile['id'] = $profileIds[$oldId];
        }
        unset($profile);
        if (is_string($oldActiveId)) $snapshot['activeProfileId'] = $profileIds[$oldActiveId] ?? null;
        $snapshot['integrity'] = $snapshot['profiles'] === [] ? null : $this->snapshotIntegrity($targetProjectId, $userId, $snapshot['activeProfileId'], $snapshot['profiles']);
        return $snapshot;
    }

    private function snapshotIntegrity(string $projectId, string $userId, ?string $activeProfileId, array $profiles): string {
        $key = $this->encryptionKey(false);
        $payload = json_encode([
            'projectId'=>$projectId,
            'userId'=>$userId,
            'activeProfileId'=>$activeProfileId,
            'profiles'=>array_map(static fn(array $profile): array => [
                'id'=>$profile['id'],
                'name'=>$profile['name'],
                'model'=>$profile['model'],
                'fallbackModel'=>$profile['fallbackModel'],
                'credentialCiphertext'=>$profile['credentialCiphertext'],
            ], $profiles),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        return hash_hmac('sha256', $payload, $key);
    }

    private static function validateStoredCredential(string $ciphertext): string {
        if (!function_exists('sodium_crypto_secretbox_open')) throw new Problem(503, 'На сервере недоступно защищённое хранилище ключей AI.');
        $decoded = base64_decode($ciphertext, true);
        if ($decoded === false || strlen($decoded) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES || strlen($ciphertext) > 1200) {
            throw new Problem(422, 'Некорректный зашифрованный ключ AI в резервной копии.');
        }
        return $ciphertext;
    }

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

    public function settings(string $projectId, string $userId, ?string $requestedProfileId = null): ?OpenRouterSettings {
        $this->store->owned($projectId, $userId);
        $state = $this->state($projectId);
        $profileId = $requestedProfileId ?? ($state['active_profile_id'] ?? null);
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
        } catch (Problem $error) {
            if ($this->store->db->inTransaction()) $this->store->db->rollBack();
            throw $error;
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
