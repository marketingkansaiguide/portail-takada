<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class IwaTicketsExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithCustomStartCell, ShouldAutoSize
{
    protected $routes;

    public function __construct($routes)
    {
        $this->routes = $routes;
    }

    public function collection()
    {
        return $this->routes;
    }

    public function startCell(): string
    {
        return 'B4';
    }

    public function headings(): array
    {
        return [
            'お客様名',
            '日程',
            "列車名\n区間\n設備",
            '出発',
            '到着',
            '出発時刻',
            '到着時刻',
            '席類',
            "乗車券\n込み",
            '乗車券開始',
            '乗車券終了',
            '大人人数',
            '子供人数'
        ];
    }

    public function map($route): array
    {
        // 💡 FORÇAGE EN CHAÎNE DE TEXTE (String)
        // Cela oblige Excel à inscrire le caractère "0" et empêche la cellule d'être vierge.
        $adults = isset($route['pax_adults']) ? (string) $route['pax_adults'] : '1';
        $children = isset($route['pax_children']) ? (string) $route['pax_children'] : '0';

        return [
            $route['pax_name'] . ' 様', // Col B
            $route['date'],            // Col C
            $route['train_name'],      // Col D
            $route['departure'],       // Col E
            $route['arrival'],         // Col F
            $route['dep_time'],        // Col G
            $route['arr_time'],        // Col H
            $route['class'],           // Col I
            '〇',                       // Col J
            $route['departure'],       // Col K
            $route['arrival'],         // Col L
            $adults,                   // Col M
            $children,                 // Col N
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->setCellValue('B2', "TAKADA TRAVEL合同会社\n06-6195-9799\n担当者：ノロ/シンディ/田中");
        $sheet->getStyle('B2')->getAlignment()->setWrapText(true);
        $sheet->getRowDimension(2)->setRowHeight(45);

        $sheet->getStyle('B4:N4')->getFont()->setBold(true);
        $sheet->getStyle('B4:N4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('B4:N4')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle('B4:N4')->getAlignment()->setWrapText(true);
        $sheet->getRowDimension(4)->setRowHeight(30);

        $highestRow = max($sheet->getHighestRow(), 5);

        $sheet->getStyle("B5:N{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("B5:N{$highestRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        
        return [
            "B4:N{$highestRow}" => [
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                    ],
                ],
            ],
        ];
    }
}