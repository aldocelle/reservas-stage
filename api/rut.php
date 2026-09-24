<?php
declare(strict_types=1);

function cleanRut(mixed $value): string {
    return strtoupper(preg_replace('/\s+/', '', (string)$value) ?? '');
}

function isGroupedRutBody(string $body): bool {
    if (!preg_match('/^\d{1,8}$/D', $body) && !preg_match('/^\d{1,3}(?:\.\d{3}){0,2}$/D', $body)) return false;
    if (!str_contains($body, '.')) return true;
    $parts = explode('.', $body);
    foreach ($parts as $index => $part) {
        if ($index === 0 ? !preg_match('/^\d{1,3}$/D', $part) : !preg_match('/^\d{3}$/D', $part)) return false;
    }
    return true;
}

function normalizeRut(mixed $value): string {
    $raw = cleanRut($value);
    if (preg_match('/^\d{8}$/D', $raw)) return '';
    if (!preg_match('/^\d{1,8}(?:\.\d{3}){0,2}-?[0-9K]$/D', $raw) && !preg_match('/^\d{1,3}(?:\.\d{3}){0,2}-?[0-9K]$/D', $raw)) return '';
    $compact = str_replace(['.', '-'], '', $raw);
    $body = substr($compact, 0, -1);
    $verifier = substr($compact, -1);
    $bodyText = preg_replace('/-?[0-9K]$/D', '', $raw) ?? '';
    if ($body === '' || !isGroupedRutBody($bodyText) || preg_match('/^0+$/', $body)) return '';
    return $body . $verifier;
}

function validateRut(mixed $value): bool {
    $rut = normalizeRut($value);
    if ($rut === '') return false;
    $body = substr($rut, 0, -1);
    $sum = 0;
    $factor = 2;
    for ($i = strlen($body) - 1; $i >= 0; $i--) {
        $sum += ((int)$body[$i]) * $factor;
        $factor = $factor === 7 ? 2 : $factor + 1;
    }
    $check = 11 - ($sum % 11);
    $expected = $check === 11 ? '0' : ($check === 10 ? 'K' : (string)$check);
    return $expected === substr($rut, -1);
}

// Alias compatible con el código existente.
function isValidRut(mixed $value): bool { return validateRut($value); }
