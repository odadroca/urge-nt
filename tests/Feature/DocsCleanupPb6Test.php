<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Phase B Sprint 6 guards — governance files exist, dead code removed,
 * documented counts/domains reconciled (DOC-01..09, DEAD-01/02).
 * These are repository-invariant checks (no DB needed).
 */
class DocsCleanupPb6Test extends TestCase
{
    public function test_governance_files_exist(): void
    {
        foreach (['LICENSE', 'SECURITY.md', 'CONTRIBUTING.md', 'CHANGELOG.md'] as $file) {
            $this->assertFileExists(base_path($file), "{$file} should exist");
        }
    }

    public function test_license_is_mit_matching_readme(): void
    {
        $this->assertStringContainsString('MIT License', file_get_contents(base_path('LICENSE')));
        $this->assertStringContainsString('[MIT](LICENSE)', file_get_contents(base_path('README.md')));
    }

    public function test_dead_aiassistant_service_removed(): void
    {
        $this->assertFileDoesNotExist(base_path('app/Services/AiAssistantService.php'));
    }

    public function test_unused_api_auth_alias_removed(): void
    {
        $bootstrap = file_get_contents(base_path('bootstrap/app.php'));
        $this->assertStringNotContainsString("'api.auth'", $bootstrap);
    }

    public function test_readme_tool_and_test_counts_reconciled(): void
    {
        $readme = file_get_contents(base_path('README.md'));
        $this->assertStringNotContainsString('29 tools', $readme);
        $this->assertStringNotContainsString('386 passing', $readme);
        $this->assertStringContainsString('31 tools', $readme);
    }

    public function test_no_hardcoded_maintainer_domain_in_docs(): void
    {
        foreach (['documentation/claude-skill.md', 'resources/openapi.json'] as $file) {
            $this->assertStringNotContainsString(
                'acordado.org',
                file_get_contents(base_path($file)),
                "{$file} should not hardcode the maintainer domain"
            );
        }
    }
}
