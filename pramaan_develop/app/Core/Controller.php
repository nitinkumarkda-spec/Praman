<?php
declare(strict_types=1);

namespace App\Core;

use App\Services\StateService;

/**
 * Base controller. UI actions answer with JSON:
 *   { ok, message, field, flashes:[{type,message}], go, state }
 * "state" is the fresh data the signed-in user may see, so the screen
 * re-renders from the database right after every change.
 */
abstract class Controller
{
    private array $flashes = [];

    public function __construct(protected Request $request)
    {
    }

    protected function flash(string $type, string $message): void
    {
        $this->flashes[] = ['type' => $type, 'message' => $message];
    }

    /** Success: queue $message (if any) and send the UI to $go (null = re-render the current page). */
    protected function done(?string $message = null, ?string $go = null, string $type = 'success'): never
    {
        if ($message !== null && $message !== '') {
            $this->flash($type, $message);
        }
        Response::json([
            'ok'      => true,
            'flashes' => $this->flashes,
            'go'      => $go,
            'state'   => StateService::build(),
        ]);
    }

    /** Validation / business-rule failure: the form stays filled and the field is marked. */
    protected function fail(string $message, ?string $field = null, int $status = 422): never
    {
        Response::json(['ok' => false, 'message' => $message, 'field' => $field], $status);
    }

    /** Validate the request with Validator rules; fails on the first error. */
    protected function validate(array $rules, ?array $data = null): array
    {
        $data ??= $this->request->all();
        $v = new Validator($data, $rules);
        if (!$v->passes()) {
            [$field, $message] = $v->firstError();
            $this->fail($message, $field);
        }
        return $v->clean();
    }

    protected function id(string $key = 'id'): int
    {
        $id = filter_var($this->request->input($key), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $id === false ? 0 : (int) $id;
    }
}
