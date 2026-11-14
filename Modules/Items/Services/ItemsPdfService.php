<?php

namespace Modules\Items\Services;

use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Modules\Core\Services\PdfService;
use Modules\Items\Entities\Order;
use Modules\Items\Entities\Product;
use Modules\Items\Support\OrderDocument;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

/**
 * The PDFs of the Items module, on mpdf/mpdf. DejaVu Sans ships with it and shapes Arabic.
 */
class ItemsPdfService extends PdfService
{
    /** 8.5 x 3 inches, one label a page. */
    private const LABEL_SIZE = [216, 76];

    /** A courier's label: 100 x 150 mm (4 x 6 inches), portrait. */
    private const WAYBILL_SIZE = [100, 150];

    /**
     * Barcode labels of a product and its variations.
     *
     * @param  array<int|string, int|string>  $quantities  product or variation id => labels; a row left out prints one
     */
    public function generateBarcodeLabels(Product $product, array $quantities = []): Response
    {
        $html = view('items::pdf.barcode-labels', ['labels' => $this->labels($product, $quantities)])->render();

        return response($this->render($html, self::LABEL_SIZE), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="barcode-labels-'.Str::slug((string) $product->slug).'.pdf"',
        ]);
    }

    /**
     * An order's invoice on A4, in $locale (Arabic and Hebrew right to left), opened in the
     * browser to print.
     */
    public function invoice(Order $order, string $locale): Response
    {
        return OrderDocument::inLocale($locale, function () use ($order, $locale): Response {
            $document = OrderDocument::for($order, $locale);
            $html = view('items::pdf.invoice', ['doc' => $document])->render();

            return $this->inline(
                $this->render($html, 'A4', __('Invoice').' '.$document['number'], $document['dir'], [10, 10, 10, 12]),
                'invoice-'.$document['number'].'.pdf',
            );
        });
    }

    /**
     * Shipping labels, 100 x 150 mm, one page an order, in $locale.
     *
     * @param  iterable<Order>  $orders
     */
    public function waybills(iterable $orders, string $locale): Response
    {
        return OrderDocument::inLocale($locale, function () use ($orders, $locale): Response {
            $documents = [];
            foreach ($orders as $order) {
                $documents[] = OrderDocument::for($order, $locale);
            }

            $numbers = array_column($documents, 'number');
            $html = view('items::pdf.waybill', ['docs' => $documents])->render();
            $name = count($numbers) === 1 ? 'waybill-'.$numbers[0] : 'waybills-'.$numbers[0];

            return $this->inline(
                $this->render($html, self::WAYBILL_SIZE, __('Waybill').' '.implode(', ', $numbers), $documents[0]['dir'] ?? 'rtl', [4, 4, 4, 4]),
                $name.'.pdf',
            );
        });
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

    /**
     * @param  string|array{0: int, 1: int}  $format  a paper name or [width, height] in mm, portrait
     * @param  array{0: int, 1: int, 2: int, 3: int}  $margins  left, right, top, bottom in mm
     */
    private function render(string $html, string|array $format, ?string $title = null, string $dir = 'rtl', array $margins = [2, 2, 4, 2]): string
    {
        $pdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => $format,
            'orientation' => 'P', // a size given as [w, h] is already the right way round
            'default_font' => 'dejavusans',
            'margin_left' => $margins[0],
            'margin_right' => $margins[1],
            'margin_top' => $margins[2],
            'margin_bottom' => $margins[3],
            'tempDir' => sys_get_temp_dir().DIRECTORY_SEPARATOR.'mpdf', // font metrics only, no store data
        ]);

        $pdf->SetDirectionality($dir);

        if ($title !== null) {
            $pdf->SetTitle($title);
        }

        $pdf->WriteHTML($html);

        return $pdf->Output('', Destination::STRING_RETURN);
    }

    /**
     * Opened in the browser's PDF viewer, where it is printed.
     */
    private function inline(string $pdf, string $filename): Response
    {
        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }
}
