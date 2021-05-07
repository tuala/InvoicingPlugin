<?php

declare(strict_types=1);

namespace Sylius\InvoicingPlugin\Entity;

interface PromotionItemInterface
{
    public function id(): string;

    public function name(): ?string;

    public function type(): ?string;

    public function reference(): ?string;

    public function rate(): int;

    public function total(): int;
}
