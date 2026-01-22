<?php

declare(strict_types=1);

namespace App\Domain\Entities;

final readonly class PagePermission
{
    public function __construct(
        public int $id,
        public string $pageKey,
        public string $pageName,
        public ?string $description = null,
    ) {
    }
}
