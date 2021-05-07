<?php

declare(strict_types=1);

namespace Sylius\InvoicingPlugin\Converter;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\InvoicingPlugin\Entity\PromotionItem;
use Sylius\Component\Core\Model\AdjustmentInterface;
use Sylius\Component\Order\Model\AdjustmentInterface as OrderAdjustmentInterface;
use Sylius\Component\Core\OrderShippingStates;

final class PromotionItemsConverter implements PromotionItemsConverterInterface
{

    private function getTaxableTaxRate(TaxableInterface $taxable, OrderInterface $resource){
        $ratio = 0;
        foreach ($taxable->getTaxCategory()->getRates() as $rate){
            foreach ($rate->getZone()->getMembers() as $member){
                if($member->getCode() == $resource->getShippingAddress()->getProvinceCode()){
                    $ratio +=  $rate->getAmount();
                }
            }
        }
        return $ratio;
    }
    private function getOriginalPricesDiscount(OrderInterface $order): array
    {
        $discounts = [];
        foreach ($order->getItems() as $item){
            $pricing = $item->getVariant()->getChannelPricingForChannel($order->getChannel());
            $tax = $item->getTaxTotal();
            if($pricing->getOriginalPrice() && $pricing->getOriginalPrice() > $pricing->getPrice()){
                $title = PromotionItemInterface::TITLE_ORIGIN;
                if($item->getVariant()->getProduct()->isSubscription()){
                    $title = PromotionItemInterface::TITLE_SUBSCRIPTION;
                  if($item->getVariant()->getProduct()->isTrial()){
                      $title = PromotionItemInterface::TITLE_TRIAL;
                  }
                } elseif ($item->getVariant()->getUnitVariants()){
                    $title = PromotionItemInterface::TITLE_PACK;
                }
                $discount = ($pricing->getOriginalPrice() * $item->getQuantity()) - ($item->getUnitPrice() * $item->getQuantity());
                // GET RATIO
                // STORE AMOUNTS IN RATIO ARRAY
                $ratio = round($tax / $item->getSubtotal(), 2);
                $discounts[] = [
                    'amount' => $discount,
                    'title' => $title,
                    'rate' => $ratio * 100
                ];
            }
        }
        return $discounts;
    }

    private function getRateFromTaxTotal(int $taxTotal, int $total){
      return $taxTotal / ($total - $taxTotal);
    }
    private function getAdjustmentTaxRate(OrderAdjustmentInterface $adjustment, OrderInterface $order): float
    {
        $ratio = 0;
        if($adjustment->getType() == AdjustmentInterface::SHIPPING_ADJUSTMENT) {
            foreach ($order->getShipments() as $shipment){
                if($shipment->getState() == OrderShippingStates::STATE_SHIPPED){
                    $ratio = $this->getTaxableTaxRate($shipment->getMethod(), $order);
                    break;
                }
            }
        } else {
            if($adjustment->getType() == AdjustmentInterface::ORDER_UNIT_PROMOTION_ADJUSTMENT){
                $item = $adjustment->getOrderItemUnit()->getOrderItem();
                $ratio = $this->getRateFromTaxTotal($item->getTaxTotal(), $item->getTotal());
            } else if ($adjustment->getType() == AdjustmentInterface::ORDER_ITEM_PROMOTION_ADJUSTMENT && $adjustment->getOrderItem() != null){
                $item = $adjustment->getOrderItem();
                $ratio = $this->getRateFromTaxTotal($item->getTaxTotal(), $item->getTotal());
            }
        }

        return $ratio * 100;
    }
    public function convert(OrderInterface $order): ArrayCollection
    {
        $done = [];
        $promotionItems = new ArrayCollection();
        // ADJUSTMENT DISCOUNT FROM ORDER
        $adjustments = $order->getAdjustments();
        foreach ($adjustments as $adjustment){
            if($adjustment->getAmount() != 0 && $adjustment->getType() != AdjustmentInterface::TAX_ADJUSTMENT){
                $taxRate = $this->getAdjustmentTaxRate($adjustment, $order);
                $promotionItems->add(new PromotionItem(
                    $adjustment->getLabel(),
                    $adjustment->getAmount(),
                    $taxRate
                ));
                $done[] = $adjustment->getLabel();
            }
        }
        // ORIGINAL PRICE DISCOUNT FROM ORDER
        $originalPriceDiscounts = $this->getOriginalPriceDiscounts($order);
        foreach ($originalPriceDiscounts as $originalDiscount){
            $promotionItems->add(new PromotionItem(
                "",
                'Remise permanente',
                $originalDiscount['title'],
                $originalDiscount['amount'],
                $originalDiscount['rate']
            ));
        }
        foreach ($order->getItems() as $item){
            foreach ($item->getUnits() as $unit){
                foreach ($unit->getAdjustments() as $adjustment){
                        if($adjustment->getAmount() != 0 && $adjustment->getType() != AdjustmentInterface::TAX_ADJUSTMENT){
                            if(in_array($adjustment->getLabel(), $done) !== false){
                                foreach ($promotionItems as $i => $promotionItem){
                                    if($promotionItem->name() == $adjustment->getLabel()){
                                        $promotionItem->setTotal($promotionItem->total() + $adjustment->getAmount());
                                    }
                                }
                            } else {
                                $promotionItems->add(new PromotionItem(
                                    $adjustment->getLabel(),
                                    'Remise',
                                    $adjustment->getAmount(),
                                    $this->getAdjustmentTaxRate($adjustment, $order)
                                ));
                                $done[] = $adjustment->getLabel();
                            }
                        }
                }
            }
            foreach ($item->getAdjustments() as $adjustment){
                if($adjustment->getAmount() != 0 && $adjustment->getType() != AdjustmentInterface::TAX_ADJUSTMENT){
                    if(in_array($adjustment->getLabel(), $done) !== false){
                        $amount = 0;
                        foreach ($promotionItems as $promotionItem){
                            if($promotionItem->name() == $adjustment->getLabel()){
                                $amount = $promotionItem->total();
                                $title = $promotionItem->title();
                                $rate = $promotionItem->taxRate();
                                $type = $promotionItem->type();
                                $promotionItems->removeElement($promotionItem);
                                $promotionItems->add(new PromotionItem(
                                    $adjustment->getLabel(),
                                    $title,
                                    $type,
                                    $adjustment->getAmount() + $amount,
                                    $rate
                                ));
                            }
                        }
                    } else {
                        $promotionItems->add(new PromotionItem(
                            $adjustment->getLabel(),
                            $adjustment->getAmount(),
                            $this->getAdjustmentTaxRate($adjustment, $order)
                        ));
                        $done[] = $adjustment->getLabel();
                    }
                }
            }
        }
        return $promotionItems;
    }
}
