<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Response;
use App\Core\View;
use App\Services\StateService;

/**
 * The application page. Screens are drawn in the browser from the state
 * embedded here (as JSON data, never as inline script) and refreshed from /api/state.
 */
final class HomeController extends Controller
{
    public function index(): void
    {
        Response::html(View::render('layouts/app', ['state' => StateService::build()]));
    }

    /** GET /api/state — fresh data for the signed-in user (polled for new notifications). */
    public function state(): void
    {
        Response::json(['ok' => true, 'state' => StateService::build()]);
    }
}
