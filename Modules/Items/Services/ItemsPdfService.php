<?php

namespace Modules\Items\Services;

use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Modules\Core\Services\PdfService;
use Modules\Items\Entities\Product;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

/**
 * The PDFs of the Items module, on mpdf/mpdf. DejaVu Sans ships with it and shapes Arabic.
 */
class ItemsPdfService extends PdfService
{
    /** 8.5 x 3 inches, one label a page. */
    private const LABEL_SIZE = [216, 76];

    /**
     * Barcode labels of a product and its variations.
     *
     * @param  array<int|string, int|string>  $quantities  product or variation id => labels; a row left out prints one
     */
    public function generateBarcodeLabels(Product $product, array $quantities = []): Response
    {
        $html = view('items::pdf.barcode-labels', ['labels' => $this->labels($product, $quantities)])->render();

        return response($this->render($html), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="barcode-labels-'.Str::slug((string) $product->slug).'.pdf"',
        ]);
    }

    /**
     * What a label encodes: the barcode, or the sku when it has none or nothing printable.
     */
    public function labelCode(Product $product): string
    {
        $clean = fn (?string $code) => preg_replace('/[^A-Za-z0-9\-]/', '', (string) $code);

        return $clean($product->barcode) ?: $clean($product->sku);
    }

    /**
     * @param  array<int|string, int|string>  $quantities
     * @return list<array{name: string, code: string}>
     */
    private function labels(Product $product, array $quantities): array
    {
        $labels = [];

        foreach ([$product, ...$product->variations] as $row) {
            $count = (int) ($quantities[$row->id] ?? 1);

            for ($i = 0; $i < $count; $i++) {
                $labels[] = ['name' => (string) $row->name, 'code' => $this->labelCode($row)];
            }
        }

        return $labels;
    }

    private function render(string $html): string
    {
        $pdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => self::LABEL_SIZE,
            'orientation' => 'P', // the size above is already the wide one
            'default_font' => 'dejavusans',
            'margin_left' => 2,
            'margin_right' => 2,
            'margin_top' => 4,
            'margin_bottom' => 2,
            'tempDir' => sys_get_temp_dir().DIRECTORY_SEPARATOR.'mpdf', // font metrics only, no store data
        ]);

        $pdf->WriteHTML($html);

        return $pdf->Output('', Destination::STRING_RETURN);
    }
}
