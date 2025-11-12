<?php

namespace App\Http;

use App\Tools\Httpx;
use App\Helpers\Helpers;
use App\Services\Http;
use App\Services\LiveSubdomain;

class HttpDiscovery {
    public $http;
    public Helpers $helper;
    public LiveSubdomain $liveSubdomain;

    public function __construct() {
        $this->http = new Http();
        $this->helper = new Helpers();
        $this->liveSubdomain = new LiveSubdomain();
    }

    public function discover_http() {
        $live_subdomains = $this->liveSubdomain->getAllLives();
        $just_subdomains = [];

        foreach ($live_subdomains as $item) {
            $just_subdomains[] = $item['subdomain'];
        }

        $tempFile = $this->helper->create_temp_file($just_subdomains, 'subdomain');
        $httpx = new Httpx($tempFile);
        $responses = $httpx->getHttpResponses();

        // Build a map by subdomain for quick lookup
        $subdomain_map = [];
        foreach ($live_subdomains as $item) {
            $subdomain_map[$item['subdomain']] = $item;
        }

        // Helper function to normalize ips to an array
        $normalizeIps = function($ips) {
            if (is_array($ips)) {
                return $ips;
            }

            if ($ips === null) {
                return [];
            }

            // Attempt to decode from JSON
            $decoded = json_decode($ips, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }

            // Split by comma/space/semicolon
            $parts = preg_split('/[\s,;]+/', trim($ips));
            $parts = array_filter($parts, function($v) { return $v !== '' && $v !== null; });
            return array_values($parts);
        };

        foreach ($responses as $response) {
            // httpx may have different fields; try to get Host correctly
            $url = $response['url'] ?? ($response['input'] ?? ($response['host'] ?? ''));
            $host = parse_url($url, PHP_URL_HOST);

            // Some httpx outputs may provide a separate host field
            if (empty($host) && !empty($response['host'])) {
                $host = $response['host'];
            }

            if (empty($host)) {
                // If we couldn't extract host, skip or log it
                // error_log("discover_http: couldn't extract host from response: " . json_encode($response));
                continue;
            }

            if (isset($subdomain_map[$host])) {
                $item = $subdomain_map[$host];

                // Normalize ips before calling upsert_http
                $ips_array = $normalizeIps($item['ips'] ?? null);

                $this->http->upsert_http(
                    $item['program_name'] ?? '',
                    $item['subdomain'],
                    $ips_array,
                    $response['tech'] ?? [],
                    $response['title'] ?? '',
                    $response['status_code'] ?? 0,
                    $response['header'] ?? [],
                    $response['url'] ?? '',
                    $response['url'] ?? '',
                    $response['favicon'] ?? ''
                );
            } else {
                // If host not in DB, we can log or take other actions
                // error_log("discover_http: host not found in subdomain_map: $host");
            }
        }

        $this->helper->delete_temp_file($tempFile);
    }

}