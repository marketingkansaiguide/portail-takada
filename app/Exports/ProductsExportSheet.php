<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Une feuille de l'export produits : en-têtes en gras figés, filtres automatiques. */
class ProductsExportSheet implements FromArray, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    public function __construct(
        private string $title,
        private array $headings,
        private array $rows,
    ) {}

    public function array(): array
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return $this->headings;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function styles(Worksheet $sheet): array
    {
        $lastColumn = $sheet->getHighestColumn();
        $lastRow = max(1, $sheet->getHighestRow());

        $sheet->freezePane('A2');
        $sheet->setAutoFilter("A1:{$lastColumn}{$lastRow}");

        // Les textes longs (description, champs personnalisés…) ne doivent pas élargir la colonne à l'infini.
        foreach ($sheet->getColumnIterator() as $column) {
            $dimension = $sheet->getColumnDimension($column->getColumnIndex());
            $dimension->setAutoSize(false);
            $dimension->setWidth(min(60, max(10, $this->columnWidth($column->getColumnIndex()))));
        }

        $sheet->getStyle("A1:{$lastColumn}{$lastRow}")->getAlignment()
            ->setVertical(Alignment::VERTICAL_TOP)
            ->setWrapText(true);

        $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F2937']],
        ]);

        return [];
    }

    private function columnWidth(string $column): int
    {
        $index = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($column) - 1;

        $longest = mb_strlen((string) ($this->headings[$index] ?? ''));
        foreach (array_slice($this->rows, 0, 200) as $row) {
            $value = (string) ($row[$index] ?? '');
            foreach (explode("\n", $value) as $line) {
                $longest = max($longest, mb_strlen($line));
            }
        }

        return $longest + 2;
    }
}
