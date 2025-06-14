<?php

namespace App\Service;

use PhpOffice\PhpSpreadsheet\IOFactory;

class ExcelService
{
    public function processExcelFile(string $filePath): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();
        $data = $worksheet->toArray();
        
        if (empty($data)) {
            throw new \RuntimeException('Excel file is empty');
        }

        $headers = array_shift($data);
        $recipients = [];

        foreach ($data as $row) {
            $recipient = [];
            foreach ($headers as $index => $header) {
                $recipient[$header] = $row[$index] ?? '';
            }
            if (!empty($recipient['email'])) {
                $recipients[] = $recipient;
            }
        }

        return $recipients;
    }
} 