<?php

namespace App\Console\Commands;

use App\Models\OAuthClient;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreateOAuthClientCommand extends Command
{
    protected $signature = 'oauth:create-client
        {name : Client name (e.g. "Le Chat", "Claude Desktop")}
        {--redirect=* : Allowed redirect URIs}
        {--confidential : Generate a client_secret for confidential clients}
        {--scope= : Allowed scopes (space-separated)}';

    protected $description = 'Create a pre-registered OAuth client with optional client_secret';

    public function handle(): int
    {
        $clientId = Str::uuid()->toString();
        $rawSecret = null;
        $authMethod = 'none';

        if ($this->option('confidential')) {
            $rawSecret = Str::random(48);
            $authMethod = 'client_secret_post';
        }

        $redirectUris = $this->option('redirect');
        if (empty($redirectUris)) {
            $this->error('At least one --redirect URI is required.');

            return 1;
        }

        $client = OAuthClient::create([
            'client_id' => $clientId,
            'client_secret' => $rawSecret ? hash('sha256', $rawSecret) : null,
            'client_name' => $this->argument('name'),
            'redirect_uris' => $redirectUris,
            'grant_types' => ['authorization_code'],
            'response_types' => ['code'],
            'token_endpoint_auth_method' => $authMethod,
            'scope' => $this->option('scope'),
        ]);

        $this->info('OAuth client created:');
        $this->line("  client_id:     {$client->client_id}");
        if ($rawSecret) {
            $this->line("  client_secret: {$rawSecret}");
            $this->warn('  ↑ Save this now — it cannot be retrieved again.');
        }
        $this->line("  name:          {$client->client_name}");
        $this->line('  redirect_uris: '.implode(', ', $client->redirect_uris));
        $this->line("  auth_method:   {$client->token_endpoint_auth_method}");

        return 0;
    }
}
