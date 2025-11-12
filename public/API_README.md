# Watch Tower API Documentation

## Overview

The Watch Tower API provides RESTful read-only access to program, subdomain, HTTP service, and live subdomain data stored in the database. The API is fully documented with Swagger/OpenAPI 3.0.

## Quick Start

### Access Swagger UI Documentation

1. **Interactive Swagger UI**: Open `http://localhost/swagger-ui.html` in your browser
2. **API Root**: Visit `http://localhost/api.php` for API information
3. **OpenAPI Spec**: Access `http://localhost/swagger.yaml` for the OpenAPI specification

## API Endpoints

### Programs
- `GET /api.php/programs?format=json` - Get all programs (JSON)
- `GET /api.php/programs?format=text` - Get all programs (plain text list)
- `GET /api.php/programs/{programName}?format=json` - Get specific program by name

### Subdomains
- `GET /api.php/subdomains?format=json` - Get all subdomains (JSON)
- `GET /api.php/subdomains?format=text` - Get all subdomains (plain text list)
- `GET /api.php/subdomains?program_name=discourse&format=json` - Filter by program

### HTTP Services
- `GET /api.php/http?format=json` - Get all HTTP services (JSON)
- `GET /api.php/http?format=text` - Get HTTP subdomains (plain text list)
- `GET /api.php/http?program_name=discourse&format=json` - Filter by program

### Live Subdomains
- `GET /api.php/live-subdomains?format=json` - Get all live subdomains (JSON)
- `GET /api.php/live-subdomains?format=text` - Get live subdomains (plain text list)
- `GET /api.php/live-subdomains?program_name=discourse&format=json` - Filter by program
- `GET /api.php/lives?format=json` - Alias for live-subdomains

## Parameters

- **format**: `json` (default) for JSON format, `text` for plain text list
- **program_name**: (optional) Filter results by program name

## Response Formats

### JSON Format (`format=json` or default)
Returns structured JSON data with all fields:
```json
{
  "programs": [
    {
      "id": 1,
      "program_name": "discourse",
      "created_date": "2024-01-01T00:00:00Z",
      "config": {...},
      "scopes": ["*.discourse.org"],
      "ooscopes": ["test.com"]
    }
  ]
}
```

### Plain Text Format (`format=text`)
Returns a simple list of domains/subdomains, one per line:
```
discourse.org
test.discourse.org
api.discourse.org
```

## Examples

### Get all programs as JSON
```bash
curl "http://localhost/api.php/programs?format=json"
```

### Get subdomains for a specific program (plain text)
```bash
curl "http://localhost/api.php/subdomains?program_name=discourse&format=text"
```

### Get HTTP services as JSON
```bash
curl "http://localhost/api.php/http?format=json"
```

### Get specific program by name
```bash
curl "http://localhost/api.php/programs/discourse?format=json"
```

## Swagger Documentation

The API includes comprehensive Swagger/OpenAPI 3.0 documentation:

1. **Interactive UI**: Visit `/swagger-ui.html` for a beautiful, interactive API documentation interface
2. **OpenAPI Spec**: Access `/swagger.yaml` for the machine-readable specification
3. **Try It Out**: Use the Swagger UI to test endpoints directly from your browser

## Important Notes

- **Read-Only API**: This API only supports GET requests. No data modification is allowed.
- **CORS Enabled**: All endpoints support CORS for cross-origin requests
- **Error Handling**: Error responses include appropriate HTTP status codes (404, 500, etc.)
- **JSONB Parsing**: JSONB fields (scopes, config, ips, tech, headers, cdn) are automatically parsed in JSON responses
- **Plain Text Optimization**: Plain text responses are optimized for easy parsing and scripting

## Files

- `api.php` - Main REST API endpoint file (read-only)
- `swagger.yaml` - OpenAPI 3.0 specification
- `swagger-ui.html` - Swagger UI interface
- `API_README.md` - This file
