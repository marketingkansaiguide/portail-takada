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
        // 💡 Les en-têtes seront sur la ligne 4, les données à partir de la ligne 5, Colonne B.
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
        return [
            $route['pax_name'] . ' 様', // お客様名
            $route['date'],            // 日程
            $route['train_name'],      // 列車名
            $route['departure'],       // 出発
            $route['arrival'],         // 到着
            $route['dep_time'],        // 出発時刻
            $route['arr_time'],        // 到着時刻
            $route['class'],           // 席類
            '〇',                       // 乗車券込み
            $route['departure'],       // 乗車券開始
            $route['arrival'],         // 乗車券終了
            $route['pax_adults'],      // 大人人数
            $route['pax_children']     // 子供人数
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Ligne 2 : Informations de TAKADA TRAVEL (En B2, C2, etc.)
        $sheet->setCellValue('B2', "TAKADA TRAVEL合同会社\n06-6195-9799\n担当者：ノロ/シンディ/田中");
        $sheet->getStyle('B2')->getAlignment()->setWrapText(true);
        $sheet->getRowDimension(2)->setRowHeight(45);

        // Style des en-têtes (Ligne 4)
        $sheet->getStyle('B4:N4')->getFont()->setBold(true);
        $sheet->getStyle('B4:N4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('B4:N4')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle('B4:N4')->getAlignment()->setWrapText(true);
        $sheet->getRowDimension(4)->setRowHeight(30);

        // Alignement général des données
        $highestRow = $sheet->getHighestRow();
        $sheet->getStyle('B5:N' . $highestRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('B5:N' . $highestRow)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        
        return [
            // Bordures autour du tableau de données
            'B4:N' . $highestRow => [
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                    ],
                ],
            ],
        ];
    }
}