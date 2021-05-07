<?php

declare(strict_types=1);

namespace Sylius\InvoicingPlugin\Entity;

use Sylius\Component\Resource\Model\ResourceInterface;

/** @final */
class PromotionItem implements PromotionItemInterface, ResourceInterface
{
    /** @var string */
    protected $id;

    /** @var InvoiceInterface */
    protected $invoice;

    /** @var string */
    protected $type;

    /** @var string */
    protected $reference;

    /** @var string */
    protected $name;

    /** @var int */
    protected $total;

    /** @var int */
    protected $rate;

    public function __construct(
        string $name,
        string $type,
        string $reference,
        int $total,
        ?int $rate = 0
    ) {
        $this->rate = $rate;
        $this->reference = $reference;
        $this->type = $type;
        $this->name = $name;
        $this->total = $total;
    }

    public function getId(): string
    {
        return $this->id();
    }

    public function invoice(): InvoiceInterface
    {
        return $this->invoice;
    }

    public function setInvoice(InvoiceInterface $invoice): void
    {
        $this->invoice = $invoice;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function rate(): int
    {
        return $this->rate;
    }

    public function reference(): string
    {
        return $this->reference;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function total(): int
    {
        return $this->total;
    }

    public function setTotal($total): int
    {
        return $this->total = $total;
    }
}
