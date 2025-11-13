<?php

// require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../db/db.php';

// Initialize database connection
$db = Database::connect();

// Set CORS headers if needed
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

// Allow only GET and DELETE
if (!in_array($method, ['GET', 'DELETE'], true)) {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Method not allowed. Only GET and DELETE requests are supported.']);
    exit;
}

// Parse request path
$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$script_name = $_SERVER['SCRIPT_NAME'];

// Remove script name from path
if (strpos($request_uri, $script_name) === 0) {
    $path = substr($request_uri, strlen($script_name));
} else {
    $path = $request_uri;
}

// Remove leading/trailing slashes and clean up
$path = trim($path, '/');
$path = preg_replace('#^api\.php/?#', '', $path);
$path = trim($path, '/');

// Get query parameters
$json_param = $_GET['json'] ?? null;
$is_json = ($json_param === null || strtolower($json_param) === 'true');
$program_name = $_GET['program_name'] ?? null;
$domain = $_GET['domain'] ?? null;
$source = $_GET['source'] ?? null;

// Get resource from path (handle /api/programs/name format)
$path_parts = array_filter(explode('/', $path), function($part) {
    return !empty($part);
});
$path_parts = array_values($path_parts); // Re-index array

$resource = $path_parts[0] ?? '';
$resource_id = $path_parts[1] ?? null;

// Helper function to parse JSONB fields
function parse_jsonb_fields($records) {
    $jsonb_fields = ['scopes', 'ooscopes', 'config', 'ips', 'tech', 'headers', 'cdn', 'metadata', 'parameters'];
    
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

// Helper function to send JSON response
function send_json($data, $status_code = 200) {
    http_response_code($status_code);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

// Helper function to send text response
function send_text($data, $status_code = 200) {
    http_response_code($status_code);
    header('Content-Type: text/plain; charset=utf-8');
    echo $data;
}

try {
    switch ($resource) {
        case 'programs':
            if ($method === 'DELETE') {
                if (!$resource_id) {
                    send_json(['error' => 'Program name is required in path for deletion', 'code' => 400], 400);
                    break;
                }

                $program_to_delete = $resource_id;
                $db->beginTransaction();

                try {
                    $deleteSummary = [];

                    $tables = [
                        'live_subdomains' => 'DELETE FROM live_subdomains WHERE program_name = :program_name',
                        'subdomains' => 'DELETE FROM subdomains WHERE program_name = :program_name',
                        'http' => 'DELETE FROM http WHERE program_name = :program_name',
                        'urls' => 'DELETE FROM urls WHERE program_name = :program_name',
                        'ports' => 'DELETE FROM ports WHERE program_name = :program_name',
                    ];

                    foreach ($tables as $label => $sql) {
                        $stmt = $db->prepare($sql);
                        $stmt->execute([':program_name' => $program_to_delete]);
                        $deleteSummary[$label] = $stmt->rowCount();
                    }

                    $programStmt = $db->prepare("DELETE FROM programs WHERE program_name = :program_name");
                    $programStmt->execute([':program_name' => $program_to_delete]);
                    $programDeleted = $programStmt->rowCount();

                    if ($programDeleted === 0) {
                        $db->rollBack();
                        send_json(['error' => 'Program not found', 'code' => 404], 404);
                        break;
                    }

                    $db->commit();

                    send_json([
                        'message' => 'Program and related data deleted successfully',
                        'program_name' => $program_to_delete,
                        'deleted' => array_merge(['programs' => $programDeleted], $deleteSummary),
                    ]);
                } catch (Exception $exception) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }

                    error_log('API program deletion error: ' . $exception->getMessage());
                    send_json(['error' => 'Failed to delete program', 'code' => 500], 500);
                }
            } elseif ($resource_id) {
                // Get specific program by name
                $stmt = $db->prepare("SELECT * FROM programs WHERE program_name = :program_name LIMIT 1");
                $stmt->execute([':program_name' => $resource_id]);
                $data = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$data) {
                    send_json(['error' => 'Program not found', 'code' => 404], 404);
                    exit;
                }
                
                $data = parse_jsonb_fields([$data])[0];
                
                if ($is_json) {
                    send_json($data);
                } else {
                    send_text($data['program_name'] ?? '');
                }
            } else {
                // Get all programs
                $stmt = $db->prepare("SELECT * FROM programs ORDER BY created_date DESC");
                $stmt->execute();
                $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $data = parse_jsonb_fields($data);
                
                if ($is_json) {
                    send_json(['programs' => $data]);
                } else {
                    $programs = extract_domains($data, 'program_name');
                    send_text(implode("\n", $programs));
                }
            }
            break;
            
        case 'subdomains':
            $conditions = [];
            $params = [];
            
            if ($program_name) {
                $conditions[] = "program_name = :program_name";
                $params[':program_name'] = $program_name;
            }
            
            if ($domain) {
                $conditions[] = "subdomain LIKE :domain";
                $params[':domain'] = '%' . $domain . '%';
            }
            
            $where_clause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';
            $stmt = $db->prepare("SELECT * FROM subdomains $where_clause ORDER BY last_update DESC");
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if ($is_json) {
                send_json(['subdomains' => $data]);
            } else {
                $domains = extract_domains($data, 'subdomain');
                send_text(implode("\n", $domains));
            }
            break;
            
        case 'http':
            $conditions = [];
            $params = [];
            
            if ($program_name) {
                $conditions[] = "program_name = :program_name";
                $params[':program_name'] = $program_name;
            }
            
            if ($domain) {
                $conditions[] = "subdomain LIKE :domain";
                $params[':domain'] = '%' . $domain . '%';
            }
            
            $where_clause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';
            $stmt = $db->prepare("SELECT * FROM http $where_clause ORDER BY last_update DESC");
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $data = parse_jsonb_fields($data);
            
            if ($is_json) {
                send_json(['http' => $data]);
            } else {
                $domains = extract_domains($data, 'subdomain');
                send_text(implode("\n", $domains));
            }
            break;

        case 'urls':
            if (!$program_name) {
                send_json(['error' => 'program_name parameter is required'], 400);
                break;
            }

            $conditions = ["program_name = :program_name"];
            $params = [':program_name' => $program_name];

            if ($domain) {
                $conditions[] = "url LIKE :url_like";
                $params[':url_like'] = '%' . $domain . '%';
            }

            if ($source) {
                $conditions[] = "source = :source";
                $params[':source'] = $source;
            }

            $where_clause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';
            $stmt = $db->prepare("SELECT * FROM urls $where_clause ORDER BY last_seen DESC");
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $data = parse_jsonb_fields($data);

            if ($is_json) {
                send_json([
                    'program_name' => $program_name,
                    'count' => count($data),
                    'urls' => $data,
                ]);
            } else {
                $urls = array_map(function ($row) {
                    return $row['url'] ?? null;
                }, $data);
                $urls = array_filter($urls);
                send_text(implode("\n", $urls));
            }
            break;
        
        case 'ports':
            $conditions = [];
            $params = [];

            if ($program_name) {
                $conditions[] = "program_name = :program_name";
                $params[':program_name'] = $program_name;
            }

            if ($domain) {
                $conditions[] = "subdomain LIKE :domain";
                $params[':domain'] = '%' . $domain . '%';
            }

            $where_clause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';
            $stmt = $db->prepare("SELECT * FROM ports $where_clause ORDER BY last_update DESC, port ASC");
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $data = parse_jsonb_fields($data);

            if ($is_json) {
                send_json(['ports' => $data]);
            } else {
                $entries = array_map(function ($row) {
                    return sprintf(
                        '%s:%s (%s/%s)',
                        $row['subdomain'] ?? '',
                        $row['port'] ?? '',
                        $row['protocol'] ?? '',
                        $row['service'] ?? ''
                    );
                }, $data);

                send_text(implode("\n", array_filter($entries)));
            }
            break;

        case 'port-scanner':
            $conditions = [];
            $params = [];

            if ($domain) {
                $conditions[] = "subdomain = :subdomain";
                $params[':subdomain'] = $domain;
            }

            if ($program_name) {
                $conditions[] = "program_name = :program_name";
                $params[':program_name'] = $program_name;
            }

            if (!$domain && !$program_name) {
                send_json(['error' => 'Provide either a domain or a program_name parameter'], 400);
                break;
            }

            $where_clause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';
            $stmt = $db->prepare("SELECT * FROM ports $where_clause ORDER BY subdomain ASC, port ASC");
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $data = parse_jsonb_fields($data);

            if ($domain) {
                $port_entries = array_map(function ($row) {
                    return [
                        'port' => isset($row['port']) ? (int) $row['port'] : null,
                        'protocol' => $row['protocol'] ?? null,
                        'service' => $row['service'] ?? null,
                        'program_name' => $row['program_name'] ?? null,
                        'last_update' => $row['last_update'] ?? null,
                    ];
                }, $data);
                $port_entries = array_values(array_filter($port_entries, function ($entry) {
                    return $entry['port'] !== null;
                }));

                if (empty($port_entries)) {
                    send_json(['error' => 'No open ports found for the requested domain', 'code' => 404], 404);
                    break;
                }

                $result = [
                    'subdomain' => $domain,
                    'ports' => $port_entries,
                ];

                if ($is_json) {
                    send_json($result);
                } else {
                    $lines = array_map(function ($entry) {
                        return sprintf(
                            '%s/%s (%s)',
                            $entry['port'],
                            $entry['protocol'] ?? 'unknown',
                            $entry['service'] ?? 'unknown'
                        );
                    }, $port_entries);
                    send_text(implode("\n", $lines));
                }
            } else {
                $grouped = [];
                foreach ($data as $row) {
                    $subdomain = $row['subdomain'] ?? null;
                    $port = isset($row['port']) ? (int) $row['port'] : null;

                    if (!$subdomain || $port === null) {
                        continue;
                    }

                    if (!isset($grouped[$subdomain])) {
                        $grouped[$subdomain] = [
                            'subdomain' => $subdomain,
                            'ports' => [],
                        ];
                    }

                    $grouped[$subdomain]['ports'][] = [
                        'port' => $port,
                        'protocol' => $row['protocol'] ?? null,
                        'service' => $row['service'] ?? null,
                        'last_update' => $row['last_update'] ?? null,
                    ];
                }

                $result = [
                    'program_name' => $program_name,
                    'domains' => array_values($grouped),
                ];

                if ($is_json) {
                    send_json($result);
                } else {
                    $lines = [];
                    foreach ($result['domains'] as $domain_entry) {
                        $ports_list = implode(', ', array_map(function ($port_entry) {
                            return (string) $port_entry['port'];
                        }, $domain_entry['ports']));
                        $lines[] = sprintf('%s: %s', $domain_entry['subdomain'], $ports_list);
                    }
                    send_text(implode("\n", $lines));
                }
            }
            break;
            
        case 'live-subdomains':
        case 'lives':
            $conditions = [];
            $params = [];
            
            if ($program_name) {
                $conditions[] = "program_name = :program_name";
                $params[':program_name'] = $program_name;
            }
            
            if ($domain) {
                $conditions[] = "subdomain LIKE :domain";
                $params[':domain'] = '%' . $domain . '%';
            }
            
            $where_clause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';
            $stmt = $db->prepare("SELECT * FROM live_subdomains $where_clause ORDER BY last_update DESC");
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $data = parse_jsonb_fields($data);
            
            if ($is_json) {
                send_json(['live_subdomains' => $data]);
            } else {
                $domains = extract_domains($data, 'subdomain');
                send_text(implode("\n", $domains));
            }
            break;
            
        case 'all':
            // Get all programs and subdomains together
            $programs_stmt = $db->prepare("SELECT * FROM programs ORDER BY created_date DESC");
            $programs_stmt->execute();
            $programs_data = $programs_stmt->fetchAll(PDO::FETCH_ASSOC);
            $programs_data = parse_jsonb_fields($programs_data);
            
            $subdomains_stmt = $db->prepare("SELECT * FROM subdomains ORDER BY last_update DESC");
            $subdomains_stmt->execute();
            $subdomains_data = $subdomains_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if ($is_json) {
                send_json([
                    'programs' => $programs_data,
                    'subdomains' => $subdomains_data
                ]);
            } else {
                $output = [];
                $output[] = "=== PROGRAMS ===";
                foreach ($programs_data as $program) {
                    $output[] = $program['program_name'] ?? '';
                }
                $output[] = "";
                $output[] = "=== SUBDOMAINS ===";
                foreach ($subdomains_data as $subdomain) {
                    $output[] = $subdomain['subdomain'] ?? '';
                }
                send_text(implode("\n", $output));
            }
            break;
            
        case '':
        default:
            // API information endpoint
            if ($is_json) {
                send_json([
                    'name' => 'Watch Tower API',
                    'version' => '1.0.0',
                    'description' => 'REST API for reading data from Watch Tower database',
                    'documentation' => '/swagger-ui.html',
                    'endpoints' => [
                        'GET /api.php/programs' => 'Get all programs',
                        'GET /api.php/programs/{name}' => 'Get program by name',
                        'GET /api.php/all' => 'Get all programs and subdomains together',
                        'GET /api.php/subdomains?program_name=xxx&domain=xxx' => 'Get subdomains (optional filters)',
                        'GET /api.php/http?program_name=xxx&domain=xxx' => 'Get HTTP services (optional filters)',
                        'GET /api.php/urls?program_name=xxx&domain=xxx&source=xxx' => 'Get archived URLs (program_name required)',
                        'DELETE /api.php/programs/{name}' => 'Delete program and related data',
                        'GET /api.php/live-subdomains?program_name=xxx&domain=xxx' => 'Get live subdomains (optional filters)',
                        'GET /api.php/ports?program_name=xxx&domain=xxx' => 'Get discovered open ports (optional filters)',
                    ],
                    'parameters' => [
                        'json' => 'Response format: "true" (default) or "false" for plain text',
                        'program_name' => 'Filter results by program name (optional)',
                        'domain' => 'Filter results by domain (optional, uses LIKE search)'
                    ]
                ]);
            } else {
                send_text("Watch Tower API v1.0.0\n\nAvailable endpoints:\n- GET /api.php/programs\n- GET /api.php/programs/{name}\n- GET /api.php/all\n- GET /api.php/subdomains\n- GET /api.php/http\n- GET /api.php/live-subdomains\n- GET /api.php/ports\n\nUse ?json=true (default) or ?json=false\nUse ?program_name=xxx to filter by program\nUse ?domain=xxx to filter by domain");
            }
            break;
    }
    
} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage());
    send_json(['error' => 'Internal server error: ' . $e->getMessage(), 'code' => 500], 500);
}
