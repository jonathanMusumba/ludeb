<?php
require '../vendor/autoload.php';

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;

// Create a new Word document
$phpWord = new PhpWord();

// Page 1: Introduction (Portrait)
$section1 = $phpWord->addSection(['orientation' => 'portrait']);
$section1->addTitle('Mock Results Report', 1);
$section1->addTitle('Introduction', 2);
$section1->addText(
    "This report introduces the examination body, its examination conduct, the marking of the exams, the assessment process, and the release of results."
);

// Page for Charts (Landscape)
$sectionCharts = $phpWord->addSection(['orientation' => 'landscape']);
$sectionCharts->addTitle('Charts and Analysis', 1);
$sectionCharts->addText('Include charts here:');

// Placeholder for chart images
$sectionCharts->addText('Number of Registered, Sat, and Absentees:');
$sectionCharts->addText('Insert chart image here...');
$sectionCharts->addText('Divisions and Their Corresponding Numbers:');
$sectionCharts->addText('Insert chart image here...');
$sectionCharts->addText('Subjects Performance (Grades and Their Counts):');
$sectionCharts->addText('Insert chart image here...');

// Page: Challenges, Recommendations, and Conclusion (Portrait)
$sectionChallenges = $phpWord->addSection(['orientation' => 'portrait']);
$sectionChallenges->addTitle('Challenges and Recommendations', 1);
$sectionChallenges->addTitle('Challenges', 2);
$sectionChallenges->addText(
    "Candidates Failing to write their Index Numbers and Correct names.\n" .
    "Examiners writing wrong names of the Candidates.\n" .
    "Teachers Using names different from those captured in the system.\n" .
    "Poor recording of the marks leading to candidates missing marks.\n" .
    "Examiners not marking some candidates' scripts well and poor awarding of marks.\n" .
    "Problem of Power during the Marks Entry and Analysis causing Delay in the Results release."
);
$sectionChallenges->addTitle('Recommendations', 2);
$sectionChallenges->addText(
    "The exam checkers should thoroughly check all the marked scripts to rectify the anomalies of under marking.\n" .
    "Through checking of the Marksheets to find out if the recorded scripts tally with the number of the marked scripts.\n" .
    "Exams should be conducted a little earlier to create room for marking and proper Assessment.\n" .
    "The Data Entry Process should be thoroughly monitored.\n" .
    "Teachers and Headteachers should emphasize the correct writing of index numbers and names by their Candidates."
);
$sectionChallenges->addTitle('Achievements', 2);
$sectionChallenges->addText(
    "Exams were done successfully and results released.\n" .
    "Achieved an Exam Assessment system that can store and retrieve data even for previous years.\n" .
    "Got a team of experienced examiners."
);
$sectionChallenges->addTitle('Conclusion', 2);
$sectionChallenges->addText(
    "I thank the RDC, Chairperson LCV, The Executive, DEO Staff of the Education Office, Headteachers, Teachers, Candidates, and Parents for their cooperation that has led to this success.\n" .
    "I request headteachers and teachers to go through the papers and make corrections. Mock exams are a preparation for the final exams. UNEB still makes amendments to the Learners' Names.\n" .
    "A good quote about preparing for the finals: 'Success is not the key to happiness. Happiness is the key to success. If you love what you are doing, you will be successful.'\n" .
    "I wish you success in your final exams.\n" .
    "Nabwire Jane\n" .
    "District Inspector of Schools"
);

// Page: Appendix (Landscape)
$sectionAppendix = $phpWord->addSection(['orientation' => 'landscape']);
$sectionAppendix->addTitle('Appendix: General Schools Performance', 1);
$sectionAppendix->addText('Insert data and charts for General Schools Performance here...');

// Save the Word document
$filename = 'Mock_Results_Report.docx';
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$writer = IOFactory::createWriter($phpWord, 'Word2007');
$writer->save('php://output');
exit();
?>
