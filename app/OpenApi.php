<?php

namespace App;

use OpenApi\Attributes as OA;

#[OA\OpenApi(tags: [
    new OA\Tag(name: 'Leave Management', description: 'Employee leave application and request APIs.'),
    new OA\Tag(name: 'HR Leave', description: 'HR leave application, listing, and decision APIs.'),
])]
#[OA\Info(
    version: '1.0.0',
    title: 'HRMS CRM API Documentation',
    description: 'API documentation for the HRMS CRM application'
)]
#[OA\Server(
    url: 'http://hrmscrm.test',
    description: 'Local Development Server'
)]
#[OA\Server(
    url: 'https://ymhrms.girafdev.com',
    description: 'Demo Production Server'
)]
#[OA\SecurityScheme(
    securityScheme: 'sanctum',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT',
    description: 'Enter your Bearer token in the format: Bearer {token}'
)]
class OpenApi {}
