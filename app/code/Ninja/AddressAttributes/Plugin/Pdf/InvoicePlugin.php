<?php
declare(strict_types=1);

namespace Ninja\AddressAttributes\Plugin\Pdf;

use Magento\Sales\Model\Order\Pdf\Invoice;
use Ninja\AddressAttributes\Model\DynamicFieldStorage;

class InvoicePlugin
{
    public function __construct(
        private DynamicFieldStorage $dynamicFieldStorage
    ) {}

    /**
     * Around plugin to draw attributes after grand total dynamically
     */
    public function aroundGetPdf(
        Invoice $subject,
        callable $proceed,
        array $invoices = []
    ): \Zend_Pdf {
        $pdf = $proceed($invoices);

        foreach ($invoices as $invoice) {
            try {
                $this->appendAttributesToInvoice($pdf, $invoice, $subject);
            } catch (\Throwable $e) {
                // never break PDF generation
            }
        }

        return $pdf;
    }

    private function appendAttributesToInvoice(
        \Zend_Pdf $pdf,
        $invoice,
        Invoice $subject
    ): void {
        $order           = $invoice->getOrder();
        $shippingAddress = $order?->getShippingAddress();
        if (!$shippingAddress) {
            return;
        }

        $addressId = (int)$shippingAddress->getId();
        $map       = $this->dynamicFieldStorage->getAttributeMap(['show_in_invoice_pdf' => 1]);
        $values    = $this->dynamicFieldStorage->getOrderValuesByCode($addressId);

        if (!$map) {
            return;
        }

        $lines = [];
        foreach ($map as $code => $meta) {
            $rawValue = $values[$code] ?? null;
            if ($rawValue === null || $rawValue === '') {
                continue;
            }

            $type    = (string)($meta['type'] ?? 'text');
            $display = $type === 'yesno'
                ? ($rawValue === '1' ? 'Yes' : 'No')
                : $this->dynamicFieldStorage->resolveDisplayValue($code, $rawValue);

            $lines[] = ['label' => (string)$meta['label'], 'value' => $display];
        }

        if (!$lines) {
            return;
        }

        $lineHeight   = 14;
        $headerHeight = 20;
        $blockHeight  = $headerHeight + (count($lines) * $lineHeight) + 10;
        $paddingBottom = 30;

        $lastPage = end($pdf->pages);
        if (!$lastPage) {
            return;
        }

        $pageHeight = $lastPage->getHeight();
        $pageWidth  = $lastPage->getWidth();
        $currentY   = $this->detectCurrentY($subject, $pageHeight);

        if (($currentY - $blockHeight) < $paddingBottom) {
            $newPage      = $pdf->newPage(\Zend_Pdf_Page::SIZE_A4);
            $pdf->pages[] = $newPage;
            $lastPage     = $newPage;
            $currentY     = $pageHeight - 40;
        }

        $this->drawBlock($lastPage, $lines, $currentY, $pageWidth);
    }

    private function detectCurrentY(Invoice $subject, float $pageHeight): float
    {
        try {
            $reflection = new \ReflectionClass($subject);
            if ($reflection->hasProperty('y')) {
                $prop = $reflection->getProperty('y');
                $prop->setAccessible(true);
                $y = (float)$prop->getValue($subject);
                if ($y > 0) {
                    return $y;
                }
            }
        } catch (\Throwable $e) {
            // fall through
        }

        return 160;
    }

    private function drawBlock(
        \Zend_Pdf_Page $page,
        array $lines,
        float $startY,
        float $pageWidth
    ): void {
        $font             = \Zend_Pdf_Font::fontWithName(\Zend_Pdf_Font::FONT_HELVETICA);
        $fontBold         = \Zend_Pdf_Font::fontWithName(\Zend_Pdf_Font::FONT_HELVETICA_BOLD);
        $fontSize         = 9;
        $lineHeight       = 14;
        $leftMargin       = 35;
        $rightMargin      = $pageWidth - 35;
        $y                = $startY - 10;
        $labelColumnWidth = 150;
        $valueX           = $leftMargin + $labelColumnWidth;

        $page->setLineColor(new \Zend_Pdf_Color_GrayScale(0.5));
        $page->setLineWidth(0.5);
        $page->drawLine($leftMargin, $y + 5, $rightMargin, $y + 5);

        $page->setFont($fontBold, $fontSize + 1);
        $page->setFillColor(new \Zend_Pdf_Color_GrayScale(0));
        $page->drawText('Additional Information', $leftMargin, $y, 'UTF-8');
        $y -= $lineHeight + 4;

        foreach ($lines as $line) {
            $page->setFont($fontBold, $fontSize);
            $page->drawText($line['label'] . ':', $leftMargin, $y, 'UTF-8');
            $page->setFont($font, $fontSize);
            $page->drawText($line['value'], $valueX, $y, 'UTF-8');
            $y -= $lineHeight;
        }
    }
}
