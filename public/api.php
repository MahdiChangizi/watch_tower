<?php

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../db/db.php';

use PDO;

// Initialize database connection
$db = Database::connect();

// Get query parameters
$json_format = isset($_GET['json']) && strtolower($_GET['json']) === 'true';
$program_name = $_GET['program_name'] ?? null;

// Parse request path
$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$script_name = $_SERVER['SCRIPT_NAME'];

// Remove script name from path
if (strpos($request_uri, $script_name) === 0) {
    $path = substr($request_uri, strlen($script_name));
} else {
    $path = $request_uri;
}

$path = trim($path, '/');
$path = preg_replace('#^api\.php/?#', '', $path);
$path = trim($path, '/');

// Get resource from path
$path_parts = explode('/', $path);
$resource = $path_parts[0] ?? '';

// Helper function to parse JSONB fields
function parse_jsonb_fields($records) {
    $jsonb_fields = ['scopes', 'ooscopes', 'config', 'ips', 'tech', 'headers', 'cdn'];
    
    foreach ($records as &$record) {
        foreach ($jsonb_fields as $field) {
            if (isset($record[$field]) && is_string($record[$field])) {
                $decoded = json_decode($record[$field], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $record[$field] = $decoded;
                }
            }
        }
    }
    
    return $records;
}

// Helper function to extract subdomain/domain values for plain text output
function extract_domains($data, $field_name = 'subdomain') {
    $domains = [];
    foreach ($data as $row) {
        if (isset($row[$field_name]) && !empty($row[$field_name])) {
            $domains[] = $row[$field_name];
        }
    }
    return $domains;
}

try {
    switch ($resource) {
        case 'programs':
            $stmt = $db->prepare("SELECT * FROM programs ORDER BY created_date DESC");
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $data = parse_jsonb_fields($data);
            
            if ($json_format) {
                header('Content-Type: application/json');
                echo json_encode(['programs' => $data], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            } else {
                // Return plain text list of program names
                header('Content-Type: text/plain');
                $programs = extract_domains($data, 'program_name');
                echo implode("\n", $programs);
            }
            break;
            
        case 'subdomains':
            if ($program_name) {
                $stmt = $db->prepare("SELECT * FROM subdomains WHERE program_name = :program_name ORDER BY last_update DESC");
                $stmt->execute([':program_name' => $program_name]);
            } else {
                $stmt = $db->prepare("SELECT * FROM subdomains ORDER BY last_update DESC");
                $stmt->execute();
            }
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if ($json_format) {
                header('Content-Type: application/json');
                echo json_encode(['subdomains' => $data], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            } else {
                // Return plain text list of subdomains
                header('Content-Type: text/plain');
                $domains = extract_domains($data, 'subdomain');
                echo implode("\n", $domains);
            }
            break;
            
        case 'http':
            if ($program_name) {
                $stmt = $db->prepare("SELECT * FROM http WHERE program_name = :program_name ORDER BY last_update DESC");
                $stmt->execute([':program_name' => $program_name]);
            } else {
                $stmt = $db->prepare("SELECT * FROM http ORDER BY last_update DESC");
                $stmt->execute();
            }
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $data = parse_jsonb_fields($data);
            
            if ($json_format) {
                header('Content-Type: application/json');
                echo json_encode(['http' => $data], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            } else {
                // Return plain text list of subdomains
                header('Content-Type: text/plain');
                $domains = extract_domains($data, 'subdomain');
                echo implode("\n", $domains);
            }
            break;
            
        case 'live-subdomains':
        case 'lives':
            if ($program_name) {
                $stmt = $db->prepare("SELECT * FROM live_subdomains WHERE program_name = :program_name ORDER BY last_update DESC");
                $stmt->execute([':program_name' => $program_name]);
            } else {
                $stmt = $db->prepare("SELECT * FROM live_subdomains ORDER BY last_update DESC");
                $stmt->execute();
            }
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $data = parse_jsonb_fields($data);
            
            if ($json_format) {
                header('Content-Type: application/json');
                echo json_encode(['live_subdomains' => $data], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            } else {
                // Return plain text list of subdomains
                header('Content-Type: text/plain');
                $domains = extract_domains($data, 'subdomain');
                echo implode("\n", $domains);
            }
            break;
            
        case 'openapi':
        case 'swagger':
        case 'api-docs':
            // Serve OpenAPI specification
            $swagger_file = __DIR__ . '/swagger.yaml';
            if (file_exists($swagger_file)) {
                header('Content-Type: application/x-yaml');
                readfile($swagger_file);
            } else {
                http_response_code(404);
                echo "OpenAPI specification not found";
            }
            break;
            
        case '':
        default:
            // API documentation
            if ($json_format) {
                header('Content-Type: application/json');
                echo json_encode([
                    'message' => 'Watch Tower API',
                    'version' => '1.0.0',
                    'documentation' => [
                        'swagger_ui' => '/api-docs.html',
                        'openapi_spec' => '/api.php/openapi',
                        'swagger_spec' => '/swagger.yaml'
                    ],
                    'endpoints' => [
                        'GET /api.php/programs?json=true' => 'Get all programs (JSON format)',
                        'GET /api.php/programs?json=false' => 'Get all programs (plain text list)',
                        'GET /api.php/subdomains?json=true&program_name=xxx' => 'Get subdomains as JSON (optional: filter by program)',
                        'GET /api.php/subdomains?json=false&program_name=xxx' => 'Get subdomains as plain text list (optional: filter by program)',
                        'GET /api.php/http?json=true&program_name=xxx' => 'Get HTTP data as JSON (optional: filter by program)',
                        'GET /api.php/http?json=false&program_name=xxx' => 'Get HTTP subdomains as plain text list (optional: filter by program)',
                        'GET /api.php/live-subdomains?json=true&program_name=xxx' => 'Get live subdomains as JSON (optional: filter by program)',
                        'GET /api.php/live-subdomains?json=false&program_name=xxx' => 'Get live subdomains as plain text list (optional: filter by program)',
                    ],
                    'parameters' => [
                        'json' => 'true for JSON format, false or omitted for plain text list',
                        'program_name' => 'Filter results by program name (optional)'
                    ]
                ], JSON_PRETTY_PRINT);
            } else {
                header('Content-Type: text/html; charset=utf-8');
                echo "<!DOCTYPE html><html><head><title>Watch Tower API</title>";
                echo "<style>
                    body { font-family: Arial, sans-serif; max-width: 1200px; margin: 0 auto; padding: 20px; background: #f5f5f5; }
                    .container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
                    h1 { color: #1f2937; border-bottom: 3px solid #3b82f6; padding-bottom: 10px; }
                    h2 { color: #374151; margin-top: 30px; }
                    a { color: #3b82f6; text-decoration: none; }
                    a:hover { text-decoration: underline; }
                    ul { line-height: 1.8; }
                    .swagger-link { display: inline-block; margin: 20px 0; padding: 12px 24px; background: #3b82f6; color: white; border-radius: 4px; font-weight: bold; }
                    .swagger-link:hover { background: #2563eb; text-decoration: none; }
                    code { background: #f3f4f6; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
                </style>";
                echo "</head><body><div class='container'>";
                echo "<h1>🔍 Watch Tower API</h1>";
                echo "<a href='/api-docs.html' class='swagger-link'>📚 View Interactive API Documentation (Swagger UI)</a>";
                echo "<h2>Available Endpoints:</h2>";
                echo "<ul>";
                echo "<li><a href='/api.php/programs?json=true'><code>GET /api.php/programs?json=true</code></a> - Get all programs (JSON format)</li>";
                echo "<li><a href='/api.php/programs?json=false'><code>GET /api.php/programs?json=false</code></a> - Get all programs (plain text list)</li>";
                echo "<li><a href='/api.php/subdomains?json=true'><code>GET /api.php/subdomains?json=true</code></a> - Get all subdomains (JSON format)</li>";
                echo "<li><a href='/api.php/subdomains?json=false'><code>GET /api.php/subdomains?json=false</code></a> - Get all subdomains (plain text list)</li>";
                echo "<li><a href='/api.php/http?json=true'><code>GET /api.php/http?json=true</code></a> - Get all HTTP data (JSON format)</li>";
                echo "<li><a href='/api.php/http?json=false'><code>GET /api.php/http?json=false</code></a> - Get HTTP subdomains (plain text list)</li>";
                echo "<li><a href='/api.php/live-subdomains?json=true'><code>GET /api.php/live-subdomains?json=true</code></a> - Get all live subdomains (JSON format)</li>";
                echo "<li><a href='/api.php/live-subdomains?json=false'><code>GET /api.php/live-subdomains?json=false</code></a> - Get live subdomains (plain text list)</li>";
                echo "</ul>";
                echo "<h2>Parameters:</h2>";
                echo "<ul>";
                echo "<li><strong><code>json</code></strong>: <code>true</code> for JSON format, <code>false</code> or omitted for plain text list</li>";
                echo "<li><strong><code>program_name</code></strong>: Filter results by program name (optional)</li>";
                echo "</ul>";
                echo "<h2>Examples:</h2>";
                echo "<ul>";
                echo "<li><a href='/api.php/http?program_name=discourse&json=false'><code>/api.php/http?program_name=discourse&json=false</code></a> - Returns plain text list of subdomains</li>";
                echo "<li><a href='/api.php/subdomains?program_name=discourse&json=false'><code>/api.php/subdomains?program_name=discourse&json=false</code></a> - Returns plain text list of subdomains</li>";
                echo "</ul>";
                echo "<h2>API Documentation:</h2>";
                echo "<ul>";
                echo "<li><a href='/api-docs.html'>Swagger UI Documentation</a> - Interactive API documentation</li>";
                echo "<li><a href='/api.php/openapi'>OpenAPI Specification (YAML)</a> - Machine-readable API spec</li>";
                echo "<li><a href='/swagger.yaml'>Swagger YAML File</a> - Direct link to specification file</li>";
                echo "</ul>";
                echo "</div></body></html>";
            }
            break;
    }
    
} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage());
    http_response_code(500);
    if ($json_format) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Internal server error: ' . $e->getMessage()], JSON_PRETTY_PRINT);
    } else {
        header('Content-Type: text/plain');
        echo "Error: " . $e->getMessage();
    }
}
