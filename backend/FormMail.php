<?php
declare(strict_types=1);
namespace Juzimder;

final class FormMailVault {
    public function __construct(private readonly Store $store) {}

    public function status(string $projectId, string $userId): array {
        $this->store->owned($projectId, $userId);
        $row = $this->store->query('SELECT settings_ciphertext,version FROM project_form_mail_settings WHERE project_id=?', [$projectId])->fetch();
        if (!$row) return ['configured'=>false,'host'=>'','port'=>587,'security'=>'starttls','username'=>'','hasPassword'=>false,'version'=>0];
        $settings = $this->decryptSettings($row['settings_ciphertext']);
        return ['configured'=>true,'host'=>$settings['host'],'port'=>$settings['port'],'security'=>$settings['security'],'username'=>$settings['username'],'hasPassword'=>$settings['password']!=='','version'=>(int)$row['version']];
    }

    public function save(string $projectId, string $userId, int $expectedVersion, mixed $input): array {
        $this->store->owned($projectId, $userId);
        if ($input instanceof \stdClass) $input = get_object_vars($input);
        if (!is_array($input) || array_diff(array_keys($input), ['host','port','security','username','password']) || !is_int($input['port'] ?? null)) throw new Problem(422, 'Проверьте параметры SMTP.');
        $host = trim(is_string($input['host'] ?? null) ? $input['host'] : '');
        $username = trim(is_string($input['username'] ?? null) ? $input['username'] : '');
        $password = is_string($input['password'] ?? null) ? $input['password'] : '';
        $security = $input['security'] ?? 'starttls';
        if ($host === '' || strlen($host) > 253 || preg_match('/[\x00-\x20\x7F\/\\@]/', $host) || !preg_match('/^[A-Za-z0-9.:-]+$/D', $host)) throw new Problem(422, 'Укажите корректный адрес SMTP-сервера.');
        if ($input['port'] < 1 || $input['port'] > 65535 || !in_array($security, ['none','starttls','ssl'], true)) throw new Problem(422, 'Проверьте порт и тип защищённого соединения SMTP.');
        if (strlen($username) > 254 || strlen($password) > 1024 || preg_match('/[\r\n\x00]/', $username.$password)) throw new Problem(422, 'Проверьте имя пользователя и пароль SMTP.');
        $existing = $this->store->query('SELECT settings_ciphertext,version FROM project_form_mail_settings WHERE project_id=?', [$projectId])->fetch();
        $version = $existing ? (int)$existing['version'] : 0;
        if ($version !== $expectedVersion) throw new Problem(409, 'Настройки SMTP изменились в другой вкладке. Обновите страницу.');
        if ($password === '' && $existing) $password = $this->decryptSettings($existing['settings_ciphertext'])['password'];
        if ($username !== '' && $password === '') throw new Problem(422, 'Введите пароль SMTP для указанного пользователя.');
        if ($username !== '' && $security === 'none') throw new Problem(422, 'SMTP-логин и пароль нельзя отправлять без TLS. Выберите STARTTLS или SSL/TLS.');
        $next = ['host'=>$host,'port'=>$input['port'],'security'=>$security,'username'=>$username,'password'=>$password];
        $ciphertext = $this->encryptSettings($next);
        if ($existing) {
            $updated = $this->store->query('UPDATE project_form_mail_settings SET settings_ciphertext=?,version=version+1,updated_at=CURRENT_TIMESTAMP WHERE project_id=? AND version=?', [$ciphertext,$projectId,$expectedVersion]);
            if ($updated->rowCount() !== 1) throw new Problem(409, 'Настройки SMTP изменились в другой вкладке. Обновите страницу.');
        } else {
            $this->store->query('INSERT INTO project_form_mail_settings(project_id,settings_ciphertext,version) VALUES(?,?,1)', [$projectId,$ciphertext]);
        }
        return $this->status($projectId, $userId);
    }

    /** Server-only decrypted delivery settings. */
    public function deliverySettings(string $projectId): ?array {
        $row = $this->store->query('SELECT settings_ciphertext FROM project_form_mail_settings WHERE project_id=?', [$projectId])->fetch();
        return $row ? $this->decryptSettings($row['settings_ciphertext']) : null;
    }

    private function encryptSettings(array $settings): string {
        if (!function_exists('sodium_crypto_secretbox')) throw new Problem(503, 'Защищённое хранилище почтовых настроек недоступно.');
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plain = json_encode($settings, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        return base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, $this->encryptionKey(true)));
    }

    private function decryptSettings(string $ciphertext): array {
        if (!function_exists('sodium_crypto_secretbox_open')) throw new Problem(503, 'Защищённое хранилище почтовых настроек недоступно.');
        $packed = base64_decode($ciphertext, true);
        if ($packed === false || strlen($packed) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) throw new Problem(503, 'Сохранённые почтовые настройки недоступны.');
        $nonce = substr($packed, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plain = sodium_crypto_secretbox_open(substr($packed, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $nonce, $this->encryptionKey(false));
        if (!is_string($plain)) throw new Problem(503, 'Сохранённые почтовые настройки недоступны.');
        $decoded = json_decode($plain, true, 8, JSON_THROW_ON_ERROR);
        if (!is_array($decoded) || !is_string($decoded['host'] ?? null) || !is_int($decoded['port'] ?? null) || !is_string($decoded['security'] ?? null) || !is_string($decoded['username'] ?? null) || !is_string($decoded['password'] ?? null)) throw new Problem(503, 'Сохранённые почтовые настройки повреждены.');
        return $decoded;
    }

    private function encryptionKey(bool $create): string {
        $path = $this->store->path . '.forms-key';
        if (is_link($path)) throw new Problem(503, 'Файл ключа почтового хранилища должен быть обычным закрытым файлом.');
        if (!is_file($path) && $create) {
            $oldUmask = umask(0077);
            try { $handle = @fopen($path, 'x+b'); } finally { umask($oldUmask); }
            if (is_resource($handle)) {
                $key = random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
                $written = fwrite($handle, $key); fflush($handle); fclose($handle); @chmod($path, 0600);
                if ($written !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) { @unlink($path); throw new Problem(503, 'Не удалось создать закрытое почтовое хранилище.'); }
                return $key;
            }
        }
        $permissions = @fileperms($path); $key = @file_get_contents($path);
        if ($permissions === false || ($permissions & 0077) !== 0 || !is_string($key) || strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) throw new Problem(503, 'Почтовое хранилище недоступно или имеет неверные права.');
        return $key;
    }
}

final class SmtpTransport {
    public function verify(array $settings): void { $this->withConnection($settings, static fn($socket) => null); }

    public function send(array $settings, string $fromEmail, string $fromName, array $recipients, string $subject, string $body): void {
        $fromEmail = self::address($fromEmail);
        if ($recipients === [] || count($recipients) > 10) throw new \RuntimeException('No recipients configured.');
        foreach ($recipients as $recipient) self::address($recipient);
        foreach ([$fromName,$subject,$body] as $text) if (preg_match('/[\x00]/', $text)) throw new \RuntimeException('Invalid message content.');
        $this->withConnection($settings, function($socket) use ($fromEmail,$fromName,$recipients,$subject,$body): void {
            $this->command($socket, 'MAIL FROM:<' . $fromEmail . '>', [250]);
            foreach ($recipients as $recipient) $this->command($socket, 'RCPT TO:<' . self::address($recipient) . '>', [250,251]);
            $this->command($socket, 'DATA', [354]);
            $encodedName = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
            $headers = ['From: ' . ($fromName !== '' ? $encodedName . ' ' : '') . '<' . $fromEmail . '>', 'To: ' . implode(', ', array_map([self::class,'formatAddress'], $recipients)), 'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=', 'Date: ' . date(DATE_RFC2822), 'MIME-Version: 1.0', 'Content-Type: text/plain; charset=UTF-8', 'Content-Transfer-Encoding: base64'];
            $message = implode("\r\n", $headers) . "\r\n\r\n" . chunk_split(base64_encode(str_replace(["\r\n","\r"], "\n", $body)), 76, "\r\n");
            $message = preg_replace('/(?m)^\./', '..', $message) ?? $message;
            if (fwrite($socket, $message . ".\r\n") === false) throw new \RuntimeException('SMTP message write failed.');
            $this->expect($socket, [250]);
            $this->command($socket, 'QUIT', [221]);
        });
    }

    private function withConnection(array $settings, callable $callback): mixed {
        $host = $settings['host'] ?? ''; $port = $settings['port'] ?? 0; $security = $settings['security'] ?? ''; $username = $settings['username'] ?? ''; $password = $settings['password'] ?? '';
        if (!is_string($host) || !is_int($port) || !in_array($security, ['none','starttls','ssl'], true) || !is_string($username) || !is_string($password)) throw new \RuntimeException('SMTP settings are incomplete.');
        $target = ($security === 'ssl' ? 'tls://' : 'tcp://') . $host . ':' . $port;
        $context = stream_context_create(['ssl'=>['verify_peer'=>true,'verify_peer_name'=>true,'peer_name'=>$host,'SNI_enabled'=>true]]);
        $socket = @stream_socket_client($target, $errorCode, $errorMessage, 10, STREAM_CLIENT_CONNECT, $context);
        if (!is_resource($socket)) throw new \RuntimeException('SMTP server connection failed.');
        stream_set_timeout($socket, 10);
        try {
            $this->expect($socket, [220]);
            $this->command($socket, 'EHLO juzimder.local', [250]);
            if ($security === 'starttls') {
                $this->command($socket, 'STARTTLS', [220]);
                if (stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT) !== true) throw new \RuntimeException('SMTP TLS negotiation failed.');
                $this->command($socket, 'EHLO juzimder.local', [250]);
            }
            if ($username !== '') {
                $this->command($socket, 'AUTH LOGIN', [334]);
                $this->command($socket, base64_encode($username), [334]);
                $this->command($socket, base64_encode($password), [235]);
            }
            $result = $callback($socket);
            return $result;
        } finally { fclose($socket); }
    }

    private function command($socket, string $command, array $expected): void {
        if (preg_match('/[\r\n]/', $command) || fwrite($socket, $command . "\r\n") === false) throw new \RuntimeException('SMTP command failed.');
        $this->expect($socket, $expected);
    }

    private function expect($socket, array $expected): void {
        $code = null;
        do {
            $line = fgets($socket, 2048);
            if (!is_string($line) || !preg_match('/^(\d{3})([ -])/', $line, $match)) throw new \RuntimeException('SMTP server response was invalid.');
            $code = (int)$match[1];
            $more = $match[2] === '-';
        } while ($more);
        if (!in_array($code, $expected, true)) throw new \RuntimeException('SMTP server rejected the request (' . $code . ').');
    }

    private static function address(string $value): string {
        if (strlen($value) > 254 || !filter_var($value, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n\x00]/', $value)) throw new \RuntimeException('Invalid email address.');
        return $value;
    }

    private static function formatAddress(string $email): string { return '<' . self::address($email) . '>'; }
}

final class FormMailDelivery {
    public function __construct(private readonly Store $store, private readonly FormMailVault $vault, private readonly SmtpTransport $smtp) {}

    public function deliver(string $projectId, string $formId, string $pagePath, array $fields): void {
        try {
            $row = $this->store->query('SELECT settings_payload,name FROM projects WHERE id=?', [$projectId])->fetch();
            if (!$row) return;
            $settings = json_decode($row['settings_payload'] ?: '{}', true, 64, JSON_THROW_ON_ERROR);
            $mail = $settings['forms'] ?? [];
            $notifyOwner = !empty($mail['notificationsEnabled']) && !empty($mail['recipients']);
            $autoReply = !empty($mail['autoresponderEnabled']);
            if ((!$notifyOwner && !$autoReply) || empty($mail['senderEmail'])) return;
            $smtp = $this->vault->deliverySettings($projectId);
            if (!$smtp) return;
            $variables = self::variables($row['name'], $formId, $pagePath, $fields);
            $subject = self::render((string)($mail['subject'] ?? ''), $variables);
            $body = self::render((string)($mail['body'] ?? ''), $variables);
            if ($notifyOwner) $this->smtp->send($smtp, $mail['senderEmail'], (string)($mail['senderName'] ?? ''), $mail['recipients'], $subject, $body);
            if (!empty($mail['autoresponderEnabled'])) {
                $emailField = (string)($mail['autoresponderEmailField'] ?? 'email');
                $recipient = $fields[$emailField] ?? '';
                if (is_string($recipient) && filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                    $this->smtp->send($smtp, $mail['senderEmail'], (string)($mail['senderName'] ?? ''), [$recipient], self::render((string)($mail['autoresponderSubject'] ?? ''), $variables), self::render((string)($mail['autoresponderBody'] ?? ''), $variables));
                }
            }
        } catch (\Throwable) {
            // Submission storage succeeds independently; delivery errors never expose mail data to the visitor.
        }
    }

    private static function variables(string $site, string $form, string $page, array $fields): array {
        $lines = [];
        foreach ($fields as $name=>$value) { $lines[] = $name . ': ' . (is_scalar($value) ? (string)$value : ''); }
        $vars = ['site'=>$site,'form'=>$form,'page'=>$page,'date'=>date('Y-m-d H:i:s'),'fields'=>implode("\n",$lines)];
        foreach ($fields as $name=>$value) if (is_string($name) && preg_match('/^[A-Za-z][A-Za-z0-9_-]{0,63}$/D',$name)) $vars['field:'.$name]=is_scalar($value)?(string)$value:'';
        return $vars;
    }

    private static function render(string $template, array $variables): string {
        return preg_replace_callback('/\{\{([A-Za-z][A-Za-z0-9_-]{0,63}(?::[A-Za-z][A-Za-z0-9_-]{0,63})?)\}\}/', static fn($match)=>$variables[$match[1]]??'', $template) ?? '';
    }
}
