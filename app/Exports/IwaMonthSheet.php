<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Un onglet mensuel de la commande IWA. */
class IwaMonthSheet implements FromArray, WithTitle, WithCustomStartCell, WithHeadings, WithColumnFormatting, WithColumnWidths, WithStyles
{
    public const DATE_FORMAT = 'yyyy"年"m"月"d"日"';

    public function __construct(protected string $title, protected array $rows)
    {
    }

    public function title(): string
    {
        return $this->title;
    }

    public function array(): array
    {
        return $this->rows;
    }

    public function startCell(): string
    {
        return 'B4';
    }

    public function headings(): array
    {
        return ['お客様名', '日程', '列車名/区間/設備/乗車券', '出発時刻', '到着時刻', '人数'];
    }

    public function columnFormats(): array
    {
        return ['C' => self::DATE_FORMAT];
    }

    public function columnWidths(): array
    {
        return ['B' => 22, 'C' => 16, 'D' => 42, 'E' => 10, 'F' => 10, 'G' => 12];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->setCellValue('B2', "TAKADA TRAVEL合同会社\n06-6195-9799\n担当者：ノロ/シンディ/田中");
        $sheet->getStyle('B2')->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
        $sheet->getRowDimension(2)->setRowHeight(48);

        $last = max(4 + count($this->rows), 4);

        $head = $sheet->getStyle('B4:G4');
        $head->getFont()->setBold(true);
        $head->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('C9DAF8');
        $head->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $sheet->getRowDimension(4)->setRowHeight(30);

        if ($last > 4) {
            $sheet->getStyle("B5:G{$last}")->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle("D5:D{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setWrapText(true);
        }
        $sheet->getStyle("B4:G{$last}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        $sheet->freezePane('A5');
        $sheet->setAutoFilter("B4:G{$last}");

        return [];
    }
}
