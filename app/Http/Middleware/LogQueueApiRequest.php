<?php

namespace App\Http\Middleware;

use App\Actions\Audit\RecordOrganizationAudit;
use App\Enums\AuditAction;
use App\Models\ApiToken;
use App\Models\Team;
use App\Models\User;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class LogQueueApiRequest
{
    public function __construct(
        protected RecordOrganizationAudit $recordOrganizationAudit,
    ) {
        //
    }

    /**
     * Log authenticated queue API access after downstream authorization and validation.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            $status = match (true) {
                $exception instanceof ValidationException => 422,
                $exception instanceof AuthorizationException => 403,
                $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
                default => 500,
            };

            $this->record($request, $status);

            throw $exception;
        }

        $this->record($request, $response->getStatusCode());

        return $response;
    }

    protected function record(Request $request, int $status): void
    {
        $token = $request->attributes->get('apiToken');
        $user = $request->user();
        $team = $user?->currentTeam;

        if ($token instanceof ApiToken && $user instanceof User && $team instanceof Team) {
            $this->recordOrganizationAudit->handle(
                $team,
                AuditAction::QueueApiRequested,
                $user,
                'api_token',
                $token->id,
                null,
                [
                    'method' => $request->method(),
                    'path' => '/'.$request->path(),
                    'status' => $status,
                    'token_name' => $token->name,
                ],
            );
        }
    }
}
