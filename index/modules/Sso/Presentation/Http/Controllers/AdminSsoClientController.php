<?php

namespace Modules\Sso\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Shared\Application\CommandBus;
use Modules\Shared\Application\QueryBus;
use Modules\Sso\Application\Command\CreateSsoClient;
use Modules\Sso\Application\Command\DeleteSsoClient;
use Modules\Sso\Application\Command\UpdateSsoClient;
use Modules\Sso\Application\Query\GetSsoClient;
use Modules\Sso\Application\Query\ListSsoClients;
use Modules\Sso\Domain\Exceptions\DuplicateSsoClientIdException;
use Modules\Sso\Domain\Exceptions\SsoClientNotFoundException;
use Modules\Sso\Domain\SsoClientDefinition;

final class AdminSsoClientController extends Controller
{
    public function __construct(
        private readonly QueryBus $queries,
        private readonly CommandBus $commands,
    ) {}

    public function index(): Response
    {
        /** @var list<SsoClientDefinition> $clients */
        $clients = $this->queries->ask(new ListSsoClients);

        return Inertia::render('private/sso-clients/ListPage', [
            'clients' => array_map(
                static fn (SsoClientDefinition $client): array => $client->toArray(),
                $clients,
            ),
            'sharedSecret' => (string) config('sso.secret', ''),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('private/sso-clients/FormPage', [
            'client' => null,
            'sharedSecret' => (string) config('sso.secret', ''),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        try {
            $this->commands->dispatch(new CreateSsoClient(
                $data['client_id'],
                $data['name'],
                $data['domain'],
                $data['redirect_uris'],
                $data['redirect_uri_patterns'],
                $data['enabled'],
            ));
        } catch (DuplicateSsoClientIdException) {
            throw ValidationException::withMessages([
                'client_id' => 'client_id đã tồn tại.',
            ]);
        }

        return redirect()->route('admin.sso-clients.index');
    }

    public function edit(string $id): Response
    {
        /** @var SsoClientDefinition|null $client */
        $client = $this->queries->ask(new GetSsoClient($id));

        if ($client === null) {
            abort(404);
        }

        return Inertia::render('private/sso-clients/FormPage', [
            'client' => $client->toArray(),
            'sharedSecret' => (string) config('sso.secret', ''),
        ]);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $data = $this->validated($request);

        try {
            $this->commands->dispatch(new UpdateSsoClient(
                $id,
                $data['client_id'],
                $data['name'],
                $data['domain'],
                $data['redirect_uris'],
                $data['redirect_uri_patterns'],
                $data['enabled'],
            ));
        } catch (SsoClientNotFoundException) {
            abort(404);
        } catch (DuplicateSsoClientIdException) {
            throw ValidationException::withMessages([
                'client_id' => 'client_id đã tồn tại.',
            ]);
        }

        return redirect()->route('admin.sso-clients.index');
    }

    public function destroy(string $id): RedirectResponse
    {
        try {
            $this->commands->dispatch(new DeleteSsoClient($id));
        } catch (SsoClientNotFoundException) {
            abort(404);
        }

        return redirect()->route('admin.sso-clients.index');
    }

    /**
     * @return array{
     *     client_id: string,
     *     name: string,
     *     domain: string|null,
     *     redirect_uris: list<string>,
     *     redirect_uri_patterns: list<string>,
     *     enabled: bool
     * }
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'client_id' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9][a-z0-9\-]*$/'],
            'name' => ['required', 'string', 'max:255'],
            'domain' => ['nullable', 'string', 'max:2048'],
            'redirect_uris' => ['nullable', 'string', 'max:10000'],
            'redirect_uri_patterns' => ['nullable', 'string', 'max:10000'],
            'enabled' => ['boolean'],
        ]);

        $domain = isset($data['domain']) ? rtrim(trim((string) $data['domain']), '/') : null;
        if ($domain === '') {
            $domain = null;
        }

        return [
            'client_id' => strtolower(trim((string) $data['client_id'])),
            'name' => trim((string) $data['name']),
            'domain' => $domain,
            'redirect_uris' => $this->linesToList((string) ($data['redirect_uris'] ?? '')),
            'redirect_uri_patterns' => $this->linesToList((string) ($data['redirect_uri_patterns'] ?? '')),
            'enabled' => (bool) ($data['enabled'] ?? true),
        ];
    }

    /**
     * @return list<string>
     */
    private function linesToList(string $raw): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $raw) ?: [];

        return array_values(array_filter(
            array_map(static fn (string $line): string => trim($line), $lines),
            static fn (string $line): bool => $line !== '',
        ));
    }
}
