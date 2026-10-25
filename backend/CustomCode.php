<?php
declare(strict_types=1);
namespace Juzimder;

/** Code stays data on the server and is rendered in an opaque-origin iframe with restrictive CSP. */
final class CustomCode {
    private const PREFIX = 'jzcc1.';
    private const MAX_TOTAL_BYTES = 65536;

    public static function validatePayload(mixed $payload): array {
        if ($payload instanceof \stdClass) $payload = get_object_vars($payload);
        if (!is_array($payload) || array_is_list($payload)) throw new Problem(422, 'Некорректные данные блока Custom Code.');
        $keys = array_keys($payload); sort($keys);
        if ($keys !== ['css','html','js'] || !is_string($payload['html']) || !is_string($payload['css']) || !is_string($payload['js'])) throw new Problem(422, 'В Custom Code разрешены только поля HTML, CSS и JavaScript.');
        if (strlen($payload['html']) > 32768 || strlen($payload['css']) > 16384 || strlen($payload['js']) > 16384 || strlen($payload['html']) + strlen($payload['css']) + strlen($payload['js']) > self::MAX_TOTAL_BYTES) throw new Problem(422, 'HTML, CSS и JavaScript блока превышают допустимый размер.');
        if (preg_match('~</style\s*>~i', $payload['css'])) throw new Problem(422, 'В CSS блока нельзя закрывать тег style.');
        return ['html'=>$payload['html'],'css'=>$payload['css'],'js'=>$payload['js']];
    }

    public static function decode(mixed $value): array {
        if (!is_string($value) || !str_starts_with($value, self::PREFIX)) throw new Problem(422, 'Некорректный блок Custom Code.');
        $encoded = substr($value, strlen(self::PREFIX));
        if ($encoded === '' || strlen($encoded) > 90000 || !preg_match('/^[A-Za-z0-9_-]+$/D', $encoded)) throw new Problem(422, 'Код блока пуст или превышает допустимый размер.');
        $json = base64_decode(strtr($encoded, '-_', '+/') . str_repeat('=', (4 - strlen($encoded) % 4) % 4), true);
        if (!is_string($json) || strlen($json) > self::MAX_TOTAL_BYTES) throw new Problem(422, 'Код блока превышает допустимый размер.');
        try { $payload = json_decode($json, true, 8, JSON_THROW_ON_ERROR); } catch (\JsonException) { throw new Problem(422, 'Некорректные данные блока Custom Code.'); }
        return self::validatePayload($payload);
    }

    public static function encode(array $payload): string {
        $payload = self::validatePayload($payload);
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if (strlen($json) > self::MAX_TOTAL_BYTES) throw new Problem(422, 'HTML, CSS и JavaScript блока превышают допустимый размер.');
        return self::PREFIX . rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }

    public static function countdownPayload(mixed $deadline): array {
        if (!is_string($deadline) || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/D', $deadline)) throw new Problem(422, 'Укажите дату счётчика в UTC.');
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', $deadline, new \DateTimeZone('UTC'));
        $errors = \DateTimeImmutable::getLastErrors();
        if (!$date || ($errors !== false && ($errors['warning_count'] || $errors['error_count'])) || $date->format('Y-m-d\TH:i:s\Z') !== $deadline) throw new Problem(422, 'Дата счётчика указана неверно.');
        $html = '<div class="jz-countdown" role="timer" aria-label="Обратный отсчёт"><strong data-jz-countdown="days">00</strong><span>дней</span><strong data-jz-countdown="hours">00</strong><span>часов</span><strong data-jz-countdown="minutes">00</strong><span>минут</span><strong data-jz-countdown="seconds">00</strong><span>секунд</span></div>';
        $css = '.jz-countdown{display:flex;flex-wrap:wrap;align-items:baseline;gap:.35rem .65rem;font:600 clamp(1rem,3vw,2rem)/1.2 system-ui,sans-serif}.jz-countdown strong{font-variant-numeric:tabular-nums}.jz-countdown span{font-size:.8em;font-weight:400}';
        $end = json_encode($deadline, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
        $js = '(function(){const end=new Date(' . $end . ').getTime();const fields={days:document.querySelector("[data-jz-countdown=days]"),hours:document.querySelector("[data-jz-countdown=hours]"),minutes:document.querySelector("[data-jz-countdown=minutes]"),seconds:document.querySelector("[data-jz-countdown=seconds]")};function update(){let left=Math.max(0,Math.floor((end-Date.now())/1000));const values={days:Math.floor(left/86400),hours:Math.floor(left%86400/3600),minutes:Math.floor(left%3600/60),seconds:left%60};for(const [key,node] of Object.entries(fields))node.textContent=String(values[key]).padStart(2,"0");if(left>0)setTimeout(update,1000)}update()})();';
        return ['html'=>$html,'css'=>$css,'js'=>$js];
    }

    public static function sandboxDocument(array $payload, string $nonce): string {
        if (!preg_match('/^[a-f0-9]{32}$/D', $nonce)) throw new Problem(422, 'Некорректный nonce песочницы.');
        $payload = self::validatePayload($payload);
        $script = '';
        if ($payload['js'] !== '') {
            $scriptData = json_encode(base64_encode($payload['js']), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
            $script = '<script nonce="' . $nonce . '">(function(){const data=' . $scriptData . ';const bytes=Uint8Array.from(atob(data),c=>c.charCodeAt(0));const code=new TextDecoder().decode(bytes);const node=document.createElement("script");node.nonce=document.currentScript.nonce;node.textContent=code;document.body.append(node);})();</script>';
        }
        $csp = "default-src 'none'; script-src 'nonce-" . $nonce . "'; connect-src 'none'; img-src data: blob:; media-src data: blob:; font-src data:; style-src 'unsafe-inline'; frame-src 'none'; object-src 'none'; base-uri 'none'; form-action 'none'";
        return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta http-equiv="Content-Security-Policy" content="' . htmlspecialchars($csp, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8') . '"><style>*,*::before,*::after{box-sizing:border-box}body{margin:0}' . $payload['css'] . '</style></head><body>' . $payload['html'] . $script . '</body></html>';
    }
}
