<?php

namespace Tests\Unit;

use Tests\TestCase;

final class DockerfileModulesCopyTest extends TestCase
{
    public function test_it_should_copy_modules_directory_into_runtime_image(): void
    {
        $dockerfile = file_get_contents(base_path('Dockerfile'));

        $this->assertIsString($dockerfile);
        $this->assertStringContainsString(
            'COPY --from=vendor /app/modules ./modules',
            $dockerfile,
            'Runtime image must include modules/ for Modules\\* service providers.',
        );
    }
}
