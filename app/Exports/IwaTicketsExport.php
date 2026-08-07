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
        // On commence à écrire les données (les lignes) à partir de la ligne 5
        // (les en-têtes seront sur la ligne 4, et les coordonnées ligne 2)
        return 'B5';
    }

    public function headings(): array
    {
        // Ces en-têtes seront placés juste avant startCell() -> Ligne 4
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
        // $route contient les données préparées depuis le BulkAction
        return [
            $route['pax_name'] . ' 様', // Nom du pax leader
            $route['date'],            // Date (YYYY-MM-DD)
            $route['train_name'],      // Nom du train
            $route['departure'],       // Gare de départ
            $route['arrival'],         // Gare d'arrivée
            $route['dep_time'],        // Heure de départ
            $route['arr_time'],        // Heure d'arrivée
            $route['class'],           // Option / Classe (ex: GREEN)
            '〇',                       // Billet de base inclus (par défaut rond O)
            $route['departure'],       // Début de validité du billet
            $route['arrival'],         // Fin de validité du billet
            $route['pax_adults'],      // Nb adultes
            $route['pax_children']     // Nb enfants
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Ligne 2 : Informations de TAKADA TRAVEL
        $sheet->setCellValue('B2', "TAKADA TRAVEL合同会社\n06-6195-9799\n担当者：ノロ/シンディ/田中");
        $sheet->getStyle('B2')->getAlignment()->setWrapText(true);
        $sheet->getRowDimension(2)->setRowHeight(60);

        // Style des en-têtes (Ligne 4)
        $sheet->getStyle('B4:N4')->getFont()->setBold(true);
        $sheet->getStyle('B4:N4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('B4:N4')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle('B4:N4')->getAlignment()->setWrapText(true);
        $sheet->getRowDimension(4)->setRowHeight(40);

        // Alignement général des données
        $highestRow = $sheet->getHighestRow();
        $sheet->getStyle('B5:N' . $highestRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('B5:N' . $highestRow)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        
        return [
            // Bordures autour du tableau de données (Ligne 4 à la fin)
            'B4:N' . $highestRow => [
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    ],
                ],
            ],
        ];
    }
}