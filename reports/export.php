<?php
require 'vendor/autoload.php'; // For PDF and Word export libraries
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Dompdf\Dompdf;

if (isset($_GET['format'])) {
    $format = $_GET['format'];

    // Fetch data
    include 'helpers.php'; 
    $schoolsData = getSchoolPerformanceData($conn);
    $rankedSchools = rankSchools($schoolsData);

    if ($format == 'pdf') {
        $dompdf = new Dompdf();
        $html = include 'pdf_template.php'; // Create a separate PHP file for PDF HTML template
        $dompdf->loadHtml($html);
        $dompdf->render();
        $dompdf->stream('report.pdf');
    } elseif ($format == 'docx') {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        $table = $section->addTable();
        $table->addRow();
        $table->addCell(2000)->addText('Rank');
        $table->addCell(2000)->addText('School');
        $table->addCell(2000)->addText('Pass Rate (%)');
        $table->addCell(2000)->addText('Fail Rate (%)');
        $table->addCell(2000)->addText('Total Candidates');

        foreach ($rankedSchools as $rank => $school) {
            $table->addRow();
            $table->addCell(2000)->addText($rank + 1);
            $table->addCell(2000)->addText($school['school_name']);
            $table->addCell(2000)->addText($school['pass_rate']);
            $table->addCell(2000)->addText($school['fail_rate']);
            $table->addCell(2000)->addText($school['total_candidates']);
        }

        $filename = 'report.docx';
        $phpWord->save($filename);
        header("Content-Description: File Transfer");
        header("Content-Disposition: attachment; filename=$filename");
        header("Content-Type: application/vnd.ms-word");
        readfile($filename);
    } elseif ($format == 'xlsx') {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', 'Rank')
              ->setCellValue('B1', 'School')
              ->setCellValue('C1', 'Pass Rate (%)')
              ->setCellValue('D1', 'Fail Rate (%)')
              ->setCellValue('E1', 'Total Candidates');

        $row = 2;
        foreach ($rankedSchools as $rank => $school) {
            $sheet->setCellValue('A' . $row, $rank + 1)
                  ->setCellValue('B' . $row, $school['school_name'])
                  ->setCellValue('C' . $row, $school['pass_rate'])
                  ->setCellValue('D' . $row, $school['fail_rate'])
                  ->setCellValue('E' . $row, $school['total_candidates']);
            $row++;
        }

        $writer = new Xlsx($spreadsheet);
        $filename = 'report.xlsx';
        $writer->save($filename);

        header("Content-Description: File Transfer");
        header("Content-Disposition: attachment; filename=$filename");
        header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
        readfile($filename);
    }
}
