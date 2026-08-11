<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;

class ApiDocumentationController extends Controller
{
    public function __invoke(): Response
    {
        return response(File::get(base_path('../docs/openapi.yaml')), 200, [
            'Content-Type' => 'application/yaml; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="waflow-openapi.yaml"',
        ]);
    }
}
