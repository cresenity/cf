<?php

/**
 * Lapisan server MCP (Model Context Protocol) milik framework: JSON-RPC di atas Streamable HTTP, hanya tool, tanpa sesi.
 * Ditulis untuk PHP 7.4; autentikasi dan otorisasi tetap urusan aplikasi pemanggil.
 */
class CAI_MCP {
    /**
     * Versi protokol yang dipahami, dari yang terbaru.
     *
     * @var array
     */
    const PROTOCOL_VERSIONS = ['2025-06-18', '2025-03-26', '2024-11-05'];

    /**
     * @param string $name
     * @param string $version
     *
     * @return CAI_MCP_Server
     */
    public static function server($name, $version = '1.0.0') {
        return new CAI_MCP_Server($name, $version);
    }

    /**
     * Nama tool pada setiap tools/call di body JSON-RPC (tunggal atau batch), untuk pemeriksaan sebelum dispatch.
     *
     * @param string $body
     *
     * @return array
     */
    public static function toolCalls($body) {
        $decoded = json_decode((string) $body, true);
        if (!is_array($decoded)) {
            return [];
        }
        $messages = isset($decoded['method']) ? [$decoded] : $decoded;
        $names = [];
        foreach ($messages as $message) {
            if (is_array($message) && isset($message['method']) && $message['method'] === 'tools/call' && isset($message['params']['name']) && is_string($message['params']['name'])) {
                $names[] = $message['params']['name'];
            }
        }

        return $names;
    }
}
