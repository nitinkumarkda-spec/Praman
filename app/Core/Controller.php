<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Base controller with rendering, validation and redirect helpers.
 */
abstract class Controller
{
    protected Request $request;

    public function __construct()
    {
        $this->request = Request::current();
    }

    protected function view(string $view, array $data = [], ?string $layout = 'layouts/main'): void
    {
        echo View::render($view, $data, $layout);
    }

    protected function json(array $data, int $status = 200): never
    {
        Response::json($data, $status);
    }

    protected function redirect(string $path): never
    {
        Response::redirect($path);
    }

    protected function back(string $fallback = '/'): never
    {
        Response::back($fallback);
    }

    protected function flash(string $type, string $message): void
    {
        Session::flash($type, $message);
    }

    /**
     * Validate POST data. On failure: flash errors + old input and redirect back
     * (or JSON 422 for AJAX). Returns the validated subset on success.
     */
    protected function validate(array $rules, array $labels = [], ?string $redirectTo = null): array
    {
        $data = $this->request->all();
        $v = Validator::make($data, $rules, $labels);
        if ($v->fails()) {
            $this->failValidation($v->errors(), $redirectTo);
        }
        $out = [];
        foreach (array_keys($rules) as $field) {
            $out[$field] = $data[$field] ?? null;
        }
        return $out;
    }

    /** @param array<string, string> $errors */
    protected function failValidation(array $errors, ?string $redirectTo = null, string $message = 'Please correct the highlighted errors.'): never
    {
        if ($this->request->wantsJson()) {
            $this->json(['ok' => false, 'message' => $message, 'errors' => $errors], 422);
        }
        Session::flash('errors', $errors);
        // Never keep secrets in the session for re-filling forms.
        $old = array_diff_key($this->request->all(), array_flip(['_csrf', 'password', 'current_password', 'new_password', 'confirm_password']));
        Session::flash('old', $old);
        Session::flash('error', $message);
        if ($redirectTo !== null) {
            $this->redirect($redirectTo);
        }
        $this->back();
    }

    /** Positive integer route parameter or 404. */
    protected function idParam(string $name = 'id'): int
    {
        $v = $this->request->param($name);
        if ($v === null || !ctype_digit($v) || (int) $v < 1) {
            throw new HttpException(404, 'Record not found.');
        }
        return (int) $v;
    }
}
