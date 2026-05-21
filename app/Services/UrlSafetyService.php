<?php

namespace App\Services;

/**
 * Block server-side outbound HTTP from being weaponized as an SSRF
 * primitive via attacker-controlled URLs (LLM-01, AUTH-06).
 *
 * Pragmatic, IP-literal-only host guard: refuses literal IPv4/IPv6 in
 * private / loopback / link-local / cloud-metadata ranges. Does NOT
 * do live DNS resolution (full DNS-rebind protection requires hooking
 * Guzzle's curl handle to lock the resolved IP per request — out of
 * scope here). Operators who need that should add it at the network
 * layer (egress firewall).
 */
class UrlSafetyService
{
    /**
     * Validate a URL for use as an outbound HTTP destination.
     *
     * @param array{allow_loopback?:bool, allow_http?:bool} $opts
     */
    public static function assertSafe(string $url, array $opts = []): void
    {
        $allowLoopback = $opts['allow_loopback'] ?? false;
        $allowHttp = $opts['allow_http'] ?? false;

        $parsed = parse_url($url);
        if (!$parsed || empty($parsed['host'])) {
            throw new \InvalidArgumentException('Invalid URL.');
        }

        $scheme = strtolower($parsed['scheme'] ?? '');
        if ($scheme !== 'https' && !($allowHttp && $scheme === 'http')) {
            throw new \InvalidArgumentException('Only https:// URLs are allowed for this provider.');
        }

        $host = strtolower($parsed['host']);

        // Loopback hosts
        $loopback = in_array($host, ['localhost', 'ip6-localhost'], true)
            || self::ipIs($host, '127.0.0.0/8')
            || self::ipIs($host, '::1/128');

        if ($loopback && !$allowLoopback) {
            throw new \InvalidArgumentException('Loopback hosts are not allowed for this provider.');
        }

        // Cloud-metadata literals
        if (in_array($host, ['169.254.169.254', 'metadata.google.internal', 'fd00:ec2::254'], true)) {
            throw new \InvalidArgumentException('Cloud-metadata addresses are not allowed.');
        }

        // RFC1918 / link-local / unique-local — IPv4 + IPv6
        $blocked = [
            '10.0.0.0/8',
            '172.16.0.0/12',
            '192.168.0.0/16',
            '169.254.0.0/16',
            '100.64.0.0/10', // CGNAT
            'fc00::/7',      // ULA
            'fe80::/10',     // link-local
        ];
        foreach ($blocked as $range) {
            if (self::ipIs($host, $range)) {
                throw new \InvalidArgumentException("Host {$host} is in a private/restricted range.");
            }
        }
    }

    private static function ipIs(string $host, string $cidr): bool
    {
        // Only check if host parses as an IP literal — we don't resolve DNS.
        if (filter_var($host, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        [$subnet, $bits] = array_pad(explode('/', $cidr, 2), 2, null);
        $hostBin = inet_pton($host);
        $subnetBin = inet_pton($subnet);
        if ($hostBin === false || $subnetBin === false || strlen($hostBin) !== strlen($subnetBin)) {
            return false;
        }

        $bits = (int) $bits;
        $bytes = intdiv($bits, 8);
        $remainder = $bits % 8;

        if (substr($hostBin, 0, $bytes) !== substr($subnetBin, 0, $bytes)) {
            return false;
        }
        if ($remainder === 0) {
            return true;
        }
        $mask = chr(0xFF << (8 - $remainder) & 0xFF);

        return (ord($hostBin[$bytes]) & ord($mask)) === (ord($subnetBin[$bytes]) & ord($mask));
    }
}
