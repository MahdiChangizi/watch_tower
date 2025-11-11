# Watch Tower API Documentation

## Overview

The Watch Tower API provides RESTful access to program, subdomain, HTTP service, and live subdomain data stored in the database.

## Quick Start

### Access Swagger UI Documentation

1. **Interactive Documentation**: Open `http://localhost:8000/api-docs.html` in your browser
2. **API Root**: Visit `http://localhost:8000/api.php` for a simple documentation page
3. **OpenAPI Spec**: Access `http://localhost:8000/api.php/openapi` or `http://localhost:8000/swagger.yaml` for the OpenAPI specification

## API Endpoints

### Programs
- `GET /api.php/programs?json=true` - Get all programs (JSON)
- `GET /api.php/programs?json=false` - Get all programs (plain text list)

### Subdomains
- `GET /api.php/subdomains?json=true` - Get all subdomains (JSON)
- `GET /api.php/subdomains?json=false` - Get all subdomains (plain text list)
- `GET /api.php/subdomains?program_name=discourse&json=true` - Filter by program

### HTTP Services
- `GET /api.php/http?json=true` - Get all HTTP services (JSON)
- `GET /api.php/http?json=false` - Get HTTP subdomains (plain text list)
- `GET /api.php/http?program_name=discourse&json=true` - Filter by program

### Live Subdomains
- `GET /api.php/live-subdomains?json=true` - Get all live subdomains (JSON)
- `GET /api.php/live-subdomains?json=false` - Get live subdomains (plain text list)
- `GET /api.php/live-subdomains?program_name=discourse&json=true` - Filter by program
- `GET /api.php/lives?json=true` - Alias for live-subdomains

## Parameters

- **json**: `true` for JSON format, `false` or omitted for plain text list
- **program_name**: (optional) Filter results by program name

## Response Formats

### JSON Format (`json=true`)
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

### Plain Text Format (`json=false` or omitted)
Returns a simple list of domains/subdomains, one per line:
```
discourse.org
test.discourse.org
api.discourse.org
```

## Examples

### Get all programs as JSON
```bash
curl "http://localhost:8000/api.php/programs?json=true"
```

### Get subdomains for a specific program (plain text)
```bash
curl "http://localhost:8000/api.php/subdomains?program_name=discourse&json=false"
```

### Get HTTP services as JSON
```bash
curl "http://localhost:8000/api.php/http?json=true"
```

## Swagger Documentation

The API includes comprehensive Swagger/OpenAPI documentation:

1. **Interactive UI**: Visit `/api-docs.html` for a beautiful, interactive API documentation interface
2. **OpenAPI Spec**: Access `/swagger.yaml` or `/api.php/openapi` for the machine-readable specification
3. **Try It Out**: Use the Swagger UI to test endpoints directly from your browser

## Files

- `api.php` - Main API endpoint file
- `swagger.yaml` - OpenAPI 3.0 specification
- `api-docs.html` - Swagger UI interface
- `API_README.md` - This file

## Notes

- All endpoints support CORS
- Error responses include appropriate HTTP status codes
- JSONB fields (scopes, config, ips, tech, headers, cdn) are automatically parsed in JSON responses
- Plain text responses are optimized for easy parsing and scripting

